<?php

namespace App\Services;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\StockRequest;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    protected StokService $stokService;

    public function __construct(StokService $stokService)
    {
        $this->stokService = $stokService;
    }

    /**
     * Generate next nomor transaksi transfer unik (e.g. TRF-202611-0001).
     */
    public function nextNoTransaksi(): string
    {
        $prefix = 'TRF-' . date('Ym') . '-';
        $last = Transfer::where('no_transaksi', 'like', $prefix . '%')
            ->orderByDesc('no_transaksi')
            ->value('no_transaksi');

        $nextSeq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Buat Transfer baru (FR-TRF-01..07, TC-20, TC-25, TC-38, TC-39).
     */
    public function create(array $data, User $actor): Transfer
    {
        $jenis = $data['jenis'];
        $requestId = $data['request_id'] ?? null;
        $batchId = $data['referensi_batch_id'] ?? null;

        // 1. Validasi jenis & referensi request
        if ($jenis === 'pemenuhan_request_bahan') {
            if (! $requestId) {
                throw ValidationException::withMessages([
                    'request_id' => 'Transfer jenis Pemenuhan Request Bahan Baku wajib merujuk ke dokumen Request.',
                ]);
            }
            $request = StockRequest::findOrFail($requestId);
            if ($request->jenis !== 'request_bahan' || $request->status !== 'disetujui') {
                throw ValidationException::withMessages([
                    'request_id' => 'Request yang dipilih harus berupa Request Bahan Baku yang telah disetujui Manager.',
                ]);
            }
        } elseif ($jenis === 'kirim_produk_jadi') {
            // Dapat merujuk request produk jadi disetujui ATAU batch selesai (TC-38)
            if ($requestId) {
                $request = StockRequest::findOrFail($requestId);
                if ($request->jenis !== 'request_produk_jadi' || $request->status !== 'disetujui') {
                    throw ValidationException::withMessages([
                        'request_id' => 'Request yang dipilih harus berupa Request Produk Jadi yang telah disetujui Manager.',
                    ]);
                }
            } elseif ($batchId) {
                $batch = BatchProduksi::findOrFail($batchId);
                if ($batch->status !== 'selesai') {
                    throw ValidationException::withMessages([
                        'referensi_batch_id' => 'Transfer kirim produk jadi dari batch hanya dapat dibuat untuk batch yang sudah selesai.',
                    ]);
                }
            }
        } else {
            // Jenis lain (retur, antar fulfillment) tidak merujuk request (TC-25)
            $requestId = null;
        }

        // 2. Penegakan 1:1 Active Transfer per Request (TC-39)
        if ($requestId) {
            $hasActiveTransfer = Transfer::where('request_id', $requestId)
                ->where('status', '!=', 'dibatalkan')
                ->lockForUpdate()
                ->exists();

            if ($hasActiveTransfer) {
                throw ValidationException::withMessages([
                    'request_id' => 'Request ini sudah memiliki dokumen Transfer aktif yang sedang berjalan.',
                ]);
            }
        }

        if (empty($data['items']) || ! is_array($data['items'])) {
            throw ValidationException::withMessages([
                'items' => 'Minimal satu item produk wajib dimasukkan.',
            ]);
        }

        return DB::transaction(function () use ($data, $actor, $jenis, $requestId, $batchId) {
            $transfer = Transfer::create([
                'no_transaksi' => $this->nextNoTransaksi(),
                'jenis' => $jenis,
                'gudang_asal_id' => $data['gudang_asal_id'] ?? null,
                'gudang_tujuan_id' => $data['gudang_tujuan_id'],
                'request_id' => $requestId,
                'referensi_batch_id' => $batchId,
                'status' => 'draft',
                'catatan' => $data['catatan'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($data['items'] as $item) {
                $qty = (float) ($item['qty_dikirim'] ?? 0);
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Kuantitas kirim harus lebih besar dari 0.',
                    ]);
                }

                $transfer->items()->create([
                    'produk_id' => $item['produk_id'],
                    'request_item_id' => $item['request_item_id'] ?? null,
                    'qty_dikirim' => $qty,
                    'qty_rusak' => 0,
                ]);
            }

            // Jika merujuk Request, ubah status Request menjadi diproses (FR-TRF-07)
            if ($requestId) {
                StockRequest::where('id', $requestId)->update(['status' => 'diproses']);
            }

            return $transfer;
        });
    }

    /**
     * Batal Draft Transfer (FR-TRF-07, TC-39).
     * Jika merujuk Request, kembalikan status Request dari 'diproses' ke 'disetujui' agar dapat dibuatkan Transfer pengganti.
     */
    public function cancelDraft(Transfer $transfer, User $actor, string $alasan): void
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan transfer wajib diisi.',
            ]);
        }

        if ($transfer->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya transfer berstatus draft yang dapat dibatalkan melalui aksi ini.',
            ]);
        }

        DB::transaction(function () use ($transfer, $alasan) {
            $transfer->update([
                'status' => 'dibatalkan',
                'alasan_batal' => $alasan,
            ]);

            // Kembalikan status request ke disetujui jika ada
            if ($transfer->request_id) {
                $req = StockRequest::find($transfer->request_id);
                if ($req && $req->status === 'diproses') {
                    $req->update(['status' => 'disetujui']);
                }
            }
        });
    }

    /**
     * Kirim Transfer (FR-TRF-04..05, TC-21, TC-23).
     * Memotong stok fisik gudang asal dan mencatat kartu stok keluar.
     */
    public function ship(Transfer $transfer, User $actor): void
    {
        if ($transfer->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya transfer dengan status draft yang dapat dikirim.',
            ]);
        }

        $transfer->loadMissing(['items.produk', 'gudangAsal']);

        // Cek kecukupan stok di gudang asal (kecuali kirim produk jadi langsung dari batch tanpa gudang asal)
        if ($transfer->gudang_asal_id) {
            foreach ($transfer->items as $item) {
                $saldo = $this->stokService->saldo($item->produk_id, $transfer->gudang_asal_id);
                if ($saldo < (float) $item->qty_dikirim) {
                    throw ValidationException::withMessages([
                        'stok' => sprintf(
                            "Stok tidak mencukupi untuk item '%s' di gudang '%s'. Saldo saat ini: %s, jumlah yang akan dikirim: %s.",
                            $item->produk?->nama ?? $item->produk_id,
                            $transfer->gudangAsal?->nama ?? $transfer->gudang_asal_id,
                            number_format($saldo, 2, ',', '.'),
                            number_format((float) $item->qty_dikirim, 2, ',', '.')
                        ),
                    ]);
                }
            }
        }

        DB::transaction(function () use ($transfer) {
            // Potong stok asal
            if ($transfer->gudang_asal_id) {
                foreach ($transfer->items as $item) {
                    $this->stokService->keluar(
                        $item->produk_id,
                        $transfer->gudang_asal_id,
                        (float) $item->qty_dikirim,
                        'transfer',
                        $transfer->id,
                        "Transfer Keluar {$transfer->no_transaksi}"
                    );
                }
            }

            $transfer->update([
                'status' => 'dikirim',
                'waktu_dikirim' => now(),
            ]);
        });
    }

    /**
     * Penerimaan & QC Anti-Fraud Transfer (FR-TRF-08, TC-22, TC-24, user override).
     * Invarian ketat: qty_baik + qty_rusak === qty_dikirim.
     * Hanya qty_baik yang masuk ke saldo stok gudang tujuan.
     */
    public function receive(Transfer $transfer, array $receivingData, User $actor): void
    {
        if ($transfer->status !== 'dikirim') {
            throw ValidationException::withMessages([
                'status' => 'Hanya transfer yang sedang dalam status dikirim yang dapat diterima.',
            ]);
        }

        // Segregation of Duties: Penerima tidak boleh sama dengan pembuat/pengirim, kecuali Manager/Admin
        $roleNames = array_map('strtolower', $actor->getRoleNames()->toArray());
        $isManagerOrAdmin = in_array('manager', $roleNames, true) || in_array('super admin', $roleNames, true) || in_array('admin', $roleNames, true);

        if (! $isManagerOrAdmin && $transfer->created_by === $actor->id) {
            throw ValidationException::withMessages([
                'penerima' => 'Prinsip Segregation of Duties: Penerima barang di gudang tujuan tidak boleh merupakan pengguna yang sama dengan pembuat/pengirim transfer.',
            ]);
        }

        $transfer->loadMissing('items.produk');
        $itemsPayload = isset($receivingData['items']) && is_array($receivingData['items'])
            ? $receivingData['items']
            : (array) $receivingData;

        // Validasi invarian QC per item
        foreach ($transfer->items as $item) {
            $input = collect($itemsPayload)->firstWhere('id', $item->id)
                ?? collect($itemsPayload)->firstWhere('item_id', $item->id)
                ?? [];

            $qtyBaik = (float) ($input['qty_baik'] ?? 0);
            $qtyRusak = (float) ($input['qty_rusak'] ?? 0);
            $qtyDikirim = (float) $item->qty_dikirim;

            if ($qtyBaik < 0 || $qtyRusak < 0) {
                throw ValidationException::withMessages([
                    'items' => "Kuantitas baik atau rusak pada item '{$item->produk?->nama}' tidak boleh negatif.",
                ]);
            }

            // Invarian anti-fraud bulat eksak: qty_baik + qty_rusak === qty_dikirim
            if (abs(($qtyBaik + $qtyRusak) - $qtyDikirim) > 0.0001) {
                throw ValidationException::withMessages([
                    'items' => sprintf(
                        "Invarian QC Gagal pada item '%s': Total Qty Baik (%s) + Qty Rusak (%s) harus sama persis dengan Qty Dikirim (%s).",
                        $item->produk?->nama ?? $item->produk_id,
                        number_format($qtyBaik, 2, ',', '.'),
                        number_format($qtyRusak, 2, ',', '.'),
                        number_format($qtyDikirim, 2, ',', '.')
                    ),
                ]);
            }

            // Keterangan rusak wajib diisi jika ada barang rusak
            $keteranganRusak = trim((string) ($input['keterangan_rusak'] ?? ''));
            if ($qtyRusak > 0 && $keteranganRusak === '') {
                throw ValidationException::withMessages([
                    'items' => "Keterangan kerusakan wajib diisi untuk item '{$item->produk?->nama}' yang mengalami kerusakan.",
                ]);
            }
        }

        DB::transaction(function () use ($transfer, $itemsPayload, $actor) {
            $waktuSelesai = now();

            foreach ($transfer->items as $item) {
                $input = collect($itemsPayload)->firstWhere('id', $item->id)
                    ?? collect($itemsPayload)->firstWhere('item_id', $item->id)
                    ?? [];

                $qtyBaik = (float) ($input['qty_baik'] ?? 0);
                $qtyRusak = (float) ($input['qty_rusak'] ?? 0);
                $keterangan = $input['keterangan_rusak'] ?? null;

                $item->update([
                    'qty_diterima' => $qtyBaik, // Diterima fisik bagus
                    'qty_baik' => $qtyBaik,
                    'qty_rusak' => $qtyRusak,
                    'keterangan_rusak' => $keterangan,
                ]);

                // Hanya kuantitas baik yang masuk ke saldo stok gudang tujuan
                if ($qtyBaik > 0) {
                    $this->stokService->masuk(
                        $item->produk_id,
                        $transfer->gudang_tujuan_id,
                        $qtyBaik,
                        'transfer',
                        $transfer->id,
                        "Penerimaan Transfer {$transfer->no_transaksi}"
                    );
                }
            }

            $transfer->update([
                'status' => 'selesai',
                'waktu_diterima' => $waktuSelesai,
                'diterima_oleh' => $actor->id,
            ]);

            // Jika memiliki request_id, otomatis selesaikan StockRequest (TC-24)
            if ($transfer->request_id) {
                StockRequest::where('id', $transfer->request_id)->update(['status' => 'selesai']);
            }
        });
    }

    /**
     * Alias method untuk getMonthlyWarehouseDefectAnalytics untuk konsistensi pelaporan defect.
     */
    public function getMonthlyDefectAnalytics(int $year, ?int $month = null, ?int $gudangId = null): array
    {
        return $this->getMonthlyWarehouseDefectAnalytics($year, $month, $gudangId);
    }

    /**
     * Engine Kalkulasi & Rekapitulasi Barang Rusak Bulanan per Gudang — Khusus Transfer (TC-42, User Override).
     */
    public function getMonthlyWarehouseDefectAnalytics(int $year, ?int $month = null, ?int $gudangId = null): array
    {
        return $this->buildWarehouseDefectAnalytics($year, $month, $gudangId, false, false);
    }

    /**
     * Rekapitulasi Barang Rusak Gabungan untuk Dashboard: Transfer + Purchasing (PO) + Batch Produksi.
     */
    public function getCombinedMonthlyWarehouseDefectAnalytics(int $year, ?int $month = null, ?int $gudangId = null): array
    {
        return $this->buildWarehouseDefectAnalytics($year, $month, $gudangId, true, true);
    }

    /**
     * Daftar ringkas barang rusak terbaru dari Transfer, Purchasing (Barang Datang), dan Batch Produksi.
     *
     * @return Collection<int, object>
     */
    public function getRecentDefectLogs(int $limit = 5): Collection
    {
        $transferLogs = DB::table('transfer_items as ti')
            ->join('transfers as t', 't.id', '=', 'ti.transfer_id')
            ->join('produk as p', 'p.id', '=', 'ti.produk_id')
            ->leftJoin('gudang as g', 'g.id', '=', 't.gudang_tujuan_id')
            ->where('t.status', 'selesai')
            ->where('ti.qty_rusak', '>', 0)
            ->orderByRaw('COALESCE(t.waktu_diterima, t.updated_at, ti.updated_at, ti.created_at) DESC')
            ->limit($limit)
            ->select([
                't.id as doc_id',
                't.no_transaksi as nomor',
                'p.nama as produk_nama',
                'p.satuan as satuan',
                'g.nama as gudang_nama',
                'ti.qty_rusak as qty_rusak',
                'ti.keterangan_rusak as keterangan',
                DB::raw('COALESCE(t.waktu_diterima, t.updated_at, ti.updated_at, ti.created_at) as sort_at'),
            ])
            ->get()
            ->map(fn ($row) => $this->formatDefectLogItem(
                sourceBadge: 'Transfer',
                nomor: $row->nomor,
                produkNama: $row->produk_nama,
                satuan: $row->satuan,
                gudangNama: $row->gudang_nama,
                qtyRusak: (float) $row->qty_rusak,
                keterangan: $row->keterangan ?: 'Kerusakan fisik transfer',
                url: route('transfers.show', $row->doc_id),
                sortAt: $row->sort_at
            ));

        $purchasingLogs = DB::table('barang_datang_items as bdi')
            ->join('barang_datang as bd', 'bd.id', '=', 'bdi.bardat_id')
            ->join('purchase_orders as po', 'po.id', '=', 'bd.po_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'bdi.po_item_id')
            ->join('produk as p', 'p.id', '=', 'poi.produk_id')
            ->leftJoin('gudang as g', 'g.id', '=', 'po.gudang_id')
            ->where('po.status', '!=', 'dibatalkan')
            ->where('bdi.selisih', '<', 0)
            ->orderByRaw('COALESCE(bd.waktu_tiba, bd.created_at, bd.tanggal_terima) DESC')
            ->limit($limit)
            ->select([
                'po.id as doc_id',
                'po.no_po as nomor',
                'p.nama as produk_nama',
                'p.satuan as satuan',
                'g.nama as gudang_nama',
                DB::raw('ABS(bdi.selisih) as qty_rusak'),
                'bdi.keterangan_selisih as keterangan',
                DB::raw('COALESCE(bd.waktu_tiba, bd.created_at, bd.tanggal_terima) as sort_at'),
            ])
            ->get()
            ->map(fn ($row) => $this->formatDefectLogItem(
                sourceBadge: 'Purchasing',
                nomor: $row->nomor,
                produkNama: $row->produk_nama,
                satuan: $row->satuan,
                gudangNama: $row->gudang_nama ?? 'Gudang PO',
                qtyRusak: (float) $row->qty_rusak,
                keterangan: $row->keterangan ?: 'Selisih/rusak penerimaan PO',
                url: route('purchasing.show', $row->doc_id),
                sortAt: $row->sort_at
            ));

        $batchLogs = DB::table('batch_produksi as bp')
            ->leftJoin('batch_produksi_outputs as bpo', 'bpo.batch_id', '=', 'bp.id')
            ->join('produk as p', function ($join) {
                $join->on('p.id', '=', DB::raw('COALESCE(bpo.produk_id, bp.produk_id)'));
            })
            ->leftJoin('gudang as g', function ($join) {
                $join->on('g.id', '=', DB::raw('COALESCE(bp.gudang_operasional_id, bp.gudang_tujuan_rencana_id)'));
            })
            ->where('bp.status', 'selesai')
            ->whereRaw('COALESCE(bpo.qty_rusak, bp.qty_rusak, 0) > 0')
            ->orderByRaw('COALESCE(bp.updated_at, bp.created_at, bp.tanggal) DESC')
            ->limit($limit)
            ->select([
                'bp.id as doc_id',
                'bp.no_batch as nomor',
                'p.nama as produk_nama',
                'p.satuan as satuan',
                'g.nama as gudang_nama',
                DB::raw('COALESCE(bpo.qty_rusak, bp.qty_rusak, 0) as qty_rusak'),
                'bpo.catatan as keterangan',
                DB::raw('COALESCE(bp.updated_at, bp.created_at, bp.tanggal) as sort_at'),
            ])
            ->get()
            ->map(fn ($row) => $this->formatDefectLogItem(
                sourceBadge: 'Batch',
                nomor: $row->nomor,
                produkNama: $row->produk_nama,
                satuan: $row->satuan,
                gudangNama: $row->gudang_nama ?? 'Lab Produksi',
                qtyRusak: (float) $row->qty_rusak,
                keterangan: $row->keterangan ?: 'Reject QC hasil produksi',
                url: route('batches.show', $row->doc_id),
                sortAt: $row->sort_at
            ));

        return $transferLogs
            ->concat($purchasingLogs)
            ->concat($batchLogs)
            ->sortByDesc('sort_ts')
            ->take($limit)
            ->values();
    }

    private function formatDefectLogItem(
        string $sourceBadge,
        string $nomor,
        ?string $produkNama,
        ?string $satuan,
        ?string $gudangNama,
        float $qtyRusak,
        string $keterangan,
        string $url,
        mixed $sortAt
    ): object {
        $ts = $sortAt ? Carbon::parse($sortAt)->getTimestamp() : 0;

        return (object) [
            'is_defect_log' => true,
            'source_badge' => $sourceBadge,
            'nomor' => $nomor,
            'produk_nama' => $produkNama ?? 'Item Rusak',
            'satuan' => $satuan ?? 'pcs',
            'gudang_nama' => $gudangNama ?? 'Gudang',
            'qty_rusak' => $qtyRusak,
            'keterangan' => $keterangan,
            'url' => $url,
            'sort_ts' => $ts,
        ];
    }

    private function buildWarehouseDefectAnalytics(
        int $year,
        ?int $month,
        ?int $gudangId,
        bool $includePurchasing,
        bool $includeBatches
    ): array {
        $entries = $this->fetchTransferDefectEntries($year, $gudangId);

        if ($includePurchasing) {
            $entries = $entries->concat($this->fetchPurchasingDefectEntries($year, $gudangId));
        }
        if ($includeBatches) {
            $entries = $entries->concat($this->fetchBatchDefectEntries($year, $gudangId));
        }

        // Lampirkan bulan integer sekali saja untuk menyederhanakan agregasi KPI & matriks
        $entries = $entries->map(function ($row) {
            $row->month_num = $row->event_at ? (int) Carbon::parse($row->event_at)->month : 1;
            $row->qty_total = (float) $row->qty_total;
            $row->qty_baik = (float) $row->qty_baik;
            $row->qty_rusak = (float) $row->qty_rusak;
            return $row;
        });

        $filtered = $month !== null
            ? $entries->filter(fn ($r) => $r->month_num === $month)
            : $entries;

        $totalRusak = (float) $filtered->sum('qty_rusak');
        $totalDikirim = (float) $filtered->sum('qty_total');
        $totalTransfer = $filtered->pluck('doc_key')->unique()->count();
        $transferTerdampak = $filtered->filter(fn ($r) => $r->qty_rusak > 0)->pluck('doc_key')->unique()->count();
        $defectRatePercent = $totalDikirim > 0 ? round(($totalRusak / $totalDikirim) * 100, 2) : 0.0;

        $bySource = [
            'transfer' => (float) $filtered->where('source', 'transfer')->sum('qty_rusak'),
            'purchasing' => (float) $filtered->where('source', 'purchasing')->sum('qty_rusak'),
            'batch' => (float) $filtered->where('source', 'batch')->sum('qty_rusak'),
        ];

        $topGudang = $filtered
            ->filter(fn ($r) => $r->qty_rusak > 0)
            ->groupBy('gudang_nama')
            ->map(fn ($group, $nama) => (object) ['nama' => $nama, 'total_rusak' => (float) $group->sum('qty_rusak')])
            ->sortByDesc('total_rusak')
            ->first();

        $warehousesQuery = Gudang::active()->orderBy('nama');
        if ($gudangId) {
            $warehousesQuery->where('id', $gudangId);
        }
        $allWarehouses = $warehousesQuery->get(['id', 'nama', 'kode']);

        $monthlyByWarehouse = [];
        foreach ($allWarehouses as $wh) {
            $whEntries = $entries->where('gudang_id', $wh->id);
            $months = array_fill(1, 12, 0.0);
            $totalTahunan = 0.0;

            foreach ($whEntries->groupBy('month_num') as $m => $group) {
                $val = (float) $group->sum('qty_rusak');
                $months[(int) $m] = $val;
                $totalTahunan += $val;
            }

            $skuList = $whEntries
                ->filter(fn ($r) => $r->qty_rusak > 0)
                ->groupBy(fn ($r) => $r->sku . '||' . $r->produk_nama)
                ->map(function ($group) {
                    $first = $group->first();
                    return [
                        'sku' => $first->sku,
                        'nama' => $first->produk_nama,
                        'qty_rusak' => (float) $group->sum('qty_rusak'),
                    ];
                })
                ->sortByDesc('qty_rusak')
                ->values()
                ->toArray();

            $monthlyByWarehouse[] = [
                'gudang_id' => $wh->id,
                'nama' => $wh->nama,
                'kode' => $wh->kode,
                'months' => $months,
                'total_tahunan' => $totalTahunan,
                'sku_rusak_list' => $skuList,
            ];
        }

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $entriesByMonth = $entries->groupBy('month_num');
        $monthlyMatrix = [];
        for ($m = 1; $m <= 12; $m++) {
            $mEntries = $entriesByMonth->get($m, collect());
            $mQtyKirim = (float) $mEntries->sum('qty_total');
            $mQtyBaik = (float) $mEntries->sum('qty_baik');
            $mQtyRusak = (float) $mEntries->sum('qty_rusak');
            $mTrfCount = $mEntries->pluck('doc_key')->unique()->count();
            $mRate = $mQtyKirim > 0 ? round(($mQtyRusak / $mQtyKirim) * 100, 2) : 0.0;

            $warehouses = $mEntries
                ->groupBy(fn ($r) => ($r->gudang_id ?? 'none') . '||' . $r->gudang_nama)
                ->map(function ($group) {
                    $first = $group->first();
                    return [
                        'gudang_id' => $first->gudang_id,
                        'gudang_nama' => $first->gudang_nama,
                        'qty_dikirim' => (float) $group->sum('qty_total'),
                        'qty_baik' => (float) $group->sum('qty_baik'),
                        'qty_rusak' => (float) $group->sum('qty_rusak'),
                    ];
                })
                ->values()
                ->toArray();

            $monthlyMatrix[] = [
                'month' => $m,
                'month_name' => $monthNames[$m],
                'transfers_count' => $mTrfCount,
                'qty_dikirim' => $mQtyKirim,
                'qty_baik' => $mQtyBaik,
                'qty_rusak' => $mQtyRusak,
                'defect_rate_percent' => $mRate,
                'warehouses' => $warehouses,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'summary' => [
                'total_transfers' => $totalTransfer,
                'total_transfer_count' => $totalTransfer,
                'total_qty_dikirim' => $totalDikirim,
                'total_dikirim_bulan_ini' => $totalDikirim,
                'total_qty_rusak' => $totalRusak,
                'total_rusak_bulan_ini' => $totalRusak,
                'defect_rate_percent' => $defectRatePercent,
                'transfer_terdampak_count' => $transferTerdampak,
                'gudang_tertinggi_nama' => $topGudang?->nama ?? '—',
                'gudang_tertinggi_qty' => (float) ($topGudang?->total_rusak ?? 0),
                'by_source' => $bySource,
            ],
            'monthly_matrix' => $monthlyMatrix,
            'monthly_by_warehouse' => $monthlyByWarehouse,
        ];
    }

    private function fetchTransferDefectEntries(int $year, ?int $gudangId): Collection
    {
        $receivedAtExpr = 'COALESCE(t.waktu_diterima, t.updated_at, t.created_at)';

        return DB::table('transfers as t')
            ->join('transfer_items as ti', 'ti.transfer_id', '=', 't.id')
            ->join('gudang as g', 'g.id', '=', 't.gudang_tujuan_id')
            ->join('produk as p', 'p.id', '=', 'ti.produk_id')
            ->where('t.status', 'selesai')
            ->whereYear(DB::raw($receivedAtExpr), $year)
            ->when($gudangId, fn ($q) => $q->where('t.gudang_tujuan_id', $gudangId))
            ->select([
                DB::raw("'transfer' as source"),
                't.id as doc_id',
                't.gudang_tujuan_id as gudang_id',
                'g.nama as gudang_nama',
                'p.sku',
                'p.nama as produk_nama',
                DB::raw("{$receivedAtExpr} as event_at"),
                'ti.qty_dikirim as qty_total',
                DB::raw('COALESCE(ti.qty_baik, ti.qty_diterima, 0) as qty_baik'),
                'ti.qty_rusak as qty_rusak',
            ])
            ->get()
            ->map(function ($row) {
                $row->doc_key = 'transfer:' . $row->doc_id;
                return $row;
            });
    }

    private function fetchPurchasingDefectEntries(int $year, ?int $gudangId): Collection
    {
        $eventExpr = 'COALESCE(bd.waktu_tiba, bd.tanggal_terima, bd.created_at)';
        $goodExpr = 'COALESCE(bdi.qty_diterima, bdi.qty_fisik, 0)';
        $totalExpr = "CASE WHEN poi.qty >= {$goodExpr} THEN poi.qty ELSE {$goodExpr} END";
        $rusakExpr = 'CASE WHEN bdi.selisih < 0 THEN ABS(bdi.selisih) ELSE 0 END';

        return DB::table('barang_datang as bd')
            ->join('barang_datang_items as bdi', 'bdi.bardat_id', '=', 'bd.id')
            ->join('purchase_orders as po', 'po.id', '=', 'bd.po_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'bdi.po_item_id')
            ->join('produk as p', 'p.id', '=', 'poi.produk_id')
            ->leftJoin('gudang as g', 'g.id', '=', 'po.gudang_id')
            ->where('po.status', '!=', 'dibatalkan')
            ->whereYear(DB::raw($eventExpr), $year)
            ->when($gudangId, fn ($q) => $q->where('po.gudang_id', $gudangId))
            ->select([
                DB::raw("'purchasing' as source"),
                'bd.id as doc_id',
                'po.gudang_id as gudang_id',
                DB::raw("COALESCE(g.nama, 'Gudang PO') as gudang_nama"),
                'p.sku',
                'p.nama as produk_nama',
                DB::raw("{$eventExpr} as event_at"),
                DB::raw("{$totalExpr} as qty_total"),
                DB::raw("{$goodExpr} as qty_baik"),
                DB::raw("{$rusakExpr} as qty_rusak"),
            ])
            ->get()
            ->map(function ($row) {
                $row->doc_key = 'purchasing:' . $row->doc_id;
                return $row;
            });
    }

    private function fetchBatchDefectEntries(int $year, ?int $gudangId): Collection
    {
        $eventExpr = 'COALESCE(bp.tanggal, bp.updated_at, bp.created_at)';
        $gudangExpr = 'COALESCE(bp.gudang_operasional_id, bp.gudang_tujuan_rencana_id)';
        $baikExpr = 'COALESCE(bpo.qty_baik, bp.qty_baik, 0)';
        $rusakExpr = 'COALESCE(bpo.qty_rusak, bp.qty_rusak, 0)';
        $totalExpr = "CASE WHEN ({$baikExpr} + {$rusakExpr}) > 0 THEN ({$baikExpr} + {$rusakExpr}) ELSE COALESCE(bpo.qty_rencana, bp.qty_rencana, 0) END";

        return DB::table('batch_produksi as bp')
            ->leftJoin('batch_produksi_outputs as bpo', 'bpo.batch_id', '=', 'bp.id')
            ->join('produk as p', function ($join) {
                $join->on('p.id', '=', DB::raw('COALESCE(bpo.produk_id, bp.produk_id)'));
            })
            ->leftJoin('gudang as g', function ($join) use ($gudangExpr) {
                $join->on('g.id', '=', DB::raw($gudangExpr));
            })
            ->where('bp.status', 'selesai')
            ->whereYear(DB::raw($eventExpr), $year)
            ->when($gudangId, fn ($q) => $q->whereRaw("{$gudangExpr} = ?", [$gudangId]))
            ->select([
                DB::raw("'batch' as source"),
                'bp.id as doc_id',
                DB::raw("{$gudangExpr} as gudang_id"),
                DB::raw("COALESCE(g.nama, 'Lab Produksi') as gudang_nama"),
                'p.sku',
                'p.nama as produk_nama',
                DB::raw("{$eventExpr} as event_at"),
                DB::raw("{$totalExpr} as qty_total"),
                DB::raw("{$baikExpr} as qty_baik"),
                DB::raw("{$rusakExpr} as qty_rusak"),
            ])
            ->get()
            ->map(function ($row) {
                $row->doc_key = 'batch:' . $row->doc_id;
                return $row;
            });
    }
}

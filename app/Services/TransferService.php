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
        $itemsPayload = $receivingData['items'] ?? [];

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
     * Engine Kalkulasi & Rekapitulasi Barang Rusak Bulanan per Gudang (TC-42, User Override).
     */
    public function getMonthlyWarehouseDefectAnalytics(int $year, ?int $month = null, ?int $gudangId = null): array
    {
        $selectedMonth = $month ?: (int) date('n');

        // Query dasar transaksi transfer yang sudah selesai pada tahun bersangkutan
        $baseQuery = DB::table('transfers as t')
            ->join('transfer_items as ti', 'ti.transfer_id', '=', 't.id')
            ->join('gudang as g', 'g.id', '=', 't.gudang_tujuan_id')
            ->join('produk as p', 'p.id', '=', 'ti.produk_id')
            ->where('t.status', 'selesai')
            ->whereYear('t.waktu_diterima', $year);

        if ($gudangId) {
            $baseQuery->where('t.gudang_tujuan_id', $gudangId);
        }

        // 1. KPI Summary Bulan Terpilih
        $monthQuery = (clone $baseQuery)->whereMonth('t.waktu_diterima', $selectedMonth);

        $totalRusakBulanIni = (float) (clone $monthQuery)->sum('ti.qty_rusak');
        $totalDikirimBulanIni = (float) (clone $monthQuery)->sum('ti.qty_dikirim');
        $totalTransferBulanIni = (int) (clone $monthQuery)->distinct('t.id')->count('t.id');
        $transferTerdampakBulanIni = (int) (clone $monthQuery)->where('ti.qty_rusak', '>', 0)->distinct('t.id')->count('t.id');

        $defectRatePercent = $totalDikirimBulanIni > 0
            ? round(($totalRusakBulanIni / $totalDikirimBulanIni) * 100, 2)
            : 0.0;

        // Cari gudang tujuan paling terdampak di bulan terpilih
        $topGudang = (clone $monthQuery)
            ->select('g.nama', DB::raw('SUM(ti.qty_rusak) as total_rusak'))
            ->groupBy('g.nama')
            ->orderByDesc('total_rusak')
            ->first();

        // 2. Matriks Kerusakan per Gudang x 12 Bulan (Jan s.d. Des)
        $allWarehouses = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'kode']);
        $monthlyByWarehouse = [];

        // Ambil data agregat bulanan per gudang
        $aggData = (clone $baseQuery)
            ->select(
                't.gudang_tujuan_id',
                DB::raw(DB::getDriverName() === 'sqlite' ? "strftime('%m', t.waktu_diterima) as bulan" : "EXTRACT(MONTH FROM t.waktu_diterima) as bulan"),
                DB::raw('SUM(ti.qty_rusak) as total_rusak')
            )
            ->groupBy('t.gudang_tujuan_id', DB::raw(DB::getDriverName() === 'sqlite' ? "strftime('%m', t.waktu_diterima)" : "EXTRACT(MONTH FROM t.waktu_diterima)"))
            ->get();

        // Ambil rincian SKU rusak per gudang
        $skuRusakData = (clone $baseQuery)
            ->where('ti.qty_rusak', '>', 0)
            ->select('t.gudang_tujuan_id', 'p.sku', 'p.nama', DB::raw('SUM(ti.qty_rusak) as total_rusak'))
            ->groupBy('t.gudang_tujuan_id', 'p.sku', 'p.nama')
            ->orderByDesc('total_rusak')
            ->get();

        foreach ($allWarehouses as $wh) {
            $months = array_fill(1, 12, 0.0);
            $totalTahunan = 0.0;

            $whAggs = $aggData->where('gudang_tujuan_id', $wh->id);
            foreach ($whAggs as $row) {
                $m = (int) $row->bulan;
                $val = (float) $row->total_rusak;
                $months[$m] = $val;
                $totalTahunan += $val;
            }

            $skuList = $skuRusakData->where('gudang_tujuan_id', $wh->id)->map(fn ($r) => [
                'sku' => $r->sku,
                'nama' => $r->nama,
                'qty_rusak' => (float) $r->total_rusak,
            ])->values()->toArray();

            $monthlyByWarehouse[] = [
                'gudang_id' => $wh->id,
                'nama' => $wh->nama,
                'kode' => $wh->kode,
                'months' => $months,
                'total_tahunan' => $totalTahunan,
                'sku_rusak_list' => $skuList,
            ];
        }

        return [
            'year' => $year,
            'month' => $selectedMonth,
            'summary' => [
                'total_rusak_bulan_ini' => $totalRusakBulanIni,
                'total_dikirim_bulan_ini' => $totalDikirimBulanIni,
                'defect_rate_percent' => $defectRatePercent,
                'total_transfer_count' => $totalTransferBulanIni,
                'transfer_terdampak_count' => $transferTerdampakBulanIni,
                'gudang_tertinggi_nama' => $topGudang?->nama ?? '—',
                'gudang_tertinggi_qty' => (float) ($topGudang?->total_rusak ?? 0),
            ],
            'monthly_by_warehouse' => $monthlyByWarehouse,
        ];
    }
}

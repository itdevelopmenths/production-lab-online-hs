<?php

namespace App\Http\Controllers;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\RequestTransfer;
use App\Models\Stok;
use App\Services\StokService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class BatchController extends Controller
{
    public function __construct(private readonly StokService $stok)
    {
    }

    public function index()
    {
        $this->authorize('batch.view');

        return view('batches.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('batch.view');

        $query = BatchProduksi::query()->select('batch_produksi.*')
            ->with(['produk:id,sku,nama,satuan', 'outputs.produk:id,sku,nama,satuan', 'gudangTujuan:id,nama']);

        return DataTables::eloquent($query)
            ->addColumn('produk_nama', function ($b) {
                if ($b->outputs->isNotEmpty()) {
                    return $b->outputs->map(function ($out) {
                        $nama = e($out->produk?->nama ?? '-');
                        $qty = \App\Helpers\NumberHelper::formatQty($out->qty_rencana);
                        $satuan = e($out->produk?->satuan ?? 'pcs');
                        return "<div class='py-0.5'><span class='font-medium text-gray-900'>{$nama}</span> <span class='text-gray-500 font-mono text-[11px]'>({$qty} {$satuan})</span></div>";
                    })->implode('');
                }
                $nama = e($b->produk?->nama ?? '-');
                $satuan = e($b->produk?->satuan ?? 'pcs');
                $qty = \App\Helpers\NumberHelper::formatQty($b->qty_rencana);
                return "<div><span class='font-medium text-gray-900'>{$nama}</span> <span class='text-gray-500 font-mono text-[11px]'>({$qty} {$satuan})</span></div>";
            })
            ->editColumn('qty_rencana', fn ($b) => \App\Helpers\NumberHelper::formatQty($b->qty_rencana))
            ->addColumn('qty_baik', fn ($b) => $b->qty_baik !== null ? \App\Helpers\NumberHelper::formatQty($b->qty_baik) : '-')
            ->addColumn('qty_rusak', fn ($b) => $b->qty_rusak !== null ? \App\Helpers\NumberHelper::formatQty($b->qty_rusak) : '-')
            ->editColumn('tanggal', fn ($b) => $b->tanggal?->format('d/m/Y'))
            ->editColumn('status', function ($b) {
                $variant = match($b->status) {
                    'rencana' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'release' => 'bg-sky-50 text-sky-700 border-sky-200',
                    'selesai' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'dibatalkan' => 'bg-rose-50 text-rose-700 border-rose-200',
                    default => 'bg-gray-50 text-gray-700 border-gray-200',
                };
                $label = $b->status === 'release' ? 'Release' : ucfirst($b->status);
                return "<span class='inline-flex items-center px-2 py-0.5 rounded-xs text-[11px] font-semibold border {$variant}'>{$label}</span>";
            })
            ->addColumn('yield', function ($b) {
                $y = $b->yield();
                return $y !== null ? number_format($y, 1) . '%' : '-';
            })
            ->addColumn('action', fn ($b) => view('batches._actions', ['b' => $b])->render())
            ->rawColumns(['action', 'produk_nama', 'status'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('batch.create');
        $produk = Produk::produkJadi()->active()->orderBy('nama')->get(['id', 'sku', 'nama', 'satuan']);
        $gudangOp = Gudang::active()->where('tipe', 'operasional')->orderBy('nama')->get(['id', 'nama']);
        $gudangFf = Gudang::active()->fulfillment()->orderBy('nama')->get(['id', 'nama']);

        return view('batches.create', compact('produk', 'gudangOp', 'gudangFf'));
    }

    public function store(Request $request)
    {
        $this->authorize('batch.create');

        $data = $request->validate([
            'produk_id' => ['nullable', 'exists:produk,id'],
            'qty_rencana' => ['nullable', 'numeric', 'gt:0'],
            'outputs' => ['nullable', 'array', 'min:1'],
            'outputs.*.produk_id' => ['required_with:outputs', 'exists:produk,id'],
            'outputs.*.qty_rencana' => ['required_with:outputs', 'numeric', 'gt:0'],
            'outputs.*.catatan' => ['nullable', 'string', 'max:255'],
            'gudang_operasional_id' => ['nullable', 'exists:gudang,id'],
            'gudang_tujuan_rencana_id' => ['nullable', 'exists:gudang,id'],
            'tanggal' => ['required', 'date'],
        ]);

        // Process outputs: either array or single fallback
        $outputsList = [];
        if (!empty($data['outputs'])) {
            foreach ($data['outputs'] as $row) {
                if (!empty($row['produk_id']) && (float) ($row['qty_rencana'] ?? 0) > 0) {
                    $outputsList[] = [
                        'produk_id' => (int) $row['produk_id'],
                        'qty_rencana' => (float) $row['qty_rencana'],
                        'catatan' => $row['catatan'] ?? null,
                    ];
                }
            }
        }

        if (empty($outputsList)) {
            abort_unless(!empty($data['produk_id']) && (float) ($data['qty_rencana'] ?? 0) > 0, 422, 'Minimal satu produk jadi luaran harus ditentukan.');
            $outputsList[] = [
                'produk_id' => (int) $data['produk_id'],
                'qty_rencana' => (float) $data['qty_rencana'],
                'catatan' => null,
            ];
        }

        $primaryProdukId = $outputsList[0]['produk_id'];
        $totalQtyRencana = array_sum(array_column($outputsList, 'qty_rencana'));

        $batch = DB::transaction(function () use ($data, $outputsList, $primaryProdukId, $totalQtyRencana) {
            $batch = BatchProduksi::create([
                'no_batch' => $this->nextNo(),
                'produk_id' => $primaryProdukId,
                'qty_rencana' => $totalQtyRencana,
                'gudang_operasional_id' => $data['gudang_operasional_id'] ?? null,
                'gudang_tujuan_rencana_id' => $data['gudang_tujuan_rencana_id'] ?? null,
                'status' => 'rencana',
                'tanggal' => $data['tanggal'],
                'created_by' => auth()->id(),
            ]);

            // Save individual outputs
            foreach ($outputsList as $out) {
                $batch->outputs()->create([
                    'produk_id' => $out['produk_id'],
                    'qty_rencana' => $out['qty_rencana'],
                    'catatan' => $out['catatan'],
                ]);
            }

            // BOM explode aggregated across all outputs -> alokasi bahan (aktif)
            $aggregatedBom = [];
            foreach ($outputsList as $out) {
                $produk = Produk::with('bom')->find($out['produk_id']);
                if ($produk && $produk->bom) {
                    foreach ($produk->bom as $bomItem) {
                        $bahanId = $bomItem->bahan_id;
                        $kebutuhan = (float) $bomItem->qty_per_unit * (float) $out['qty_rencana'];
                        $aggregatedBom[$bahanId] = ($aggregatedBom[$bahanId] ?? 0) + $kebutuhan;
                    }
                }
            }

            foreach ($aggregatedBom as $bahanId => $totalAlokasi) {
                $batch->alokasi()->create([
                    'bahan_id' => $bahanId,
                    'qty_dialokasikan' => $totalAlokasi,
                    'status' => 'aktif',
                ]);
            }

            return $batch;
        });

        return redirect()->route('batches.show', $batch)->with('success', "Batch {$batch->no_batch} dibuat, alokasi bahan tercipta.");
    }

    public function show(BatchProduksi $batch)
    {
        $this->authorize('batch.view');
        $batch->load([
            'produk',
            'outputs.produk:id,sku,nama,satuan',
            'gudangOperasional',
            'gudangTujuan',
            'creator:id,name',
            'alokasi.bahan:id,sku,nama,satuan,tipe',
            'opname.bahan:id,nama',
            'transferKirim:id,no_transaksi,referensi_batch_id,status,gudang_asal_id,gudang_tujuan_id',
        ]);

        $bahanIds = $batch->alokasi->pluck('bahan_id')->unique()->filter()->values();

        // 1. Stok fisik di Gudang Operasional saat ini
        $stokOperasional = [];
        if ($batch->gudang_operasional_id && $bahanIds->isNotEmpty()) {
            $stokOperasional = Stok::where('gudang_id', $batch->gudang_operasional_id)
                ->whereIn('produk_id', $bahanIds)
                ->pluck('qty_saat_ini', 'produk_id')
                ->map(fn ($v) => (float) $v)
                ->toArray();
        }

        // 2. Total Alokasi Aktif (dari seluruh batch rencana/aktif) di Gudang Operasional
        $alokasiAktifTotal = [];
        if ($batch->gudang_operasional_id && $bahanIds->isNotEmpty()) {
            $alokasiAktifTotal = DB::table('alokasi_bahan as ab')
                ->join('batch_produksi as bp', 'bp.id', '=', 'ab.batch_id')
                ->whereIn('ab.bahan_id', $bahanIds)
                ->where('ab.status', 'aktif')
                ->where(function ($q) use ($batch) {
                    $q->where('bp.gudang_operasional_id', $batch->gudang_operasional_id)
                      ->orWhereNull('bp.gudang_operasional_id');
                })
                ->groupBy('ab.bahan_id')
                ->select('ab.bahan_id', DB::raw('SUM(ab.qty_dialokasikan) as total_alokasi'))
                ->pluck('total_alokasi', 'ab.bahan_id')
                ->map(fn ($v) => (float) $v)
                ->toArray();
        }

        // 3. Stok fisik di Gudang Bahan Baku Pusat (sebagai referensi untuk Request Bahan jika kurang)
        $gudangPusat = Gudang::bahanBakuPusat()->first()
            ?? Gudang::where('tipe', 'bahan_baku')->first()
            ?? Gudang::where('kode', 'GD-PUSAT')->first();
        $stokPusat = [];
        if ($gudangPusat && $bahanIds->isNotEmpty()) {
            $stokPusat = Stok::where('gudang_id', $gudangPusat->id)
                ->whereIn('produk_id', $bahanIds)
                ->pluck('qty_saat_ini', 'produk_id')
                ->map(fn ($v) => (float) $v)
                ->toArray();
        }

        // 4. Analisa Early Warning / Peringatan Dini
        $earlyWarnings = [];
        $hasDeficitFisik = false;
        $hasDeficitRencana = false;

        foreach ($batch->alokasi as $a) {
            $bahanId = $a->bahan_id;
            $qtyAlokasi = (float) $a->qty_dialokasikan;
            $fisikOp = (float) ($stokOperasional[$bahanId] ?? 0);
            $totalAlok = (float) ($alokasiAktifTotal[$bahanId] ?? 0);
            $stokRencana = $fisikOp - $totalAlok;
            $pusat = (float) ($stokPusat[$bahanId] ?? 0);

            $kurangFisik = max(0, $qtyAlokasi - $fisikOp);

            if ($kurangFisik > 0) {
                $hasDeficitFisik = true;
            }
            if ($stokRencana < 0) {
                $hasDeficitRencana = true;
            }

            $earlyWarnings[$a->id] = [
                'stok_fisik_op' => $fisikOp,
                'total_alokasi' => $totalAlok,
                'stok_rencana' => $stokRencana,
                'stok_pusat' => $pusat,
                'kurang_fisik' => $kurangFisik,
                'is_cukup_fisik' => $kurangFisik <= 0,
                'is_cukup_rencana' => $stokRencana >= 0,
            ];
        }

        return view('batches.show', compact(
            'batch',
            'earlyWarnings',
            'hasDeficitFisik',
            'hasDeficitRencana',
            'gudangPusat'
        ));
    }

    /** Release & Issue: tarik bahan riil dari stok gudang operasional, alokasi dilepas. */
    public function release(BatchProduksi $batch)
    {
        $this->authorize('batch.release');
        abort_unless($batch->status === 'rencana', 422, 'Hanya batch berstatus Rencana yang dapat di-release.');
        abort_unless($batch->gudang_operasional_id, 422, 'Gudang operasional batch belum ditentukan.');

        try {
            DB::transaction(function () use ($batch) {
                foreach ($batch->alokasi()->where('status', 'aktif')->get() as $alok) {
                    $this->stok->keluar($alok->bahan_id, $batch->gudang_operasional_id, (float) $alok->qty_dialokasikan, 'batch_produksi', $batch->id, "Issue {$batch->no_batch}");
                    $alok->update(['status' => 'dilepas']);
                }
                $batch->update(['status' => 'release']);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Bahan di-issue dari stok operasional.');
    }

    /** Selesai: catat qty baik/rusak, produk jadi masuk stok. */
    public function complete(Request $request, BatchProduksi $batch)
    {
        $this->authorize('batch.complete');
        abort_unless($batch->status === 'release', 422, 'Hanya batch yang sudah Release yang dapat diselesaikan.');

        $data = $request->validate([
            'qty_baik' => ['nullable', 'integer', 'min:0'],
            'qty_rusak' => ['nullable', 'integer', 'min:0'],
            'outputs' => ['nullable', 'array'],
            'outputs.*.id' => ['required_with:outputs', 'exists:batch_produksi_outputs,id'],
            'outputs.*.qty_baik' => ['required_with:outputs', 'integer', 'min:0'],
            'outputs.*.qty_rusak' => ['required_with:outputs', 'integer', 'min:0'],
        ]);

        if (!empty($data['outputs'])) {
            foreach ($data['outputs'] as $index => $row) {
                $output = $batch->outputs()->where('id', $row['id'])->first();
                if ($output) {
                    $baik = (int) ($row['qty_baik'] ?? 0);
                    $rusak = (int) ($row['qty_rusak'] ?? 0);
                    $total = $baik + $rusak;
                    $target = (int) round((float) $output->qty_rencana);
                    if ($total !== $target) {
                        throw ValidationException::withMessages([
                            "outputs.{$index}.qty_baik" => "Total kuantitas (Qty Baik + Qty Rusak: {$total}) untuk {$output->produk?->nama} tidak boleh lebih atau kurang dari kuantitas rencana ({$target}).",
                        ]);
                    }
                }
            }
        } else {
            $qtyBaik = (int) ($data['qty_baik'] ?? 0);
            $qtyRusak = (int) ($data['qty_rusak'] ?? 0);
            $total = $qtyBaik + $qtyRusak;
            $target = (int) round((float) $batch->qty_rencana);
            if ($total !== $target) {
                throw ValidationException::withMessages([
                    'qty_baik' => "Total kuantitas (Qty Baik + Qty Rusak: {$total}) tidak boleh lebih atau kurang dari kuantitas rencana ({$target}).",
                ]);
            }
        }

        DB::transaction(function () use ($batch, $data) {
            $gudang = $batch->gudang_operasional_id ?? $batch->gudang_tujuan_rencana_id;

            if (!empty($data['outputs'])) {
                $totalBaik = 0;
                $totalRusak = 0;
                foreach ($data['outputs'] as $row) {
                    $output = $batch->outputs()->where('id', $row['id'])->first();
                    if ($output) {
                        $baik = (float) ($row['qty_baik'] ?? 0);
                        $rusak = (float) ($row['qty_rusak'] ?? 0);
                        $output->update([
                            'qty_baik' => $baik,
                            'qty_rusak' => $rusak,
                        ]);
                        $totalBaik += $baik;
                        $totalRusak += $rusak;

                        if ($gudang && $baik > 0) {
                            $this->stok->masuk(
                                $output->produk_id,
                                $gudang,
                                $baik,
                                'batch_produksi',
                                $batch->id,
                                "Hasil {$batch->no_batch} - {$output->produk?->nama}"
                            );
                        }
                    }
                }
                $batch->update([
                    'qty_baik' => $totalBaik,
                    'qty_rusak' => $totalRusak,
                    'status' => 'selesai',
                ]);
            } else {
                $qtyBaik = (float) ($data['qty_baik'] ?? 0);
                $qtyRusak = (float) ($data['qty_rusak'] ?? 0);

                $firstOutput = $batch->outputs()->first();
                if ($firstOutput) {
                    $firstOutput->update([
                        'qty_baik' => $qtyBaik,
                        'qty_rusak' => $qtyRusak,
                    ]);
                }

                $batch->update([
                    'qty_baik' => $qtyBaik,
                    'qty_rusak' => $qtyRusak,
                    'status' => 'selesai',
                ]);

                if ($gudang && $qtyBaik > 0) {
                    $this->stok->masuk(
                        $batch->produk_id,
                        $gudang,
                        $qtyBaik,
                        'batch_produksi',
                        $batch->id,
                        "Hasil {$batch->no_batch}"
                    );
                }
            }
        });

        return back()->with('success', 'Batch selesai, produk jadi masuk stok.');
    }

    public function cancel(BatchProduksi $batch)
    {
        $this->authorize('batch.cancel');
        abort_unless($batch->status === 'rencana', 422, 'Hanya batch Rencana yang dapat dibatalkan (alokasi dilepas tanpa mutasi stok).');

        DB::transaction(function () use ($batch) {
            $batch->alokasi()->where('status', 'aktif')->update(['status' => 'dibatalkan']);
            $batch->update(['status' => 'dibatalkan']);
        });

        return back()->with('success', 'Batch dibatalkan, alokasi dilepas.');
    }

    /** Stock opname: bandingkan pemakaian teoritis (BOM) vs aktual. */
    public function opname(Request $request, BatchProduksi $batch)
    {
        $this->authorize('batch.opname');

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.bahan_id' => ['required', 'exists:produk,id'],
            'items.*.pemakaian_aktual' => ['required', 'numeric', 'min:0'],
            'items.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($batch, $data) {
            $batch->opname()->delete();
            foreach ($data['items'] as $row) {
                $teoritis = (float) ($batch->alokasi()->where('bahan_id', $row['bahan_id'])->value('qty_dialokasikan') ?? 0);
                $batch->opname()->create([
                    'bahan_id' => $row['bahan_id'],
                    'pemakaian_teoritis' => $teoritis,
                    'pemakaian_aktual' => $row['pemakaian_aktual'],
                    'keterangan' => $row['keterangan'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Stock opname batch dicatat.');
    }

    /** Ajukan Kirim Produk Jadi ke Fulfillment (buat dokumen Request & Transfer). */
    public function kirim(BatchProduksi $batch)
    {
        $this->authorize('rt.create');
        abort_unless($batch->status === 'selesai', 422, 'Batch harus Selesai untuk dikirim.');
        abort_unless((float) $batch->qty_baik > 0, 422, 'Tidak ada qty baik untuk dikirim.');

        $existing = RequestTransfer::where('referensi_batch_id', $batch->id)
            ->where('jenis', 'kirim_produk_jadi')
            ->where('status', '!=', 'dibatalkan')
            ->first();

        if ($existing) {
            return redirect()->route('rt.show', $existing)
                ->with('info', "Dokumen transfer untuk batch {$batch->no_batch} sudah dibuat ({$existing->no_transaksi}).");
        }

        $rt = DB::transaction(function () use ($batch) {
            $year = now()->year;
            $no = sprintf('TR-%d-%04d', $year, RequestTransfer::whereYear('created_at', $year)->count() + 1);
            $rt = RequestTransfer::create([
                'no_transaksi' => $no,
                'jenis' => 'kirim_produk_jadi',
                'gudang_asal_id' => $batch->gudang_operasional_id,
                'gudang_tujuan_id' => $batch->gudang_tujuan_rencana_id,
                'referensi_batch_id' => $batch->id,
                'status' => 'draft',
                'catatan' => "Kirim hasil batch {$batch->no_batch}",
                'created_by' => auth()->id(),
            ]);

            $outputs = $batch->outputs()->where('qty_baik', '>', 0)->get();
            if ($outputs->isNotEmpty()) {
                foreach ($outputs as $out) {
                    $rt->items()->create([
                        'produk_id' => $out->produk_id,
                        'qty_diminta' => $out->qty_baik,
                    ]);
                }
            } else {
                $rt->items()->create([
                    'produk_id' => $batch->produk_id,
                    'qty_diminta' => $batch->qty_baik,
                ]);
            }

            return $rt;
        });

        return redirect()->route('rt.show', $rt)->with('success', "Dokumen kirim {$rt->no_transaksi} dibuat.");
    }

    private function nextNo(): string
    {
        $year = now()->year;
        $count = BatchProduksi::whereYear('tanggal', $year)->count() + 1;

        return sprintf('B-%d-%04d', $year, $count);
    }
}

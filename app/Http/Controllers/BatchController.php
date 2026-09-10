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
            ->with(['produk:id,sku,nama', 'gudangTujuan:id,nama']);

        return DataTables::eloquent($query)
            ->addColumn('produk_nama', fn ($b) => $b->produk?->nama)
            ->editColumn('tanggal', fn ($b) => $b->tanggal?->format('d/m/Y'))
            ->editColumn('status', fn ($b) => ucfirst($b->status))
            ->addColumn('yield', fn ($b) => $b->yield() !== null ? number_format($b->yield(), 1) . '%' : '-')
            ->addColumn('action', fn ($b) => view('batches._actions', ['b' => $b])->render())
            ->rawColumns(['action'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('batch.create');
        $produk = Produk::produkJadi()->active()->orderBy('nama')->get(['id', 'sku', 'nama']);
        $gudangOp = Gudang::active()->where('tipe', 'operasional')->orderBy('nama')->get(['id', 'nama']);
        $gudangFf = Gudang::active()->fulfillment()->orderBy('nama')->get(['id', 'nama']);

        return view('batches.create', compact('produk', 'gudangOp', 'gudangFf'));
    }

    public function store(Request $request)
    {
        $this->authorize('batch.create');

        $data = $request->validate([
            'produk_id' => ['required', 'exists:produk,id'],
            'qty_rencana' => ['required', 'numeric', 'gt:0'],
            'gudang_operasional_id' => ['nullable', 'exists:gudang,id'],
            'gudang_tujuan_rencana_id' => ['nullable', 'exists:gudang,id'],
            'tanggal' => ['required', 'date'],
        ]);

        $batch = DB::transaction(function () use ($data) {
            $batch = BatchProduksi::create([
                'no_batch' => $this->nextNo(),
                'produk_id' => $data['produk_id'],
                'qty_rencana' => $data['qty_rencana'],
                'gudang_operasional_id' => $data['gudang_operasional_id'] ?? null,
                'gudang_tujuan_rencana_id' => $data['gudang_tujuan_rencana_id'] ?? null,
                'status' => 'rencana',
                'tanggal' => $data['tanggal'],
                'created_by' => auth()->id(),
            ]);

            // BOM explode → alokasi bahan (aktif), tanpa mengubah stok fisik.
            $bom = Produk::findOrFail($data['produk_id'])->bom;
            foreach ($bom as $item) {
                $batch->alokasi()->create([
                    'bahan_id' => $item->bahan_id,
                    'qty_dialokasikan' => (float) $item->qty_per_unit * (float) $data['qty_rencana'],
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
            'gudangOperasional',
            'gudangTujuan',
            'creator:id,name',
            'alokasi.bahan:id,sku,nama,satuan,tipe',
            'opname.bahan:id,nama',
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
        $gudangPusat = Gudang::where('tipe', 'bahan_baku')->first()
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
            'qty_baik' => ['required', 'numeric', 'min:0'],
            'qty_rusak' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($batch, $data) {
            $batch->update([
                'qty_baik' => $data['qty_baik'],
                'qty_rusak' => $data['qty_rusak'],
                'status' => 'selesai',
            ]);
            $gudang = $batch->gudang_operasional_id ?? $batch->gudang_tujuan_rencana_id;
            if ($gudang && (float) $data['qty_baik'] > 0) {
                $this->stok->masuk($batch->produk_id, $gudang, (float) $data['qty_baik'], 'batch_produksi', $batch->id, "Hasil {$batch->no_batch}");
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
            $rt->items()->create([
                'produk_id' => $batch->produk_id,
                'qty_diminta' => $batch->qty_baik,
            ]);

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

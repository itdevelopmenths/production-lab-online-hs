<?php

namespace App\Http\Controllers;

use App\Models\AnalisaFulfillmentInput;
use App\Models\AnalisaImpor;
use App\Models\AnalisaImporMeta;
use App\Models\AnalisaLokal;
use App\Models\AnalisaLokalInput;
use App\Models\Gudang;
use App\Models\LeadTimeImpor;
use App\Models\LeadTimeLokal;
use App\Models\LeadTimeLokalStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\RekomendasiOrderLokal;
use App\Models\RiwayatAnalisa;
use App\Models\Supplier;
use App\Services\AnalisaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalisaController extends Controller
{
    public function __construct(private readonly AnalisaService $analisa)
    {
    }

    public function index()
    {
        $this->authorize('analisa.view');

        $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori']);
        $gudang = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'tipe']);

        return view('analisa.index', compact('suppliers', 'gudang'));
    }

    /**
     * Endpoint data lokal: Mengembalikan working data dari 4 tabel tersimpan
     */
    public function lokalData(): JsonResponse
    {
        $this->authorize('analisa.view');

        // Pastikan tabel rekomendasi sudah ada data, jika belum, jalankan generateLokal awal
        if (! RekomendasiOrderLokal::exists()) {
            $this->analisa->generateLokal(null, auth()->id());
        }

        $rekomendasi = RekomendasiOrderLokal::with([
            'produk' => function ($q) {
                $q->select('id', 'sku', 'nama', 'satuan', 'faktor_konversi', 'satuan_order_moq', 'harga_hpp', 'supplier_id')
                    ->with('supplier:id,nama,kategori');
            },
            'generator:id,name'
        ])->get();
        $analisa = AnalisaLokal::with(['produk:id,sku,nama,satuan', 'generator:id,name'])->get()->keyBy('produk_id');
        $leadTime = LeadTimeLokal::with('produk:id,sku,nama')->get()->keyBy('produk_id');
        $stages = LeadTimeLokalStage::with('produk:id,sku,nama')->get()->groupBy('produk_id');
        $lastGenerated = AnalisaLokal::latest('generated_at')->with('generator:id,name')->first();

        $rows = $rekomendasi->map(function ($rek) use ($analisa, $leadTime) {
            $al = $analisa->get($rek->produk_id);
            $lt = $leadTime->get($rek->produk_id);

            return [
                'id' => $rek->id,
                'produk_id' => $rek->produk_id,
                'sku' => $rek->produk?->sku,
                'nama' => $rek->produk?->nama,
                'satuan' => $rek->produk?->satuan,
                'supplier_id' => $rek->produk?->supplier_id,
                'supplier_nama' => $rek->produk?->supplier?->nama ?? '-',
                'supplier_kategori' => $rek->produk?->supplier?->kategori ?? '-',
                'faktor_konversi' => (float) ($rek->produk?->faktor_konversi ?: 1),
                'total_avg_lead_time' => (int) ($lt?->total_average_lead_time ?? $al?->total_average_lead_time ?? 0),
                'total_max_lead_time' => (int) ($lt?->total_max_lead_time ?? 0),
                'safety_stock' => (int) ($lt?->safety_stock ?? $al?->safety_stock ?? 0),
                'safety_stock_hari' => (int) ($lt?->safety_stock ?? $al?->safety_stock ?? 0),
                'terjual_rata_rata_4bulan' => (float) ($al?->terjual_rata_rata_4bulan ?? 0),
                'adu' => (float) ($al?->adu ?? 0),
                'review_period' => (int) ($al?->review_period ?? 15),
                'batas_minimum' => (float) $rek->batas_minimum,
                'target_stock' => (float) $rek->target_stock,
                'stok_saat_ini' => (float) $rek->stok_saat_ini,
                'akan_datang' => (float) $rek->akan_datang,
                'tersedia' => (float) ($rek->stok_saat_ini + $rek->akan_datang),
                'selisih' => (float) $rek->selisih,
                'rumus_moq' => (float) $rek->rumus_moq,
                'status' => $rek->status,
                'qty_order' => (float) $rek->rumus_moq, // kompatibilitas kolom lama
                'rekomendasi_order' => (float) $rek->rekomendasi_order,
                'harga_per_satuan' => (float) $rek->harga_ml_pcs,
                'total_nominal_order' => (float) $rek->total_nominal_order,
                'generated_at' => $rek->generated_at?->format('d/m/Y H:i'),
                'generated_by' => $rek->generator?->name ?? 'Sistem',
            ];
        });

        // Format stages untuk grid tabel tahap
        $formattedStages = [];
        foreach ($stages as $prodId => $stgList) {
            $p = $stgList->first()->produk;
            $avg = $stgList->firstWhere('skenario', 'average');
            $max = $stgList->firstWhere('skenario', 'max');
            $formattedStages[] = [
                'produk_id' => $prodId,
                'sku' => $p?->sku,
                'nama' => $p?->nama,
                'average' => $avg ? [
                    'id' => $avg->id,
                    'perencanaan' => $avg->perencanaan,
                    'approval' => $avg->approval,
                    'supplier_confirm' => $avg->supplier_confirm,
                    'payment' => $avg->payment,
                    'po' => $avg->po,
                    'pengemasan' => $avg->pengemasan,
                    'pengiriman' => $avg->pengiriman,
                    'unloading' => $avg->unloading,
                    'input' => $avg->input,
                    'total' => $avg->totalHari(),
                ] : null,
                'max' => $max ? [
                    'id' => $max->id,
                    'perencanaan' => $max->perencanaan,
                    'approval' => $max->approval,
                    'supplier_confirm' => $max->supplier_confirm,
                    'payment' => $max->payment,
                    'po' => $max->po,
                    'pengemasan' => $max->pengemasan,
                    'pengiriman' => $max->pengiriman,
                    'unloading' => $max->unloading,
                    'input' => $max->input,
                    'total' => $max->totalHari(),
                ] : null,
            ];
        }

        return response()->json([
            'data' => $rows,
            'tables' => [
                'rekomendasi' => $rows,
                'analisa' => $analisa->values(),
                'lead_time' => $leadTime->values(),
                'stages' => $formattedStages,
            ],
            'meta' => [
                'last_generated_at' => $lastGenerated?->generated_at?->format('d/m/Y H:i'),
                'last_generated_by' => $lastGenerated?->generator?->name ?? 'Sistem',
            ],
        ]);
    }

    /**
     * Trigger eksekusi Generate Analisa Lokal
     */
    public function generateLokal(Request $request)
    {
        $this->authorize('analisa.manage');

        $produkId = $request->input('produk_id') ? (int) $request->input('produk_id') : null;
        $result = $this->analisa->generateLokal($produkId, auth()->id());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil men-generate data analisa lokal ({$result['generated_count']} produk).",
                'data' => $result,
            ]);
        }

        return back()->with('success', "Berhasil men-generate data analisa lokal ({$result['generated_count']} produk).");
    }

    /**
     * Update 9 tahap lead time
     */
    public function updateLeadTimeStages(Request $request)
    {
        $this->authorize('analisa.manage');

        $data = $request->validate([
            'stages' => ['required', 'array'],
            'stages.*.id' => ['required', 'exists:lead_time_lokal_stage,id'],
            'stages.*.perencanaan' => ['nullable', 'integer', 'min:0'],
            'stages.*.approval' => ['nullable', 'integer', 'min:0'],
            'stages.*.supplier_confirm' => ['nullable', 'integer', 'min:0'],
            'stages.*.payment' => ['nullable', 'integer', 'min:0'],
            'stages.*.po' => ['nullable', 'integer', 'min:0'],
            'stages.*.pengemasan' => ['nullable', 'integer', 'min:0'],
            'stages.*.pengiriman' => ['nullable', 'integer', 'min:0'],
            'stages.*.unloading' => ['nullable', 'integer', 'min:0'],
            'stages.*.input' => ['nullable', 'integer', 'min:0'],
            'tambahan_buffer_hari' => ['nullable', 'integer', 'min:0'],
            'produk_id' => ['nullable', 'exists:produk,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['stages'] as $stg) {
                LeadTimeLokalStage::where('id', $stg['id'])->update([
                    'perencanaan' => (int) ($stg['perencanaan'] ?? 0),
                    'approval' => (int) ($stg['approval'] ?? 0),
                    'supplier_confirm' => (int) ($stg['supplier_confirm'] ?? 0),
                    'payment' => (int) ($stg['payment'] ?? 0),
                    'po' => (int) ($stg['po'] ?? 0),
                    'pengemasan' => (int) ($stg['pengemasan'] ?? 0),
                    'pengiriman' => (int) ($stg['pengiriman'] ?? 0),
                    'unloading' => (int) ($stg['unloading'] ?? 0),
                    'input' => (int) ($stg['input'] ?? 0),
                ]);
            }

            if (! empty($data['produk_id'])) {
                if (isset($data['tambahan_buffer_hari'])) {
                    LeadTimeLokal::where('produk_id', $data['produk_id'])->update([
                        'tambahan_buffer_hari' => (int) $data['tambahan_buffer_hari'],
                    ]);
                }
                $this->analisa->generateLokal((int) $data['produk_id'], auth()->id());
            }
        });

        $leadTime = ! empty($data['produk_id']) ? LeadTimeLokal::where('produk_id', $data['produk_id'])->first() : null;

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tahap lead time berhasil diperbarui & analisa dihitung ulang.',
                'data' => $leadTime,
            ]);
        }

        return back()->with('success', 'Tahap lead time berhasil diperbarui & analisa dihitung ulang.');
    }

    public function imporData(): JsonResponse
    {
        $this->authorize('analisa.view');

        // Pastikan tabel data kerja impor sudah terisi; jika belum, generate otomatis
        if (! AnalisaImpor::exists()) {
            $this->analisa->generateImpor(null, auth()->id());
        }

        $imporList = AnalisaImpor::with([
            'produk' => function ($q) {
                $q->select('id', 'sku', 'nama', 'satuan', 'satuan_order_moq', 'faktor_konversi', 'supplier_id')
                    ->with('supplier:id,nama,kategori');
            },
            'generator:id,name'
        ])->get();
        $ltMap = LeadTimeImpor::all()->keyBy('produk_id');
        $lastGenerated = $imporList->sortByDesc('generated_at')->first();

        $rows = $imporList->map(function ($ai) use ($ltMap) {
            $lt = $ltMap->get($ai->produk_id);

            return [
                'id' => $ai->id,
                'produk_id' => $ai->produk_id,
                'sku' => $ai->produk?->sku,
                'nama' => $ai->produk?->nama,
                'satuan' => $ai->produk?->satuan,
                'supplier_id' => $ai->produk?->supplier_id,
                'supplier_nama' => $ai->produk?->supplier?->nama ?? '-',
                'supplier_kategori' => $ai->produk?->supplier?->kategori ?? '-',
                'satuan_order_moq' => $ai->produk?->satuan_order_moq,
                'faktor_konversi' => (float) ($ai->produk?->faktor_konversi ?: 1),
                'punya_varian' => (bool) $ai->punya_varian,
                'klasifikasi_abc' => $ai->klasifikasi_abc,
                'lead_time_average' => (float) ($lt?->lead_time_average ?? $ai->lead_time),
                'lead_time_max' => (float) ($lt?->lead_time_max ?? 0),
                'buffer_days' => (float) $ai->buffer_days,
                'safety_stock' => (float) $ai->safety_stock,
                'minimum_stock' => (float) $ai->minimum_stock,
                'target_stock' => (float) $ai->target_stock,
                'stok_saat_ini' => (float) $ai->stok_saat_ini,
                'inbound_before_eta' => (float) $ai->inbound_before_eta,
                'proyeksi' => (float) $ai->proyeksi,
                'qty_order' => (float) $ai->qty_order,
                'po' => (float) $ai->po,
                'total_qty_order' => (float) ($ai->po ?: $ai->qty_order),
                'total_selisih' => (float) ($ai->proyeksi - $ai->target_stock),
                'status' => $ai->status,
                'harga_per_satuan' => (float) $ai->harga_per_satuan,
                'total_nominal_order' => (float) $ai->total_nominal_order,
                'varian' => $ai->varian_detail ?? [],
                'generated_at' => $ai->generated_at?->format('d/m/Y H:i'),
                'generated_by' => $ai->generator?->name ?? 'Sistem',
            ];
        })->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'last_generated_at' => $lastGenerated?->generated_at?->format('d/m/Y H:i'),
                'last_generated_by' => $lastGenerated?->generator?->name ?? 'Sistem',
            ],
        ]);
    }

    /**
     * Trigger eksekusi Generate Analisa Impor
     */
    public function generateImpor(Request $request)
    {
        $this->authorize('analisa.manage');

        $produkId = $request->input('produk_id') ? (int) $request->input('produk_id') : null;
        $result = $this->analisa->generateImpor($produkId, auth()->id());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil men-generate analisa impor ({$result['generated_count']} produk).",
                'data' => $result,
            ]);
        }

        return back()->with('success', "Berhasil men-generate analisa impor ({$result['generated_count']} produk).");
    }

    public function fulfillmentData(): JsonResponse
    {
        $this->authorize('analisa.view');

        $grouped = AnalisaFulfillmentInput::with(['produk:id,sku,nama,satuan_order_moq', 'gudang:id,nama'])
            ->get()->groupBy('produk_id');

        $rows = $grouped->map(function ($rows, $produkId) {
            $produk = $rows->first()->produk;
            $hasil = $this->analisa->fulfillment($rows, (float) ($produk?->satuan_order_moq ?? 1));

            return array_merge([
                'produk_id' => $produkId,
                'sku' => $produk?->sku,
                'nama' => $produk?->nama,
            ], $hasil);
        })->values();

        return response()->json(['data' => $rows]);
    }

    public function riwayatData(): JsonResponse
    {
        $this->authorize('analisa.view');

        $rows = RiwayatAnalisa::with('pencatat:id,name')->latest()->limit(500)->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'session_id' => $r->session_id ?? '-',
                'tanggal' => $r->tanggal->format('d/m/Y'),
                'tipe' => ucwords(str_replace('_', ' ', $r->tipe)),
                'tipe_raw' => $r->tipe,
                'item_label' => $r->item_label,
                'batas_minimum' => (float) $r->batas_minimum,
                'target_stock' => (float) $r->target_stock,
                'status' => $r->status,
                'qty_order' => (float) $r->qty_order,
                'is_locked' => (bool) $r->is_locked,
                'pencatat' => $r->pencatat?->name ?? 'Sistem',
                'detail' => $r->detail_payload ?? [],
            ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Workflow Finalisasi Analisa: Kunci working data aktif ke riwayat_analisa
     */
    public function finalisasi(Request $request)
    {
        $this->authorize('analisa.snapshot');

        $data = $request->validate([
            'tipe' => ['nullable', 'string', 'in:bahan_lokal,bahan_impor,produk_jadi_fulfillment,all'],
        ]);
        $tipe = $data['tipe'] ?? 'all';

        $result = $this->analisa->finalisasi($tipe, auth()->id());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Analisa berhasil difinalisasi ke Riwayat ({$result['count']} baris, Sesi: {$result['session_id']}).",
                'session_id' => $result['session_id'],
                'count' => $result['count'],
                'data' => $result,
            ]);
        }

        return back()->with('success', "Analisa berhasil difinalisasi ke Riwayat ({$result['count']} baris, Sesi: {$result['session_id']}).");
    }

    /** Simpan snapshot hasil analisa saat ini ke tab Riwayat. */
    public function snapshot(Request $request)
    {
        $this->authorize('analisa.snapshot');

        $tipe = $request->validate([
            'tipe' => ['required', Rule::in(['bahan_lokal', 'bahan_impor', 'produk_jadi_fulfillment'])],
        ])['tipe'];

        $count = DB::transaction(function () use ($tipe) {
            $n = 0;
            if ($tipe === 'bahan_lokal') {
                foreach (AnalisaLokalInput::with('produk')->get() as $input) {
                    $h = $this->analisa->lokal($input);
                    $this->simpanRiwayat($tipe, $input->produk?->sku ?? '-', $h['batas_minimum'], $h['target_stock'], $h['status'], $h['qty_order']);
                    $n++;
                }
            } elseif ($tipe === 'bahan_impor') {
                foreach (AnalisaImporMeta::with(['produk', 'varian'])->get() as $meta) {
                    $h = $this->analisa->impor($meta);
                    $this->simpanRiwayat($tipe, $meta->produk?->sku ?? '-', 0, 0, $h['status'], $h['total_qty_order']);
                    $n++;
                }
            } else {
                $grouped = AnalisaFulfillmentInput::with(['produk', 'gudang'])->get()->groupBy('produk_id');
                foreach ($grouped as $rows) {
                    $produk = $rows->first()->produk;
                    $h = $this->analisa->fulfillment($rows, (float) ($produk?->satuan_order_moq ?? 1));
                    $this->simpanRiwayat($tipe, $produk?->sku ?? '-', $h['batas_minimum_total'], $h['target_stock_total'], $h['status'], $h['qty_order']);
                    $n++;
                }
            }

            return $n;
        });

        return back()->with('success', "Snapshot tersimpan ({$count} baris).");
    }

    /**
     * Formulir pembuatan Draft PO dari hasil analisa stok (halaman tersendiri)
     */
    public function createPoForm(Request $request)
    {
        $this->authorize('analisa.create_po');

        $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori']);
        $gudang = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'tipe']);

        // Ambil ID rekomendasi jika dikirim spesifik lewat query / POST
        $ids = $request->input('ids');
        if (is_string($ids)) {
            $ids = array_filter(array_map('intval', explode(',', $ids)));
        } elseif (is_array($ids)) {
            $ids = array_filter(array_map('intval', $ids));
        }

        $query = RekomendasiOrderLokal::with(['produk' => function ($q) {
            $q->select('id', 'sku', 'nama', 'satuan', 'satuan_order_moq', 'faktor_konversi', 'harga_hpp', 'supplier_id')
                ->with('supplier:id,nama');
        }]);

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            // Default: muat seluruh item yang statusnya perlu order ('order' atau 'po')
            $query->whereIn('status', ['order', 'po']);
        }

        $rekomendasiList = $query->get();

        // Fallback jika id yang dikirim adalah produk_id
        if ($rekomendasiList->isEmpty() && ! empty($ids)) {
            $rekomendasiList = RekomendasiOrderLokal::with(['produk' => function ($q) {
                $q->select('id', 'sku', 'nama', 'satuan', 'satuan_order_moq', 'faktor_konversi', 'harga_hpp', 'supplier_id')
                    ->with('supplier:id,nama');
            }])->whereIn('produk_id', $ids)->get();
        }

        // Susun item awal untuk form Alpine.js
        $prefilledItems = $rekomendasiList->map(function ($r) {
            $faktor = (float) ($r->produk?->faktor_konversi ?: 1);
            $qtySatuanBeli = (float) $r->rekomendasi_order;
            $qtyDasar = (float) $r->rumus_moq;
            $satuanBeli = $r->produk?->satuan_order_moq ?: $r->produk?->satuan ?: 'pcs';
            $hargaSatuan = (float) $r->harga_ml_pcs;
            $hargaTotal = (float) $r->total_nominal_order;

            return [
                'id' => $r->id,
                'produk_id' => $r->produk_id,
                'sku' => $r->produk?->sku ?? '-',
                'nama' => $r->produk?->nama ?? '-',
                'supplier_id' => $r->produk?->supplier_id,
                'supplier_nama' => $r->produk?->supplier?->nama ?? '-',
                'satuan_dasar' => $r->produk?->satuan ?? 'pcs',
                'satuan_beli' => $satuanBeli,
                'qty_satuan_beli' => $qtySatuanBeli,
                'faktor_konversi' => $faktor,
                'qty' => $qtyDasar,
                'harga_satuan' => $hargaSatuan,
                'harga_total' => $hargaTotal,
            ];
        })->values();

        // Dukung juga item dari AnalisaImpor jika ada $ids yang cocok atau jika rekomendasi lokal kosong
        if (! empty($ids)) {
            $imporItems = AnalisaImpor::with(['produk' => function ($q) {
                $q->select('id', 'sku', 'nama', 'satuan', 'satuan_order_moq', 'faktor_konversi', 'harga_hpp', 'supplier_id')
                    ->with('supplier:id,nama');
            }])
                ->where(function ($q) use ($ids) {
                    $q->whereIn('id', $ids)->orWhereIn('produk_id', $ids);
                })
                ->whereNotIn('produk_id', $prefilledItems->pluck('produk_id'))
                ->get();

            $prefilledImpor = $imporItems->map(function ($ai) {
                $faktor = (float) ($ai->produk?->faktor_konversi ?: 1);
                $qtySatuanBeli = (float) ($ai->po ?: $ai->qty_order);
                $qtyDasar = $qtySatuanBeli * $faktor;
                $satuanBeli = $ai->produk?->satuan_order_moq ?: $ai->produk?->satuan ?: 'pcs';
                $hargaSatuan = (float) $ai->harga_per_satuan;
                $hargaTotal = (float) $ai->total_nominal_order;

                return [
                    'id' => 'impor_'.$ai->id,
                    'produk_id' => $ai->produk_id,
                    'sku' => $ai->produk?->sku ?? '-',
                    'nama' => $ai->produk?->nama ?? '-',
                    'supplier_id' => $ai->produk?->supplier_id,
                    'supplier_nama' => $ai->produk?->supplier?->nama ?? '-',
                    'satuan_dasar' => $ai->produk?->satuan ?? 'pcs',
                    'satuan_beli' => $satuanBeli,
                    'qty_satuan_beli' => $qtySatuanBeli,
                    'faktor_konversi' => $faktor,
                    'qty' => $qtyDasar,
                    'harga_satuan' => $hargaSatuan,
                    'harga_total' => $hargaTotal,
                ];
            });

            $prefilledItems = $prefilledItems->concat($prefilledImpor);
        }

        $defaultSupplierId = $request->filled('supplier_id') ? (int) $request->input('supplier_id') : null;
        if (! $defaultSupplierId) {
            $supIds = $prefilledItems->pluck('supplier_id')->filter()->unique();
            if ($supIds->count() === 1) {
                $defaultSupplierId = $supIds->first();
            }
        }

        $allProduk = Produk::active()->bahan()->orderBy('nama')->get(['id', 'sku', 'nama', 'satuan', 'satuan_order_moq', 'faktor_konversi', 'harga_hpp']);

        return view('analisa.create-po', compact('suppliers', 'gudang', 'prefilledItems', 'allProduk', 'defaultSupplierId'));
    }

    /** Buat draft PO dari hasil analisa (ditandai "Dari Analisa"). */
    public function createPo(Request $request)
    {
        $this->authorize('analisa.create_po');

        $data = $request->validate([
            'supplier_id' => ['required', 'exists:supplier,id'],
            'gudang_id' => ['nullable', 'exists:gudang,id'],
            'tanggal' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
            'no_invoice' => ['nullable', 'string', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'sumber_dana' => ['nullable', 'string', 'max:100'],
            'skema_bayar' => ['nullable', 'string', 'in:cash,tempo,termin'],
            'tanggal_tempo' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'distinct', 'exists:produk,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.qty_satuan_beli' => ['nullable', 'numeric', 'gt:0'],
            'items.*.satuan_beli' => ['nullable', 'string', 'max:50'],
            'items.*.faktor_konversi' => ['nullable', 'numeric', 'gt:0'],
            'items.*.harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'items.*.harga_total' => ['required', 'numeric', 'min:0'],
        ]);

        $po = DB::transaction(function () use ($data) {
            $year = now()->year;
            $no = sprintf('PO-%d-%04d', $year, PurchaseOrder::whereYear('tanggal', $year)->count() + 1);
            $subtotal = (float) collect($data['items'])->sum('harga_total');
            $po = PurchaseOrder::create([
                'no_po' => $no,
                'supplier_id' => $data['supplier_id'],
                'gudang_id' => $data['gudang_id'] ?? null,
                'no_invoice' => $data['no_invoice'] ?? null,
                'tanggal' => $data['tanggal'] ?? now()->toDateString(),
                'eta' => $data['eta'] ?? null,
                'catatan' => $data['catatan'] ?? null,
                'sumber_dana' => $data['sumber_dana'] ?? null,
                'skema_bayar' => $data['skema_bayar'] ?? 'cash',
                'tanggal_tempo' => $data['tanggal_tempo'] ?? null,
                'status' => 'draft',
                'dari_analisa' => true,
                'subtotal_produk' => $subtotal,
                'grand_total' => $subtotal,
                'created_by' => auth()->id(),
            ]);
            foreach ($data['items'] as $row) {
                $po->items()->create([
                    'produk_id' => $row['produk_id'],
                    'qty' => $row['qty'],
                    'qty_satuan_beli' => $row['qty_satuan_beli'] ?? null,
                    'satuan_beli' => $row['satuan_beli'] ?? null,
                    'faktor_konversi' => $row['faktor_konversi'] ?? 1,
                    'harga_satuan' => $row['harga_satuan'] ?? (($row['qty'] > 0) ? ($row['harga_total'] / $row['qty']) : 0),
                    'harga_total' => $row['harga_total'],
                ]);
            }

            return $po;
        });

        return redirect()->route('purchasing.show', $po)->with('success', "Draft PO {$po->no_po} dibuat dari Analisa.");
    }

    private function simpanRiwayat(string $tipe, string $label, float $batasMin, float $target, string $status, float $qtyOrder): void
    {
        RiwayatAnalisa::create([
            'tanggal' => now()->toDateString(),
            'tipe' => $tipe,
            'item_label' => $label,
            'batas_minimum' => $batasMin,
            'target_stock' => $target,
            'status' => $status,
            'qty_order' => $qtyOrder,
            'dicatat_oleh' => auth()->id(),
        ]);
    }
}

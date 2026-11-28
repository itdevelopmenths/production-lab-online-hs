<?php

namespace App\Http\Controllers;

use App\Models\AnalisaFulfillmentInput;
use App\Models\AnalisaImpor;
use App\Models\AnalisaImporMeta;
use App\Models\AnalisaImporVarian;
use App\Models\AnalisaLokal;
use App\Models\AnalisaLokalInput;
use App\Models\Gudang;
use App\Models\KlasifikasiAbc;
use App\Models\LeadTimeImpor;
use App\Models\LeadTimeLokal;
use App\Models\LeadTimeLokalStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\RekomendasiOrderLokal;
use App\Models\RiwayatAnalisa;
use App\Models\Stok;
use App\Models\Supplier;
use App\Models\SupplierProdukLtStageSummary;
use App\Models\Uom;
use App\Services\AnalisaService;
use App\Services\Purchasing\PurchasingCostCalculator;
use App\Services\Purchasing\PurchasingPaymentService;
use App\Services\Purchasing\PurchasingStageService;
use App\Services\StokService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnalisaController extends Controller
{
    public function __construct(
        private readonly AnalisaService $analisa,
        private readonly PurchasingCostCalculator $calculator,
        private readonly PurchasingPaymentService $paymentService,
        private readonly PurchasingStageService $stageService,
        private readonly StokService $stok
    ) {
    }

    /** Sub menu Analisa Stok (sidebar) -> tab halaman analisa. */
    public const TABS = [
        'lokal' => 'Bahan Baku Lokal',
        'impor' => 'Bahan Baku Impor',
        'produksi' => 'Produk Jadi (Gudang Produksi)',
        'fulfillment' => 'Produk Jadi (Fulfillment)',
        'riwayat' => 'Log Riwayat Snapshot',
    ];

    public function index(Request $request)
    {
        $this->authorize('analisa.view');

        $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori']);
        $gudang = Gudang::active()->orderBy('nama')->get(['id', 'nama', 'tipe']);
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'lokal';
        $tabLabel = self::TABS[$tab];

        return view('analisa.index', compact('suppliers', 'gudang', 'tab', 'tabLabel'));
    }

    /**
     * Endpoint data lokal: Mengembalikan working data dari 4 tabel tersimpan
     */
    public function lokalData(): JsonResponse
    {
        $this->authorize('analisa.view');

        // Hitung ulang otomatis setiap tab dibuka supaya stok, PO berjalan, ADU, dan lead time selalu terbaru
        // (tidak perlu tombol Generate). Lock mencegah dua pengguna menghitung bersamaan; bila sedang
        // dihitung pengguna lain, tampilkan hasil terakhir.
        $this->hitungUlangLive('lokal');

        $lokalIds = $this->analisa->lokalEligibleProdukIds();

        $rekomendasi = empty($lokalIds) ? collect() : RekomendasiOrderLokal::with([
            'produk' => function ($q) {
                $q->select('id', 'sku', 'nama', 'satuan', 'faktor_konversi', 'satuan_order_moq', 'harga_hpp', 'supplier_id')
                    ->with('supplier:id,nama,kategori');
            },
            'generator:id,name'
        ])->whereIn('produk_id', $lokalIds)->get();
        $analisa = empty($lokalIds) ? collect() : AnalisaLokal::with([
            'produk' => function ($q) {
                $q->select('id', 'sku', 'nama', 'satuan', 'supplier_id')->with('supplier:id,nama,kategori');
            },
            'supplierTerpilih:id,nama,kategori',
            'generator:id,name'
        ])->whereIn('produk_id', $lokalIds)->get()->keyBy('produk_id');
        $leadTime = empty($lokalIds) ? collect() : LeadTimeLokal::with([
            'produk' => function ($q) {
                $q->select('id', 'sku', 'nama', 'satuan', 'supplier_id')->with('supplier:id,nama,kategori');
            }
        ])->whereIn('produk_id', $lokalIds)->get()->keyBy('produk_id');
        $stages = empty($lokalIds) ? collect() : LeadTimeLokalStage::with([
            'produk' => function ($q) {
                $q->select('id', 'sku', 'nama', 'satuan', 'supplier_id')->with('supplier:id,nama,kategori');
            }
        ])->whereIn('produk_id', $lokalIds)->get()->groupBy('produk_id');
        $empiricalStageSummaries = SupplierProdukLtStageSummary::where('jumlah_transaksi', '>=', 1)
            ->get()
            ->groupBy(fn ($row) => $row->supplier_id . ':' . $row->produk_id);
        $lastGenerated = AnalisaLokal::latest('generated_at')->with('generator:id,name')->first();

        $actualStockMap = empty($lokalIds) ? collect() : Stok::whereIn('produk_id', $lokalIds)
            // Stok analisa bahan = Gudang Bahan Baku saja (stok Lab tidak dihitung).
            ->whereIn('gudang_id', Gudang::where('tipe', 'bahan_baku')->select('id'))
            ->groupBy('produk_id')
            ->select('produk_id', DB::raw('SUM(qty_saat_ini) as total_qty'))
            ->pluck('total_qty', 'produk_id');
        $hppMap = empty($lokalIds) ? [] : $this->stok->resolveHppMap(is_array($lokalIds) ? $lokalIds : $lokalIds->all());

        $rows = $rekomendasi->map(function ($rek) use ($analisa, $leadTime, $actualStockMap, $hppMap) {
            $al = $analisa->get($rek->produk_id);
            $lt = $leadTime->get($rek->produk_id);

            return [
                'id' => $rek->id,
                'produk_id' => $rek->produk_id,
                'sku' => $rek->produk?->sku,
                'nama' => $rek->produk?->nama,
                'satuan' => $rek->produk?->satuan,
                'supplier_id' => $al?->supplier_terpilih_id ?? $rek->produk?->supplier_id,
                'supplier_nama' => $al?->supplierTerpilih?->nama ?? $rek->produk?->supplier?->nama ?? '-',
                'supplier_kategori' => $al?->supplierTerpilih?->kategori ?? $rek->produk?->supplier?->kategori ?? '-',
                'sumber_lt' => $al?->sumber_lt ?? 'manual',
                'faktor_konversi' => (float) ($rek->produk?->faktor_konversi ?: 1),
                'total_avg_lead_time' => AnalisaService::roundLeadTimeDays((float) ($lt?->total_average_lead_time ?? $al?->total_average_lead_time ?? 0)),
                'total_max_lead_time' => AnalisaService::roundLeadTimeDays((float) ($lt?->total_max_lead_time ?? 0)),
                'tambahan_buffer_hari' => AnalisaService::roundLeadTimeDays((float) ($lt?->tambahan_buffer_hari ?? 0)),
                'safety_stock' => AnalisaService::roundLeadTimeDays((float) ($lt?->safety_stock ?? $al?->safety_stock ?? 0)),
                'safety_stock_hari' => AnalisaService::roundLeadTimeDays((float) ($lt?->safety_stock ?? $al?->safety_stock ?? 0)),
                'terjual_rata_rata_4bulan' => (float) round($al?->terjual_rata_rata_4bulan ?? 0),
                'adu' => (float) round($al?->adu ?? 0),
                'review_period' => (int) ($al?->review_period ?? 15),
                'batas_minimum' => (float) round($rek->batas_minimum),
                'target_stock' => (float) round($rek->target_stock),
                'stok_saat_ini' => (float) round($rek->stok_saat_ini),
                'current_hpp' => (float) ($hppMap[$rek->produk_id] ?? $rek->produk?->harga_hpp ?? 0),
                'current_stok_aktual' => (float) ($actualStockMap[$rek->produk_id] ?? 0),
                'current_terjual_sistem' => (float) round($al?->terjual_rata_rata_4bulan ?? 0),
                'akan_datang' => (float) round($rek->akan_datang),
                'tersedia' => (float) round($rek->stok_saat_ini + $rek->akan_datang),
                'selisih' => (float) round($rek->selisih),
                'rumus_moq' => (float) round($rek->rumus_moq),
                'status' => $rek->status,
                'qty_order' => (float) round($rek->rumus_moq), // kompatibilitas kolom lama
                'rekomendasi_order' => (float) round($rek->rekomendasi_order),
                'harga_per_satuan' => (float) round($rek->harga_ml_pcs, 2),
                'total_nominal_order' => (float) round($rek->total_nominal_order),
                'generated_at' => $rek->generated_at?->format('d/m/Y H:i'),
                'generated_by' => $rek->generator?->name ?? 'Sistem',
            ];
        });

        // Format stages untuk grid tabel 9 tahap: sertakan seluruh produk aktif + fallback stage manual lama
        $stageProductsById = [];
        foreach ($rekomendasi as $rek) {
            if ($rek->produk) {
                $stageProductsById[$rek->produk_id] = $rek->produk;
            }
        }
        foreach ($stages as $prodId => $stgList) {
            if (! isset($stageProductsById[$prodId]) && $stgList->first()?->produk) {
                $stageProductsById[$prodId] = $stgList->first()->produk;
            }
        }

        $formattedStages = [];
        foreach ($stageProductsById as $prodId => $p) {
            $al = $analisa->get($prodId);
            $lt = $leadTime->get($prodId);
            $activeSupplierId = $al?->supplier_terpilih_id ?? $p?->supplier_id;
            $activeSupplierNama = $al?->supplierTerpilih?->nama ?? $p?->supplier?->nama ?? '-';

            $manualRows = $stages->get($prodId) ?? collect();
            $empiricalRows = $activeSupplierId
                ? ($empiricalStageSummaries->get($activeSupplierId . ':' . $prodId) ?? collect())
                : collect();

            $resolvedStages = $this->analisa->resolveLokalStageBreakdown($manualRows, $empiricalRows);

            $formattedStages[] = [
                'produk_id' => $prodId,
                'sku' => $p?->sku,
                'nama' => $p?->nama,
                'satuan' => $p?->satuan,
                'supplier_id' => $activeSupplierId,
                'supplier_nama' => $activeSupplierNama,
                'sumber_lt' => $resolvedStages['sumber_lt'],
                'jumlah_transaksi' => $resolvedStages['jumlah_transaksi'],
                'tambahan_buffer_hari' => AnalisaService::roundLeadTimeDays((float) ($lt?->tambahan_buffer_hari ?? 0)),
                'safety_stock' => AnalisaService::roundLeadTimeDays((float) ($lt?->safety_stock ?? $al?->safety_stock ?? 0)),
                'total_average_lead_time' => AnalisaService::roundLeadTimeDays((float) ($lt?->total_average_lead_time ?? $al?->total_average_lead_time ?? 0)),
                'total_max_lead_time' => AnalisaService::roundLeadTimeDays((float) ($lt?->total_max_lead_time ?? 0)),
                'average' => $resolvedStages['average'],
                'max' => $resolvedStages['max'],
                'manual_average' => $resolvedStages['manual_average'],
                'manual_max' => $resolvedStages['manual_max'],
            ];
        }

        $supplierAduRows = $this->analisa->buildLokalSupplierAduRows(
            $rekomendasi,
            $analisa,
            $leadTime,
            $stages,
            $actualStockMap,
            $hppMap
        );

        return response()->json([
            'data' => $rows,
            'tables' => [
                'rekomendasi' => $rows,
                'analisa' => $supplierAduRows,
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
            'stages.*.id' => ['nullable', 'integer'],
            'stages.*.skenario' => ['nullable', 'in:average,max'],
            'stages.*.perencanaan' => ['nullable', 'numeric', 'min:0'],
            'stages.*.approval' => ['nullable', 'numeric', 'min:0'],
            'stages.*.supplier_confirm' => ['nullable', 'numeric', 'min:0'],
            'stages.*.payment' => ['nullable', 'numeric', 'min:0'],
            'stages.*.po' => ['nullable', 'numeric', 'min:0'],
            'stages.*.pengiriman' => ['nullable', 'numeric', 'min:0'],
            'stages.*.unloading' => ['nullable', 'numeric', 'min:0'],
            'stages.*.input' => ['nullable', 'numeric', 'min:0'],
            'tambahan_buffer_hari' => ['nullable', 'numeric', 'min:0'],
            'produk_id' => ['nullable', 'exists:produk,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['stages'] as $stg) {
                $payload = [
                    'perencanaan' => AnalisaService::roundLeadTimeDays((float) ($stg['perencanaan'] ?? 0)),
                    'approval' => AnalisaService::roundLeadTimeDays((float) ($stg['approval'] ?? 0)),
                    'supplier_confirm' => AnalisaService::roundLeadTimeDays((float) ($stg['supplier_confirm'] ?? 0)),
                    'payment' => AnalisaService::roundLeadTimeDays((float) ($stg['payment'] ?? 0)),
                    'po' => AnalisaService::roundLeadTimeDays((float) ($stg['po'] ?? 0)),
                    'pengiriman' => AnalisaService::roundLeadTimeDays((float) ($stg['pengiriman'] ?? 0)),
                    'unloading' => AnalisaService::roundLeadTimeDays((float) ($stg['unloading'] ?? 0)),
                    'input' => AnalisaService::roundLeadTimeDays((float) ($stg['input'] ?? 0)),
                ];
                if (! empty($stg['id'])) {
                    LeadTimeLokalStage::where('id', $stg['id'])->update($payload);
                } elseif (! empty($data['produk_id']) && ! empty($stg['skenario'])) {
                    LeadTimeLokalStage::updateOrCreate(
                        ['produk_id' => $data['produk_id'], 'skenario' => $stg['skenario']],
                        $payload
                    );
                }
            }

            if (! empty($data['produk_id'])) {
                if (isset($data['tambahan_buffer_hari'])) {
                    LeadTimeLokal::updateOrCreate(
                        ['produk_id' => $data['produk_id']],
                        ['tambahan_buffer_hari' => AnalisaService::roundLeadTimeDays((float) $data['tambahan_buffer_hari'])]
                    );
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

    /**
     * Update data manual bahan lokal (ADU / Terjual 4 bulan, Review Period, Buffer, Stok Saat Ini, Harga Satuan)
     */
    public function updateManualLokal(Request $request): JsonResponse
    {
        $this->authorize('analisa.manage');

        $data = $request->validate([
            'produk_id' => ['required', 'exists:produk,id'],
            // ADU Gudang Pusat otomatis dari permintaan bahan baku Gudang Produksi (4 bulan); tidak diinput manual.
            'review_period' => ['nullable', 'integer', 'min:1'],
            'tambahan_buffer_hari' => ['nullable', 'numeric', 'min:0'],
            'stok_saat_ini' => ['nullable', 'numeric', 'min:0'],
            'harga_ml_pcs' => ['nullable', 'numeric', 'min:0'],
        ]);

        $prodId = (int) $data['produk_id'];

        DB::transaction(function () use ($prodId, $data) {
            // Parameter tetap (review period, harga) disimpan ke AnalisaLokalInput.
            // Stok Saat Ini manual = koreksi sekali pakai: tidak disimpan permanen, Checking berikutnya membaca stok live.
            $input = AnalisaLokalInput::firstOrNew(['produk_id' => $prodId]);
            if (array_key_exists('review_period', $data) && $data['review_period'] !== null) {
                $input->review_period = (int) $data['review_period'];
            }
            $overrides = [];
            if (array_key_exists('stok_saat_ini', $data) && $data['stok_saat_ini'] !== null) {
                $overrides[$prodId] = ['stok_saat_ini' => (float) $data['stok_saat_ini']];
            }
            if (array_key_exists('harga_ml_pcs', $data) && $data['harga_ml_pcs'] !== null) {
                $input->harga_per_satuan = round((float) $data['harga_ml_pcs'], 2);
            }
            $input->save();

            // Update tambahan buffer jika dikirim
            if (array_key_exists('tambahan_buffer_hari', $data) && $data['tambahan_buffer_hari'] !== null) {
                LeadTimeLokal::updateOrCreate(
                    ['produk_id' => $prodId],
                    ['tambahan_buffer_hari' => AnalisaService::roundLeadTimeDays((float) $data['tambahan_buffer_hari'])]
                );
            }

            // Hitung ulang analisa lokal produk ini
            $this->analisa->generateLokal($prodId, auth()->id(), $overrides);
        });

        return response()->json([
            'success' => true,
            'message' => 'Data input manual bahan lokal berhasil disimpan & analisa dihitung ulang.',
        ]);
    }

    public function availableSkus(Request $request): JsonResponse
    {
        $this->authorize('analisa.view');

        $query = Produk::query()
            ->active()
            ->whereIn('tipe', ['bahan', 'kemas'])
            ->with('klasifikasiAbc:id,kode,nama,warna_badge');

        if ($search = trim((string) $request->input('q', ''))) {
            $words = array_filter(explode(' ', strtolower($search)));
            foreach ($words as $word) {
                $query->where(function ($sub) use ($word) {
                    $sub->whereRaw('LOWER(sku) LIKE ?', ["%{$word}%"])
                        ->orWhereRaw('LOWER(nama) LIKE ?', ["%{$word}%"])
                        ->orWhereRaw('LOWER(nama_produk) LIKE ?', ["%{$word}%"]);
                });
            }
        }

        $lokalMap = AnalisaLokal::pluck('produk_id')->flip();
        $imporMap = AnalisaImpor::pluck('produk_id')->flip();

        $itemsQuery = $query->orderBy('nama')
            ->limit(60)
            ->get(['id', 'sku', 'nama', 'satuan', 'tipe', 'profil_analisa', 'klasifikasi_abc_id', 'harga_hpp']);

        $pIds = $itemsQuery->pluck('id')->all();
        $actualStockMap = empty($pIds) ? collect() : Stok::whereIn('produk_id', $pIds)
            // Stok analisa bahan = Gudang Bahan Baku saja (stok Lab tidak dihitung).
            ->whereIn('gudang_id', Gudang::where('tipe', 'bahan_baku')->select('id'))
            ->groupBy('produk_id')
            ->select('produk_id', DB::raw('SUM(qty_saat_ini) as total_qty'))
            ->pluck('total_qty', 'produk_id');
        $hppMap = empty($pIds) ? [] : $this->stok->resolveHppMap($pIds);

        $items = $itemsQuery->map(fn ($p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'nama' => $p->nama,
            'satuan' => $p->satuan,
            'tipe' => $p->tipe,
            'profil_analisa' => $p->profil_analisa,
            'is_impor' => AnalisaService::isImporProfile($p->profil_analisa),
            'klasifikasi_abc_id' => $p->klasifikasi_abc_id,
            'klasifikasi_abc_kode' => $p->klasifikasiAbc?->kode,
            'klasifikasi_abc_nama' => $p->klasifikasiAbc?->nama,
            'di_analisa_lokal' => $lokalMap->has($p->id),
            'di_analisa_impor' => $imporMap->has($p->id),
            'harga_hpp' => (float) ($p->harga_hpp ?? 0),
            'current_hpp' => (float) ($hppMap[$p->id] ?? $p->harga_hpp ?? 0),
            'current_stok_aktual' => (float) ($actualStockMap[$p->id] ?? 0),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function addSku(Request $request): JsonResponse
    {
        $this->authorize('analisa.manage');

        $validator = Validator::make($request->all(), [
            'produk_id' => ['required', 'integer', 'exists:produk,id'],
            'target_profil' => ['required', Rule::in(['lokal', 'impor'])],
            'klasifikasi_abc_id' => ['nullable', 'required_if:target_profil,impor', 'integer', 'exists:klasifikasi_abc,id'],
            'out' => ['nullable', 'numeric', 'min:0'],
            'lead_time_average' => ['nullable', 'numeric', 'min:0'],
            'lead_time_max' => ['nullable', 'numeric', 'min:0'],
            'terjual_rata_rata_4bulan' => ['nullable', 'numeric', 'min:0'],
            'tambahan_buffer_hari' => ['nullable', 'numeric', 'min:0'],
            'review_period' => ['nullable', 'integer', 'min:1'],
            'harga_per_satuan' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $data = $validator->validated();
        $produk = Produk::findOrFail((int) $data['produk_id']);

        $initialParams = [];
        foreach (['out', 'lead_time_average', 'lead_time_max', 'terjual_rata_rata_4bulan', 'tambahan_buffer_hari', 'review_period', 'harga_per_satuan'] as $paramKey) {
            if (array_key_exists($paramKey, $data) && $data[$paramKey] !== null && $data[$paramKey] !== '') {
                $initialParams[$paramKey] = $data[$paramKey];
            }
        }

        try {
            $this->analisa->addSkuToAnalisa(
                $produk,
                $data['target_profil'],
                ! empty($data['klasifikasi_abc_id']) ? (int) $data['klasifikasi_abc_id'] : null,
                $initialParams,
                auth()->id()
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $labelProfil = $data['target_profil'] === 'impor' ? 'Analisa Impor' : 'Analisa Lokal';

        return response()->json([
            'success' => true,
            'message' => "SKU '{$produk->sku}' berhasil ditambahkan ke {$labelProfil}.",
        ]);
    }

    /**
     * Update data manual bahan impor (Lead Time Avg/Max, Out 4 bulan, Review Period, ABC, Stok, Inbound, Harga)
     */
    public function updateManualImpor(Request $request): JsonResponse
    {
        $this->authorize('analisa.manage');

        $validator = Validator::make($request->all(), [
            'produk_id' => ['required', 'exists:produk,id'],
            'lead_time_average' => ['nullable', 'numeric', 'min:0'],
            'lead_time_max' => ['nullable', 'numeric', 'min:0'],
            // Out/ADU impor otomatis dari permintaan bahan baku Gudang Produksi (4 bulan); tidak diinput manual.
            'periode_mulai' => ['nullable', 'date'],
            'periode_akhir' => ['nullable', 'date'],
            'review_period' => ['nullable', 'integer', 'min:1'],
            'klasifikasi_abc' => ['nullable', 'string'],
            'klasifikasi_abc_id' => ['nullable', 'exists:klasifikasi_abc,id'],
            'stok_saat_ini' => ['nullable', 'numeric', 'min:0'],
            'inbound_before_eta' => ['nullable', 'numeric', 'min:0'],
            'harga_per_satuan' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $data = $validator->validated();
        $prodId = (int) $data['produk_id'];
        $produk = Produk::findOrFail($prodId);
        if (! $produk->is_active || $produk->tipe === 'produk_jadi') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya SKU Bahan Baku atau Kemasan yang aktif yang dapat diatur pada Analisa Impor.',
            ], 422);
        }

        DB::transaction(function () use ($prodId, $produk, $data) {
            if (! AnalisaService::isImporProfile($produk->profil_analisa)) {
                $produk->profil_analisa = 'impor';
                $produk->save();
                $this->analisa->cleanupWorkingTablesForProfile($produk, 'impor');
            }

            // Update Lead Time Impor hanya jika parameter LT dikirim
            if ((array_key_exists('lead_time_average', $data) && $data['lead_time_average'] !== null)
                || (array_key_exists('lead_time_max', $data) && $data['lead_time_max'] !== null)) {
                $lt = LeadTimeImpor::firstOrNew(['produk_id' => $prodId]);
                if (array_key_exists('lead_time_average', $data) && $data['lead_time_average'] !== null) {
                    $lt->lead_time_average = $data['lead_time_average'];
                }
                if (array_key_exists('lead_time_max', $data) && $data['lead_time_max'] !== null) {
                    $lt->lead_time_max = $data['lead_time_max'];
                }
                $lt->save();
            }

            // Update Analisa Impor
            $ai = AnalisaImpor::firstOrNew(['produk_id' => $prodId]);
            if (array_key_exists('periode_mulai', $data) && $data['periode_mulai'] !== null) {
                $ai->periode_mulai = $data['periode_mulai'];
            }
            if (array_key_exists('periode_akhir', $data) && $data['periode_akhir'] !== null) {
                $ai->periode_akhir = $data['periode_akhir'];
            }
            if (! empty($data['periode_mulai']) && ! empty($data['periode_akhir'])) {
                $ai->jumlah_hari_periode = \Carbon\Carbon::parse($data['periode_mulai'])->diffInDays(\Carbon\Carbon::parse($data['periode_akhir'])) + 1;
            }
            if (array_key_exists('review_period', $data) && $data['review_period'] !== null) {
                $ai->review_period = (int) $data['review_period'];
            }

            $abcExplicitlyChanged = false;
            if (array_key_exists('klasifikasi_abc_id', $data)) {
                $abcExplicitlyChanged = true;
                if ($data['klasifikasi_abc_id'] !== null) {
                    $abc = KlasifikasiAbc::find($data['klasifikasi_abc_id']);
                    $ai->klasifikasi_abc_id = $abc?->id;
                    $ai->klasifikasi_abc = $abc?->kode ?? 'c';
                    $ai->tambahan_buffer_hari = $abc ? (int) $abc->tambahan_buffer_hari : 0;
                    $produk->klasifikasi_abc_id = $abc?->id;
                } else {
                    $ai->klasifikasi_abc_id = null;
                    $ai->klasifikasi_abc = 'c';
                    $ai->tambahan_buffer_hari = 0;
                    $produk->klasifikasi_abc_id = null;
                }
                $produk->save();
            } elseif (array_key_exists('klasifikasi_abc', $data)) {
                $abcExplicitlyChanged = true;
                if ($data['klasifikasi_abc'] !== null && $data['klasifikasi_abc'] !== '') {
                    $code = strtolower($data['klasifikasi_abc']);
                    $abc = KlasifikasiAbc::where('kode', $code)->first();
                    $ai->klasifikasi_abc = $code;
                    $ai->klasifikasi_abc_id = $abc?->id;
                    $ai->tambahan_buffer_hari = $abc ? (int) $abc->tambahan_buffer_hari : (AnalisaImpor::ABC_BUFFER[$code] ?? 0);
                    $produk->klasifikasi_abc_id = $abc?->id;
                } else {
                    $ai->klasifikasi_abc_id = null;
                    $ai->klasifikasi_abc = 'c';
                    $ai->tambahan_buffer_hari = 0;
                    $produk->klasifikasi_abc_id = null;
                }
                $produk->save();
            }

            // Stok & inbound manual = koreksi sekali pakai (tidak permanen); Checking berikutnya membaca data live.
            $overrideImpor = [];
            if (array_key_exists('stok_saat_ini', $data) && $data['stok_saat_ini'] !== null) {
                $overrideImpor['stok_saat_ini'] = (float) $data['stok_saat_ini'];
            }
            if (array_key_exists('inbound_before_eta', $data) && $data['inbound_before_eta'] !== null) {
                $overrideImpor['inbound_before_eta'] = (float) $data['inbound_before_eta'];
            }
            if (array_key_exists('harga_per_satuan', $data) && $data['harga_per_satuan'] !== null) {
                $ai->harga_per_satuan = round((float) $data['harga_per_satuan'], 2);
            }
            $ai->save();

            // Jika produk memiliki varian di AnalisaImporMeta, sinkronkan juga ke meta
            $meta = AnalisaImporMeta::where('produk_id', $prodId)->first();
            if ($meta) {
                if (isset($data['periode_mulai'])) $meta->periode_mulai = $data['periode_mulai'];
                if (isset($data['periode_akhir'])) $meta->periode_akhir = $data['periode_akhir'];
                if ($ai->jumlah_hari_periode) $meta->jumlah_hari_periode = $ai->jumlah_hari_periode;
                if ($abcExplicitlyChanged) {
                    $meta->klasifikasi_abc_id = $ai->klasifikasi_abc_id;
                    $meta->klasifikasi_abc = $ai->klasifikasi_abc;
                } elseif ($ai->klasifikasi_abc_id) {
                    $meta->klasifikasi_abc_id = $ai->klasifikasi_abc_id;
                    $meta->klasifikasi_abc = $ai->klasifikasi_abc;
                }
                if (isset($data['harga_per_satuan'])) $meta->harga_per_satuan = round((float) $data['harga_per_satuan'], 2);
                if (isset($data['review_period'])) $meta->review_period = $data['review_period'];
                if (isset($data['lead_time_average'])) $meta->lead_time_average = $data['lead_time_average'];
                if (isset($data['lead_time_max'])) $meta->lead_time_max = $data['lead_time_max'];
                $meta->save();
            }

            // Hitung ulang analisa impor produk ini
            $this->analisa->generateImpor($prodId, auth()->id(), $overrideImpor ? [$prodId => $overrideImpor] : []);
        });

        return response()->json([
            'success' => true,
            'message' => 'Data input manual bahan impor berhasil disimpan & analisa dihitung ulang.',
        ]);
    }

    public function imporData(): JsonResponse
    {
        $this->authorize('analisa.view');

        // Hitung ulang otomatis setiap tab dibuka (lihat lokalData).
        $this->hitungUlangLive('impor');

        $imporIds = $this->analisa->imporEligibleProdukIds();
        $imporIds = is_array($imporIds) ? $imporIds : (is_object($imporIds) ? $imporIds->all() : []);

        $imporList = empty($imporIds) ? collect() : AnalisaImpor::with([
            'produk' => function ($q) {
                $q->select('id', 'sku', 'nama', 'satuan', 'satuan_order_moq', 'faktor_konversi', 'supplier_id', 'harga_hpp')
                    ->with('supplier:id,nama,kategori');
            },
            'supplierTerpilih:id,nama,kategori',
            'klasifikasiAbc',
            'generator:id,name'
        ])->whereIn('produk_id', $imporIds)->get();
        $ltMap = LeadTimeImpor::all()->keyBy('produk_id');
        $lastGenerated = $imporList->sortByDesc('generated_at')->first();

        $actualStockMap = empty($imporIds) ? collect() : Stok::whereIn('produk_id', $imporIds)
            // Stok analisa bahan = Gudang Bahan Baku saja (stok Lab tidak dihitung).
            ->whereIn('gudang_id', Gudang::where('tipe', 'bahan_baku')->select('id'))
            ->groupBy('produk_id')
            ->select('produk_id', DB::raw('SUM(qty_saat_ini) as total_qty'))
            ->pluck('total_qty', 'produk_id');

        $hppMap = empty($imporIds) ? [] : $this->stok->resolveHppMap($imporIds);

        $inboundPoMap = empty($imporIds) ? collect() : DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.po_id')
            // PO yang sudah disetujui Manager & belum diposting (sama dengan AnalisaService::akanDatangGudangBahanBaku).
            ->whereIn('poi.po_id', PurchaseOrder::query()->disetujuiBelumDiterima()->select('purchase_orders.id'))
            ->whereIn('poi.produk_id', $imporIds)
            ->groupBy('poi.produk_id')
            ->select('poi.produk_id', DB::raw('SUM(poi.qty) as total_inbound'))
            ->pluck('total_inbound', 'poi.produk_id');

        $rows = [];
        foreach ($imporList as $ai) {
            $lt = $ltMap->get($ai->produk_id);

            $rows[] = [
                'id' => (string) $ai->id,
                'analisa_impor_id' => $ai->id,
                'produk_id' => $ai->produk_id,
                'sku' => $ai->produk?->sku,
                'nama' => $ai->produk?->nama,
                'satuan' => $ai->produk?->satuan,
                'supplier_id' => $ai->supplier_terpilih_id ?? $ai->produk?->supplier_id,
                'supplier_nama' => $ai->supplierTerpilih?->nama ?? $ai->produk?->supplier?->nama ?? '-',
                'supplier_kategori' => $ai->supplierTerpilih?->kategori ?? $ai->produk?->supplier?->kategori ?? '-',
                'sumber_lt' => $ai->sumber_lt ?? 'manual',
                'satuan_order_moq' => $ai->produk?->satuan_order_moq,
                'faktor_konversi' => (float) ($ai->produk?->faktor_konversi ?: 1),
                'periode_mulai' => $ai->periode_mulai?->format('Y-m-d'),
                'periode_akhir' => $ai->periode_akhir?->format('Y-m-d'),
                'jumlah_hari_periode' => (int) ($ai->jumlah_hari_periode ?: 122),
                'adu_base' => (float) $ai->adu_base,
                'adu_eta' => (float) $ai->adu_eta,
                'klasifikasi_abc' => $ai->klasifikasi_abc,
                'klasifikasi_abc_id' => $ai->klasifikasi_abc_id,
                'klasifikasi_abc_nama' => $ai->klasifikasiAbc?->nama ?? ($ai->klasifikasi_abc_id ? strtoupper($ai->klasifikasi_abc) : 'Default (Belum Diset)'),
                'klasifikasi_abc_badge' => $ai->klasifikasiAbc?->warna_badge ?? 'gray',
                'tambahan_buffer_hari' => (int) round($ai->tambahan_buffer_hari),
                'out' => (float) round($ai->out),
                'review_period' => (int) ($ai->review_period ?: 30),
                'lead_time_average' => (float) ($lt?->lead_time_average ?? $ai->lead_time),
                'lead_time_max' => (float) ($lt?->lead_time_max ?? 0),
                'buffer_days' => (float) $ai->buffer_days,
                'safety_stock' => (float) round($ai->safety_stock),
                'minimum_stock' => (float) round($ai->minimum_stock),
                'target_stock' => (float) round($ai->target_stock),
                'stok_saat_ini' => (float) round($ai->stok_saat_ini),
                'inbound_before_eta' => (float) round($ai->inbound_before_eta),
                'current_hpp' => (float) ($hppMap[$ai->produk_id] ?? $ai->produk?->harga_hpp ?? 0),
                'current_stok_aktual' => (float) ($actualStockMap[$ai->produk_id] ?? 0),
                'current_inbound' => (float) ($inboundPoMap[$ai->produk_id] ?? 0),
                'empirical_avg_lt' => ($lt && $lt->lead_time_average !== null) ? (float) $lt->lead_time_average : null,
                'empirical_max_lt' => ($lt && $lt->lead_time_max !== null) ? (float) $lt->lead_time_max : null,
                'proyeksi' => (float) round($ai->proyeksi),
                'qty_order' => (float) round($ai->qty_order),
                'po' => (float) round($ai->po),
                'total_qty_order' => (float) round($ai->po ?: $ai->qty_order),
                'total_selisih' => (float) round($ai->proyeksi - $ai->target_stock),
                'status' => $ai->status,
                'harga_per_satuan' => (float) round($ai->harga_per_satuan, 2),
                'total_nominal_order' => (float) round($ai->total_nominal_order),
                'generated_at' => $ai->generated_at?->format('d/m/Y H:i'),
                'generated_by' => $ai->generator?->name ?? 'Sistem',
            ];
        }

        $supplierAduRows = $this->analisa->buildImporSupplierAduRows(
            $imporList,
            $ltMap,
            $actualStockMap,
            $hppMap,
            $inboundPoMap
        );

        return response()->json([
            'data' => $rows,
            'tables' => [
                'rekomendasi' => $rows,
                'analisa' => $supplierAduRows,
            ],
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

        return response()->json(['data' => $this->analisa->fulfillmentRows()]);
    }

    /**
     * Daftar produk jadi untuk ditambahkan ke Analisa Fulfillment, plus stok live per gudang fulfillment.
     */
    public function fulfillmentAvailableProduk(Request $request): JsonResponse
    {
        $this->authorize('analisa.manage');

        $query = Produk::query()->active()->where('tipe', 'produk_jadi');
        if ($search = trim((string) $request->input('q', ''))) {
            foreach (array_filter(explode(' ', strtolower($search))) as $word) {
                $query->where(function ($sub) use ($word) {
                    $sub->whereRaw('LOWER(sku) LIKE ?', ["%{$word}%"])
                        ->orWhereRaw('LOWER(nama) LIKE ?', ["%{$word}%"]);
                });
            }
        }

        $items = $query->orderBy('nama')->limit(60)->get(['id', 'sku', 'nama', 'satuan', 'satuan_order_moq']);
        $sudahAda = AnalisaFulfillmentInput::whereIn('produk_id', $items->pluck('id'))->pluck('produk_id')->flip();

        return response()->json([
            'success' => true,
            'data' => $items->map(fn ($p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'nama' => $p->nama,
                'satuan' => $p->satuan,
                'moq' => (float) ($p->satuan_order_moq ?: 1),
                'di_analisa' => $sudahAda->has($p->id),
            ])->values(),
        ]);
    }

    /**
     * Stok live & parameter tersimpan satu produk jadi di setiap gudang fulfillment aktif (untuk modal).
     */
    public function fulfillmentDetail(Produk $produk): JsonResponse
    {
        $this->authorize('analisa.manage');

        $gudang = Gudang::active()->whereIn('tipe', Gudang::TIPE_FULFILLMENT)->orderByDesc('is_pusat')->orderBy('nama')
            ->get(['id', 'nama', 'is_pusat']);
        $inputs = AnalisaFulfillmentInput::where('produk_id', $produk->id)->get()->keyBy('gudang_id');
        $live = $this->analisa->fulfillmentLiveStock([$produk->id], $gudang->pluck('id')->map(fn ($id) => (int) $id)->all());

        $ltStats = $this->analisa->distribusiLeadTimeStats([$produk->id]);

        return response()->json([
            'success' => true,
            'produk' => [
                'id' => $produk->id,
                'sku' => $produk->sku,
                'nama' => $produk->nama,
                'satuan' => $produk->satuan,
                'moq' => (float) ($produk->satuan_order_moq ?: 1),
            ],
            'gudang' => $gudang->map(function ($g) use ($inputs, $live, $produk, $ltStats) {
                $in = $inputs->get($g->id);
                $key = "{$produk->id}_{$g->id}";
                $lt = $ltStats[$key] ?? null;

                return [
                    'gudang_id' => $g->id,
                    'nama' => $g->nama,
                    'is_pusat' => (bool) $g->is_pusat,
                    'aktif' => $in !== null,
                    'terjual_rata_rata_4bulan' => (float) ($in->terjual_rata_rata_4bulan ?? 0),
                    'lead_time_distribusi' => $lt ? $lt['avg'] : (int) ($in->lead_time_distribusi ?? 0),
                    'lead_time_distribusi_sumber' => $lt ? 'transfer' : 'manual',
                    'jumlah_transfer_selesai' => $lt['count'] ?? 0,
                    'buffer_distribusi' => (int) ($in->buffer_distribusi ?? 0),
                    'review_period' => (int) ($in->review_period ?? 15),
                    'stok_saat_ini' => (float) ($live['stok'][$key] ?? 0),
                    'akan_datang' => (float) ($live['akan_datang'][$key] ?? 0),
                ];
            })->values(),
        ]);
    }

    /**
     * Simpan parameter Analisa Fulfillment (terjual 4 bulan, lead time, buffer, review period) per gudang.
     */
    public function fulfillmentSave(Request $request): JsonResponse
    {
        $this->authorize('analisa.manage');

        $data = $request->validate([
            'produk_id' => ['required', 'integer', 'exists:produk,id'],
            'gudang' => ['required', 'array', 'min:1'],
            'gudang.*.gudang_id' => ['required', 'integer', 'distinct', 'exists:gudang,id'],
            'gudang.*.terjual_rata_rata_4bulan' => ['required', 'numeric', 'min:0'],
            // Terukur otomatis dari request->transfer (bisa desimal); disimpan dibulatkan sebagai fallback manual.
            'gudang.*.lead_time_distribusi' => ['required', 'numeric', 'min:0'],
            'gudang.*.buffer_distribusi' => ['required', 'integer', 'min:0'],
            'gudang.*.review_period' => ['required', 'integer', 'min:0'],
        ]);

        $produk = Produk::findOrFail((int) $data['produk_id']);
        $this->analisa->saveFulfillmentInputs($produk, $data['gudang']);

        return response()->json([
            'success' => true,
            'message' => "Analisa Fulfillment '{$produk->sku}' berhasil disimpan.",
        ]);
    }

    /**
     * Keluarkan satu produk jadi dari Analisa Fulfillment (semua gudang).
     */
    public function fulfillmentDestroy(Produk $produk): JsonResponse
    {
        $this->authorize('analisa.manage');

        AnalisaFulfillmentInput::where('produk_id', $produk->id)->delete();

        return response()->json([
            'success' => true,
            'message' => "'{$produk->sku}' dikeluarkan dari Analisa Fulfillment.",
        ]);
    }

    public function produksiData(): JsonResponse
    {
        $this->authorize('analisa.view');

        return response()->json(['data' => $this->analisa->produksi()]);
    }

    /**
     * Log Riwayat Snapshot dikelompokkan Bulan → Sesi finalisasi → item.
     * Query `bulan=YYYY-MM` (default: bulan terbaru yang punya snapshot). Seluruh baris bulan itu dikirim,
     * jadi riwayat bulan lama tidak terpotong limit baris.
     */
    public function riwayatData(Request $request): JsonResponse
    {
        $this->authorize('analisa.view');

        // Daftar bulan: agregasi per tanggal lalu digabung per bulan di PHP (portabel SQLite & PostgreSQL).
        $perTanggal = RiwayatAnalisa::query()
            ->select('tanggal', 'session_id', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('tanggal', 'session_id')
            ->get();

        $bulanList = [];
        foreach ($perTanggal as $t) {
            $key = $t->tanggal->format('Y-m');
            $bulanList[$key] ??= [
                'key' => $key,
                'label' => $this->labelBulan($t->tanggal),
                'jumlah_item' => 0,
                'sesi' => [],
            ];
            $bulanList[$key]['jumlah_item'] += (int) $t->jumlah;
            $bulanList[$key]['sesi'][$t->session_id ?? ('LEGACY-'.$t->tanggal->toDateString())] = true;
        }
        krsort($bulanList);
        $bulanList = array_values(array_map(fn ($b) => [
            'key' => $b['key'],
            'label' => $b['label'],
            'jumlah_item' => $b['jumlah_item'],
            'jumlah_sesi' => count($b['sesi']),
        ], $bulanList));

        // `bulan` tidak valid diabaikan dan jatuh ke bulan terbaru (endpoint ini dibaca via fetch JSON).
        $bulanQuery = (string) $request->query('bulan', '');
        $bulan = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulanQuery)
            ? $bulanQuery
            : ($bulanList[0]['key'] ?? now()->format('Y-m'));
        $awal = \Carbon\Carbon::createFromFormat('Y-m-d', $bulan.'-01')->startOfMonth();
        $bulanBerikut = $awal->copy()->addMonthNoOverflow()->startOfMonth();

        // Batas setengah-terbuka: kolom date bisa tersimpan 'Y-m-d H:i:s' di SQLite.
        $records = RiwayatAnalisa::with(['pencatat:id,name', 'supplier:id,nama'])
            ->where('tanggal', '>=', $awal->toDateString())
            ->where('tanggal', '<', $bulanBerikut->toDateString())
            ->orderByDesc('created_at')
            ->get();

        $sessions = $records
            ->groupBy(fn ($r) => $r->session_id ?? ('LEGACY-'.$r->tanggal->toDateString()))
            ->map(function ($items, $key) {
                $first = $items->sortBy('created_at')->first();
                $tipes = $items->pluck('tipe')->unique()->values();

                return [
                    'key' => $key,
                    'session_id' => $first->session_id ?? '-',
                    'tanggal' => $first->tanggal->format('d/m/Y'),
                    'waktu' => $first->created_at?->format('H:i'),
                    'sort' => $first->created_at?->timestamp ?? 0,
                    'tipe_labels' => $tipes->map(fn ($t) => RiwayatAnalisa::labelTipe($t))->all(),
                    'pencatat' => $first->pencatat?->name ?? 'Sistem',
                    'jumlah_item' => $items->count(),
                    'jumlah_order' => $items->filter(fn ($r) => in_array(strtolower((string) $r->status), ['order', 'po'], true))->count(),
                ];
            })
            ->sortByDesc('sort')
            ->values();

        $rows = $records
            ->map(fn ($r) => [
                'id' => $r->id,
                'session_id' => $r->session_id ?? '-',
                'session_key' => $r->session_id ?? ('LEGACY-'.$r->tanggal->toDateString()),
                'tanggal' => $r->tanggal->format('d/m/Y'),
                'tipe' => RiwayatAnalisa::labelTipe($r->tipe),
                'tipe_raw' => $r->tipe,
                'item_label' => $r->item_label,
                'supplier_id' => $r->supplier_id,
                'supplier_nama' => $r->supplier?->nama ?? ($r->detail_payload['supplier_nama'] ?? '-'),
                'average_lt_dipakai' => $r->average_lt_dipakai !== null ? (float) $r->average_lt_dipakai : null,
                'max_lt_dipakai' => $r->max_lt_dipakai !== null ? (float) $r->max_lt_dipakai : null,
                'sumber_lt' => $r->sumber_lt ?? '-',
                'batas_minimum' => (float) $r->batas_minimum,
                'target_stock' => (float) $r->target_stock,
                'status' => $r->status,
                'qty_order' => (float) $r->qty_order,
                'is_locked' => (bool) $r->is_locked,
                'pencatat' => $r->pencatat?->name ?? 'Sistem',
                'detail' => $r->detail_payload ?? [],
            ]);

        return response()->json([
            'data' => $rows,
            'riwayat' => [
                'bulan' => $bulan,
                'bulan_label' => $this->labelBulan($awal),
                'bulan_list' => $bulanList,
                'sessions' => $sessions,
            ],
        ]);
    }

    /**
     * Hitung ulang analisa lokal/impor untuk seluruh SKU terdaftar, dijaga cache lock.
     * Lock 120 detik: bila perhitungan lain sedang berjalan, request ini memakai hasil terakhir.
     */
    private function hitungUlangLive(string $tipe): void
    {
        // Hasil < 60 detik dipakai ulang (pindah tab/filter tidak menghitung ulang ~20 query per SKU),
        // kecuali tombol Muat Ulang (?refresh=1) atau ada SKU terdaftar yang belum punya baris.
        if (! request()->boolean('refresh')) {
            $eligible = $tipe === 'impor' ? $this->analisa->imporEligibleProdukIds() : $this->analisa->lokalEligibleProdukIds();
            $model = $tipe === 'impor' ? AnalisaImpor::class : RekomendasiOrderLokal::class;
            $rows = $model::whereIn('produk_id', $eligible);
            $terakhir = (clone $rows)->max('generated_at');
            $lengkap = (clone $rows)->count() >= count($eligible);
            if ($lengkap && $terakhir && \Illuminate\Support\Carbon::parse($terakhir)->gt(now()->subSeconds(60))) {
                return;
            }
        }

        \Illuminate\Support\Facades\Cache::lock("analisa:hitung-ulang:{$tipe}", 120)->get(function () use ($tipe) {
            $tipe === 'impor'
                ? $this->analisa->generateImpor(null, auth()->id())
                : $this->analisa->generateLokal(null, auth()->id());
        });
    }

    private function labelBulan(\Carbon\CarbonInterface $tanggal): string
    {
        $nama = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $nama[(int) $tanggal->format('n') - 1].' '.$tanggal->format('Y');
    }

    /**
     * Workflow Finalisasi Analisa: Kunci working data aktif ke riwayat_analisa
     */
    public function finalisasi(Request $request)
    {
        $this->authorize('analisa.snapshot');

        $data = $request->validate([
            'tipe' => ['nullable', 'string', 'in:bahan_lokal,bahan_impor,produk_jadi_produksi,produk_jadi_fulfillment,all'],
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
                foreach (AnalisaImporMeta::with('produk')->get() as $meta) {
                    $h = $this->analisa->impor($meta);
                    $this->simpanRiwayat($tipe, $meta->produk?->sku ?? '-', 0, 0, $h['status'], $h['total_qty_order']);
                    $n++;
                }
            } else {
                foreach ($this->analisa->fulfillmentRows() as $h) {
                    $this->simpanRiwayat($tipe, $h['sku'] ?? '-', $h['batas_minimum_total'], $h['target_stock_total'], $h['status'], $h['qty_order']);
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
        $uomList = Uom::active()->orderBy('nama')->get(['id', 'kode', 'nama', 'kategori', 'satuan_dasar', 'faktor_konversi']);

        // Ambil ID rekomendasi jika dikirim spesifik lewat query / POST
        $ids = $request->input('ids');
        if (is_string($ids)) {
            $ids = array_filter(array_map('intval', explode(',', $ids)));
        } elseif (is_array($ids)) {
            $ids = array_filter(array_map('intval', $ids));
        }

        $tipe = $request->input('tipe');
        $prefilledItems = collect();

        // 1. Muat Rekomendasi Lokal jika tipe bukan 'impor'
        if ($tipe !== 'impor') {
            $lokalEligibleIds = $this->analisa->lokalEligibleProdukIds();
            $query = RekomendasiOrderLokal::with(['produk' => function ($q) {
                $q->select('id', 'sku', 'nama', 'satuan', 'satuan_order_moq', 'faktor_konversi', 'harga_hpp', 'supplier_id')
                    ->with('supplier:id,nama');
            }])->whereIn('produk_id', empty($lokalEligibleIds) ? [-1] : $lokalEligibleIds);

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
                }])->whereIn('produk_id', empty($lokalEligibleIds) ? [-1] : $lokalEligibleIds)
                  ->whereIn('produk_id', $ids)->get();
            }

            // Susun item awal untuk form Alpine.js
            $analisaLokalMap = AnalisaLokal::with('supplierTerpilih:id,nama')->get()->keyBy('produk_id');
            $prefilledLokal = $rekomendasiList->map(function ($r) use ($analisaLokalMap) {
                $al = $analisaLokalMap->get($r->produk_id);
                $supId = $al?->supplier_terpilih_id ?? $r->produk?->supplier_id;
                $supNama = $al?->supplierTerpilih?->nama ?? $r->produk?->supplier?->nama ?? '-';

                $qty = (float) ($r->rumus_moq ?: $r->rekomendasi_order);
                $satuan = $r->produk?->satuan ?? 'pcs';
                $hargaSatuan = (float) $r->harga_ml_pcs;
                $hargaTotal = (float) $r->total_nominal_order;
                if ($hargaTotal <= 0 && $qty > 0 && $hargaSatuan > 0) {
                    $hargaTotal = round($qty * $hargaSatuan);
                }

                return [
                    'id' => $r->id,
                    'produk_id' => $r->produk_id,
                    'sku' => $r->produk?->sku ?? '-',
                    'nama' => $r->produk?->nama ?? '-',
                    'supplier_id' => $supId,
                    'supplier_nama' => $supNama,
                    'satuan_dasar' => $satuan,
                    'satuan_beli' => $satuan,
                    'qty_satuan_beli' => $qty,
                    'faktor_konversi' => 1,
                    'qty' => $qty,
                    'harga_satuan' => $hargaSatuan,
                    'harga_total' => $hargaTotal,
                    'harga_hpp' => (float) ($r->produk?->harga_hpp ?? 0),
                ];
            });

            $prefilledItems = $prefilledItems->concat($prefilledLokal);
        }

        // 2. Dukung item dari AnalisaImpor jika tipe 'impor' atau jika $ids cocok
        if ($tipe === 'impor' || ! empty($ids)) {
            $imporEligibleIds = $this->analisa->imporEligibleProdukIds();
            $imporQuery = AnalisaImpor::with(['produk' => function ($q) {
                $q->select('id', 'sku', 'nama', 'satuan', 'satuan_order_moq', 'faktor_konversi', 'harga_hpp', 'supplier_id')
                    ->with('supplier:id,nama');
            }, 'supplierTerpilih:id,nama'])
            ->whereIn('produk_id', empty($imporEligibleIds) ? [-1] : $imporEligibleIds);

            if (! empty($ids)) {
                $imporQuery->where(function ($q) use ($ids) {
                    $q->whereIn('id', $ids)->orWhereIn('produk_id', $ids);
                });
            } else {
                $imporQuery->whereIn('status', ['order', 'po']);
            }

            if ($prefilledItems->isNotEmpty()) {
                $imporQuery->whereNotIn('produk_id', $prefilledItems->pluck('produk_id'));
            }

            $imporItems = $imporQuery->get();

            $prefilledImpor = collect();
            foreach ($imporItems as $ai) {
                $supId = $ai->supplier_terpilih_id ?? $ai->produk?->supplier_id;
                $supNama = $ai->supplierTerpilih?->nama ?? $ai->produk?->supplier?->nama ?? '-';
                $satuan = $ai->produk?->satuan ?? 'pcs';
                $hargaSatuan = (float) $ai->harga_per_satuan;

                $qty = (float) ($ai->po ?: $ai->qty_order);
                if ($qty <= 0) continue;
                $hargaTotal = (float) $ai->total_nominal_order;
                if ($hargaTotal <= 0 && $qty > 0 && $hargaSatuan > 0) {
                    $hargaTotal = round($qty * $hargaSatuan);
                }

                $prefilledImpor->push([
                    'id' => 'impor_'.$ai->id,
                    'produk_id' => $ai->produk_id,
                    'sku' => $ai->produk?->sku ?? '-',
                    'nama' => $ai->produk?->nama ?? '-',
                    'supplier_id' => $supId,
                    'supplier_nama' => $supNama,
                    'satuan_dasar' => $satuan,
                    'satuan_beli' => $satuan,
                    'qty_satuan_beli' => $qty,
                    'faktor_konversi' => 1,
                    'qty' => $qty,
                    'harga_satuan' => $hargaSatuan,
                    'harga_total' => $hargaTotal,
                    'harga_hpp' => (float) ($ai->produk?->harga_hpp ?? 0),
                ]);
            }

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

        return view('analisa.create-po', compact('suppliers', 'gudang', 'prefilledItems', 'allProduk', 'defaultSupplierId', 'uomList'));
    }

    /** Buat draft PO dari hasil analisa (ditandai "Dari Analisa"). */
    public function createPo(Request $request)
    {
        $this->authorize('analisa.create_po');

        $data = $request->validate([
            'no_invoice' => ['nullable', 'string', 'max:50'],
            'supplier_id' => ['required', 'exists:supplier,id'],
            'gudang_id' => ['nullable', 'exists:gudang,id'],
            'tanggal' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'sumber_dana' => ['nullable', 'string', 'max:50'],
            'skema_bayar' => ['nullable', Rule::in(['cash', 'tempo', 'termin'])],
            'tanggal_tempo' => ['nullable', 'date', 'required_if:skema_bayar,tempo'],
            'diskon_total' => ['nullable', 'numeric', 'min:0'],
            'ppn_nominal' => ['nullable', 'numeric', 'min:0'],
            'ongkos_kirim' => ['nullable', 'numeric', 'min:0'],
            'adjustment' => ['nullable', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produk_id' => ['required', 'distinct', 'exists:produk,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.qty_satuan_beli' => ['nullable', 'numeric', 'gt:0'],
            'items.*.satuan_beli' => ['nullable', 'string', 'max:50'],
            'items.*.faktor_konversi' => ['nullable', 'numeric', 'gt:0'],
            'items.*.harga_satuan' => ['nullable', 'numeric', 'min:0'],
            'items.*.harga_total' => ['required', 'numeric', 'min:0'],
            'items.*.diskon' => ['nullable', 'numeric', 'min:0'],
            'items.*.ppn' => ['nullable', 'numeric', 'min:0'],
            'items.*.ongkir' => ['nullable', 'numeric', 'min:0'],
            'items.*.adjustment' => ['nullable', 'numeric'],
            'termins' => ['nullable', 'array'],
            'termins.*.tanggal_tempo' => ['required_with:termins', 'date'],
            'termins.*.nominal_tagihan' => ['required_with:termins', 'numeric', 'gt:0'],
            'termins.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $supplierId = (int) $data['supplier_id'];
        $itemProdukIds = collect($data['items'])->pluck('produk_id')->all();

        $invalidProdukNames = Produk::whereIn('id', $itemProdukIds)
            ->where(function ($query) use ($supplierId) {
                $query->whereDoesntHave('suppliers', function ($q) use ($supplierId) {
                    $q->where('supplier.id', $supplierId)
                      ->where('produk_supplier.status', 'aktif');
                })
                ->where(function ($q) use ($supplierId) {
                    $q->whereNull('supplier_id')
                      ->orWhere('supplier_id', '!=', $supplierId);
                });
            })
            ->pluck('nama');

        if ($invalidProdukNames->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Item produk berikut tidak terdaftar aktif pada supplier yang dipilih: ' . $invalidProdukNames->implode(', ') . '.',
            ]);
        }

        $totals = $this->calculator->calculatePoTotals(
            $data['items'],
            (float) ($data['diskon_total'] ?? 0),
            (float) ($data['ppn_nominal'] ?? 0),
            (float) ($data['ongkos_kirim'] ?? 0),
            (float) ($data['adjustment'] ?? 0)
        );

        if (! empty($data['termins']) && ($data['skema_bayar'] ?? '') === 'termin') {
            $this->paymentService->assertTerminsMatchGrandTotal($data['termins'], (float) $totals['grand_total']);
        }

        $po = DB::transaction(function () use ($data, $totals) {
            $po = PurchaseOrder::create([
                'no_po' => $this->nextNoPo(),
                'no_invoice' => ! empty($data['no_invoice']) ? $data['no_invoice'] : $this->nextNoInvoice(),
                'supplier_id' => $data['supplier_id'],
                'gudang_id' => $data['gudang_id'] ?? null,
                'tanggal' => $data['tanggal'] ?? now()->toDateString(),
                'eta' => $data['eta'] ?? null,
                'catatan' => $data['catatan'] ?? null,
                'sumber_dana' => $data['sumber_dana'] ?? null,
                'skema_bayar' => $data['skema_bayar'] ?? 'cash',
                'tanggal_tempo' => ($data['skema_bayar'] ?? '') === 'tempo' ? ($data['tanggal_tempo'] ?? null) : null,
                'subtotal_produk' => $totals['subtotal_produk'],
                'diskon_total' => $totals['diskon_total'],
                'ppn_nominal' => $totals['ppn_nominal'],
                'ongkos_kirim' => $totals['ongkos_kirim'],
                'adjustment' => $totals['adjustment'],
                'grand_total' => $totals['grand_total'],
                'status' => 'draft',
                'status_pembayaran' => 'belum_lunas',
                'dari_analisa' => true,
                'created_by' => auth()->id(),
            ]);

            foreach ($totals['items'] as $itemData) {
                $po->items()->create($itemData);
            }

            if (! empty($data['termins']) && ($data['skema_bayar'] ?? '') === 'termin') {
                $this->paymentService->createTerminsForPo($po, $data['termins']);
            }

            $this->stageService->initializePoStages($po, now(), auth()->user());

            return $po;
        });

        return redirect()->route('purchasing.show', $po)->with('success', "Draft PO {$po->no_po} dibuat dari Analisa.");
    }

    private function nextNoPo(): string
    {
        $year = now()->year;
        $prefix = "PO-{$year}-";

        $last = PurchaseOrder::where('no_po', 'like', $prefix . '%')
            ->orderByDesc('no_po')
            ->value('no_po');

        $nextSeq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        do {
            $candidate = sprintf('PO-%d-%04d', $year, $nextSeq);
            if (! PurchaseOrder::where('no_po', $candidate)->exists()) {
                return $candidate;
            }
            $nextSeq++;
        } while (true);
    }

    private function nextNoInvoice(): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $prefix = "INV/PO/{$year}{$month}/";

        $last = PurchaseOrder::where('no_invoice', 'like', $prefix . '%')
            ->orderByDesc('no_invoice')
            ->value('no_invoice');

        $nextSeq = 1;
        if ($last && preg_match('/\/(\d+)$/', $last, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        do {
            $candidate = sprintf('INV/PO/%s%s/%04d', $year, $month, $nextSeq);
            if (! PurchaseOrder::where('no_invoice', $candidate)->exists()) {
                return $candidate;
            }
            $nextSeq++;
        } while (true);
    }

    public function selectSupplier(Request $request): JsonResponse
    {
        $this->authorize('analisa.manage');

        $validated = $request->validate([
            'produk_id' => 'required|exists:produk,id',
            'supplier_id' => 'required|exists:supplier,id',
        ]);

        $result = $this->analisa->selectSupplierForProduct(
            (int) $validated['produk_id'],
            (int) $validated['supplier_id'],
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Supplier terpilih berhasil diubah dan analisa telah diperbarui.',
            'data' => $result,
        ]);
    }

    public function compareSuppliers(Request $request, Produk $produk): JsonResponse
    {
        $this->authorize('analisa.view');

        $comparison = $this->analisa->compareSuppliersForProduct($produk);

        return response()->json([
            'success' => true,
            'produk_id' => $produk->id,
            'produk_nama' => $produk->nama,
            'suppliers' => $comparison,
            'data' => [
                'produk_id' => $produk->id,
                'produk_nama' => $produk->nama,
                'suppliers' => $comparison,
            ],
        ]);
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

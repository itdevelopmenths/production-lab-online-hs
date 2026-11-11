<?php

namespace App\Http\Controllers;

use App\Models\AnalisaImpor;
use App\Models\KlasifikasiAbc;
use App\Models\Produk;
use App\Services\AnalisaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class KlasifikasiAbcController extends Controller
{
    public function index()
    {
        $this->authorize('analisa.view');

        return view('klasifikasi-abc.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('analisa.view');

        $canManage = auth()->user()->can('analisa.manage');

        $hasProdukAbcColumn = $this->hasProdukKlasifikasiColumn();

        $produkCounts = $hasProdukAbcColumn
            ? Produk::query()
                ->whereNotNull('klasifikasi_abc_id')
                ->where('is_active', true)
                ->whereIn('tipe', ['bahan', 'kemas'])
                ->selectRaw('klasifikasi_abc_id, COUNT(*) as cnt')
                ->groupBy('klasifikasi_abc_id')
                ->pluck('cnt', 'klasifikasi_abc_id')
            : collect();

        $legacyImporQuery = AnalisaImpor::query()
            ->join('produk', 'produk.id', '=', 'analisa_impor.produk_id')
            ->where('produk.is_active', true)
            ->whereIn('produk.tipe', ['bahan', 'kemas'])
            ->whereNotNull('analisa_impor.klasifikasi_abc_id');

        if ($hasProdukAbcColumn) {
            $legacyImporQuery->whereNull('produk.klasifikasi_abc_id');
        }

        $legacyImporCounts = $legacyImporQuery
            ->selectRaw('analisa_impor.klasifikasi_abc_id, COUNT(DISTINCT analisa_impor.produk_id) as cnt')
            ->groupBy('analisa_impor.klasifikasi_abc_id')
            ->pluck('cnt', 'klasifikasi_abc_id');

        $query = KlasifikasiAbc::query();

        return DataTables::eloquent($query)
            ->addColumn('badge', function ($item) {
                $color = match ($item->warna_badge) {
                    'red' => 'bg-rose-50 text-rose-700 border-rose-200',
                    'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'blue' => 'bg-sky-50 text-sky-700 border-sky-200',
                    'emerald', 'green' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    default => 'bg-gray-50 text-gray-700 border-gray-200',
                };

                return '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-bold border ' . $color . '">' . e(strtoupper($item->kode)) . '</span>';
            })
            ->addColumn('tambahan_buffer_label', function ($item) {
                return '<span class="font-mono text-xs font-semibold text-gray-900">+' . $item->tambahan_buffer_hari . ' Hari</span>';
            })
            ->addColumn('penggunaan_count', function ($item) use ($produkCounts, $legacyImporCounts) {
                $total = (int) ($produkCounts[$item->id] ?? 0) + (int) ($legacyImporCounts[$item->id] ?? 0);
                $json = $this->encodeItemPayload($item);

                return '<button type="button" @click="openSkuModal(' . $json . ')" class="inline-flex items-center gap-1 font-mono text-xs text-primary-700 bg-primary-50 hover:bg-primary-100 px-2.5 py-0.5 rounded-sm border border-primary-200 transition cursor-pointer" title="Lihat & Kelola Daftar SKU">' . number_format($total) . ' SKU</button>';
            })
            ->addColumn('status_badge', function ($item) {
                return $item->is_active
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-gray-50 text-gray-500 border border-gray-200">Non-Aktif</span>';
            })
            ->addColumn('action', function ($item) use ($canManage) {
                $json = $this->encodeItemPayload($item);

                $html = '<div class="flex items-center justify-center gap-2">';
                $html .= '<button type="button" @click="openSkuModal(' . $json . ')" class="px-2 py-1 rounded-md text-xs font-medium text-foreground hover:bg-accent border border-border transition-colors cursor-pointer">Kelola SKU</button>';

                if ($canManage) {
                    $html .= '<button type="button" @click="editItem(' . $json . ')" class="text-primary-600 hover:text-primary-800 text-xs font-semibold cursor-pointer">Edit</button>';
                    $html .= '<button type="button" onclick="hapusAbc(' . $item->id . ', \'' . e($item->kode) . '\')" class="text-rose-500 hover:text-rose-700 text-xs font-semibold cursor-pointer">Hapus</button>';
                }

                $html .= '</div>';

                return $html;
            })
            ->rawColumns(['badge', 'tambahan_buffer_label', 'penggunaan_count', 'status_badge', 'action'])
            ->toJson();
    }

    public function options(): JsonResponse
    {
        $this->authorize('analisa.view');

        $options = KlasifikasiAbc::where('is_active', true)
            ->orderBy('tambahan_buffer_hari', 'desc')
            ->get(['id', 'kode', 'nama', 'tambahan_buffer_hari', 'warna_badge']);

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    public function availableSkus(Request $request): JsonResponse
    {
        $this->authorize('analisa.view');

        $query = Produk::query()
            ->active()
            ->whereIn('tipe', ['bahan', 'kemas'])
            ->with('klasifikasiAbc:id,kode,nama,warna_badge');

        if ($request->filled('exclude_klasifikasi_id')) {
            $excludeId = (int) $request->input('exclude_klasifikasi_id');
            $query->where(function ($q) use ($excludeId) {
                $q->whereNull('klasifikasi_abc_id')
                    ->orWhere('klasifikasi_abc_id', '!=', $excludeId);
            });
        }

        if ($request->filled('profil')) {
            $profil = (string) $request->input('profil');
            if ($profil === 'impor') {
                $query->whereIn('profil_analisa', AnalisaService::IMPOR_PROFILES);
            } elseif ($profil === 'lokal') {
                $query->where('profil_analisa', 'lokal');
            } elseif ($profil === 'unset') {
                $query->whereNull('profil_analisa');
            }
        }

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

        $items = $query->orderBy('nama')
            ->limit(60)
            ->get(['id', 'sku', 'nama', 'satuan', 'tipe', 'profil_analisa', 'klasifikasi_abc_id'])
            ->map(fn ($p) => [
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
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function skus(KlasifikasiAbc $klasifikasiAbc): JsonResponse
    {
        $this->authorize('analisa.view');

        $legacyProdIds = AnalisaImpor::where('klasifikasi_abc_id', $klasifikasiAbc->id)
            ->pluck('produk_id')
            ->all();

        $skus = Produk::query()
            ->active()
            ->whereIn('tipe', ['bahan', 'kemas'])
            ->where(function ($q) use ($klasifikasiAbc, $legacyProdIds) {
                $q->where('klasifikasi_abc_id', $klasifikasiAbc->id);
                if (! empty($legacyProdIds)) {
                    $q->orWhere(function ($sub) use ($legacyProdIds) {
                        $sub->whereNull('klasifikasi_abc_id')
                            ->whereIn('id', $legacyProdIds);
                    });
                }
            })
            ->orderBy('nama')
            ->get(['id', 'sku', 'nama', 'satuan', 'tipe', 'profil_analisa', 'klasifikasi_abc_id']);

        return response()->json([
            'success' => true,
            'klasifikasi' => [
                'id' => $klasifikasiAbc->id,
                'kode' => $klasifikasiAbc->kode,
                'nama' => $klasifikasiAbc->nama,
                'tambahan_buffer_hari' => $klasifikasiAbc->tambahan_buffer_hari,
            ],
            'data' => $skus,
        ]);
    }

    public function assignSkus(Request $request, KlasifikasiAbc $klasifikasiAbc, AnalisaService $analisa): JsonResponse
    {
        $this->authorize('analisa.manage');

        $validator = Validator::make($request->all(), [
            'produk_ids' => ['required', 'array', 'min:1'],
            'produk_ids.*' => ['required', 'integer', 'exists:produk,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $count = $analisa->assignKlasifikasiAbcToSkus(
                $klasifikasiAbc->id,
                $validated['produk_ids'],
                AnalisaImpor::exists(),
                auth()->id()
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} SKU berhasil ditetapkan ke klasifikasi '{$klasifikasiAbc->nama}'.",
            'count' => $count,
        ]);
    }

    public function unassignSku(KlasifikasiAbc $klasifikasiAbc, Produk $produk, AnalisaService $analisa): JsonResponse
    {
        $this->authorize('analisa.manage');

        $belongsInImpor = AnalisaImpor::where('produk_id', $produk->id)
            ->where('klasifikasi_abc_id', $klasifikasiAbc->id)
            ->exists();

        if ((int) $produk->klasifikasi_abc_id === (int) $klasifikasiAbc->id || $belongsInImpor) {
            $analisa->syncProdukKlasifikasiAbc($produk, null, [
                'ensure_analisa_row' => false,
                'user_id' => auth()->id(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "SKU '{$produk->sku}' dilepas dari klasifikasi '{$klasifikasiAbc->kode}'.",
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('analisa.manage');

        $validated = $this->validateAbcPayload($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $validated['kode'] = strtolower(trim($validated['kode']));
        $item = KlasifikasiAbc::create($validated);

        return response()->json([
            'success' => true,
            'message' => "Klasifikasi ABC '{$item->kode}' berhasil ditambahkan.",
            'data' => $item,
        ]);
    }

    public function update(Request $request, KlasifikasiAbc $klasifikasiAbc): JsonResponse
    {
        $this->authorize('analisa.manage');

        $validated = $this->validateAbcPayload($request, $klasifikasiAbc->id);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $validated['kode'] = strtolower(trim($validated['kode']));
        $klasifikasiAbc->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Klasifikasi ABC '{$klasifikasiAbc->kode}' berhasil diperbarui.",
            'data' => $klasifikasiAbc,
        ]);
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function validateAbcPayload(Request $request, ?int $ignoreId = null): array|JsonResponse
    {
        // Normalize kode before unique validation so case-sensitive DBs (PostgreSQL) return 422 instead of 23505.
        $payload = $request->all();
        if (isset($payload['kode']) && is_string($payload['kode'])) {
            $payload['kode'] = strtolower(trim($payload['kode']));
        }

        $kodeRule = Rule::unique('klasifikasi_abc', 'kode');
        if ($ignoreId !== null) {
            $kodeRule->ignore($ignoreId);
        }

        $validator = Validator::make($payload, [
            'kode' => ['required', 'string', 'max:20', $kodeRule],
            'nama' => ['required', 'string', 'max:100'],
            'tambahan_buffer_hari' => ['required', 'integer', 'min:0', 'max:365'],
            'warna_badge' => ['required', 'string', 'in:red,amber,blue,emerald,gray'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        return $validator->validated();
    }

    public function destroy(KlasifikasiAbc $klasifikasiAbc): JsonResponse
    {
        $this->authorize('analisa.manage');

        $produkCount = $this->hasProdukKlasifikasiColumn()
            ? $klasifikasiAbc->produks()->count()
            : 0;
        $imporCount = $klasifikasiAbc->analisaImpor()->count() + $klasifikasiAbc->analisaImporMeta()->count();
        if ($produkCount + $imporCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Klasifikasi '{$klasifikasiAbc->kode}' sedang digunakan oleh data Master Produk / Analisa Impor dan tidak dapat dihapus.",
            ], 422);
        }

        $kode = $klasifikasiAbc->kode;
        $klasifikasiAbc->delete();

        return response()->json([
            'success' => true,
            'message' => "Klasifikasi ABC '{$kode}' berhasil dihapus.",
        ]);
    }

    private function hasProdukKlasifikasiColumn(): bool
    {
        return Schema::hasColumn('produk', 'klasifikasi_abc_id');
    }

    private function encodeItemPayload(KlasifikasiAbc $item): string
    {
        return htmlspecialchars(json_encode([
            'id' => $item->id,
            'kode' => $item->kode,
            'nama' => $item->nama,
            'tambahan_buffer_hari' => $item->tambahan_buffer_hari,
            'warna_badge' => $item->warna_badge,
            'deskripsi' => $item->deskripsi,
            'is_active' => (bool) $item->is_active,
        ]), ENT_QUOTES, 'UTF-8');
    }
}

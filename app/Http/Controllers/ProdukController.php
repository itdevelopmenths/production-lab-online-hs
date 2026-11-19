<?php

namespace App\Http\Controllers;

use App\Models\AnalisaImpor;
use App\Models\AnalisaImporMeta;
use App\Models\Kategori;
use App\Models\KlasifikasiAbc;
use App\Models\Produk;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\Varian;
use App\Services\AnalisaService;
use App\Services\StokService;
use App\Support\UiTableHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ProdukController extends Controller
{
    public function index()
    {
        $this->authorize('produk.view');

        $kategoriList = Kategori::orderBy('nama')->get();

        return view('produk.index', compact('kategoriList'));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('produk.view');

        // Cek izin sekali per request, bukan per baris
        $canEdit = $request->user()->can('produk.edit');
        $canDelete = $request->user()->can('produk.delete');

        $query = Produk::query()
            ->with(['kategori', 'varian', 'uom', 'klasifikasiAbc'])
            ->withCount('activeSuppliers')
            ->select('produk.*')
            ->when($request->filled('tipe') && $request->tipe !== 'all', function ($q) use ($request) {
                $q->where('tipe', $request->tipe);
            })
            ->when($request->filled('kategori_id'), function ($q) use ($request) {
                $q->where('kategori_id', $request->kategori_id);
            });

        return DataTables::eloquent($query)
            ->filter(function ($q) use ($request) {
                if ($search = $request->input('search.value')) {
                    $keywords = array_filter(explode(' ', strtolower(trim($search))));
                    if (!empty($keywords)) {
                        $q->where(function ($query) use ($keywords) {
                            foreach ($keywords as $word) {
                                $query->where(function ($sub) use ($word) {
                                    // whereIn subquery (bukan whereHas) agar Postgres tidak menjalankan EXISTS per baris
                                    $sub->whereRaw('LOWER(produk.sku) LIKE ?', ["%{$word}%"])
                                        ->orWhereRaw('LOWER(produk.nama) LIKE ?', ["%{$word}%"])
                                        ->orWhereRaw('LOWER(produk.nama_produk) LIKE ?', ["%{$word}%"])
                                        ->orWhereIn('produk.varian_id', Varian::query()
                                            ->select('id')
                                            ->whereRaw('LOWER(nama) LIKE ?', ["%{$word}%"]));
                                });
                            }
                        });
                    }
                }
            })
            ->editColumn('sku', fn ($p) => '<span class="font-mono text-xs font-semibold text-[#101828] dark:text-white">' . e($p->sku) . '</span>')
            ->editColumn('nama', fn ($p) => '<span class="font-medium text-[#101828] dark:text-white">' . e($p->nama) . '</span>')
            ->editColumn('kategori_nama', fn ($p) => $p->kategori
                ? '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold tracking-wide uppercase bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">' . e($p->kategori->nama) . '</span>'
                : '<span class="text-text-muted">—</span>'
            )
            ->editColumn('varian_nama', fn ($p) => $p->varian
                ? '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold tracking-wide uppercase bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border border-indigo-200/80 dark:border-indigo-500/20">' . e($p->varian->nama) . '</span>'
                : '<span class="text-text-muted">—</span>'
            )
            ->editColumn('tipe', fn ($p) => match($p->tipe) {
                'bahan' => '<span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20">Bahan Baku</span>',
                'kemas' => '<span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border border-sky-200 dark:border-sky-500/20">Kemasan</span>',
                'produk_jadi' => '<span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-[11px] font-semibold tracking-wide uppercase bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">Produk Jadi</span>',
                default => '<span class="text-text-muted">' . e(ucfirst(str_replace('_', ' ', $p->tipe))) . '</span>'
            })
            ->addColumn('supplier_count', function ($p) {
                if ($p->tipe === 'produk_jadi') {
                    return '<span class="text-xs text-text-muted font-mono">—</span>';
                }
                $count = (int) ($p->active_suppliers_count ?? 0);
                if ($count > 0) {
                    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">' . $count . ' Supplier</span>';
                }
                return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">0 Supplier</span>';
            })
            ->editColumn('satuan', fn ($p) => '<span class="font-mono text-xs text-text-muted font-medium">' . e($p->satuan) . '</span>')
            ->addColumn('klasifikasi_abc_badge', function ($p) {
                if ($p->tipe === 'produk_jadi') {
                    return '<span class="text-xs text-text-muted font-mono">—</span>';
                }

                if (AnalisaService::isImporProfile($p->profil_analisa)) {
                    if ($p->klasifikasiAbc) {
                        $color = match ($p->klasifikasiAbc->warna_badge) {
                            'red' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-500/20',
                            'amber' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/20',
                            'blue' => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-500/20',
                            'emerald', 'green' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20',
                            default => 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700',
                        };

                        return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase border ' . $color . '" title="' . e($p->klasifikasiAbc->nama) . '">Impor · ' . e(strtoupper($p->klasifikasiAbc->kode)) . '</span>';
                    }

                    return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700">Impor · Default C</span>';
                }

                return '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium bg-gray-100 dark:bg-gray-800 text-text-muted border border-border">Lokal</span>';
            })
            ->editColumn('is_active', fn ($p) => $p->is_active
                ? UiTableHelper::statusPill('Aktif', 'completed')
                : UiTableHelper::statusPill('Nonaktif', 'cancelled')
            )
            ->addColumn('action', function ($p) use ($canEdit, $canDelete) {
                $buttons = [];
                if ($canEdit) {
                    $buttons[] = UiTableHelper::actionLink(route('produk.edit', $p), 'Edit', 'pencil', 'default');
                }
                if ($canDelete) {
                    $buttons[] = UiTableHelper::actionButton("hapus('" . e(route('produk.destroy', $p)) . "')", 'Hapus', 'trash', 'danger');
                }

                return UiTableHelper::actionGroup($buttons);
            })
            ->rawColumns(['sku', 'nama', 'kategori_nama', 'varian_nama', 'tipe', 'supplier_count', 'satuan', 'klasifikasi_abc_badge', 'is_active', 'action'])
            ->toJson();
    }

    public function selectData(Request $request, StokService $stokService): JsonResponse
    {
        $query = Produk::query()->active();

        if ($request->filled('supplier_id')) {
            $supplierId = $request->input('supplier_id');
            $query->where(function ($q) use ($supplierId) {
                $q->whereExists(function ($sub) use ($supplierId) {
                    $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('produk_supplier')
                        ->whereColumn('produk_supplier.produk_id', 'produk.id')
                        ->where('produk_supplier.supplier_id', $supplierId)
                        ->where('produk_supplier.status', 'aktif');
                })->orWhere(function ($fallback) use ($supplierId) {
                    $fallback->where('produk.supplier_id', $supplierId)
                        ->whereNotExists(function ($sub) use ($supplierId) {
                            $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                                ->from('produk_supplier')
                                ->whereColumn('produk_supplier.produk_id', 'produk.id')
                                ->where('produk_supplier.supplier_id', $supplierId);
                        });
                });
            });
        }

        if ($request->filled('tipe')) {
            $tipe = $request->get('tipe');
            if (is_string($tipe)) {
                $tipe = explode(',', $tipe);
            }
            $query->whereIn('tipe', (array) $tipe);
        }

        if ($request->filled('q')) {
            $search = strtolower(trim($request->get('q')));
            $keywords = array_filter(explode(' ', $search));
            if (!empty($keywords)) {
                $query->where(function ($q) use ($keywords) {
                    foreach ($keywords as $word) {
                        $q->where(function ($sub) use ($word) {
                            $sub->whereRaw('LOWER(sku) LIKE ?', ["%{$word}%"])
                                ->orWhereRaw('LOWER(nama) LIKE ?', ["%{$word}%"])
                                ->orWhereRaw('LOWER(nama_produk) LIKE ?', ["%{$word}%"]);
                        });
                    }
                });
            }
        }

        $paginated = $query->orderBy('nama')
            ->select(['id', 'sku', 'nama', 'nama_produk', 'satuan', 'faktor_konversi', 'tipe', 'harga_hpp'])
            ->paginate(10);

        $productIds = collect($paginated->items())->pluck('id')->all();
        $hppMap = $stokService->resolveHppMap($productIds);

        foreach ($paginated->items() as $item) {
            $item->harga_hpp = (float) ($hppMap[$item->id] ?? $item->harga_hpp ?? 0);
        }

        return response()->json([
            'items' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'has_more' => $paginated->hasMorePages(),
            'total' => $paginated->total(),
        ]);
    }

    public function create()
    {
        $this->authorize('produk.create');

        $kategoriList = Kategori::with(['varians' => fn ($q) => $q->orderBy('nama')])->orderBy('nama')->get();
        $uomList = Uom::active()->orderBy('nama')->get();
        $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori']);
        $klasifikasiAbcList = KlasifikasiAbc::where('is_active', true)
            ->orderByDesc('tambahan_buffer_hari')
            ->get(['id', 'kode', 'nama', 'tambahan_buffer_hari', 'warna_badge']);

        return view('produk.create', compact('kategoriList', 'uomList', 'suppliers', 'klasifikasiAbcList'));
    }

    public function store(Request $request, AnalisaService $analisaService)
    {
        $this->authorize('produk.create');

        $data = $this->validated($request);

        $rawSuppliers = $this->getRawSuppliers($request);
        $firstActiveSupplierId = $this->extractFirstActiveSupplierId($rawSuppliers);
        if (empty($data['supplier_id']) && $firstActiveSupplierId) {
            $data['supplier_id'] = $firstActiveSupplierId;
        }

        $produk = Produk::create($data);

        $this->syncSuppliers($produk, $rawSuppliers);
        $analisaService->cleanupWorkingTablesForProfile(
            $produk,
            ($produk->tipe === 'produk_jadi' || ! $produk->is_active) ? null : $produk->profil_analisa
        );

        return redirect()->route('produk.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Produk $produk)
    {
        $this->authorize('produk.edit');

        $produk->load(['kategori', 'varian', 'suppliers', 'klasifikasiAbc']);
        $kategoriList = Kategori::with(['varians' => fn ($q) => $q->orderBy('nama')])->orderBy('nama')->get();
        $uomList = Uom::active()->orderBy('nama')->get();
        $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori']);
        $klasifikasiAbcList = KlasifikasiAbc::where('is_active', true)
            ->orderByDesc('tambahan_buffer_hari')
            ->get(['id', 'kode', 'nama', 'tambahan_buffer_hari', 'warna_badge']);

        return view('produk.edit', compact('produk', 'kategoriList', 'uomList', 'suppliers', 'klasifikasiAbcList'));
    }

    public function update(Request $request, Produk $produk, AnalisaService $analisaService)
    {
        $this->authorize('produk.edit');

        $data = $this->validated($request, $produk->id);

        $rawSuppliers = $this->getRawSuppliers($request);
        $firstActiveSupplierId = $this->extractFirstActiveSupplierId($rawSuppliers);

        if (empty($data['supplier_id']) && empty($produk->supplier_id) && $firstActiveSupplierId) {
            $data['supplier_id'] = $firstActiveSupplierId;
        }

        $produk->update($data);

        $this->syncSuppliers($produk, $rawSuppliers);
        $analisaService->cleanupWorkingTablesForProfile(
            $produk,
            ($produk->tipe === 'produk_jadi' || ! $produk->is_active) ? null : $produk->profil_analisa
        );

        if (AnalisaService::isImporProfile($produk->profil_analisa)
            && $produk->tipe !== 'produk_jadi'
            && (AnalisaImpor::where('produk_id', $produk->id)->exists() || AnalisaImporMeta::where('produk_id', $produk->id)->exists())) {
            $analisaService->syncProdukKlasifikasiAbc($produk, $produk->klasifikasi_abc_id, [
                'ensure_analisa_row' => false,
                'user_id' => auth()->id(),
            ]);
        }

        return redirect()->route('produk.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Produk $produk): JsonResponse
    {
        $this->authorize('produk.delete');
        $produk->delete();

        return response()->json(['message' => 'Produk berhasil dihapus.']);
    }

    private function getRawSuppliers(Request $request): mixed
    {
        $raw = $request->input('supplier_assignments', $request->input('suppliers'));
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $raw;
    }

    private function extractFirstActiveSupplierId(mixed $rawSuppliers): ?int
    {
        if (!is_array($rawSuppliers)) {
            return null;
        }

        foreach ($rawSuppliers as $key => $val) {
            if (is_array($val) && isset($val['supplier_id'])) {
                $status = ($val['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
                if ($status === 'aktif') {
                    return (int) $val['supplier_id'];
                }
            } elseif (is_numeric($key) && is_array($val)) {
                $status = ($val['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
                if ($status === 'aktif') {
                    return (int) $key;
                }
            } elseif (is_numeric($val)) {
                return (int) $val;
            }
        }

        return null;
    }

    private function syncSuppliers(Produk $produk, mixed $rawSuppliers): void
    {
        if ($rawSuppliers === null) {
            return;
        }

        $syncData = [];
        $firstActiveSupplierId = null;

        if (is_array($rawSuppliers)) {
            foreach ($rawSuppliers as $key => $val) {
                if (is_array($val) && isset($val['supplier_id'])) {
                    $sId = (int) $val['supplier_id'];
                    $status = ($val['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
                    $syncData[$sId] = ['status' => $status];
                    if ($status === 'aktif' && !$firstActiveSupplierId) {
                        $firstActiveSupplierId = $sId;
                    }
                } elseif (is_numeric($key) && is_array($val)) {
                    $sId = (int) $key;
                    $status = ($val['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
                    $syncData[$sId] = ['status' => $status];
                    if ($status === 'aktif' && !$firstActiveSupplierId) {
                        $firstActiveSupplierId = $sId;
                    }
                } elseif (is_numeric($val)) {
                    $sId = (int) $val;
                    $syncData[$sId] = ['status' => 'aktif'];
                    if (!$firstActiveSupplierId) {
                        $firstActiveSupplierId = $sId;
                    }
                }
            }
        }

        $produk->suppliers()->sync($syncData);

        if ($firstActiveSupplierId && empty($produk->supplier_id)) {
            $produk->supplier_id = $firstActiveSupplierId;
            $produk->saveQuietly();
        }
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $kategoriId = $request->input('kategori_id');

        $rules = [
            'sku' => ['required', 'string', 'max:30', Rule::unique('produk', 'sku')->ignore($id)],
            'kategori_id' => ['required', 'integer', 'exists:kategori,id'],
            'varian_id' => [
                'nullable',
                'integer',
                Rule::exists('varian', 'id')->where('kategori_id', $kategoriId),
            ],
            'nama_produk' => ['required', 'string', 'max:150'],
            'tipe' => ['required', Rule::in(Produk::TIPE)],
            'satuan' => ['required', 'string', 'max:15', 'exists:uom,kode'],
            'faktor_konversi' => ['nullable', 'numeric', 'min:0.0001'],
            'satuan_order_moq' => ['required', 'numeric', 'min:0'],
            'profil_analisa' => ['nullable', Rule::in(['lokal', 'impor', 'bahan_impor'])],
            'klasifikasi_abc_id' => ['nullable', 'integer', 'exists:klasifikasi_abc,id'],
            'is_active' => ['boolean'],
            'supplier_id' => ['nullable', 'integer', 'exists:supplier,id'],
            'suppliers' => ['nullable'],
            'supplier_assignments' => ['nullable'],
        ];

        $validated = $request->validate($rules, [
            'varian_id.exists' => 'Varian yang dipilih tidak valid atau tidak termasuk dalam kategori terpilih.',
        ], [
            'sku' => 'SKU',
            'kategori_id' => 'Kategori',
            'varian_id' => 'Varian',
            'nama_produk' => 'Nama Produk',
            'tipe' => 'Tipe Produk',
            'satuan' => 'Satuan Dasar',
            'faktor_konversi' => 'Faktor Konversi',
            'satuan_order_moq' => 'Satuan Order (MOQ)',
            'profil_analisa' => 'Profil Analisa',
        ]);

        // Auto-generate nama string
        $varianNama = '';
        if (!empty($validated['varian_id'])) {
            $varian = Varian::find($validated['varian_id']);
            if ($varian) {
                $varianNama = ' ' . $varian->nama;
            }
        }
        $validated['faktor_konversi'] = (float) ($validated['faktor_konversi'] ?? 1);
        $validated['nama'] = trim($validated['nama_produk'] . $varianNama);
        if (($validated['tipe'] ?? '') === 'produk_jadi') {
            $validated['profil_analisa'] = null;
            $validated['klasifikasi_abc_id'] = null;
        } elseif (! AnalisaService::isImporProfile($validated['profil_analisa'] ?? null)) {
            $validated['klasifikasi_abc_id'] = null;
        }

        return $validated;
    }
}

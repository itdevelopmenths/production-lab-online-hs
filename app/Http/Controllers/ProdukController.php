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
    public function index(Request $request)
    {
        $this->authorize('produk.view');

        $kelompok = Produk::normalizeKelompok($request->query('kelompok'));

        // User yang hanya boleh melihat kelompok lain diarahkan ke menunya sendiri.
        if (! $request->user()->can(Produk::izinKelompok('view', $kelompok))) {
            $lain = $kelompok === Produk::KELOMPOK_BAHAN ? Produk::KELOMPOK_PRODUK_JADI : Produk::KELOMPOK_BAHAN;
            abort_unless($request->user()->can(Produk::izinKelompok('view', $lain)), 403);

            return redirect()->to(Produk::indexUrl($lain));
        }

        $tipeOptions = Produk::KELOMPOK_TIPE[$kelompok];
        $canCreate = $request->user()->can(Produk::izinKelompok('create', $kelompok));

        $kategoriList = Kategori::orderBy('nama')->get();

        $createKategoriList = $uomList = $suppliers = $klasifikasiAbcList = collect();
        if ($canCreate) {
            $createKategoriList = Kategori::with(['varians' => fn ($q) => $q->orderBy('nama')])->orderBy('nama')->get();
            $uomList = Uom::active()->orderBy('nama')->get();
            $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori']);
            $klasifikasiAbcList = KlasifikasiAbc::where('is_active', true)
                ->orderByDesc('tambahan_buffer_hari')
                ->get(['id', 'kode', 'nama', 'tambahan_buffer_hari', 'warna_badge']);
        }

        return view('produk.index', compact('kategoriList', 'createKategoriList', 'uomList', 'suppliers', 'klasifikasiAbcList', 'kelompok', 'tipeOptions', 'canCreate'));
    }

    public function data(Request $request): JsonResponse
    {
        $kelompok = Produk::normalizeKelompok($request->input('kelompok'));
        $this->authorize(Produk::izinKelompok('view', $kelompok));

        // Cek izin sekali per request, bukan per baris
        $canEdit = $request->user()->can(Produk::izinKelompok('edit', $kelompok));
        $canDelete = $request->user()->can(Produk::izinKelompok('delete', $kelompok));

        // Menu dipisah: Bahan Baku & Kemasan vs Produk Jadi. Filter tipe hanya berlaku di dalam kelompoknya.
        $kelompokTipe = array_keys(Produk::KELOMPOK_TIPE[$kelompok]);
        $tipe = $request->input('tipe');

        $query = Produk::query()
            ->select('produk.*')
            ->with(['kategori', 'varian', 'uom', 'klasifikasiAbc'])
            // withCount harus setelah select(): select() menimpa kolom subquery count
            ->withCount('activeSuppliers')
            ->whereIn('tipe', in_array($tipe, $kelompokTipe, true) ? [$tipe] : $kelompokTipe)
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
            ->editColumn('sku', fn ($p) => '<span class="tbl-id tabular-nums">' . e($p->sku) . '</span>')
            ->editColumn('nama', fn ($p) => '<span class="tbl-primary">' . e($p->nama) . '</span>')
            ->editColumn('kategori_nama', fn ($p) => $p->kategori
                ? UiTableHelper::tag($p->kategori->nama)
                : '<span class="tbl-dash">—</span>'
            )
            ->editColumn('varian_nama', fn ($p) => $p->varian
                ? e($p->varian->nama)
                : '<span class="tbl-dash">—</span>'
            )
            ->editColumn('tipe', fn ($p) => match($p->tipe) {
                'bahan' => UiTableHelper::tag('Bahan Baku', 'warn'),
                'kemas' => UiTableHelper::tag('Kemasan', 'info'),
                'produk_jadi' => UiTableHelper::tag('Produk Jadi', 'ink'),
                default => '<span class="tbl-dash">' . e(ucfirst(str_replace('_', ' ', $p->tipe))) . '</span>'
            })
            ->addColumn('supplier_count', function ($p) {
                if ($p->tipe === 'produk_jadi') {
                    return '<span class="tbl-dash">—</span>';
                }
                $count = (int) ($p->active_suppliers_count ?? 0);

                return $count > 0
                    ? UiTableHelper::statusDot($count . ' Supplier', 'ok')
                    : UiTableHelper::statusDot('0 Supplier', 'warn');
            })
            ->editColumn('satuan', fn ($p) => e($p->satuan))
            ->addColumn('klasifikasi_abc_badge', function ($p) {
                if ($p->tipe === 'produk_jadi') {
                    return '<span class="tbl-dash">—</span>';
                }

                if (AnalisaService::isImporProfile($p->profil_analisa)) {
                    if ($p->klasifikasiAbc) {
                        $dot = match ($p->klasifikasiAbc->warna_badge) {
                            'red' => 'bad',
                            'amber' => 'warn',
                            'blue' => 'info',
                            'emerald', 'green' => 'ok',
                            default => null,
                        };

                        return '<span title="' . e($p->klasifikasiAbc->nama) . '">' . UiTableHelper::tag('Impor · ' . strtoupper($p->klasifikasiAbc->kode), $dot) . '</span>';
                    }

                    return UiTableHelper::tag('Impor · Default C');
                }

                return UiTableHelper::tag('Lokal');
            })
            ->editColumn('is_active', fn ($p) => $p->is_active
                ? UiTableHelper::statusDot('Aktif', 'ok')
                : UiTableHelper::statusDot('Nonaktif', 'bad')
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

    public function create(Request $request)
    {
        $kelompok = Produk::normalizeKelompok($request->query('kelompok'));
        $this->authorize(Produk::izinKelompok('create', $kelompok));

        $tipeOptions = Produk::KELOMPOK_TIPE[$kelompok];

        $kategoriList = Kategori::with(['varians' => fn ($q) => $q->orderBy('nama')])->orderBy('nama')->get();
        $uomList = Uom::active()->orderBy('nama')->get();
        $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori']);
        $klasifikasiAbcList = KlasifikasiAbc::where('is_active', true)
            ->orderByDesc('tambahan_buffer_hari')
            ->get(['id', 'kode', 'nama', 'tambahan_buffer_hari', 'warna_badge']);

        return view('produk.create', compact('kategoriList', 'uomList', 'suppliers', 'klasifikasiAbcList', 'kelompok', 'tipeOptions'));
    }

    public function store(Request $request, AnalisaService $analisaService)
    {
        // Cek izin kelompok sebelum validasi agar user tanpa izin tidak bisa memancing pesan validasi.
        $this->authorize(Produk::izinKelompok('create', Produk::kelompokDariTipe($request->input('tipe'))));

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

        return redirect()->to(Produk::indexUrl(Produk::kelompokDariTipe($produk->tipe)))->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Produk $produk)
    {
        $this->authorize(Produk::izinKelompok('edit', Produk::kelompokDariTipe($produk->tipe)));

        // Tipe hanya boleh diganti ke kelompok yang juga boleh diubah user.
        $tipeOptions = [];
        foreach (Produk::KELOMPOK_TIPE as $kelompok => $opsi) {
            if (auth()->user()->can(Produk::izinKelompok('edit', $kelompok))) {
                $tipeOptions += $opsi;
            }
        }

        $produk->load(['kategori', 'varian', 'suppliers', 'klasifikasiAbc']);
        $kategoriList = Kategori::with(['varians' => fn ($q) => $q->orderBy('nama')])->orderBy('nama')->get();
        $uomList = Uom::active()->orderBy('nama')->get();
        $suppliers = Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori']);
        $klasifikasiAbcList = KlasifikasiAbc::where('is_active', true)
            ->orderByDesc('tambahan_buffer_hari')
            ->get(['id', 'kode', 'nama', 'tambahan_buffer_hari', 'warna_badge']);

        return view('produk.edit', compact('produk', 'kategoriList', 'uomList', 'suppliers', 'klasifikasiAbcList', 'tipeOptions'));
    }

    public function update(Request $request, Produk $produk, AnalisaService $analisaService)
    {
        $this->authorize(Produk::izinKelompok('edit', Produk::kelompokDariTipe($produk->tipe)));
        // Pindah kelompok (mis. bahan -> produk jadi) butuh izin ubah di kelompok tujuan juga.
        $this->authorize(Produk::izinKelompok('edit', Produk::kelompokDariTipe($request->input('tipe', $produk->tipe))));

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

        return redirect()->to(Produk::indexUrl(Produk::kelompokDariTipe($produk->tipe)))->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Produk $produk): JsonResponse
    {
        $this->authorize(Produk::izinKelompok('delete', Produk::kelompokDariTipe($produk->tipe)));
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

        // Jaga kolom legacy supplier_id tetap konsisten dengan relasi aktif di produk_supplier
        $activeIds = collect($syncData)->filter(fn ($d) => ($d['status'] ?? 'aktif') === 'aktif')->keys()->map(fn ($k) => (int) $k);
        if (! empty($produk->supplier_id) && $activeIds->contains((int) $produk->supplier_id)) {
            return;
        }
        $produk->supplier_id = $firstActiveSupplierId;
        $produk->saveQuietly();
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

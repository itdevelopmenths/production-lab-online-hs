<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Uom;
use App\Models\Varian;
use App\Services\StokService;
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

        $query = Produk::query()
            ->with(['kategori', 'varian', 'uom'])
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
                                    $sub->whereRaw('LOWER(CAST(produk.sku AS TEXT)) LIKE ?', ["%{$word}%"])
                                        ->orWhereRaw('LOWER(CAST(produk.nama AS TEXT)) LIKE ?', ["%{$word}%"])
                                        ->orWhereRaw('LOWER(CAST(produk.nama_produk AS TEXT)) LIKE ?', ["%{$word}%"])
                                        ->orWhereHas('varian', function ($v) use ($word) {
                                            $v->whereRaw('LOWER(CAST(nama AS TEXT)) LIKE ?', ["%{$word}%"]);
                                        });
                                });
                            }
                        });
                    }
                }
            })
            ->editColumn('sku', fn ($p) => '<span class="font-mono font-medium text-gray-800">' . e($p->sku) . '</span>')
            ->editColumn('nama', fn ($p) => '<span class="font-semibold text-gray-900">' . e($p->nama) . '</span>')
            ->editColumn('kategori_nama', fn ($p) => $p->kategori
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">' . e($p->kategori->nama) . '</span>'
                : '<span class="text-gray-400">—</span>'
            )
            ->editColumn('varian_nama', fn ($p) => $p->varian
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200">' . e($p->varian->nama) . '</span>'
                : '<span class="text-gray-400">—</span>'
            )
            ->editColumn('tipe', fn ($p) => match($p->tipe) {
                'bahan' => '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">Bahan Baku</span>',
                'kemas' => '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">Kemasan</span>',
                'produk_jadi' => '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">Produk Jadi</span>',
                default => '<span class="text-gray-600">' . e(ucfirst(str_replace('_', ' ', $p->tipe))) . '</span>'
            })
            ->editColumn('satuan', fn ($p) => '<span class="font-mono text-gray-700 font-medium">' . e($p->satuan) . '</span>')
            ->editColumn('is_active', fn ($p) => $p->is_active
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-semibold bg-gray-100 text-gray-500 border border-gray-200">Nonaktif</span>'
            )
            ->addColumn('action', fn ($p) => view('produk._actions', ['p' => $p])->render())
            ->rawColumns(['sku', 'nama', 'kategori_nama', 'varian_nama', 'tipe', 'satuan', 'is_active', 'action'])
            ->toJson();
    }

    public function selectData(Request $request, StokService $stokService): JsonResponse
    {
        $query = Produk::query()->active();

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

        return view('produk.create', compact('kategoriList', 'uomList'));
    }

    public function store(Request $request)
    {
        $this->authorize('produk.create');

        $data = $this->validated($request);
        Produk::create($data);

        return redirect()->route('produk.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Produk $produk)
    {
        $this->authorize('produk.edit');

        $produk->load(['kategori', 'varian']);
        $kategoriList = Kategori::with(['varians' => fn ($q) => $q->orderBy('nama')])->orderBy('nama')->get();
        $uomList = Uom::active()->orderBy('nama')->get();

        return view('produk.edit', compact('produk', 'kategoriList', 'uomList'));
    }

    public function update(Request $request, Produk $produk)
    {
        $this->authorize('produk.edit');

        $data = $this->validated($request, $produk->id);
        $produk->update($data);

        return redirect()->route('produk.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Produk $produk): JsonResponse
    {
        $this->authorize('produk.delete');
        $produk->delete();

        return response()->json(['message' => 'Produk berhasil dihapus.']);
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
            'profil_analisa' => ['nullable', Rule::in(['lokal', 'impor'])],
            'is_active' => ['boolean'],
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

        return $validated;
    }
}

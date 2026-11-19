<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\Varian;
use App\Support\UiTableHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class VarianController extends Controller
{
    public function index()
    {
        $this->authorize('produk.view');

        $kategoriList = Kategori::orderBy('nama')->get();

        return view('varian.index', compact('kategoriList'));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('produk.view');

        // Cek izin sekali per request, bukan per baris
        $canEdit = $request->user()->can('produk.edit');
        $canDelete = $request->user()->can('produk.delete');

        $query = Varian::query()
            ->with('kategori')
            ->withCount('produk')
            ->when($request->filled('kategori_id'), function ($q) use ($request) {
                $q->where('kategori_id', $request->kategori_id);
            });

        return DataTables::eloquent($query)
            ->addColumn('kategori_nama', fn ($v) => '<span class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">' . e($v->kategori?->nama ?? '—') . '</span>')
            ->addColumn('produk_count', fn ($v) => '<span class="font-mono text-xs text-[#101828] dark:text-white bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-[5px] border border-[#E8E8E8] dark:border-border">' . number_format($v->produk_count) . ' SKU</span>')
            ->addColumn('action', function ($v) use ($canEdit, $canDelete) {
                $buttons = [];
                if ($canEdit) {
                    $buttons[] = UiTableHelper::actionLink(route('varian.edit', $v), 'Edit', 'pencil', 'default');
                }
                if ($canDelete) {
                    $buttons[] = UiTableHelper::actionButton("hapus('" . e(route('varian.destroy', $v)) . "')", 'Hapus', 'trash', 'danger');
                }

                return UiTableHelper::actionGroup($buttons);
            })
            ->rawColumns(['kategori_nama', 'produk_count', 'action'])
            ->toJson();
    }

    /**
     * Endpoint API untuk dependent dropdown varian berdasarkan kategori yang dipilih.
     */
    public function byKategori(int|string $kategoriId): JsonResponse
    {
        $varians = Varian::where('kategori_id', $kategoriId)
            ->orderBy('nama')
            ->select(['id', 'kategori_id', 'nama'])
            ->get();

        return response()->json($varians);
    }

    public function create()
    {
        $this->authorize('produk.create');

        $kategoriList = Kategori::orderBy('nama')->get();

        return view('varian.create', compact('kategoriList'));
    }

    public function store(Request $request)
    {
        $this->authorize('produk.create');

        $data = $this->validated($request);
        Varian::create($data);

        return redirect()->route('varian.index')->with('success', 'Varian produk berhasil ditambahkan.');
    }

    public function edit(Varian $varian)
    {
        $this->authorize('produk.edit');

        $kategoriList = Kategori::orderBy('nama')->get();

        return view('varian.edit', compact('varian', 'kategoriList'));
    }

    public function update(Request $request, Varian $varian)
    {
        $this->authorize('produk.edit');

        $data = $this->validated($request, $varian->id);
        $varian->update($data);

        return redirect()->route('varian.index')->with('success', 'Varian produk berhasil diperbarui.');
    }

    public function destroy(Varian $varian): JsonResponse
    {
        $this->authorize('produk.delete');

        $produkCount = $varian->produk()->count();
        if ($produkCount > 0) {
            return response()->json([
                'message' => "Varian '{$varian->nama}' tidak dapat dihapus karena digunakan oleh {$produkCount} produk katalog."
            ], 422);
        }

        $varian->delete();

        return response()->json(['message' => 'Varian produk berhasil dihapus.']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $kategoriId = $request->input('kategori_id');

        return $request->validate([
            'kategori_id' => ['required', 'integer', 'exists:kategori,id'],
            'nama' => [
                'required',
                'string',
                'max:50',
                Rule::unique('varian', 'nama')
                    ->where(fn ($query) => $query->where('kategori_id', $kategoriId))
                    ->ignore($id),
            ],
        ], [
            'nama.unique' => 'Varian dengan nama ini sudah ada di dalam kategori yang sama.',
        ], [
            'kategori_id' => 'Kategori Produk',
            'nama' => 'Nama Varian',
        ]);
    }
}

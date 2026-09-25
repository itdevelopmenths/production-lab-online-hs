<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class KategoriController extends Controller
{
    public function index()
    {
        $this->authorize('produk.view');

        return view('kategori.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('produk.view');

        // Cek izin sekali per request, bukan per baris
        $canEdit = auth()->user()->can('produk.edit');
        $canDelete = auth()->user()->can('produk.delete');

        $query = Kategori::query()->select('kategori.*')
            ->withCount(['varians', 'produk']);

        return DataTables::eloquent($query)
            ->addColumn('varians_count', fn ($k) => '<span class="font-mono text-xs text-gray-700 bg-gray-100 px-2 py-0.5 rounded-sm border border-gray-200">' . number_format($k->varians_count) . ' Varian</span>')
            ->addColumn('produk_count', fn ($k) => '<span class="font-mono text-xs text-primary-700 bg-primary-50 px-2 py-0.5 rounded-sm border border-primary-200">' . number_format($k->produk_count) . ' SKU</span>')
            ->addColumn('action', function ($k) use ($canEdit, $canDelete) {
                $html = '<div class="flex items-center justify-center gap-3">';
                if ($canEdit) {
                    $html .= '<a href="' . e(route('kategori.edit', $k)) . '" class="text-primary-600 hover:text-primary-800 text-xs font-semibold">Edit</a>';
                }
                if ($canDelete) {
                    $html .= '<button type="button" onclick="hapus(\'' . e(route('kategori.destroy', $k)) . '\')" class="text-rose-500 hover:text-rose-700 text-xs font-semibold">Hapus</button>';
                }

                return $html . '</div>';
            })
            ->rawColumns(['varians_count', 'produk_count', 'action'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('produk.create');

        return view('kategori.create');
    }

    public function store(Request $request)
    {
        $this->authorize('produk.create');

        $data = $this->validated($request);
        Kategori::create($data);

        return redirect()->route('kategori.index')->with('success', 'Kategori produk berhasil ditambahkan.');
    }

    public function edit(Kategori $kategori)
    {
        $this->authorize('produk.edit');

        return view('kategori.edit', compact('kategori'));
    }

    public function update(Request $request, Kategori $kategori)
    {
        $this->authorize('produk.edit');

        $data = $this->validated($request, $kategori->id);
        $kategori->update($data);

        return redirect()->route('kategori.index')->with('success', 'Kategori produk berhasil diperbarui.');
    }

    public function destroy(Kategori $kategori): JsonResponse
    {
        $this->authorize('produk.delete');

        $produkCount = $kategori->produk()->count();
        if ($produkCount > 0) {
            return response()->json([
                'message' => "Kategori '{$kategori->nama}' tidak dapat dihapus karena digunakan oleh {$produkCount} produk aktif."
            ], 422);
        }

        $varianCount = $kategori->varians()->count();
        if ($varianCount > 0) {
            return response()->json([
                'message' => "Kategori '{$kategori->nama}' tidak dapat dihapus karena masih memiliki {$varianCount} varian terdaftar."
            ], 422);
        }

        $kategori->delete();

        return response()->json(['message' => 'Kategori produk berhasil dihapus.']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'nama' => [
                'required',
                'string',
                'max:50',
                Rule::unique('kategori', 'nama')->ignore($id),
            ],
        ], [], [
            'nama' => 'Nama Kategori',
        ]);
    }
}

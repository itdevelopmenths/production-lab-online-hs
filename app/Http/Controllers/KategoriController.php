<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Support\UiTableHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class KategoriController extends Controller
{
    public function index()
    {
        $this->authorize('kategori.view');

        return view('kategori.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('kategori.view');

        // Cek izin sekali per request, bukan per baris
        $canEdit = auth()->user()->can('kategori.edit');
        $canDelete = auth()->user()->can('kategori.delete');

        $query = Kategori::query()->select('kategori.*')
            ->withCount(['varians', 'produk']);

        return DataTables::eloquent($query)
            ->addColumn('varians_count', fn ($k) => '<span class="font-mono text-xs text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-[5px] border border-gray-200 dark:border-gray-700">' . number_format($k->varians_count) . ' Varian</span>')
            ->addColumn('produk_count', fn ($k) => '<span class="font-mono text-xs text-[#101828] dark:text-white bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-[5px] border border-[#E8E8E8] dark:border-border">' . number_format($k->produk_count) . ' SKU</span>')
            ->addColumn('action', function ($k) use ($canEdit, $canDelete) {
                $buttons = [];
                if ($canEdit) {
                    $buttons[] = UiTableHelper::actionLink(route('kategori.edit', $k), 'Edit', 'pencil', 'default');
                }
                if ($canDelete) {
                    $buttons[] = UiTableHelper::actionButton("hapus('" . e(route('kategori.destroy', $k)) . "')", 'Hapus', 'trash', 'danger');
                }

                return UiTableHelper::actionGroup($buttons);
            })
            ->rawColumns(['varians_count', 'produk_count', 'action'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('kategori.create');

        return view('kategori.create');
    }

    public function store(Request $request)
    {
        $this->authorize('kategori.create');

        $data = $this->validated($request);
        Kategori::create($data);

        return redirect()->route('kategori.index')->with('success', 'Kategori produk berhasil ditambahkan.');
    }

    public function edit(Kategori $kategori)
    {
        $this->authorize('kategori.edit');

        return view('kategori.edit', compact('kategori'));
    }

    public function update(Request $request, Kategori $kategori)
    {
        $this->authorize('kategori.edit');

        $data = $this->validated($request, $kategori->id);
        $kategori->update($data);

        return redirect()->route('kategori.index')->with('success', 'Kategori produk berhasil diperbarui.');
    }

    public function destroy(Kategori $kategori): JsonResponse
    {
        $this->authorize('kategori.delete');

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

<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\Uom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class UomController extends Controller
{
    public function index()
    {
        $this->authorize('uom.view');

        return view('uom.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('uom.view');

        $query = Uom::query()->select('uom.*')->withCount('produk');

        return DataTables::eloquent($query)
            ->editColumn('kode', fn ($u) => '<span class="font-mono font-medium text-primary-700 bg-primary-50/60 px-1.5 py-0.5 rounded-sm border border-primary-100">' . e($u->kode) . '</span>')
            ->editColumn('kategori', fn ($u) => $u->kategori ? '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">' . e($u->kategori) . '</span>' : '<span class="text-gray-400">—</span>')
            ->addColumn('produk_count', fn ($u) => '<span class="font-mono text-xs text-gray-700">' . number_format($u->produk_count) . ' SKU</span>')
            ->editColumn('is_active', fn ($u) => $u->is_active
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">Nonaktif</span>'
            )
            ->addColumn('action', fn ($u) => view('uom._actions', ['u' => $u])->render())
            ->rawColumns(['kode', 'kategori', 'produk_count', 'is_active', 'action'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('uom.create');

        return view('uom.create');
    }

    public function store(Request $request)
    {
        $this->authorize('uom.create');

        $data = $this->validated($request);
        $data['kode'] = strtolower(trim($data['kode']));

        Uom::create($data);

        return redirect()->route('uom.index')->with('success', 'Satuan UOM baru berhasil ditambahkan.');
    }

    public function edit(Uom $uom)
    {
        $this->authorize('uom.edit');

        return view('uom.edit', compact('uom'));
    }

    public function update(Request $request, Uom $uom)
    {
        $this->authorize('uom.edit');

        $data = $this->validated($request, $uom->id);
        $oldKode = $uom->kode;
        $newKode = strtolower(trim($data['kode']));
        $data['kode'] = $newKode;

        $uom->update($data);

        // Jika kode diubah, perbarui referensi satuan pada tabel produk terkait
        if ($oldKode !== $newKode) {
            Produk::where('satuan', $oldKode)->update(['satuan' => $newKode]);
        }

        return redirect()->route('uom.index')->with('success', 'Satuan UOM berhasil diperbarui.');
    }

    public function destroy(Uom $uom): JsonResponse
    {
        $this->authorize('uom.delete');

        $count = Produk::where('satuan', $uom->kode)->count();
        if ($count > 0) {
            return response()->json([
                'message' => "Satuan '{$uom->kode}' tidak dapat dihapus karena digunakan oleh {$count} produk katalog."
            ], 422);
        }

        $uom->delete();

        return response()->json(['message' => 'Satuan UOM berhasil dihapus.']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'kode' => [
                'required',
                'string',
                'max:15',
                Rule::unique('uom', 'kode')->ignore($id),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'kategori' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ], [], [
            'kode' => 'Kode Satuan',
            'nama' => 'Nama Satuan',
            'kategori' => 'Kategori Dimensi',
            'deskripsi' => 'Deskripsi',
            'is_active' => 'Status Aktif',
        ]);
    }
}

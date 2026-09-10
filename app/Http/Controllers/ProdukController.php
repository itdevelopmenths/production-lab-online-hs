<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ProdukController extends Controller
{
    public function index()
    {
        $this->authorize('produk.view');

        return view('produk.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('produk.view');

        $query = Produk::query()->select('produk.*');

        return DataTables::eloquent($query)
            ->editColumn('tipe', fn ($p) => ucfirst(str_replace('_', ' ', $p->tipe)))
            ->editColumn('satuan_order_moq', fn ($p) => rtrim(rtrim(number_format((float) $p->satuan_order_moq, 2, ',', '.'), '0'), ','))
            ->editColumn('is_active', fn ($p) => $p->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('action', fn ($p) => view('produk._actions', ['p' => $p])->render())
            ->rawColumns(['action'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('produk.create');

        return view('produk.create');
    }

    public function store(Request $request)
    {
        $this->authorize('produk.create');
        Produk::create($this->validated($request));

        return redirect()->route('produk.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Produk $produk)
    {
        $this->authorize('produk.edit');

        return view('produk.edit', compact('produk'));
    }

    public function update(Request $request, Produk $produk)
    {
        $this->authorize('produk.edit');
        $produk->update($this->validated($request, $produk->id));

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
        return $request->validate([
            'sku' => ['required', 'string', 'max:30', Rule::unique('produk', 'sku')->ignore($id)],
            'nama' => ['required', 'string', 'max:150'],
            'tipe' => ['required', Rule::in(Produk::TIPE)],
            'satuan' => ['required', 'string', 'max:10'],
            'satuan_order_moq' => ['required', 'numeric', 'min:0'],
            'profil_analisa' => ['nullable', Rule::in(['lokal', 'impor'])],
            'is_active' => ['boolean'],
        ], [], [
            'sku' => 'SKU',
            'nama' => 'Nama',
            'tipe' => 'Tipe',
            'satuan' => 'Satuan',
            'satuan_order_moq' => 'Satuan Order (MOQ)',
            'profil_analisa' => 'Profil Analisa',
        ]);
    }
}

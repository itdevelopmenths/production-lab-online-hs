<?php

namespace App\Http\Controllers;

use App\Models\Gudang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class GudangController extends Controller
{
    public function index()
    {
        $this->authorize('gudang.view');

        return view('gudang.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('gudang.view');

        $query = Gudang::query()->select('gudang.*')->with('parent:id,nama');

        return DataTables::eloquent($query)
            ->editColumn('tipe', fn ($g) => ucwords(str_replace('_', ' ', $g->tipe)))
            ->addColumn('parent', fn ($g) => $g->parent?->nama ?? '-')
            ->editColumn('status', fn ($g) => ucfirst($g->status))
            ->addColumn('action', fn ($g) => view('gudang._actions', ['g' => $g])->render())
            ->rawColumns(['action'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('gudang.create');
        $parents = Gudang::active()->orderBy('nama')->pluck('nama', 'id');

        return view('gudang.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $this->authorize('gudang.create');
        Gudang::create($this->validated($request));

        return redirect()->route('gudang.index')->with('success', 'Gudang berhasil ditambahkan.');
    }

    public function edit(Gudang $gudang)
    {
        $this->authorize('gudang.edit');
        $parents = Gudang::active()->where('id', '!=', $gudang->id)->orderBy('nama')->pluck('nama', 'id');

        return view('gudang.edit', compact('gudang', 'parents'));
    }

    public function update(Request $request, Gudang $gudang)
    {
        $this->authorize('gudang.edit');
        $gudang->update($this->validated($request, $gudang->id));

        return redirect()->route('gudang.index')->with('success', 'Gudang berhasil diperbarui.');
    }

    public function destroy(Gudang $gudang): JsonResponse
    {
        $this->authorize('gudang.delete');
        $gudang->delete();

        return response()->json(['message' => 'Gudang berhasil dihapus.']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('gudang', 'kode')->ignore($id)],
            'nama' => ['required', 'string', 'max:150'],
            'tipe' => ['required', Rule::in(Gudang::TIPE)],
            'parent_gudang_id' => ['nullable', 'exists:gudang,id'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'allow_negative_stock' => ['boolean'],
        ], [], [
            'kode' => 'Kode',
            'nama' => 'Nama',
            'tipe' => 'Tipe',
            'parent_gudang_id' => 'Gudang Induk',
        ]);
    }
}

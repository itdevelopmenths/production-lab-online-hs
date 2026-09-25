<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class SupplierController extends Controller
{
    public function index()
    {
        $this->authorize('supplier.view');

        return view('supplier.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('supplier.view');

        // Cek izin sekali per request, bukan per baris
        $canEdit = auth()->user()->can('supplier.edit');
        $canDelete = auth()->user()->can('supplier.delete');

        $query = Supplier::query()->select('supplier.*');

        return DataTables::eloquent($query)
            ->editColumn('kategori', fn ($s) => ucfirst($s->kategori))
            ->editColumn('termin_default', fn ($s) => $s->termin_default ? ucfirst($s->termin_default) : '-')
            ->editColumn('is_active', fn ($s) => $s->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('action', function ($s) use ($canEdit, $canDelete) {
                $html = '<div class="flex items-center gap-3">';
                if ($canEdit) {
                    $html .= '<a href="' . e(route('supplier.edit', $s)) . '" class="text-primary-600 hover:text-primary-800 text-xs font-medium">Edit</a>';
                }
                if ($canDelete) {
                    $html .= '<button onclick="hapus(\'' . e(route('supplier.destroy', $s)) . '\')" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>';
                }

                return $html . '</div>';
            })
            ->rawColumns(['action'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('supplier.create');

        return view('supplier.create');
    }

    public function store(Request $request)
    {
        $this->authorize('supplier.create');
        Supplier::create($this->validated($request));

        return redirect()->route('supplier.index')->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier)
    {
        $this->authorize('supplier.edit');

        return view('supplier.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorize('supplier.edit');
        $supplier->update($this->validated($request));

        return redirect()->route('supplier.index')->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('supplier.delete');
        $supplier->delete();

        return response()->json(['message' => 'Supplier berhasil dihapus.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'kategori' => ['required', Rule::in(['lokal', 'impor'])],
            'kontak' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string'],
            'termin_default' => ['nullable', Rule::in(['tempo', 'termin', 'pelunasan'])],
            'is_active' => ['boolean'],
        ], [], [
            'nama' => 'Nama',
            'kategori' => 'Kategori',
            'termin_default' => 'Termin Default',
        ]);
    }
}

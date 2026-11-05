<?php

namespace App\Http\Controllers;

use App\Models\Produk;
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

        $query = Supplier::query()
            ->withCount('activeProduks')
            ->select('supplier.*');

        return DataTables::eloquent($query)
            ->editColumn('kategori', fn ($s) => ucfirst($s->kategori))
            ->editColumn('termin_default', fn ($s) => $s->termin_default ? ucfirst($s->termin_default) : '-')
            ->editColumn('is_active', fn ($s) => $s->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('produk_count', function ($s) {
                $count = (int) ($s->active_produks_count ?? 0);
                if ($count > 0) {
                    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-500/20">' . $count . ' Produk</span>';
                }
                return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-zinc-500/10 text-zinc-600 dark:text-zinc-400 border border-zinc-500/20">0 Produk</span>';
            })
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
            ->rawColumns(['action', 'produk_count'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('supplier.create');

        $produks = Produk::whereIn('tipe', ['bahan', 'kemas', 'bahan_baku', 'kemasan'])
            ->active()
            ->orderBy('nama')
            ->get(['id', 'nama', 'sku', 'tipe']);

        return view('supplier.create', compact('produks'));
    }

    public function store(Request $request)
    {
        $this->authorize('supplier.create');
        $data = $this->validated($request);
        unset($data['produk_ids']);
        $supplier = Supplier::create($data);

        $this->syncProduks($supplier, $request);

        return redirect()->route('supplier.index')->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier)
    {
        $this->authorize('supplier.edit');

        $supplier->load('produks');

        $produks = Produk::whereIn('tipe', ['bahan', 'kemas', 'bahan_baku', 'kemasan'])
            ->active()
            ->orderBy('nama')
            ->get(['id', 'nama', 'sku', 'tipe']);

        return view('supplier.edit', compact('supplier', 'produks'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorize('supplier.edit');
        $data = $this->validated($request);
        unset($data['produk_ids']);
        $supplier->update($data);

        $this->syncProduks($supplier, $request);

        return redirect()->route('supplier.index')->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorize('supplier.delete');

        try {
            $supplier->delete();
        } catch (\DomainException $e) {
            if (request()->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Supplier berhasil dihapus.']);
        }

        return redirect()->route('supplier.index')->with('success', 'Supplier berhasil dihapus.');
    }

    private function syncProduks(Supplier $supplier, Request $request): void
    {
        if (!$request->has('produk_ids')) {
            return;
        }

        $raw = $request->input('produk_ids');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : array_filter(explode(',', $raw));
        }

        $syncData = [];
        if (is_array($raw)) {
            foreach ($raw as $pId) {
                if (!empty($pId)) {
                    $syncData[(int) $pId] = ['status' => 'aktif'];
                }
            }
        }

        $supplier->produks()->sync($syncData);
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
            'produk_ids' => ['nullable'],
            'produk_ids.*' => ['integer', 'exists:produk,id'],
        ], [], [
            'nama' => 'Nama',
            'kategori' => 'Kategori',
            'termin_default' => 'Termin Default',
            'produk_ids' => 'Produk Terkait',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\Supplier;
use App\Support\UiTableHelper;
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
            ->select('supplier.*')
            ->withCount('activeProduks');

        return DataTables::eloquent($query)
            ->editColumn('kategori', fn ($s) => ucfirst($s->kategori))
            ->editColumn('termin_default', fn ($s) => $s->termin_default ? ucfirst($s->termin_default) : '-')
            ->editColumn('is_active', fn ($s) => $s->is_active
                ? UiTableHelper::statusPill('Aktif', 'completed')
                : UiTableHelper::statusPill('Nonaktif', 'cancelled'))
            ->addColumn('produk_count', function ($s) {
                $count = (int) ($s->active_produks_count ?? 0);
                if ($count > 0) {
                    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-500/20">' . $count . ' Produk</span>';
                }
                return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700">0 Produk</span>';
            })
            ->addColumn('action', function ($s) use ($canEdit, $canDelete) {
                $buttons = [];
                if ($canEdit) {
                    $buttons[] = UiTableHelper::actionLink(route('supplier.edit', $s), 'Edit', 'pencil', 'default');
                }
                if ($canDelete) {
                    $buttons[] = UiTableHelper::actionButton("hapus('" . e(route('supplier.destroy', $s)) . "')", 'Hapus', 'trash', 'danger');
                }

                return UiTableHelper::actionGroup($buttons);
            })
            ->rawColumns(['is_active', 'action', 'produk_count'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('supplier.create');

        $produks = $this->suppliableProduks();

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

        $supplier->load('activeProduks');
        $produks = $this->suppliableProduks();

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

    private function suppliableProduks()
    {
        return Produk::suppliable()
            ->orderBy('nama')
            ->get(['id', 'nama', 'sku', 'tipe']);
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

        $requestedIds = [];
        if (is_array($raw)) {
            foreach ($raw as $pId) {
                $id = (int) $pId;
                if ($id > 0) {
                    $requestedIds[] = $id;
                }
            }
        }

        // Batasi sinkronisasi pada produk bahan/kemas aktif agar tetap sinkron dengan form & kolom index
        $selectedIds = empty($requestedIds)
            ? []
            : Produk::suppliable()
                ->whereIn('id', array_values(array_unique($requestedIds)))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $currentActiveIds = $supplier->activeProduks()
            ->pluck('produk.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $toDetach = array_values(array_diff($currentActiveIds, $selectedIds));
        if (!empty($toDetach)) {
            $supplier->produks()->detach($toDetach);
        }

        if (!empty($selectedIds)) {
            $upsertData = array_fill_keys($selectedIds, ['status' => 'aktif']);
            $supplier->produks()->syncWithoutDetaching($upsertData);
        }
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

<?php

namespace App\Http\Controllers;

use App\Models\Gudang;
use App\Support\UiTableHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class GudangController extends Controller
{
    public function index()
    {
        $this->authorize('gudang.view');

        $parents = Gudang::active()->orderBy('nama')->pluck('nama', 'id');

        return view('gudang.index', compact('parents'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('gudang.view');

        // Cek izin sekali per request, bukan per baris
        $canEdit = auth()->user()->can('gudang.edit');
        $canDelete = auth()->user()->can('gudang.delete');

        $query = Gudang::query()->select('gudang.*')->with('parent:id,nama');

        return DataTables::eloquent($query)
            ->editColumn('nama', function ($g) {
                $html = '<span class="font-medium text-[#101828] dark:text-white">' . e($g->nama) . '</span>';
                if ($g->isPusat()) {
                    $html .= ' <span class="inline-flex items-center px-1.5 py-0.5 rounded-[5px] text-[10px] font-bold bg-amber-100 dark:bg-amber-500/15 text-amber-800 dark:text-amber-400 border border-amber-300 dark:border-amber-500/30 ml-1">Pusat</span>';
                }
                return $html;
            })
            ->editColumn('tipe', function ($g) {
                $label = match ($g->tipe) {
                    'bahan_baku' => 'Bahan Baku',
                    'operasional' => 'Operasional',
                    'fulfillment', 'fulfillment_pusat', 'fulfillment_cabang' => 'Fulfillment',
                    default => ucwords(str_replace('_', ' ', $g->tipe)),
                };
                return '<span class="text-xs text-text-active">' . e($label) . '</span>';
            })
            ->addColumn('parent', fn ($g) => $g->parent?->nama ?? '-')
            ->editColumn('status', fn ($g) => UiTableHelper::statusPill(ucfirst($g->status), $g->status === 'aktif' ? 'completed' : 'draft'))
            ->addColumn('action', function ($g) use ($canEdit, $canDelete) {
                $buttons = [];
                if ($canEdit) {
                    $buttons[] = UiTableHelper::actionLink(route('gudang.edit', $g), 'Edit', 'pencil', 'default');
                }
                if ($canDelete) {
                    $buttons[] = UiTableHelper::actionButton("hapus('" . e(route('gudang.destroy', $g)) . "')", 'Hapus', 'trash', 'danger');
                }

                return UiTableHelper::actionGroup($buttons);
            })
            ->rawColumns(['nama', 'tipe', 'status', 'action'])
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
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            if ($data['is_pusat']) {
                $data['parent_gudang_id'] = null;
                // Pastikan hanya 1 pusat aktif per kategori tipe
                Gudang::where('tipe', $data['tipe'])->update(['is_pusat' => false]);
            }

            Gudang::create($data);
        });

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
        $data = $this->validated($request, $gudang->id);

        DB::transaction(function () use ($gudang, $data) {
            if ($data['is_pusat']) {
                $data['parent_gudang_id'] = null;
                // Jika diset sebagai pusat, lepas status pusat gudang lain pada tipe yang sama
                Gudang::where('tipe', $data['tipe'])
                    ->where('id', '!=', $gudang->id)
                    ->update(['is_pusat' => false]);
            }

            $gudang->update($data);
        });

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
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('gudang', 'kode')->ignore($id)],
            'nama' => ['required', 'string', 'max:150'],
            'tipe' => ['required', Rule::in(Gudang::TIPE_ALL)],
            'parent_gudang_id' => ['nullable', 'exists:gudang,id'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'is_pusat' => ['sometimes', 'boolean'],
            'allow_negative_stock' => ['sometimes', 'boolean'],
        ], [], [
            'kode' => 'Kode',
            'nama' => 'Nama',
            'tipe' => 'Tipe',
            'parent_gudang_id' => 'Gudang Induk',
        ]);

        $validated['is_pusat'] = $request->boolean('is_pusat');
        $validated['allow_negative_stock'] = $request->boolean('allow_negative_stock');

        return $validated;
    }
}

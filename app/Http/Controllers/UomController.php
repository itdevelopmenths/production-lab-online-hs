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

        // Cek izin sekali per request, bukan per baris
        $canEdit = auth()->user()->can('uom.edit');
        $canDelete = auth()->user()->can('uom.delete');

        $query = Uom::query()->select('uom.*')->withCount('produk');

        return DataTables::eloquent($query)
            ->editColumn('kode', fn ($u) => e($u->kode))
            ->editColumn('kategori', fn ($u) => $u->kategori ? e($u->kategori) : '-')
            ->addColumn('konversi', function ($u) {
                if (! $u->satuan_dasar || $u->satuan_dasar === $u->kode) {
                    return 'Satuan Pokok';
                }
                $f = (float) ($u->faktor_konversi ?: 1);
                $fFormatted = ($f == (int) $f) ? number_format($f, 0, ',', '.') : rtrim(rtrim(number_format($f, 4, ',', '.'), '0'), ',');

                return '1 ' . e($u->kode) . ' = ' . $fFormatted . ' ' . e($u->satuan_dasar);
            })
            ->addColumn('produk_count', fn ($u) => number_format($u->produk_count) . ' SKU')
            ->editColumn('is_active', fn ($u) => $u->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('action', function ($u) use ($canEdit, $canDelete) {
                $html = '<div class="flex items-center justify-center gap-3">';
                if ($canEdit) {
                    $html .= '<a href="' . e(route('uom.edit', $u)) . '" class="text-primary hover:text-primary/80 text-xs font-semibold">Edit</a>';
                }
                if ($canDelete) {
                    $html .= '<button type="button" onclick="hapus(\'' . e(route('uom.destroy', $u)) . '\')" class="text-rose-500 hover:text-rose-700 text-xs font-semibold cursor-pointer">Hapus</button>';
                }

                return $html . '</div>';
            })
            ->rawColumns(['action'])
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
        $data['is_active'] = $request->boolean('is_active');

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
        $data['is_active'] = $request->boolean('is_active');

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
            'satuan_dasar' => ['nullable', 'string', 'max:15'],
            'faktor_konversi' => ['nullable', 'numeric', 'min:0.0001'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ], [], [
            'kode' => 'Kode Satuan',
            'nama' => 'Nama Satuan',
            'kategori' => 'Kategori Dimensi',
            'satuan_dasar' => 'Satuan Pokok Acuan',
            'faktor_konversi' => 'Faktor Konversi Bawaan',
            'deskripsi' => 'Deskripsi',
            'is_active' => 'Status Aktif',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\KlasifikasiAbc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class KlasifikasiAbcController extends Controller
{
    public function index()
    {
        $this->authorize('analisa.view');

        return view('klasifikasi-abc.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('analisa.view');

        $canManage = auth()->user()->can('analisa.manage') || auth()->user()->can('produk.edit');

        $query = KlasifikasiAbc::query()->withCount(['analisaImpor', 'analisaImporMeta']);

        return DataTables::eloquent($query)
            ->addColumn('badge', function ($item) {
                $color = match ($item->warna_badge) {
                    'red' => 'bg-rose-50 text-rose-700 border-rose-200',
                    'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'blue' => 'bg-sky-50 text-sky-700 border-sky-200',
                    'emerald', 'green' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    default => 'bg-gray-50 text-gray-700 border-gray-200',
                };

                return '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-bold border ' . $color . '">' . e(strtoupper($item->kode)) . '</span>';
            })
            ->addColumn('tambahan_buffer_label', function ($item) {
                return '<span class="font-mono text-xs font-semibold text-gray-900">+' . $item->tambahan_buffer_hari . ' Hari</span>';
            })
            ->addColumn('penggunaan_count', function ($item) {
                $total = $item->analisa_impor_count + $item->analisa_impor_meta_count;

                return '<span class="font-mono text-xs text-primary-700 bg-primary-50 px-2 py-0.5 rounded-sm border border-primary-200">' . number_format($total) . ' SKU</span>';
            })
            ->addColumn('status_badge', function ($item) {
                return $item->is_active
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">Aktif</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-gray-50 text-gray-500 border border-gray-200">Non-Aktif</span>';
            })
            ->addColumn('action', function ($item) use ($canManage) {
                if (! $canManage) {
                    return '<span class="text-xs text-gray-400">Hanya Lihat</span>';
                }

                $json = htmlspecialchars(json_encode([
                    'id' => $item->id,
                    'kode' => $item->kode,
                    'nama' => $item->nama,
                    'tambahan_buffer_hari' => $item->tambahan_buffer_hari,
                    'warna_badge' => $item->warna_badge,
                    'deskripsi' => $item->deskripsi,
                    'is_active' => (bool) $item->is_active,
                ]), ENT_QUOTES, 'UTF-8');

                $html = '<div class="flex items-center justify-center gap-3">';
                $html .= '<button type="button" @click="editItem(' . $json . ')" class="text-primary-600 hover:text-primary-800 text-xs font-semibold">Edit</button>';
                $html .= '<button type="button" onclick="hapusAbc(' . $item->id . ', \'' . e($item->kode) . '\')" class="text-rose-500 hover:text-rose-700 text-xs font-semibold">Hapus</button>';
                $html .= '</div>';

                return $html;
            })
            ->rawColumns(['badge', 'tambahan_buffer_label', 'penggunaan_count', 'status_badge', 'action'])
            ->toJson();
    }

    public function options(): JsonResponse
    {
        $this->authorize('analisa.view');

        $options = KlasifikasiAbc::where('is_active', true)
            ->orderBy('tambahan_buffer_hari', 'desc')
            ->get(['id', 'kode', 'nama', 'tambahan_buffer_hari', 'warna_badge']);

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('analisa.manage');

        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:20', 'unique:klasifikasi_abc,kode'],
            'nama' => ['required', 'string', 'max:100'],
            'tambahan_buffer_hari' => ['required', 'integer', 'min:0', 'max:365'],
            'warna_badge' => ['required', 'string', 'in:red,amber,blue,emerald,gray'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $validated['kode'] = strtolower(trim($validated['kode']));
        $item = KlasifikasiAbc::create($validated);

        return response()->json([
            'success' => true,
            'message' => "Klasifikasi ABC '{$item->kode}' berhasil ditambahkan.",
            'data' => $item,
        ]);
    }

    public function update(Request $request, KlasifikasiAbc $klasifikasiAbc): JsonResponse
    {
        $this->authorize('analisa.manage');

        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('klasifikasi_abc', 'kode')->ignore($klasifikasiAbc->id)],
            'nama' => ['required', 'string', 'max:100'],
            'tambahan_buffer_hari' => ['required', 'integer', 'min:0', 'max:365'],
            'warna_badge' => ['required', 'string', 'in:red,amber,blue,emerald,gray'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $validated['kode'] = strtolower(trim($validated['kode']));
        $klasifikasiAbc->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Klasifikasi ABC '{$klasifikasiAbc->kode}' berhasil diperbarui.",
            'data' => $klasifikasiAbc,
        ]);
    }

    public function destroy(KlasifikasiAbc $klasifikasiAbc): JsonResponse
    {
        $this->authorize('analisa.manage');

        $imporCount = $klasifikasiAbc->analisaImpor()->count() + $klasifikasiAbc->analisaImporMeta()->count();
        if ($imporCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Klasifikasi '{$klasifikasiAbc->kode}' sedang digunakan oleh {$imporCount} data Analisa Impor dan tidak dapat dihapus.",
            ], 422);
        }

        $kode = $klasifikasiAbc->kode;
        $klasifikasiAbc->delete();

        return response()->json([
            'success' => true,
            'message' => "Klasifikasi ABC '{$kode}' berhasil dihapus.",
        ]);
    }
}

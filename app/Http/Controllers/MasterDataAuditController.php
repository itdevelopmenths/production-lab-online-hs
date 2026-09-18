<?php

namespace App\Http\Controllers;

use App\Models\Bom;
use App\Models\Divisi;
use App\Models\Gudang;
use App\Models\MasterDataAudit;
use App\Models\Produk;
use App\Models\Supplier;
use App\Models\Uom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class MasterDataAuditController extends Controller
{
    private const MODEL_MAP = [
        'produk' => Produk::class,
        'kategori' => \App\Models\Kategori::class,
        'varian' => \App\Models\Varian::class,
        'gudang' => Gudang::class,
        'supplier' => Supplier::class,
        'uom' => Uom::class,
        'bom' => Bom::class,
        'divisi' => Divisi::class,
    ];

    public function data(Request $request): JsonResponse
    {
        $entity = strtolower($request->get('entity', 'produk'));
        $targetClass = self::MODEL_MAP[$entity] ?? null;

        if (! $targetClass) {
            return response()->json(['data' => []]);
        }

        // Cek izin akses wewenang audit modul terkait
        $auditPerm = in_array($entity, ['kategori', 'varian']) ? 'produk.audit' : "{$entity}.audit";
        if (auth()->check()) {
            $user = auth()->user();
            if (! ($user->can($auditPerm) || $user->can('audit.view'))) {
                abort(403, 'Anda tidak memiliki wewenang untuk melihat log audit data master ini.');
            }
        }

        $query = MasterDataAudit::query()
            ->where('auditable_type', $targetClass)
            ->latest('id');

        return DataTables::eloquent($query)
            ->addColumn('waktu', fn ($a) => $a->created_at ? $a->created_at->format('d/m/Y H:i:s') : '—')
            ->addColumn('item_name', fn ($a) => $a->item_name ?: '—')
            ->addColumn('event', function ($a) {
                return match ($a->event) {
                    'created' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">DIBUAT</span>',
                    'updated' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-300">DIUBAH</span>',
                    'deleted' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">DIHAPUS</span>',
                    'imported' => '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-300">DIIMPOR</span>',
                    default => "<span class=\"px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-800\">" . strtoupper($a->event) . "</span>",
                };
            })
            ->addColumn('perubahan', fn ($a) => $a->formatted_diff)
            ->addColumn('oleh', fn ($a) => '<span class="font-medium text-gray-800">' . e($a->user_name ?? 'Sistem') . '</span>')
            ->addColumn('ip_address', fn ($a) => '<span class="font-mono text-gray-500 text-[11px]">' . e($a->ip_address ?: '—') . '</span>')
            ->rawColumns(['event', 'perubahan', 'oleh', 'ip_address'])
            ->toJson();
    }
}

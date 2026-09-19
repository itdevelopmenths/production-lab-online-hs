<?php

namespace App\Http\Controllers;

use App\Models\OperationalAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class OperationalAuditController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $module = strtolower($request->get('module', 'all'));
        $auditableId = $request->get('auditable_id');
        $user = auth()->user();

        // Verifikasi izin akses modul audit
        if ($user) {
            $hasGlobalAudit = $user->can('audit.view');
            if ($module === 'purchasing' && ! ($hasGlobalAudit || $user->can('purchasing.audit'))) {
                abort(403, 'Anda tidak memiliki wewenang untuk melihat log audit Purchasing.');
            }
            if ($module === 'produksi' && ! ($hasGlobalAudit || $user->can('batch.audit'))) {
                abort(403, 'Anda tidak memiliki wewenang untuk melihat log audit Produksi.');
            }
            if (in_array($module, ['request', 'transfer', 'request_transfer'], true) && ! ($hasGlobalAudit || $user->can('rt.audit'))) {
                abort(403, 'Anda tidak memiliki wewenang untuk melihat log audit Request & Transfer.');
            }
        }

        $query = OperationalAudit::query()->latest('id');

        if (! empty($auditableId)) {
            $query->where('auditable_id', $auditableId);
        } elseif ($module !== 'all') {
            if ($module === 'request_transfer') {
                $query->whereIn('module', ['request', 'transfer']);
            } else {
                $query->where('module', $module);
            }
        } else {
            // Filter modul yang diizinkan untuk user
            $allowedModules = [];
            $hasGlobal = $user?->can('audit.view');
            if ($hasGlobal || $user?->can('purchasing.audit')) {
                $allowedModules[] = 'purchasing';
            }
            if ($hasGlobal || $user?->can('batch.audit')) {
                $allowedModules[] = 'produksi';
            }
            if ($hasGlobal || $user?->can('rt.audit')) {
                $allowedModules[] = 'request';
                $allowedModules[] = 'transfer';
            }

            if (! empty($allowedModules)) {
                $query->whereIn('module', $allowedModules);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('date_start')) {
            $query->whereDate('created_at', '>=', $request->get('date_start'));
        }
        if ($request->filled('date_end')) {
            $query->whereDate('created_at', '<=', $request->get('date_end'));
        }

        return DataTables::eloquent($query)
            ->addColumn('waktu', fn ($a) => $a->created_at ? $a->created_at->format('d/m/Y H:i:s') : '—')
            ->addColumn('nomor_referensi', function ($a) {
                $link = match ($a->module) {
                    'purchasing' => route('purchasing.show', $a->auditable_id),
                    'produksi' => route('batches.show', $a->auditable_id),
                    'request', 'transfer' => route('rt.show', $a->auditable_id),
                    default => null,
                };

                $ref = e($a->nomor_referensi ?: '—');
                if ($link) {
                    return "<a href=\"{$link}\" class=\"font-mono font-bold text-primary-700 hover:text-primary-900 hover:underline inline-flex items-center gap-1\">{$ref}</a>";
                }
                return "<span class=\"font-mono font-semibold text-gray-800\">{$ref}</span>";
            })
            ->addColumn('module_badge', fn ($a) => $a->module_badge)
            ->addColumn('event_badge', fn ($a) => $a->event_badge)
            ->addColumn('rincian', fn ($a) => $a->formatted_diff)
            ->addColumn('pelaksana', function ($a) {
                $name = e($a->user_name ?? 'Sistem');
                $role = $a->user_role ? '<span class="text-[10px] text-gray-500 font-normal block leading-tight">' . e(ucfirst($a->user_role)) . '</span>' : '';
                return "<div class=\"text-xs font-semibold text-gray-900\">{$name}{$role}</div>";
            })
            ->addColumn('ip_address', fn ($a) => '<span class="font-mono text-gray-500 text-[11px]">' . e($a->ip_address ?: '—') . '</span>')
            ->rawColumns(['nomor_referensi', 'module_badge', 'event_badge', 'rincian', 'pelaksana', 'ip_address'])
            ->toJson();
    }
}

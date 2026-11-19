<?php

namespace App\Http\Controllers;

use App\Models\OperationalAudit;
use App\Models\PurchaseOrder;
use App\Traits\ResolvesGudangOnlyUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class OperationalAuditController extends Controller
{
    use ResolvesGudangOnlyUser;

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

        // Modul yang boleh dilihat user (berlaku untuk semua cabang, termasuk auditable_id).
        $allowedModules = [];
        $hasGlobal = (bool) $user?->can('audit.view');
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

        // User gudang murni hanya boleh melihat audit PO yang terlihat oleh gudang.
        if (! empty($auditableId) && $user && $this->isGudangOnly($user) && in_array('purchasing', $allowedModules, true)) {
            $po = Str::isUuid((string) $auditableId) ? PurchaseOrder::find($auditableId) : null;
            if ($po) {
                abort_unless($po->isVisibleToGudang(), 403, 'Anda tidak memiliki akses ke audit PO ini pada tahap saat ini.');
            } else {
                $allowedModules = array_values(array_diff($allowedModules, ['purchasing']));
            }
        }

        $query = OperationalAudit::query()->latest('id');

        if (! empty($allowedModules)) {
            $query->whereIn('module', $allowedModules);
        } else {
            $query->whereRaw('1 = 0');
        }

        if (! empty($auditableId)) {
            $query->where('auditable_id', $auditableId);
        } elseif ($module !== 'all') {
            if ($module === 'request_transfer') {
                $query->whereIn('module', ['request', 'transfer']);
            } else {
                $query->where('module', $module);
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
                    'request' => route('requests.show', $a->auditable_id),
                    'transfer' => route('transfers.show', $a->auditable_id),
                    default => null,
                };

                $ref = e($a->nomor_referensi ?: '—');
                if ($link) {
                    return "<a href=\"{$link}\" class=\"font-mono font-bold text-[#101828] dark:text-white hover:underline inline-flex items-center gap-1\">{$ref}</a>";
                }
                return "<span class=\"font-mono font-semibold text-[#101828] dark:text-white\">{$ref}</span>";
            })
            ->addColumn('module_badge', fn ($a) => $a->module_badge)
            ->addColumn('event_badge', fn ($a) => $a->event_badge)
            ->addColumn('rincian', fn ($a) => $a->formatted_diff)
            ->addColumn('pelaksana', function ($a) {
                $name = e($a->user_name ?? 'Sistem');
                $role = $a->user_role ? '<span class="text-[10px] text-text-muted font-normal block leading-tight">' . e(ucfirst($a->user_role)) . '</span>' : '';
                return "<div class=\"text-xs font-semibold text-[#101828] dark:text-white\">{$name}{$role}</div>";
            })
            ->addColumn('ip_address', fn ($a) => '<span class="font-mono text-text-muted text-[11px]">' . e($a->ip_address ?: '—') . '</span>')
            ->rawColumns(['nomor_referensi', 'module_badge', 'event_badge', 'rincian', 'pelaksana', 'ip_address'])
            ->toJson();
    }
}

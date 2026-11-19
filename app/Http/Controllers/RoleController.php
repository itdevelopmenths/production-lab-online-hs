<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleStoreRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Models\Role;
use App\Services\Authorization\PermissionCatalogService;
use App\Services\Authorization\RoleService;
use App\Support\UiTableHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class RoleController extends Controller
{
    public function __construct(
        protected RoleService $roleService,
        protected PermissionCatalogService $permissionCatalogService
    ) {}

    public function index(): View
    {
        $this->authorize('role.manage');
        return view('roles.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('role.manage');

        $query = Role::query()
            ->withCount('users')
            ->with('permissions:id,name');

        return DataTables::eloquent($query)
            ->addColumn('role_badge', function (Role $role) {
                $displayName = e($role->display_name ?: $role->name);
                $keyName = e($role->name);
                return '<div>
                            <div class="font-semibold text-[#101828] dark:text-white text-sm leading-tight">' . $displayName . '</div>
                            <div class="text-xs text-text-muted font-mono tracking-tight mt-0.5">' . $keyName . '</div>
                        </div>';
            })
            ->addColumn('system_badge', function (Role $role) {
                if ($role->is_system) {
                    return '<span class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-xs font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-400 border border-amber-200/80 dark:border-amber-500/20">
                                <svg class="w-3 h-3 mr-1 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                                Sistem
                            </span>';
                }
                return '<span class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-xs font-semibold bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-200/80 dark:border-blue-500/20">
                            Kustom
                        </span>';
            })
            ->addColumn('permissions_summary', function (Role $role) {
                $count = $role->permissions->count();
                return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                            ' . $count . ' wewenang
                        </span>';
            })
            ->addColumn('users_count_badge', function (Role $role) {
                return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . ($role->users_count > 0 ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-500/20' : 'bg-gray-50 dark:bg-gray-800 text-text-muted border border-gray-200/60 dark:border-gray-700') . '">
                            ' . $role->users_count . ' pengguna
                        </span>';
            })
            ->addColumn('action', function (Role $role) {
                $buttons = [
                    UiTableHelper::actionLink(route('roles.edit', $role), 'Atur Izin', 'sliders', 'default'),
                ];

                if (!$role->is_system) {
                    $buttons[] = UiTableHelper::actionButton("deleteRole(" . $role->id . ", '" . addslashes(e($role->display_name ?: $role->name)) . "')", 'Hapus', 'trash', 'danger');
                } else {
                    $buttons[] = '<span class="inline-flex items-center text-[11px] text-text-muted font-medium px-2 py-1 italic cursor-not-allowed" title="Peran bawaan sistem diproteksi">Terkunci</span>';
                }

                return UiTableHelper::actionGroup($buttons, 'end');
            })
            ->rawColumns(['role_badge', 'system_badge', 'permissions_summary', 'users_count_badge', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('role.manage');
        $catalog = $this->permissionCatalogService->getGroupedCatalog();
        $activePermissions = [];

        return view('roles.create', compact('catalog', 'activePermissions'));
    }

    public function store(RoleStoreRequest $request): RedirectResponse
    {
        $this->authorize('role.manage');

        $validated = $request->validated();
        $permissions = $request->input('permissions', []);

        $this->roleService->createRole($validated, $permissions);

        return redirect()->route('roles.index')->with('success', 'Peran baru berhasil dibuat dengan konfigurasi wewenang yang dipilih.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('role.manage');

        $role->load('permissions:id,name');
        $activePermissions = $role->permissions->pluck('name')->toArray();
        $catalog = $this->permissionCatalogService->getGroupedCatalog();

        return view('roles.edit', compact('role', 'catalog', 'activePermissions'));
    }

    public function update(RoleUpdateRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('role.manage');

        $validated = $request->validated();
        $permissions = $request->input('permissions', []);

        $this->roleService->updateRole($role, $validated, $permissions);

        return redirect()->route('roles.index')->with('success', "Peran '{$role->display_name}' berhasil diperbarui.");
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('role.manage');

        try {
            $this->roleService->deleteRole($role);
            return response()->json([
                'success' => true,
                'message' => "Peran '{$role->display_name}' berhasil dihapus.",
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menghapus peran.',
            ], 500);
        }
    }
}

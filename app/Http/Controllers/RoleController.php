<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleStoreRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Models\Role;
use App\Services\Authorization\PermissionCatalogService;
use App\Services\Authorization\RoleService;
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
                            <div class="font-semibold text-slate-800 text-sm leading-tight">' . $displayName . '</div>
                            <div class="text-xs text-slate-400 font-mono tracking-tight mt-0.5">' . $keyName . '</div>
                        </div>';
            })
            ->addColumn('system_badge', function (Role $role) {
                if ($role->is_system) {
                    return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200/80">
                                <svg class="w-3 h-3 mr-1 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                                Sistem
                            </span>';
                }
                return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200/80">
                            Kustom
                        </span>';
            })
            ->addColumn('permissions_summary', function (Role $role) {
                $count = $role->permissions->count();
                return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                            ' . $count . ' wewenang
                        </span>';
            })
            ->addColumn('users_count_badge', function (Role $role) {
                return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . ($role->users_count > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' : 'bg-slate-50 text-slate-500 border border-slate-200/60') . '">
                            ' . $role->users_count . ' pengguna
                        </span>';
            })
            ->addColumn('action', function (Role $role) {
                $editUrl = route('roles.edit', $role);
                $actions = '<div class="flex items-center gap-2.5 justify-end">';
                $actions .= '<a href="' . $editUrl . '" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:text-primary-900 bg-primary-50/80 hover:bg-primary-100 px-2.5 py-1 rounded transition border border-primary-200/50">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Atur Izin
                            </a>';

                if (!$role->is_system) {
                    $actions .= '<button type="button" onclick="deleteRole(' . $role->id . ', \'' . addslashes(e($role->display_name ?: $role->name)) . '\')" class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-800 bg-red-50/80 hover:bg-red-100 px-2.5 py-1 rounded transition border border-red-200/50">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                    Hapus
                                </button>';
                } else {
                    $actions .= '<span class="inline-flex items-center text-xs text-slate-400 font-medium px-2 py-1 italic cursor-not-allowed" title="Peran bawaan sistem diproteksi">
                                    Terkunci
                                </span>';
                }

                $actions .= '</div>';
                return $actions;
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

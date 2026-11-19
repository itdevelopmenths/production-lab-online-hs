<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\Divisi;
use App\Models\Gudang;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionCatalogService;
use App\Support\UiTableHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function __construct(
        protected PermissionCatalogService $permissionCatalogService
    ) {}

    public function index(): View
    {
        $this->authorize('user.manage');

        $divisiList = User::DIVISI_LIST;
        $divisis = Divisi::orderBy('nama')->get();
        $roles = Role::orderBy('is_system', 'desc')->orderBy('name')->get();

        return view('users.index', compact('divisiList', 'divisis', 'roles'));
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('user.manage');

        $query = User::query()
            ->select('users.*')
            ->with([
                'roles:id,name,display_name',
                'gudangs:id,kode,nama,tipe',
                'permissions:id,name',
                'divisiRelation:id,kode,nama,color',
            ]);

        if ($request->filled('divisi')) {
            $divisiVal = $request->divisi;
            $query->where(function ($q) use ($divisiVal) {
                $q->where('divisi', $divisiVal);
                if (is_numeric($divisiVal)) {
                    $q->orWhere('divisi_id', (int) $divisiVal);
                } else {
                    $q->orWhereHas('divisiRelation', fn ($sub) => $sub->where('kode', $divisiVal));
                }
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->role));
        }

        return DataTables::eloquent($query)
            ->addColumn('divisi_badge', function (User $u) {
                if (empty($u->divisi) && empty($u->divisi_id)) {
                    return '<span class="text-text-muted text-xs italic">-</span>';
                }

                $colorMap = [
                    'purchasing' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200/80 dark:border-amber-500/20',
                    'produksi' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200/80 dark:border-emerald-500/20',
                    'gudang' => 'bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-200/80 dark:border-blue-500/20',
                    'fulfillment' => 'bg-purple-50 dark:bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-200/80 dark:border-purple-500/20',
                    'qc' => 'bg-teal-50 dark:bg-teal-500/10 text-teal-700 dark:text-teal-400 border-teal-200/80 dark:border-teal-500/20',
                    'finance' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-200/80 dark:border-rose-500/20',
                    'manajemen' => 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border-indigo-200/80 dark:border-indigo-500/20',
                ];

                $cls = $u->divisiRelation ? $u->divisiRelation->badgeClass() : ($colorMap[$u->divisi] ?? 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700');
                $label = e($u->divisiLabel());

                return '<span class="inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-[5px] text-xs font-semibold border ' . $cls . '">' . $label . '</span>';
            })
            ->addColumn('roles_label', function (User $u) {
                $badges = $u->roles->map(function ($r) {
                    $displayName = e($r->display_name ?: $r->name);
                    return '<span class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 bg-gray-100 dark:bg-gray-800 text-[#101828] dark:text-gray-200 border border-[#E8E8E8] dark:border-gray-700 text-xs font-medium rounded-full">' . $displayName . '</span>';
                })->implode('');

                $customCount = $u->permissions->count();
                if ($customCount > 0) {
                    $badges .= '<span class="inline-flex items-center whitespace-nowrap px-2 py-0.5 bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20 text-[10px] font-semibold rounded-full" title="' . $customCount . ' wewenang khusus ditambahkan">+' . $customCount . ' izin khusus</span>';
                }

                return '<div class="flex flex-wrap items-center gap-1">' . $badges . '</div>';
            })
            ->addColumn('gudang_access_badge', function (User $u) {
                if ($u->hasRole('manager') || $u->can('stok.view.all') || $u->warehouse_access_type === 'global' || ($u->warehouse_access_type === null && $u->gudangs->isEmpty())) {
                    return '<span class="inline-flex items-center gap-1 whitespace-nowrap px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200/80 dark:border-emerald-500/20">
                                <svg class="w-3 h-3 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                                </svg>
                                Semua Gudang
                            </span>';
                }

                $html = '<div class="flex flex-wrap gap-1 items-center">';
                $firstTwo = $u->gudangs->take(2);
                foreach ($firstTwo as $g) {
                    $isPrimary = $g->pivot->is_primary;
                    $html .= '<span class="inline-flex items-center whitespace-nowrap px-1.5 py-0.5 rounded-[5px] text-[11px] font-medium ' . ($isPrimary ? 'bg-gray-100 dark:bg-gray-800 text-[#101828] dark:text-white border border-[#E8E8E8] dark:border-border' : 'bg-gray-50 dark:bg-gray-800/60 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700') . '">';
                    if ($isPrimary) {
                        $html .= '<svg class="w-2.5 h-2.5 mr-0.5 shrink-0 text-amber-500 fill-amber-400" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
                    }
                    $html .= e($g->nama) . '</span>';
                }

                $remaining = $u->gudangs->count() - 2;
                if ($remaining > 0) {
                    $html .= '<span class="inline-flex items-center whitespace-nowrap px-1.5 py-0.5 rounded-[5px] text-[10px] font-medium bg-gray-100 dark:bg-gray-800 text-text-muted">+' . $remaining . '</span>';
                }
                $html .= '</div>';

                return $html;
            })
            ->addColumn('action', function (User $u) {
                return UiTableHelper::actionGroup([
                    UiTableHelper::actionLink(route('users.edit', $u), 'Edit', 'pencil', 'default'),
                    UiTableHelper::actionButton("deleteUser(" . $u->id . ", '" . addslashes(e($u->name)) . "')", 'Hapus', 'trash', 'danger'),
                ], 'end');
            })
            ->editColumn('created_at', fn ($u) => $u->created_at?->format('d/m/Y'))
            ->rawColumns(['divisi_badge', 'roles_label', 'gudang_access_badge', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('user.manage');

        $roles = Role::orderBy('is_system', 'desc')->orderBy('name')->get();
        $gudangs = Gudang::active()->orderBy('tipe')->orderBy('nama')->get();
        $divisiList = User::DIVISI_LIST;
        $divisis = Divisi::active()->orderBy('nama')->get();
        $catalog = $this->permissionCatalogService->getGroupedCatalog();
        $assignedGudangIds = [];
        $activeDirectPermissions = [];
        $isGlobalWarehouse = true;
        $primaryGudangId = null;

        return view('users.create', compact(
            'roles',
            'gudangs',
            'divisiList',
            'divisis',
            'catalog',
            'assignedGudangIds',
            'activeDirectPermissions',
            'isGlobalWarehouse',
            'primaryGudangId'
        ));
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $this->authorize('user.manage');

        $data = $request->validated();

        $divisiId = $data['divisi_id'] ?? null;
        $divisiKode = $data['divisi'] ?? null;
        if ($divisiId) {
            $divisiModel = Divisi::find($divisiId);
            if ($divisiModel) {
                $divisiKode = $divisiModel->kode;
            }
        } elseif (!empty($divisiKode)) {
            $divisiModel = Divisi::where('kode', $divisiKode)->first();
            $divisiId = $divisiModel?->id;
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'divisi' => $divisiKode,
            'divisi_id' => $divisiId,
            'warehouse_access_type' => $data['warehouse_access_type'] ?? 'global',
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($data['role']);

        $this->syncWarehouseAccess($user, $request);
        $this->syncDirectPermissions($user, $request);

        return redirect()->route('users.index')->with('success', 'User berhasil ditambahkan dengan konfigurasi hak akses.');
    }

    public function edit(User $user): View
    {
        $this->authorize('user.manage');

        $user->load(['roles:id,name,display_name', 'gudangs', 'permissions:id,name', 'divisiRelation']);

        $roles = Role::orderBy('is_system', 'desc')->orderBy('name')->get();
        $gudangs = Gudang::active()->orderBy('tipe')->orderBy('nama')->get();
        $divisiList = User::DIVISI_LIST;
        $divisis = Divisi::active()->orderBy('nama')->get();
        $catalog = $this->permissionCatalogService->getGroupedCatalog();

        $activeDirectPermissions = $user->permissions->pluck('name')->toArray();
        $assignedGudangIds = $user->gudangs->pluck('id')->toArray();
        $primaryGudangId = $user->primaryGudang()?->id;
        $isGlobalWarehouse = $user->warehouse_access_type === 'global' || ($user->warehouse_access_type === null && $user->gudangs->isEmpty());

        return view('users.edit', compact(
            'user',
            'roles',
            'gudangs',
            'divisiList',
            'divisis',
            'catalog',
            'activeDirectPermissions',
            'assignedGudangIds',
            'primaryGudangId',
            'isGlobalWarehouse'
        ));
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->authorize('user.manage');

        $data = $request->validated();

        $divisiId = $data['divisi_id'] ?? null;
        $divisiKode = $data['divisi'] ?? null;
        if ($divisiId) {
            $divisiModel = Divisi::find($divisiId);
            if ($divisiModel) {
                $divisiKode = $divisiModel->kode;
            }
        } elseif (!empty($divisiKode)) {
            $divisiModel = Divisi::where('kode', $divisiKode)->first();
            $divisiId = $divisiModel?->id;
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'divisi' => $divisiKode,
            'divisi_id' => $divisiId,
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        $user->syncRoles([$data['role']]);

        $this->syncWarehouseAccess($user, $request);
        $this->syncDirectPermissions($user, $request);

        return redirect()->route('users.index')->with('success', "Data pengguna '{$user->name}' berhasil diperbarui.");
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('user.manage');

        if (auth()->id() === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "Pengguna '{$user->name}' berhasil dihapus.",
        ]);
    }

    protected function syncWarehouseAccess(User $user, Request $request): void
    {
        $accessType = $request->input('warehouse_access_type', 'global');
        $user->update(['warehouse_access_type' => $accessType]);

        if ($accessType === 'restricted' && ! empty($request->input('gudang_ids'))) {
            $syncData = [];
            $primaryId = (int) $request->input('primary_gudang_id');
            $gudangIds = array_map('intval', (array) $request->input('gudang_ids'));

            foreach ($gudangIds as $idx => $gid) {
                $syncData[$gid] = [
                    'is_primary' => $primaryId ? ($gid === $primaryId) : ($idx === 0),
                ];
            }
            $user->gudangs()->sync($syncData);
        } else {
            // Mode global / tanpa pembatasan lokasi
            $user->gudangs()->detach();
        }
    }

    protected function syncDirectPermissions(User $user, Request $request): void
    {
        $permissions = $request->input('permissions', []);
        $permList = is_array($permissions) ? $permissions : [];
        if (!empty($permList)) {
            $this->permissionCatalogService->ensurePermissionsExist($permList);
        }
        $user->syncPermissions($permList);
    }
}

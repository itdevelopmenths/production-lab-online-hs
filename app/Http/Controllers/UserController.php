<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\Divisi;
use App\Models\Gudang;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionCatalogService;
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
                    return '<span class="text-slate-400 text-xs italic">-</span>';
                }

                $colorMap = [
                    'purchasing' => 'bg-amber-50 text-amber-700 border-amber-200/80',
                    'produksi' => 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
                    'gudang' => 'bg-blue-50 text-blue-700 border-blue-200/80',
                    'fulfillment' => 'bg-purple-50 text-purple-700 border-purple-200/80',
                    'qc' => 'bg-teal-50 text-teal-700 border-teal-200/80',
                    'finance' => 'bg-rose-50 text-rose-700 border-rose-200/80',
                    'manajemen' => 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
                ];

                $cls = $u->divisiRelation ? $u->divisiRelation->badgeClass() : ($colorMap[$u->divisi] ?? 'bg-slate-50 text-slate-700 border-slate-200/80');
                $label = e($u->divisiLabel());

                return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border ' . $cls . '">' . $label . '</span>';
            })
            ->addColumn('roles_label', function (User $u) {
                $badges = $u->roles->map(function ($r) {
                    $displayName = e($r->display_name ?: $r->name);
                    return '<span class="px-2 py-0.5 bg-primary-50 text-primary-700 border border-primary-200/70 text-xs font-medium rounded-full">' . $displayName . '</span>';
                })->implode(' ');

                $customCount = $u->permissions->count();
                if ($customCount > 0) {
                    $badges .= ' <span class="px-1.5 py-0.5 bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-semibold rounded-full" title="' . $customCount . ' wewenang khusus ditambahkan">+' . $customCount . ' izin khusus</span>';
                }

                return $badges;
            })
            ->addColumn('gudang_access_badge', function (User $u) {
                if ($u->hasRole('manager') || $u->can('stok.view.all') || $u->warehouse_access_type === 'global' || ($u->warehouse_access_type === null && $u->gudangs->isEmpty())) {
                    return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                <svg class="w-3 h-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                                </svg>
                                Semua Gudang
                            </span>';
                }

                $html = '<div class="flex flex-wrap gap-1 items-center">';
                $firstTwo = $u->gudangs->take(2);
                foreach ($firstTwo as $g) {
                    $isPrimary = $g->pivot->is_primary;
                    $html .= '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium ' . ($isPrimary ? 'bg-primary-50 text-primary-800 border border-primary-200' : 'bg-slate-100 text-slate-700 border border-slate-200') . '">';
                    if ($isPrimary) {
                        $html .= '<svg class="w-2.5 h-2.5 mr-0.5 text-amber-500 fill-amber-400" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
                    }
                    $html .= e($g->nama) . '</span>';
                }

                $remaining = $u->gudangs->count() - 2;
                if ($remaining > 0) {
                    $html .= '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-500">+' . $remaining . '</span>';
                }
                $html .= '</div>';

                return $html;
            })
            ->addColumn('action', function (User $u) {
                return '<div class="flex items-center gap-2.5 justify-end">
                            <a href="' . route('users.edit', $u) . '" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-700 hover:text-primary-900 bg-primary-50/80 hover:bg-primary-100 px-2.5 py-1 rounded transition border border-primary-200/50">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Edit
                            </a>
                            <button type="button" onclick="deleteUser(' . $u->id . ', \'' . addslashes(e($u->name)) . '\')" class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-800 bg-red-50/80 hover:bg-red-100 px-2.5 py-1 rounded transition border border-red-200/50">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                                Hapus
                            </button>
                        </div>';
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
        $user->syncPermissions(is_array($permissions) ? $permissions : []);
    }
}

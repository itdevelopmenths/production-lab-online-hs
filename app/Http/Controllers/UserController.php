<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('user.manage');
        return view('users.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('user.manage');

        $query = User::query()->select('users.*')->with('roles:id,name,display_name');

        return DataTables::eloquent($query)
            ->addColumn('roles_label', function ($u) {
                return $u->roles->map(fn($r) => '<span class="px-2 py-0.5 bg-primary-50 text-primary-700 text-xs font-medium rounded-full">'.e($r->display_name ?: $r->name).'</span>')->implode(' ');
            })
            ->addColumn('action', function ($u) {
                return '<div class="flex items-center gap-3">
                            <a href="'.route('users.edit', $u).'" class="text-primary-600 hover:text-primary-800 text-xs font-medium">Edit</a>
                            <button onclick="deleteUser('.$u->id.')" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                        </div>';
            })
            ->editColumn('created_at', fn ($u) => $u->created_at?->format('d/m/Y'))
            ->rawColumns(['roles_label', 'action'])
            ->toJson();
    }

    public function create()
    {
        $this->authorize('user.manage');
        $roles = Role::orderBy('is_system', 'desc')->orderBy('name')->get();
        return view('users.create', compact('roles'));
    }

    public function store(UserStoreRequest $request)
    {
        $this->authorize('user.manage');

        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($data['role']);

        return redirect()->route('users.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $this->authorize('user.manage');
        $roles = Role::orderBy('is_system', 'desc')->orderBy('name')->get();
        $user->load('roles:id,name,display_name');
        return view('users.edit', compact('user', 'roles'));
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        $this->authorize('user.manage');

        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (!empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        $this->authorize('user.manage');
        $user->delete();
        return response()->json(['message' => 'User berhasil dihapus.']);
    }
}

<?php

namespace App\Services\Authorization;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    /**
     * Membuat custom role baru dan menghubungkannya dengan wewenang yang dipilih.
     *
     * @param array{name: string, display_name?: string, description?: string} $data
     * @param array<int, string> $permissionNames
     */
    public function createRole(array $data, array $permissionNames = []): Role
    {
        return DB::transaction(function () use ($data, $permissionNames) {
            $slugName = Str::slug($data['name'], '_');

            $role = Role::create([
                'name' => $slugName,
                'guard_name' => 'web',
                'display_name' => $data['display_name'] ?? ucwords(str_replace(['_', '-'], ' ', $data['name'])),
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);

            if (!empty($permissionNames)) {
                $role->syncPermissions($permissionNames);
            }

            // Invalidate Spatie cached permissions
            app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    /**
     * Memperbarui informasi peran dan menyinkronkan wewenang.
     *
     * @param Role $role
     * @param array{name?: string, display_name?: string, description?: string} $data
     * @param array<int, string> $permissionNames
     */
    public function updateRole(Role $role, array $data, array $permissionNames = []): Role
    {
        return DB::transaction(function () use ($role, $data, $permissionNames) {
            $updateData = [
                'display_name' => $data['display_name'] ?? $role->display_name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $role->description,
            ];

            // Identitas teknis (name) peran bawaan sistem tidak boleh diubah untuk mencegah broken references
            if (!$role->is_system && !empty($data['name'])) {
                $updateData['name'] = Str::slug($data['name'], '_');
            }

            $role->update($updateData);
            $role->syncPermissions($permissionNames);

            // Invalidate Spatie cached permissions
            app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    /**
     * Menghapus peran kustom jika aman (bukan system role dan tidak ada user aktif yang terikat).
     *
     * @throws \DomainException
     */
    public function deleteRole(Role $role): void
    {
        if ($role->is_system) {
            throw new \DomainException("Peran sistem bawaan ({$role->display_name}) diproteksi dan tidak dapat dihapus.");
        }

        $userCount = $role->users()->count();
        if ($userCount > 0) {
            throw new \DomainException("Peran '{$role->display_name}' tidak dapat dihapus karena masih digunakan oleh {$userCount} pengguna aktif. Mohon alihkan peran pengguna terlebih dahulu.");
        }

        DB::transaction(function () use ($role) {
            $role->syncPermissions([]);
            $role->delete();

            // Invalidate Spatie cached permissions
            app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}

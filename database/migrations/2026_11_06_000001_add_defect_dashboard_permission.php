<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Buat permission 'defect.view'
        $permission = Permission::firstOrCreate([
            'name' => 'defect.view',
            'guard_name' => 'web',
        ]);

        // 2. Berikan izin secara default ke role yang berwenang
        // - Manager: audit menyeluruh & persetujuan biaya/kerusakan
        // - Gudang: penerimaan fisik & inspeksi QC unloading
        // - Operasional: monitoring defect batch & mutasi laboratorium
        // - Fulfillment: penerimaan produk jadi di hub distribusi
        $authorizedRoles = ['manager', 'gudang', 'operasional', 'fulfillment'];
        $roles = Role::with('permissions')->whereIn('name', $authorizedRoles)->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($permission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::where('name', 'defect.view')->first();
        if ($permission) {
            $permission->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

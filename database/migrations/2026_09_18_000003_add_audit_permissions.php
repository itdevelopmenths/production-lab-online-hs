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

        $auditPermissions = [
            'produk.audit',
            'gudang.audit',
            'supplier.audit',
            'uom.audit',
            'bom.audit',
            'divisi.audit',
            'purchasing.audit',
            'batch.audit',
            'rt.audit',
            'audit.view',
        ];

        foreach ($auditPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Berikan izin audit penuh ke role manager
        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo($auditPermissions);
        }

        // Berikan izin audit relevan ke role default lainnya
        $purchasingRole = Role::where('name', 'purchasing')->first();
        if ($purchasingRole) {
            $purchasingRole->givePermissionTo(['purchasing.audit', 'supplier.audit']);
        }

        $gudangRole = Role::where('name', 'gudang')->first();
        if ($gudangRole) {
            $gudangRole->givePermissionTo(['rt.audit', 'gudang.audit']);
        }

        $operasionalRole = Role::where('name', 'operasional')->first();
        if ($operasionalRole) {
            $operasionalRole->givePermissionTo(['batch.audit', 'bom.audit']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $auditPermissions = [
            'produk.audit',
            'gudang.audit',
            'supplier.audit',
            'uom.audit',
            'bom.audit',
            'divisi.audit',
            'purchasing.audit',
            'batch.audit',
            'rt.audit',
            'audit.view',
        ];

        Permission::whereIn('name', $auditPermissions)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

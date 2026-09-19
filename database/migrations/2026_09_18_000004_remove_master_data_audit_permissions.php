<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $masterDataAuditPermissions = [
            'produk.audit',
            'gudang.audit',
            'supplier.audit',
            'uom.audit',
            'bom.audit',
            'divisi.audit',
        ];

        Permission::whereIn('name', $masterDataAuditPermissions)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $masterDataAuditPermissions = [
            'produk.audit',
            'gudang.audit',
            'supplier.audit',
            'uom.audit',
            'bom.audit',
            'divisi.audit',
        ];

        foreach ($masterDataAuditPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo($masterDataAuditPermissions);
        }

        $purchasingRole = Role::where('name', 'purchasing')->first();
        if ($purchasingRole) {
            $purchasingRole->givePermissionTo(['supplier.audit']);
        }

        $gudangRole = Role::where('name', 'gudang')->first();
        if ($gudangRole) {
            $gudangRole->givePermissionTo(['gudang.audit']);
        }

        $operasionalRole = Role::where('name', 'operasional')->first();
        if ($operasionalRole) {
            $operasionalRole->givePermissionTo(['bom.audit']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

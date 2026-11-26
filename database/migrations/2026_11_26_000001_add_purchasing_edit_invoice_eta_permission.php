<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'purchasing.edit_invoice_eta';

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
        ]);

        // Default: role purchasing. Manager lolos lewat Gate::before; role lain diatur dari UI matriks peran.
        $purchasing = Role::where('name', 'purchasing')->first();
        $purchasing?->givePermissionTo($permission);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)->first()?->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

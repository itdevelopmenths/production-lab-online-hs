<?php

use App\Services\Authorization\PermissionCatalogService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        app(PermissionCatalogService::class)->syncCatalogToDatabase();

        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole) {
            $managerRole->syncPermissions(Permission::all());
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Sinkronisasi permissions katalog bersifat aditif dan aman; tidak perlu rollback destruktif.
    }
};


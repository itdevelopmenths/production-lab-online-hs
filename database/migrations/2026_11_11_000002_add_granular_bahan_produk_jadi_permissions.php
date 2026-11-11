<?php

use App\Services\Authorization\PermissionCatalogService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GRANULAR_KEYS = [
        'request.bahan.create',
        'request.produk_jadi.create',
        'transfer.bahan.create',
        'transfer.bahan.ship',
        'transfer.bahan.receive',
        'transfer.produk_jadi.create',
        'transfer.produk_jadi.ship',
        'transfer.produk_jadi.receive',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        app(PermissionCatalogService::class)->syncCatalogToDatabase();

        $this->migrateRolePermissions(
            'operasional',
            revoke: [
                'request.create', 'transfer.create', 'transfer.ship', 'transfer.receive', 'transfer.cancel',
                'transfer.bahan.create', 'transfer.bahan.ship',
                'rt.create', 'rt.ship', 'rt.receive', 'rt.cancel',
            ],
            grant: [
                'request.view', 'request.bahan.create', 'request.submit', 'request.cancel',
                'transfer.view', 'transfer.bahan.receive',
                'transfer.produk_jadi.create', 'transfer.produk_jadi.ship',
                'defect.view', 'rt.view', 'rt.submit',
            ]
        );

        $this->migrateRolePermissions(
            'gudang',
            revoke: [
                'transfer.create', 'transfer.ship', 'transfer.receive',
                'rt.create', 'rt.ship', 'rt.receive',
            ],
            grant: [
                'request.view', 'transfer.view', 'defect.view',
                'transfer.bahan.create', 'transfer.bahan.ship', 'transfer.bahan.receive',
                'transfer.produk_jadi.create', 'transfer.produk_jadi.ship', 'transfer.produk_jadi.receive',
                'rt.view', 'rt.submit', 'rt.process',
            ]
        );

        $this->migrateRolePermissions(
            'fulfillment',
            revoke: [
                'request.create', 'transfer.create', 'transfer.ship', 'transfer.receive',
                'rt.create', 'rt.ship', 'rt.receive',
            ],
            grant: [
                'request.view', 'request.produk_jadi.create', 'request.submit', 'request.cancel',
                'transfer.view', 'defect.view',
                'transfer.produk_jadi.create', 'transfer.produk_jadi.ship', 'transfer.produk_jadi.receive',
                'rt.view',
            ]
        );

        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole) {
            $managerRole->syncPermissions(Permission::all());
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()
            ->whereIn('name', self::GRANULAR_KEYS)
            ->each(static function (Permission $permission): void {
                $permission->delete();
            });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Perbarui izin modul Request & Transfer secara idempoten tanpa menghapus izin modul lain.
     *
     * @param  array<int, string>  $revoke
     * @param  array<int, string>  $grant
     */
    private function migrateRolePermissions(string $roleName, array $revoke, array $grant): void
    {
        $role = Role::where('name', $roleName)->first();
        if (! $role) {
            return;
        }

        $revokeModels = Permission::query()->whereIn('name', $revoke)->get();
        if ($revokeModels->isNotEmpty()) {
            $role->revokePermissionTo($revokeModels);
        }

        $grantModels = Permission::query()->whereIn('name', $grant)->get();
        if ($grantModels->isNotEmpty()) {
            $role->givePermissionTo($grantModels);
        }
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin per halaman yang sebelumnya menumpang izin modul:
 * Bahan Baku & Kemasan / Produk Jadi (dulu produk.*), Kategori & Varian (dulu produk.*),
 * Klasifikasi ABC (dulu analisa.*), Log Pergerakan Stok (dulu stok.view).
 *
 * Role yang sudah ada diberi izin granular setara dengan izin lamanya,
 * sehingga akses tidak berubah dan matriks role menampilkan centang yang benar.
 */
return new class extends Migration
{
    private function granularMap(): array
    {
        $map = [];
        foreach (['view', 'create', 'edit', 'delete'] as $aksi) {
            $map["produk.{$aksi}"] = ["produk.bahan.{$aksi}", "produk.produk_jadi.{$aksi}", "kategori.{$aksi}", "varian.{$aksi}"];
        }
        $map['analisa.view'] = ['klasifikasi_abc.view'];
        $map['analisa.manage'] = ['klasifikasi_abc.manage'];
        $map['stok.view'] = ['stok.log.view'];

        return $map;
    }

    private function newPermissions(): array
    {
        return array_values(array_unique(array_merge(...array_values($this->granularMap()))));
    }

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->newPermissions() as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        foreach (Role::with('permissions')->get() as $role) {
            $owned = $role->permissions->pluck('name')->all();
            $grant = [];
            foreach ($this->granularMap() as $lama => $baru) {
                if ($role->name === 'manager' || in_array($lama, $owned, true)) {
                    array_push($grant, ...$baru);
                }
            }
            if ($grant !== []) {
                $role->givePermissionTo(array_values(array_unique($grant)));
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::whereIn('name', $this->newPermissions())->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

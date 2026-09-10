<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Master Data
            'produk.view', 'produk.create', 'produk.edit', 'produk.delete',
            'gudang.view', 'gudang.create', 'gudang.edit', 'gudang.delete',
            'supplier.view', 'supplier.create', 'supplier.edit', 'supplier.delete',
            'bom.view', 'bom.manage', 'bom.import',

            // Stok & Mutasi
            'stok.view', 'stok.mutasi', 'stok.opname', 'stok.ledger.view',

            // Purchasing
            'purchasing.view', 'purchasing.create', 'purchasing.edit',
            'purchasing.submit', 'purchasing.approve', 'purchasing.receive',
            'purchasing.pay', 'purchasing.cancel',

            // Request & Transfer
            'rt.view', 'rt.create', 'rt.submit', 'rt.approve',
            'rt.process', 'rt.ship', 'rt.receive', 'rt.cancel',

            // Analisa Stok
            'analisa.view', 'analisa.manage', 'analisa.snapshot', 'analisa.create_po',

            // Produksi (Batch)
            'batch.view', 'batch.create', 'batch.release',
            'batch.complete', 'batch.cancel', 'batch.opname',

            // Laporan
            'report.view', 'report.export',

            // Admin
            'user.manage', 'role.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Manager — akses penuh + approval di semua tahap.
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions(Permission::all());

        // Purchasing
        Role::firstOrCreate(['name' => 'purchasing'])->syncPermissions([
            'produk.view', 'produk.create', 'produk.edit',
            'gudang.view', 'supplier.view', 'supplier.create', 'supplier.edit',
            'bom.view',
            'stok.view', 'stok.mutasi', 'stok.ledger.view',
            'purchasing.view', 'purchasing.create', 'purchasing.edit',
            'purchasing.submit', 'purchasing.pay', 'purchasing.cancel',
            'analisa.view', 'analisa.manage', 'analisa.snapshot', 'analisa.create_po',
            'report.view', 'report.export',
        ]);

        // Gudang
        Role::firstOrCreate(['name' => 'gudang'])->syncPermissions([
            'produk.view', 'gudang.view', 'supplier.view', 'bom.view',
            'stok.view', 'stok.mutasi', 'stok.ledger.view',
            'purchasing.view', 'purchasing.receive',
            'rt.view', 'rt.process', 'rt.ship', 'rt.receive',
            'report.view',
        ]);

        // Operasional
        Role::firstOrCreate(['name' => 'operasional'])->syncPermissions([
            'produk.view', 'gudang.view', 'bom.view', 'bom.manage',
            'stok.view', 'stok.opname', 'stok.ledger.view',
            'batch.view', 'batch.create', 'batch.release',
            'batch.complete', 'batch.cancel', 'batch.opname',
            'rt.view', 'rt.create', 'rt.submit', 'rt.ship', 'rt.receive',
            'report.view',
        ]);

        // Fulfillment
        Role::firstOrCreate(['name' => 'fulfillment'])->syncPermissions([
            'produk.view', 'gudang.view', 'bom.view',
            'stok.view', 'stok.mutasi', 'stok.ledger.view',
            'analisa.view', 'analisa.manage', 'analisa.snapshot',
            'rt.view', 'rt.create', 'rt.ship', 'rt.receive',
            'report.view',
        ]);
    }
}

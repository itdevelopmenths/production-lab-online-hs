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
            'uom.view', 'uom.create', 'uom.edit', 'uom.delete',
            'bom.view', 'bom.manage', 'bom.import',
            'divisi.view', 'divisi.create', 'divisi.edit', 'divisi.delete',

            // Stok & Mutasi
            'stok.view', 'stok.view.all', 'stok.mutasi', 'stok.opname', 'stok.ledger.view',

            // Purchasing
            'purchasing.view', 'purchasing.price.view', 'purchasing.create', 'purchasing.edit',
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
        $manager = \App\Models\Role::firstOrCreate(
            ['name' => 'manager'],
            ['guard_name' => 'web', 'display_name' => 'Manager (Administrator)', 'description' => 'Akses penuh ke seluruh modul sistem dan wewenang approval transaksi', 'is_system' => true]
        );
        $manager->update(['display_name' => 'Manager (Administrator)', 'description' => 'Akses penuh ke seluruh modul sistem dan wewenang approval transaksi', 'is_system' => true]);
        $manager->syncPermissions(Permission::all());

        // Purchasing
        $purchasing = \App\Models\Role::firstOrCreate(
            ['name' => 'purchasing'],
            ['guard_name' => 'web', 'display_name' => 'Staff Purchasing', 'description' => 'Pengadaan bahan baku, pembuatan PO, pembayaran, dan pemantauan analisa', 'is_system' => true]
        );
        $purchasing->update(['display_name' => 'Staff Purchasing', 'description' => 'Pengadaan bahan baku, pembuatan PO, pembayaran, dan pemantauan analisa', 'is_system' => true]);
        $purchasing->syncPermissions([
            'produk.view', 'produk.create', 'produk.edit',
            'gudang.view', 'supplier.view', 'supplier.create', 'supplier.edit',
            'uom.view', 'uom.create', 'uom.edit',
            'bom.view',
            'stok.view', 'stok.mutasi', 'stok.ledger.view',
            'purchasing.view', 'purchasing.price.view', 'purchasing.create', 'purchasing.edit',
            'purchasing.submit', 'purchasing.pay', 'purchasing.cancel',
            'analisa.view', 'analisa.manage', 'analisa.snapshot', 'analisa.create_po',
            'report.view', 'report.export',
        ]);

        // Gudang
        $gudang = \App\Models\Role::firstOrCreate(
            ['name' => 'gudang'],
            ['guard_name' => 'web', 'display_name' => 'Staff Gudang Pusat', 'description' => 'Penerimaan barang datang, mutasi stok, proses pengiriman transfer, dan kartu stok', 'is_system' => true]
        );
        $gudang->update(['display_name' => 'Staff Gudang Pusat', 'description' => 'Penerimaan barang datang, mutasi stok, proses pengiriman transfer, dan kartu stok', 'is_system' => true]);
        $gudang->syncPermissions([
            'produk.view', 'gudang.view', 'supplier.view', 'uom.view', 'bom.view',
            'stok.view', 'stok.mutasi', 'stok.ledger.view',
            'purchasing.view', 'purchasing.receive',
            'rt.view', 'rt.create', 'rt.submit', 'rt.process', 'rt.ship', 'rt.receive',
            'report.view',
        ]);

        // Operasional
        $operasional = \App\Models\Role::firstOrCreate(
            ['name' => 'operasional'],
            ['guard_name' => 'web', 'display_name' => 'Staff Operasional Lab', 'description' => 'Perencanaan & eksekusi batch produksi, permintaan bahan (RT), dan stock opname', 'is_system' => true]
        );
        $operasional->update(['display_name' => 'Staff Operasional Lab', 'description' => 'Perencanaan & eksekusi batch produksi, permintaan bahan (RT), dan stock opname', 'is_system' => true]);
        $operasional->syncPermissions([
            'produk.view', 'gudang.view', 'uom.view', 'bom.view', 'bom.manage',
            'stok.view', 'stok.opname', 'stok.ledger.view',
            'batch.view', 'batch.create', 'batch.release',
            'batch.complete', 'batch.cancel', 'batch.opname',
            'rt.view', 'rt.create', 'rt.submit', 'rt.ship', 'rt.receive',
            'report.view',
        ]);

        // Fulfillment
        $fulfillment = \App\Models\Role::firstOrCreate(
            ['name' => 'fulfillment'],
            ['guard_name' => 'web', 'display_name' => 'Staff Gudang Fulfillment', 'description' => 'Distribusi produk jadi, mutasi antar fulfillment, dan monitoring analisa', 'is_system' => true]
        );
        $fulfillment->update(['display_name' => 'Staff Gudang Fulfillment', 'description' => 'Distribusi produk jadi, mutasi antar fulfillment, dan monitoring analisa', 'is_system' => true]);
        $fulfillment->syncPermissions([
            'produk.view', 'gudang.view', 'uom.view', 'bom.view',
            'stok.view', 'stok.mutasi', 'stok.ledger.view',
            'analisa.view', 'analisa.manage', 'analisa.snapshot',
            'rt.view', 'rt.create', 'rt.ship', 'rt.receive',
            'report.view',
        ]);
    }
}

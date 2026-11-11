<?php

namespace Database\Seeders;

use App\Services\Authorization\PermissionCatalogService;
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
            'purchasing.pay', 'purchasing.cancel', 'purchasing.audit',

            // Request & Transfer (PRD v2.4 + legacy aliases)
            'request.view', 'request.create', 'request.submit', 'request.approve', 'request.cancel',
            'transfer.view', 'transfer.create', 'transfer.ship', 'transfer.receive', 'transfer.cancel',
            'defect.view',
            'rt.view', 'rt.create', 'rt.submit', 'rt.approve',
            'rt.process', 'rt.ship', 'rt.receive', 'rt.cancel', 'rt.audit',

            // Analisa Stok
            'analisa.view', 'analisa.manage', 'analisa.snapshot', 'analisa.create_po',

            // Produksi (Batch)
            'batch.view', 'batch.create', 'batch.release',
            'batch.complete', 'batch.cancel', 'batch.opname', 'batch.audit',

            // Laporan
            'report.view', 'reports.view', 'report.export', 'audit.view',

            // Admin
            'user.manage', 'role.manage',
        ];

        $allPermissions = array_values(array_unique(array_merge(
            $permissions,
            app(PermissionCatalogService::class)->getAllPermissionKeys()
        )));

        foreach ($allPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
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
            'request.view',
            'transfer.view',
            'transfer.bahan.create', 'transfer.bahan.ship', 'transfer.bahan.receive',
            'transfer.produk_jadi.create', 'transfer.produk_jadi.ship', 'transfer.produk_jadi.receive',
            'defect.view',
            'rt.view', 'rt.submit', 'rt.process',
            'report.view',
        ]);

        // Operasional
        $operasional = \App\Models\Role::firstOrCreate(
            ['name' => 'operasional'],
            ['guard_name' => 'web', 'display_name' => 'Staff Operasional Lab', 'description' => 'Perencanaan & eksekusi batch produksi, permintaan bahan baku, penerimaan bahan, pengiriman produk jadi, dan stock opname', 'is_system' => true]
        );
        $operasional->update(['display_name' => 'Staff Operasional Lab', 'description' => 'Perencanaan & eksekusi batch produksi, permintaan bahan baku, penerimaan bahan, pengiriman produk jadi, dan stock opname', 'is_system' => true]);
        $operasional->syncPermissions([
            'produk.view', 'gudang.view', 'uom.view', 'bom.view', 'bom.manage',
            'stok.view', 'stok.opname', 'stok.ledger.view',
            'batch.view', 'batch.create', 'batch.release',
            'batch.complete', 'batch.cancel', 'batch.opname',
            'request.view', 'request.bahan.create', 'request.submit', 'request.cancel',
            'transfer.view', 'transfer.bahan.receive',
            'transfer.produk_jadi.create', 'transfer.produk_jadi.ship',
            'defect.view',
            'rt.view', 'rt.submit',
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
            'request.view', 'request.produk_jadi.create', 'request.submit', 'request.cancel',
            'transfer.view',
            'transfer.produk_jadi.create', 'transfer.produk_jadi.ship', 'transfer.produk_jadi.receive',
            'defect.view',
            'rt.view',
            'report.view',
        ]);
    }
}

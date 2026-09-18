<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\OperationalAudit;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\RequestTransfer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $perms = [
            'purchasing.view', 'purchasing.create', 'purchasing.approve', 'purchasing.audit',
            'batch.view', 'batch.create', 'batch.audit',
            'rt.view', 'rt.create', 'rt.audit',
            'produk.view', 'produk.audit',
            'uom.view', 'uom.audit',
            'supplier.view', 'supplier.audit',
            'gudang.view', 'gudang.audit',
            'bom.view', 'bom.audit',
            'divisi.view', 'divisi.audit',
            'audit.view',
        ];
        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $role->syncPermissions(Permission::all());

        $this->adminUser = User::factory()->create(['name' => 'Admin Test Audit']);
        $this->adminUser->assignRole($role);
    }

    public function test_purchase_order_creation_triggers_operational_audit(): void
    {
        $this->actingAs($this->adminUser);

        $supplier = Supplier::create([
            'nama' => 'PT Supplier Utama',
            'kode' => 'SUP-001',
            'kategori' => 'lokal',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-0001',
            'supplier_id' => $supplier->id,
            'tanggal' => now(),
            'status' => 'draft',
            'created_by' => $this->adminUser->id,
        ]);

        $audit = OperationalAudit::where('auditable_type', PurchaseOrder::class)
            ->where('auditable_id', $po->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('purchasing', $audit->module);
        $this->assertEquals('PO-TEST-0001', $audit->nomor_referensi);
        $this->assertEquals('created', $audit->event);
        $this->assertEquals($this->adminUser->name, $audit->user_name);
    }

    public function test_purchase_order_status_update_records_audit(): void
    {
        $this->actingAs($this->adminUser);

        $supplier = Supplier::create([
            'nama' => 'PT Vendor Logistik',
            'kode' => 'SUP-002',
            'kategori' => 'lokal',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-0002',
            'supplier_id' => $supplier->id,
            'tanggal' => now(),
            'status' => 'draft',
            'created_by' => $this->adminUser->id,
        ]);

        $po->update(['status' => 'diajukan']);

        $auditUpdate = OperationalAudit::where('auditable_type', PurchaseOrder::class)
            ->where('auditable_id', $po->id)
            ->where('event', 'submitted')
            ->first();

        $this->assertNotNull($auditUpdate);
        $this->assertStringContainsString('Perubahan status', $auditUpdate->action_title);
    }

    public function test_batch_produksi_creation_triggers_operational_audit(): void
    {
        $this->actingAs($this->adminUser);

        $gudang = Gudang::create([
            'kode' => 'GD-LAB',
            'nama' => 'Gudang Lab',
            'tipe' => 'operasional',
            'status' => 'aktif',
        ]);

        $produk = Produk::create([
            'sku' => 'PRD-TEST-01',
            'nama' => 'Parfum Lavender 50ml',
            'tipe' => 'produk_jadi',
            'satuan' => 'pcs',
            'status' => 'aktif',
        ]);

        $batch = BatchProduksi::create([
            'no_batch' => 'BATCH-TEST-0001',
            'produk_id' => $produk->id,
            'qty_rencana' => 100,
            'gudang_operasional_id' => $gudang->id,
            'status' => 'rencana',
            'tanggal' => now(),
            'created_by' => $this->adminUser->id,
        ]);

        $audit = OperationalAudit::where('auditable_type', BatchProduksi::class)
            ->where('auditable_id', $batch->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('produksi', $audit->module);
        $this->assertEquals('BATCH-TEST-0001', $audit->nomor_referensi);
        $this->assertEquals('created', $audit->event);
    }

    public function test_request_transfer_creation_triggers_operational_audit(): void
    {
        $this->actingAs($this->adminUser);

        $rt = RequestTransfer::create([
            'no_transaksi' => 'RT-TEST-0001',
            'jenis' => 'req_bahan',
            'status' => 'draft',
            'created_by' => $this->adminUser->id,
        ]);

        $audit = OperationalAudit::where('auditable_type', RequestTransfer::class)
            ->where('auditable_id', $rt->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('request', $audit->module);
        $this->assertEquals('RT-TEST-0001', $audit->nomor_referensi);
    }

    public function test_operational_audit_datatables_endpoint(): void
    {
        $this->actingAs($this->adminUser);

        $supplier = Supplier::create([
            'nama' => 'PT Supplier DataTables',
            'kode' => 'SUP-003',
            'kategori' => 'lokal',
            'is_active' => true,
        ]);

        PurchaseOrder::create([
            'no_po' => 'PO-TEST-DATA',
            'supplier_id' => $supplier->id,
            'tanggal' => now(),
            'status' => 'draft',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->getJson(route('operational-audit.data', ['module' => 'purchasing']));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                '*' => [
                    'waktu',
                    'nomor_referensi',
                    'module_badge',
                    'event_badge',
                    'rincian',
                    'pelaksana',
                    'ip_address',
                ]
            ]
        ]);
    }

    public function test_user_without_audit_permission_is_forbidden_to_access_audit_data(): void
    {
        // User yang hanya memiliki izin view, tapi tidak memiliki izin audit
        $regularRole = Role::firstOrCreate(['name' => 'regular_staff', 'guard_name' => 'web']);
        $regularRole->syncPermissions(['purchasing.view', 'batch.view', 'rt.view']);

        $staffUser = User::factory()->create(['name' => 'Staff Biasa']);
        $staffUser->assignRole($regularRole);

        $this->actingAs($staffUser);

        // Harus mendapatkan HTTP 403 Forbidden
        $responsePurchasing = $this->getJson(route('operational-audit.data', ['module' => 'purchasing']));
        $responsePurchasing->assertStatus(403);

        $responseBatch = $this->getJson(route('operational-audit.data', ['module' => 'produksi']));
        $responseBatch->assertStatus(403);

        $responseRt = $this->getJson(route('operational-audit.data', ['module' => 'request_transfer']));
        $responseRt->assertStatus(403);
    }

    public function test_master_data_audit_endpoint_enforces_audit_permission(): void
    {
        $noAuditRole = Role::firstOrCreate(['name' => 'no_audit_staff', 'guard_name' => 'web']);
        $noAuditRole->syncPermissions(['produk.view', 'uom.view', 'supplier.view']);

        $staffUser = User::factory()->create(['name' => 'Staff Master Biasa']);
        $staffUser->assignRole($noAuditRole);

        $this->actingAs($staffUser);

        // Akses audit master data ditolak jika tidak punya *.audit
        $responseProduk = $this->getJson(route('master-audit.data', ['entity' => 'produk']));
        $responseProduk->assertStatus(403);

        $responseUom = $this->getJson(route('master-audit.data', ['entity' => 'uom']));
        $responseUom->assertStatus(403);

        // Setelah diberi izin uom.audit, akses diizinkan
        $noAuditRole->givePermissionTo('uom.audit');
        $responseUomAllowed = $this->getJson(route('master-audit.data', ['entity' => 'uom']));
        $responseUomAllowed->assertStatus(200);
    }
}

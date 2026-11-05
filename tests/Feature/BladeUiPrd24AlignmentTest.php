<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\BatchProduksiOutput;
use App\Models\Gudang;
use App\Models\PoLeadTime;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Stok;
use App\Models\Supplier;
use App\Models\SupplierLtStageSummary;
use App\Models\SupplierLtSummary;
use App\Models\SupplierProdukLtSummary;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BladeUiPrd24AlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed();
    }

    /**
     * TC-01: Kolom Rencana di GET /stok/data menghitung output 2..N dari batch_produksi_outputs
     * dan tetap mendukung fallback untuk batch legacy tunggal.
     */
    public function test_kolom_rencana_in_stok_data_counts_multi_product_outputs_and_legacy_fallback(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $gudang = Gudang::firstOrCreate(
            ['kode' => 'GD-TEST-PROD'],
            ['nama' => 'Gudang Produksi Test', 'tipe' => 'operasional', 'is_active' => true]
        );

        // Produk A & B (keduanya produk_jadi)
        $produkA = Produk::firstOrCreate(
            ['sku' => 'PRD-MULTI-01'],
            ['nama' => 'Produk Multi A', 'tipe' => 'produk_jadi', 'satuan' => 'Pcs', 'is_active' => true, 'harga_hpp' => 10000]
        );
        $produkB = Produk::firstOrCreate(
            ['sku' => 'PRD-MULTI-02'],
            ['nama' => 'Produk Multi B', 'tipe' => 'produk_jadi', 'satuan' => 'Pcs', 'is_active' => true, 'harga_hpp' => 12000]
        );

        // Saldo fisik awal = 10 masing-masing
        Stok::updateOrCreate(['produk_id' => $produkA->id, 'gudang_id' => $gudang->id], ['qty_saat_ini' => 10]);
        Stok::updateOrCreate(['produk_id' => $produkB->id, 'gudang_id' => $gudang->id], ['qty_saat_ini' => 10]);

        // Batch 1: Multi-produk (outputs: A => 150, B => 250)
        $batchMulti = BatchProduksi::create([
            'no_batch' => 'BATCH-TEST-MULTI',
            'produk_id' => $produkA->id,
            'qty_rencana' => 400,
            'gudang_operasional_id' => $gudang->id,
            'gudang_tujuan_rencana_id' => $gudang->id,
            'status' => 'release',
            'tanggal' => now()->toDateString(),
            'created_by' => $manager->id,
        ]);

        BatchProduksiOutput::create([
            'batch_id' => $batchMulti->id,
            'produk_id' => $produkA->id,
            'qty_rencana' => 150,
            'gudang_tujuan_rencana_id' => $gudang->id,
        ]);

        BatchProduksiOutput::create([
            'batch_id' => $batchMulti->id,
            'produk_id' => $produkB->id,
            'qty_rencana' => 250,
            'gudang_tujuan_rencana_id' => $gudang->id,
        ]);

        // Batch 2: Legacy single-product tanpa baris outputs (fallback ke bp.produk_id & bp.qty_rencana => A => 50)
        BatchProduksi::create([
            'no_batch' => 'BATCH-TEST-LEGACY',
            'produk_id' => $produkA->id,
            'qty_rencana' => 50,
            'gudang_operasional_id' => $gudang->id,
            'gudang_tujuan_rencana_id' => $gudang->id,
            'status' => 'rencana',
            'tanggal' => now()->toDateString(),
            'created_by' => $manager->id,
        ]);

        $response = $this->getJson(route('stok.data', ['gudang_id' => $gudang->id]));
        $response->assertOk();

        $rows = collect($response->json('data'));

        // Cek Produk A: fisik 10 + multi output 150 + legacy 50 = 210
        $rowA = $rows->firstWhere('sku', 'PRD-MULTI-01');
        $this->assertNotNull($rowA, 'Baris Produk A harus ditemukan di stok.data');
        $this->assertStringContainsString('210', $rowA['kolom_rencana']);

        // Cek Produk B: fisik 10 + multi output 250 = 260
        $rowB = $rows->firstWhere('sku', 'PRD-MULTI-02');
        $this->assertNotNull($rowB, 'Baris Produk B harus ditemukan di stok.data');
        $this->assertStringContainsString('260', $rowB['kolom_rencana']);
    }

    /**
     * TC-02: GET /laporan/lead-time merender ringkasan Level-1 & Level-2 serta histori PO Lead Time.
     */
    public function test_laporan_lead_time_renders_level_1_level_2_and_history(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $supplier = Supplier::firstOrCreate(
            ['nama' => 'PT Harum Semerbak Abadi'],
            ['kategori' => 'lokal', 'is_active' => true]
        );

        $produk = Produk::firstOrCreate(
            ['sku' => 'BHK-LT-01'],
            ['nama' => 'Bahan Baku Aroma Test', 'tipe' => 'bahan', 'satuan' => 'Kg', 'is_active' => true]
        );

        // Level-1 Summary
        SupplierLtSummary::updateOrCreate(
            ['supplier_id' => $supplier->id],
            [
                'jumlah_transaksi' => 5,
                'total_lt_menit' => 7200, // 5 hari total (avg 1 hari)
                'average_lt_hari' => 1.0,
                'max_lt_hari' => 2.5,
                'diperbarui_pada' => now(),
            ]
        );

        // 9-Stage Breakdown
        SupplierLtStageSummary::updateOrCreate(
            ['supplier_id' => $supplier->id, 'stage' => PoStage::STAGE_1],
            ['jumlah_transaksi' => 5, 'total_menit' => 720, 'average_hari' => 0.1, 'max_hari' => 0.2]
        );

        // Level-2 Summary
        SupplierProdukLtSummary::updateOrCreate(
            ['supplier_id' => $supplier->id, 'produk_id' => $produk->id],
            [
                'jumlah_transaksi' => 3,
                'total_lt_menit' => 4320,
                'average_lt_hari' => 1.0,
                'max_lt_hari' => 2.0,
                'diperbarui_pada' => now(),
            ]
        );

        // Histori PoLeadTime (1 valid, 1 tidak valid)
        $poValid = PurchaseOrder::create([
            'no_po' => 'PO-LT-VALID-01',
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'status' => 'selesai',
            'created_by' => $manager->id,
        ]);
        PoLeadTime::create([
            'po_id' => $poValid->id,
            'supplier_id' => $supplier->id,
            'waktu_mulai' => now()->subDays(2),
            'waktu_selesai' => now(),
            'total_lt_menit' => 2880,
            'total_lt_hari' => 2.0,
            'lt_internal_menit' => 720,
            'lt_supplier_menit' => 1440,
            'lt_logistik_menit' => 720,
            'is_valid' => true,
        ]);

        $poInvalid = PurchaseOrder::create([
            'no_po' => 'PO-LT-INVALID-01',
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'status' => 'selesai',
            'created_by' => $manager->id,
        ]);
        PoLeadTime::create([
            'po_id' => $poInvalid->id,
            'supplier_id' => $supplier->id,
            'waktu_mulai' => now()->subDays(10),
            'waktu_selesai' => now(),
            'total_lt_menit' => 14400,
            'total_lt_hari' => 10.0,
            'lt_internal_menit' => 1440,
            'lt_supplier_menit' => 11520,
            'lt_logistik_menit' => 1440,
            'is_valid' => false,
            'alasan_tidak_valid' => 'Force majeure banjir bandang supplier',
            'divalidasi_oleh' => $manager->id,
        ]);

        $response = $this->get(route('reports.lead-time'));
        $response->assertOk();

        // Validasi konten tampilan
        $response->assertSee('PT Harum Semerbak Abadi');
        $response->assertSee('BHK-LT-01');
        $response->assertSee('PO-LT-VALID-01');
        $response->assertSee('PO-LT-INVALID-01');
        $response->assertSee('VALID');
        $response->assertSee('DIINVALIDASI');
        $response->assertSee('Force majeure banjir bandang supplier');

        // Validasi accessibility role="region"
        $response->assertSee('role="region"', false);
    }

    /**
     * TC-03: GET /laporan/defect merender defect produksi multi-produk dan defect transfer bulanan per gudang.
     */
    public function test_laporan_defect_renders_multi_product_defect_and_monthly_transfer_analytics(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $gudangAsal = Gudang::firstOrCreate(
            ['kode' => 'GD-ASAL-TEST'],
            ['nama' => 'Gudang Asal Test', 'tipe' => 'bahan_baku', 'is_active' => true]
        );
        $gudangTujuan = Gudang::firstOrCreate(
            ['kode' => 'GD-TUJUAN-TEST'],
            ['nama' => 'Gudang Tujuan Hub Test', 'tipe' => 'operasional', 'is_active' => true]
        );

        $produkP1 = Produk::firstOrCreate(
            ['sku' => 'DEF-P1'],
            ['nama' => 'Produk Rusak Satu', 'tipe' => 'produk_jadi', 'satuan' => 'Pcs', 'is_active' => true]
        );
        $produkP2 = Produk::firstOrCreate(
            ['sku' => 'DEF-P2'],
            ['nama' => 'Produk Rusak Dua', 'tipe' => 'produk_jadi', 'satuan' => 'Pcs', 'is_active' => true]
        );

        // Batch Multi-Produk dengan defect
        $batch = BatchProduksi::create([
            'no_batch' => 'BATCH-DEFECT-MULTI',
            'produk_id' => $produkP1->id,
            'qty_rencana' => 200,
            'qty_baik' => 180,
            'qty_rusak' => 20,
            'status' => 'selesai',
            'tanggal' => now()->toDateString(),
            'gudang_operasional_id' => $gudangAsal->id,
            'created_by' => $manager->id,
        ]);

        BatchProduksiOutput::create([
            'batch_id' => $batch->id,
            'produk_id' => $produkP1->id,
            'qty_rencana' => 100,
            'qty_baik' => 90,
            'qty_rusak' => 10,
        ]);
        BatchProduksiOutput::create([
            'batch_id' => $batch->id,
            'produk_id' => $produkP2->id,
            'qty_rencana' => 100,
            'qty_baik' => 90,
            'qty_rusak' => 10,
        ]);

        // Transfer Selesai dengan defect
        $transfer = Transfer::create([
            'no_transaksi' => 'TRF-TEST-DEFECT-01',
            'jenis' => 'pemenuhan_request_bahan',
            'gudang_asal_id' => $gudangAsal->id,
            'gudang_tujuan_id' => $gudangTujuan->id,
            'status' => 'selesai',
            'waktu_dikirim' => now()->subDay(),
            'waktu_diterima' => now(),
            'created_by' => $manager->id,
        ]);

        TransferItem::create([
            'transfer_id' => $transfer->id,
            'produk_id' => $produkP1->id,
            'qty_dikirim' => 50,
            'qty_baik' => 45,
            'qty_rusak' => 5,
            'alasan_rusak' => 'Kardus penyok saat bongkar',
        ]);

        $response = $this->get(route('reports.defect', ['tahun' => now()->year]));
        $response->assertOk();

        // Cek Batch Multi-Produk aman tampil di tabel
        $response->assertSee('BATCH-DEFECT-MULTI');
        $response->assertSee('Produk Rusak Satu');
        $response->assertSee('Produk Rusak Dua');

        // Cek Bagian Rekapitulasi Transfer Bulanan per Gudang
        $response->assertSee('Rekapitulasi Kerusakan Pengiriman (Transfer) Bulanan per Gudang');
        $response->assertSee('Gudang Tujuan Hub Test');
        $response->assertSee('Rincian Komoditas SKU Rusak Akumulasi Pengiriman');
    }

    /**
     * TC-04: GET /laporan/purchasing menghitung nilai komitmen tanpa PO batal
     * dan menyensor harga untuk pengguna tanpa purchasing.price.view.
     */
    public function test_laporan_purchasing_excludes_cancelled_po_and_masks_price(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $supplier = Supplier::firstOrFail();
        $produk = Produk::bahan()->firstOrFail();

        // 1. PO Aktif (grand_total = Rp 1.000.000)
        $poAktif = PurchaseOrder::create([
            'no_po' => 'PO-AKTIF-001',
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'status' => 'berjalan',
            'stage_aktif' => PoStage::STAGE_3,
            'grand_total' => 1000000,
            'created_by' => $manager->id,
        ]);
        $poAktif->items()->create([
            'produk_id' => $produk->id,
            'qty' => 10,
            'harga_total' => 1000000,
        ]);

        // 2. PO Batal (grand_total = Rp 500.000)
        $poBatal = PurchaseOrder::create([
            'no_po' => 'PO-BATAL-002',
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'status' => 'dibatalkan',
            'stage_aktif' => PoStage::STAGE_1,
            'grand_total' => 500000,
            'created_by' => $manager->id,
        ]);
        $poBatal->items()->create([
            'produk_id' => $produk->id,
            'qty' => 5,
            'harga_total' => 500000,
        ]);

        // Kunjungan oleh Manager (memiliki wewenang purchasing.price.view)
        $this->actingAs($manager);
        $resManager = $this->get(route('reports.purchasing'));
        $resManager->assertOk();

        // Nilai komitmen harus 1.000.000 (tidak boleh mencakup 500.000 PO batal)
        $resManager->assertSee('1.000.000');
        $resManager->assertDontSee('1.500.000');
        // Badge 9-stage tampil
        $resManager->assertSee('Konfirmasi Supplier');

        // Kunjungan oleh Gudang (memiliki report.view, tetapi TIDAK memiliki purchasing.price.view)
        $this->actingAs($gudangUser);
        $resGudang = $this->get(route('reports.purchasing'));
        $resGudang->assertOk();

        // Nominal harga harus disensor (Rp ••••••)
        $resGudang->assertSee('Rp ••••••');
        $resGudang->assertDontSee('1.000.000');
    }
}

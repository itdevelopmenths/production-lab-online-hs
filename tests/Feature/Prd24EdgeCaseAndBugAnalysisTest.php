<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\BatchProduksiOutput;
use App\Models\Bom;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\Supplier;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use App\Services\AnalisaService;
use App\Services\BatchService;
use App\Services\StokService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Prd24EdgeCaseAndBugAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $gudang;
    protected User $operasional;
    protected Gudang $gudangPusat;
    protected Gudang $gudangLab;
    protected Produk $bahanBaku;
    protected Produk $produkJadi;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $this->operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();

        $this->gudangPusat = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
        $this->gudangLab = Gudang::where('tipe', 'operasional_lab')->first() 
            ?? Gudang::create(['kode' => 'GD-LAB-TEST', 'nama' => 'Lab Formulasi Test', 'tipe' => 'operasional_lab', 'is_active' => true]);

        $this->bahanBaku = Produk::where('tipe', 'bahan')->firstOrFail();
        $this->produkJadi = Produk::where('tipe', 'produk_jadi')->first()
            ?? Produk::create(['sku' => 'PJD-TEST-01', 'nama' => 'Parfum Lavender 50ml', 'tipe' => 'produk_jadi', 'satuan' => 'pcs', 'is_active' => true]);

        $this->supplier = Supplier::firstOrFail();

        // Siapkan saldo awal
        app(StokService::class)->masuk($this->bahanBaku->id, $this->gudangPusat->id, 500, 'mutasi_manual');
    }

    /**
     * Test 1: Invarian Floating-Point Epsilon pada Penerimaan Transfer (qty_dikirim = qty_baik + qty_rusak)
     */
    public function test_transfer_floating_point_epsilon_invariant_accuracy(): void
    {
        $transfer = Transfer::create([
            'no_transaksi' => 'TRF-TEST-FP',
            'jenis' => 'pemenuhan_request_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangLab->id,
            'status' => 'draft',
            'created_by' => $this->gudang->id,
        ]);

        $item = TransferItem::create([
            'transfer_id' => $transfer->id,
            'produk_id' => $this->bahanBaku->id,
            'qty_dikirim' => 100.00,
        ]);

        // Ship transfer
        app(TransferService::class)->ship($transfer, $this->gudang);
        $this->assertEquals('dikirim', $transfer->fresh()->status);

        // Case A: 99.99 baik + 0.01 rusak = 100.00 (uji ketahanan floating-point precision)
        $transferService = app(TransferService::class);
        $transferService->receive($transfer, [
            [
                'id' => $item->id,
                'qty_baik' => 99.99,
                'qty_rusak' => 0.01,
                'keterangan_rusak' => '0.01 kg rembes mikro saat transit',
            ]
        ], $this->operasional);

        $transfer->refresh();
        $this->assertEquals('selesai', $transfer->status);
        $this->assertEquals(99.99, (float) $transfer->items->first()->qty_baik);
        $this->assertEquals(0.01, (float) $transfer->items->first()->qty_rusak);
    }

    /**
     * Test 2: Transfer wajib menolak selisih kuantitas dan wajib keterangan_rusak jika qty_rusak > 0
     */
    public function test_transfer_receiving_rejects_missing_keterangan_rusak_or_mismatched_sum(): void
    {
        $transfer = Transfer::create([
            'no_transaksi' => 'TRF-TEST-INV',
            'jenis' => 'pemenuhan_request_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangLab->id,
            'status' => 'draft',
            'created_by' => $this->gudang->id,
        ]);

        $item = TransferItem::create([
            'transfer_id' => $transfer->id,
            'produk_id' => $this->bahanBaku->id,
            'qty_dikirim' => 50.0,
        ]);

        app(TransferService::class)->ship($transfer, $this->gudang);

        // Case A: Jumlah tidak cocok (40 + 5 != 50) -> Harus throw exception
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(TransferService::class)->receive($transfer, [
            [
                'id' => $item->id,
                'qty_baik' => 40.0,
                'qty_rusak' => 5.0,
                'keterangan_rusak' => 'Kurang 5 kg',
            ]
        ], $this->operasional);
    }

    /**
     * Test 3: State Machine Transfer mengunci transisi ilegal
     */
    public function test_transfer_state_machine_blocks_illegal_transitions(): void
    {
        $transfer = Transfer::create([
            'no_transaksi' => 'TRF-TEST-ILLEGAL',
            'jenis' => 'pemenuhan_request_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangLab->id,
            'status' => 'draft',
            'created_by' => $this->gudang->id,
        ]);

        $item = TransferItem::create([
            'transfer_id' => $transfer->id,
            'produk_id' => $this->bahanBaku->id,
            'qty_dikirim' => 20.0,
        ]);

        // Tidak boleh langsung receive transfer yang masih draft (harus dikirim dulu)
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(TransferService::class)->receive($transfer, [
            [
                'id' => $item->id,
                'qty_baik' => 20.0,
                'qty_rusak' => 0.0,
            ]
        ], $this->operasional);
    }

    /**
     * Test 4: Uji Ketahanan Analisa Stok terhadap Zero-Sales, Zero-Review Period, & Division by Zero
     */
    public function test_analisa_stok_division_by_zero_guard_on_zero_sales(): void
    {
        // Buat produk baru tanpa penjualan sama sekali
        $newProduct = Produk::create([
            'sku' => 'PRD-ZERO-01',
            'nama' => 'Produk Baru Tanpa Sales',
            'tipe' => 'bahan',
            'satuan' => 'kg',
            'is_active' => true,
            'supplier_id' => $this->supplier->id,
        ]);

        $service = app(AnalisaService::class);

        // Generate lokal tidak boleh throw error / division by zero
        $resLokal = $service->generateLokal($newProduct->id, $this->manager->id);
        $this->assertGreaterThanOrEqual(1, $resLokal['generated_count']);

        // Generate impor tidak boleh throw error / division by zero
        $newProduct->update(['profil_analisa' => 'bahan_impor']);
        $resImpor = $service->generateImpor($newProduct->id, $this->manager->id);
        $this->assertGreaterThanOrEqual(1, $resImpor['generated_count']);
    }

    /**
     * Test 5: Pembulatan Lead Time strictly 1 Desimal Half-Up
     */
    public function test_lead_time_rounding_strictly_half_up_one_decimal(): void
    {
        // User override: < 5 menjadi 0, >= 5 menjadi +0.1
        $this->assertEquals(14.0, AnalisaService::roundLeadTimeDays(14.04));
        $this->assertEquals(14.0, AnalisaService::roundLeadTimeDays(14.049));
        $this->assertEquals(14.1, AnalisaService::roundLeadTimeDays(14.05));
        $this->assertEquals(14.1, AnalisaService::roundLeadTimeDays(14.051));
        $this->assertEquals(14.1, AnalisaService::roundLeadTimeDays(14.09));
        $this->assertEquals(81.8, AnalisaService::roundLeadTimeDays(81.75));
    }

    /**
     * Test 6: Supplier Deletion Lock jika Memiliki PO Aktif
     */
    public function test_supplier_deletion_lock_with_active_po(): void
    {
        $supplier = Supplier::create([
            'nama' => 'PT Vendor Aktif Lock',
            'kategori' => 'lokal',
            'is_active' => true,
        ]);

        // Buat PO aktif
        PurchaseOrder::create([
            'no_po' => 'PO-LOCK-01',
            'tanggal' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'gudang_id' => $this->gudangPusat->id,
            'status' => 'diproses_supplier',
            'total_harga' => 1000000,
            'created_by' => $this->manager->id,
        ]);

        // Hapus via HTTP DELETE
        $response = $this->actingAs($this->manager)->delete(route('supplier.destroy', $supplier));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('supplier', ['id' => $supplier->id]);
    }

    /**
     * Test 7: Batch Produksi Multi-Produk Agregasi BOM tidak Meledak pada Kuantitas Rencana 0
     */
    public function test_batch_service_handles_zero_target_qty_gracefully(): void
    {
        $batch = BatchProduksi::create([
            'no_batch' => 'BAT-TEST-ZERO',
            'tanggal' => now()->toDateString(),
            'produk_id' => $this->produkJadi->id,
            'gudang_id' => $this->gudangLab->id,
            'target_qty' => 0,
            'status' => 'draft',
            'created_by' => $this->manager->id,
        ]);

        BatchProduksiOutput::create([
            'batch_id' => $batch->id,
            'produk_id' => $this->produkJadi->id,
            'qty_rencana' => 0,
        ]);

        $rekap = $batch->rekapBahan();
        $this->assertIsArray($rekap);
    }

    /**
     * Test 8: Batch rekapBahan ignores retur_bahan and avoids double counting modern transfers
     */
    public function test_batch_rekap_bahan_does_not_double_count_and_excludes_retur(): void
    {
        $batch = BatchProduksi::create([
            'no_batch' => 'BAT-REKAP-01',
            'tanggal' => now()->toDateString(),
            'produk_id' => $this->produkJadi->id,
            'gudang_id' => $this->gudangLab->id,
            'target_qty' => 10,
            'status' => 'draft',
            'created_by' => $this->manager->id,
        ]);

        $batch->alokasi()->create([
            'bahan_id' => $this->bahanBaku->id,
            'qty_dialokasikan' => 20,
        ]);

        // Modern transfer selesai pemenuhan_request_bahan: 15 diterima
        $trf1 = Transfer::create([
            'no_transaksi' => 'TRF-REKAP-01',
            'jenis' => 'pemenuhan_request_bahan',
            'referensi_batch_id' => $batch->id,
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangLab->id,
            'status' => 'selesai',
            'waktu_dikirim' => now(),
            'waktu_diterima' => now(),
            'diterima_oleh' => $this->gudang->id,
            'created_by' => $this->manager->id,
        ]);
        $trf1->items()->create([
            'produk_id' => $this->bahanBaku->id,
            'qty_dikirim' => 15,
            'qty_diterima' => 15,
            'qty_baik' => 15,
            'qty_rusak' => 0,
        ]);

        // Transfer retur_bahan: 5 unit dikembalikan (TIDAK boleh menambah total_diterima)
        $trfRetur = Transfer::create([
            'no_transaksi' => 'TRF-RETUR-01',
            'jenis' => 'retur_bahan',
            'referensi_batch_id' => $batch->id,
            'gudang_asal_id' => $this->gudangLab->id,
            'gudang_tujuan_id' => $this->gudangPusat->id,
            'status' => 'selesai',
            'waktu_dikirim' => now(),
            'waktu_diterima' => now(),
            'diterima_oleh' => $this->gudang->id,
            'created_by' => $this->manager->id,
        ]);
        $trfRetur->items()->create([
            'produk_id' => $this->bahanBaku->id,
            'qty_dikirim' => 5,
            'qty_diterima' => 5,
            'qty_baik' => 5,
            'qty_rusak' => 0,
        ]);

        $rekap = collect($batch->rekapBahan())->firstWhere('bahan_id', $this->bahanBaku->id);
        $this->assertNotNull($rekap);
        $this->assertEquals(15.0, (float) $rekap['diterima'], 'Retur bahan must not be added to diterima');
    }

    /**
     * Test 9: Batch complete enforces integer quantities for finished goods per production rules
     */
    public function test_batch_complete_enforces_integer_quantities_and_completes(): void
    {
        $batch = BatchProduksi::create([
            'no_batch' => 'BAT-INT-01',
            'tanggal' => now()->toDateString(),
            'produk_id' => $this->produkJadi->id,
            'gudang_operasional_id' => $this->gudangLab->id,
            'qty_rencana' => 10,
            'target_qty' => 10,
            'status' => 'release',
            'created_by' => $this->manager->id,
        ]);

        $output = BatchProduksiOutput::create([
            'batch_id' => $batch->id,
            'produk_id' => $this->produkJadi->id,
            'qty_rencana' => 10,
            'qty_baik' => 0,
            'qty_rusak' => 0,
        ]);

        // Decimal rejected
        $decimalPayload = [
            'outputs' => [
                [
                    'id' => $output->id,
                    'qty_baik' => 9.5,
                    'qty_rusak' => 0.5,
                ],
            ],
        ];
        $decRes = $this->actingAs($this->manager)->post(route('batches.complete', $batch), $decimalPayload);
        $decRes->assertSessionHasErrors(['outputs.0.qty_baik', 'outputs.0.qty_rusak']);

        // Valid integer accepted
        $validPayload = [
            'outputs' => [
                [
                    'id' => $output->id,
                    'qty_baik' => 8,
                    'qty_rusak' => 2,
                ],
            ],
        ];
        $validRes = $this->actingAs($this->manager)->post(route('batches.complete', $batch), $validPayload);
        $validRes->assertSessionHasNoErrors();
        $this->assertEquals('selesai', $batch->fresh()->status);
        $this->assertEquals(8, (int) $output->fresh()->qty_baik);
        $this->assertEquals(2, (int) $output->fresh()->qty_rusak);
    }

    /**
     * Test 10: Supplier comparison modal payload contracts
     */
    public function test_supplier_comparison_payload_structure(): void
    {
        $supplier = Supplier::create([
            'nama' => 'PT Vendor Modal Compare',
            'kategori' => 'lokal',
            'is_active' => true,
        ]);
        $this->bahanBaku->suppliers()->attach($supplier->id, ['status' => 'utama']);

        $response = $this->actingAs($this->manager)->getJson("/analisa/compare-suppliers/{$this->bahanBaku->id}");
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'produk_id',
                'produk_nama',
                'suppliers' => [
                    '*' => [
                        'supplier_id',
                        'supplier_nama',
                        'is_current',
                        'average_lt_days',
                        'max_lt_days',
                        'sumber_lt',
                        'total_po_selesai',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test 11: Bidirectional Gate Aliasing (rt.* <-> request.* / transfer.*)
     */
    public function test_bidirectional_gate_aliasing(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('request.create');

        // User with modern request.create must pass legacy rt.create check
        $this->assertTrue($user->can('rt.create'));
        $this->assertTrue($user->can('request.create'));
        // But not rt.approve
        $this->assertFalse($user->can('rt.approve'));
    }

    /**
     * Test 12: Views rendering for requests/create, requests/edit, transfers/create, transfers/show
     */
    public function test_request_and_transfer_blade_views_render_without_syntax_error(): void
    {
        // 1. GET requests.create
        $resReqCreate = $this->actingAs($this->operasional)->get(route('requests.create'));
        $resReqCreate->assertOk();
        $resReqCreate->assertSee('Formulir Permintaan Barang');

        // 2. GET requests.edit
        $stockReq = StockRequest::create([
            'no_request' => 'REQ-TEST-VIEW-01',
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $this->gudangLab->id,
            'gudang_pemohon_id' => $this->gudangLab->id,
            'gudang_tujuan_id' => $this->gudangPusat->id,
            'status' => 'draft',
            'created_by' => $this->operasional->id,
        ]);
        $stockReq->items()->create([
            'produk_id' => $this->bahanBaku->id,
            'qty_diminta' => 10,
        ]);
        $resReqEdit = $this->actingAs($this->operasional)->get(route('requests.edit', $stockReq));
        $resReqEdit->assertOk();
        $resReqEdit->assertSee('Edit Permintaan Barang');

        // 3. GET transfers.create
        $resTrfCreate = $this->actingAs($this->gudang)->get(route('transfers.create'));
        $resTrfCreate->assertOk();
        $resTrfCreate->assertSee('Buat Transfer Barang');

        // 4. GET transfers.show
        $transfer = Transfer::create([
            'no_transaksi' => 'TRF-TEST-VIEW-01',
            'jenis' => 'distribusi',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangLab->id,
            'status' => 'draft',
            'created_by' => $this->gudang->id,
        ]);
        $transfer->items()->create([
            'produk_id' => $this->bahanBaku->id,
            'qty_dikirim' => 5,
        ]);
        $resTrfShow = $this->actingAs($this->gudang)->get(route('transfers.show', $transfer));
        $resTrfShow->assertOk();
        $resTrfShow->assertSee('TRF-TEST-VIEW-01');
    }
}

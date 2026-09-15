<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingRevampFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
        $this->seed();
    }

    public function test_purchasing_revamp_full_lifecycle_with_breakdown_termins_and_discrepancy(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $supplier = Supplier::firstOrFail();
        $gudang = Gudang::firstOrFail();
        $items = Produk::bahan()->take(2)->get();

        // 1. Purchasing creates draft PO with manual invoice, warehouse, and cost breakdown
        $this->actingAs($purchasing);

        $poData = [
            'no_invoice' => 'INV-SUPP-999',
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'eta' => now()->addDays(7)->toDateString(),
            'sumber_dana' => 'BCA Operasional',
            'skema_bayar' => 'termin',
            'diskon_total' => 20000,
            'ppn_nominal' => 50000,
            'ongkos_kirim' => 70000,
            'adjustment' => 0,
            'items' => [
                [
                    'produk_id' => $items[0]->id,
                    'qty' => 100,
                    'harga_total' => 1000000,
                    'diskon' => 10000,
                    'ppn' => 20000,
                    'ongkir' => 30000,
                    'adjustment' => 0,
                ],
                [
                    'produk_id' => $items[1]->id,
                    'qty' => 50,
                    'harga_total' => 500000,
                    'diskon' => 0,
                    'ppn' => 0,
                    'ongkir' => 0,
                    'adjustment' => 0,
                ],
            ],
            'termins' => [
                [
                    'tanggal_tempo' => now()->addDays(5)->toDateString(),
                    'nominal_tagihan' => 640000,
                    'keterangan' => 'DP',
                ],
                [
                    'tanggal_tempo' => now()->addDays(14)->toDateString(),
                    'nominal_tagihan' => 1000000,
                    'keterangan' => 'Pelunasan',
                ],
            ],
        ];

        $response = $this->post(route('purchasing.store'), $poData);
        $po = PurchaseOrder::latest()->firstOrFail();
        $response->assertRedirect(route('purchasing.show', $po));

        $this->assertEquals('draft', $po->status);
        $this->assertEquals('INV-SUPP-999', $po->no_invoice);
        $this->assertEquals($gudang->id, $po->gudang_id);
        $this->assertCount(2, $po->items);
        $this->assertCount(2, $po->termins);
        $this->assertEquals(1640000, $po->grand_total);

        // Check calculated HPP on item 0
        $item0 = $po->items()->where('produk_id', $items[0]->id)->first();
        $this->assertGreaterThan(0, (float) $item0->hpp_per_satuan);

        // 2. Quick Edit Date & ETA
        $editResponse = $this->putJson(route('purchasing.quick-dates', $po), [
            'tanggal' => now()->subDay()->toDateString(),
            'eta' => now()->addDays(10)->toDateString(),
        ]);
        $editResponse->assertOk();
        $po->refresh();
        $this->assertEquals(now()->addDays(10)->toDateString(), $po->eta->toDateString());

        // 3. Purchasing submits PO
        $this->post(route('purchasing.submit', $po))->assertSessionHas('success');
        $po->refresh();
        $this->assertEquals('diajukan', $po->status);

        // 4. Manager approves PO
        $this->actingAs($manager);
        $this->post(route('purchasing.approve', $po))->assertSessionHas('success');
        $po->refresh();
        $this->assertEquals('dikirim_ke_gudang', $po->status);

        // 5. Gudang confirms receipt with a discrepancy (kurang) and explanation
        $this->actingAs($gudangUser);
        $receiveData = [
            'gudang_id' => $gudang->id,
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'items' => [
                [
                    'po_item_id' => $item0->id,
                    'qty_diterima' => 90, // Kurang 10
                    'keterangan_selisih' => 'Bocor saat perjalanan ekspedisi',
                ],
                [
                    'po_item_id' => $po->items()->where('produk_id', $items[1]->id)->first()->id,
                    'qty_diterima' => 50, // Pas
                    'keterangan_selisih' => '',
                ],
            ],
        ];

        $this->post(route('purchasing.receive', $po), $receiveData)->assertSessionHas('success');
        $po->refresh();
        $this->assertEquals('selesai', $po->status);

        // Verify discrepancy recorded
        $bardat = $po->barangDatang()->latest()->firstOrFail();
        $bItem0 = $bardat->items()->where('po_item_id', $item0->id)->firstOrFail();
        $this->assertEquals(-10, (float) $bItem0->selisih);
        $this->assertEquals('Bocor saat perjalanan ekspedisi', $bItem0->keterangan_selisih);

        // Verify show page renders safely without LazyLoadingViolationException on BarangDatangItem poItem relation
        $showAfterReceive = $this->get(route('purchasing.show', $po));
        $showAfterReceive->assertOk();
        $showAfterReceive->assertSee('Riwayat Penerimaan Barang di Gudang');
        $showAfterReceive->assertSee($items[0]->nama);
        $showAfterReceive->assertSee('Bocor saat perjalanan ekspedisi');

        // 6. Purchasing pays Termin 1 (Rp 640.000)
        $this->actingAs($purchasing);
        $this->post(route('purchasing.pay', $po), [
            'skema' => 'termin',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 640000,
        ])->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('parsial', $po->status_pembayaran);
        $this->assertEquals(1000000, $po->sisaTagihan());

        // 7. Purchasing pays Pelunasan (Rp 1.000.000)
        $this->post(route('purchasing.pay', $po), [
            'skema' => 'pelunasan',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 1000000,
        ])->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('lunas', $po->status_pembayaran);
        $this->assertEquals(0, $po->sisaTagihan());
        $this->assertTrue($po->isLunas());
    }

    public function test_automatic_invoice_generation_when_left_empty(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $supplier = Supplier::firstOrFail();
        $item = Produk::bahan()->firstOrFail();

        $this->actingAs($purchasing);
        $response = $this->post(route('purchasing.store'), [
            'no_invoice' => '',
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'items' => [
                [
                    'produk_id' => $item->id,
                    'qty' => 10,
                    'harga_total' => 200000,
                ],
            ],
        ]);

        $po = PurchaseOrder::latest()->firstOrFail();
        $this->assertNotEmpty($po->no_invoice);
        $this->assertStringStartsWith('INV/PO/', $po->no_invoice);
    }

    /**
     * Test spesifik untuk memverifikasi halaman purchasing.show bebas dari
     * Illuminate\Database\LazyLoadingViolationException saat PO memiliki
     * barangDatangItems dan mengakses relasi poItem serta produk.
     */
    public function test_show_page_renders_without_lazy_loading_violation_with_barang_datang_items(): void
    {
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);

        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $supplier = Supplier::firstOrFail();
        $gudang = Gudang::firstOrFail();
        $item = Produk::bahan()->firstOrFail();

        $po = PurchaseOrder::create([
            'no_po' => 'PO-LAZY-TEST-001',
            'no_invoice' => 'INV-LAZY-01',
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'status' => 'selesai',
            'created_by' => $manager->id,
        ]);

        $poItem = $po->items()->create([
            'produk_id' => $item->id,
            'qty' => 50,
            'harga_total' => 500000,
        ]);

        $bardat = $po->barangDatang()->create([
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'created_by' => $gudangUser->id,
        ]);

        $bardat->items()->create([
            'po_item_id' => $poItem->id,
            'qty_diterima' => 45,
            'selisih' => -5,
            'keterangan_selisih' => 'Bocor saat perjalanan ekspedisi',
        ]);

        // 1. Manager views show page
        $this->actingAs($manager);
        $resManager = $this->get(route('purchasing.show', $po));
        $resManager->assertOk();
        $resManager->assertSee($item->nama);
        $resManager->assertSee('Bocor saat perjalanan ekspedisi');

        // 2. Gudang views show page
        $this->actingAs($gudangUser);
        $resGudang = $this->get(route('purchasing.show', $po));
        $resGudang->assertOk();
        $resGudang->assertSee($item->nama);
        $resGudang->assertSee('Bocor saat perjalanan ekspedisi');
    }

    public function test_po_tempo_requires_tanggal_tempo_and_detects_overdue(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $supplier = Supplier::firstOrFail();
        $gudang = Gudang::firstOrFail();
        $item = Produk::bahan()->firstOrFail();

        $this->actingAs($purchasing);

        // 1. Validation fails if tempo but tanggal_tempo is missing
        $failRes = $this->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'tempo',
            // tanggal_tempo missing!
            'items' => [
                [
                    'produk_id' => $item->id,
                    'qty' => 10,
                    'harga_total' => 200000,
                ],
            ],
        ]);
        $failRes->assertSessionHasErrors('tanggal_tempo');

        // 2. Creation succeeds with tanggal_tempo in the future
        $futureTempo = now()->addDays(30)->toDateString();
        $passRes = $this->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'tempo',
            'tanggal_tempo' => $futureTempo,
            'items' => [
                [
                    'produk_id' => $item->id,
                    'qty' => 10,
                    'harga_total' => 200000,
                ],
            ],
        ]);
        $po = PurchaseOrder::latest()->firstOrFail();
        $passRes->assertRedirect(route('purchasing.show', $po));
        $this->assertEquals($futureTempo, $po->tanggal_tempo->toDateString());
        $this->assertEquals('belum_lunas', $po->status_pembayaran);

        // 3. Past tanggal_tempo results in overdue status
        $po->update(['tanggal_tempo' => now()->subDays(2)->toDateString()]);
        $po->refreshStatusPembayaran();
        $po->save();
        $this->assertEquals('overdue', $po->status_pembayaran);

        // 4. Show page displays deadline and overdue indicator
        $showRes = $this->get(route('purchasing.show', $po));
        $showRes->assertOk();
        $showRes->assertSee('Deadline Tempo');
        $showRes->assertSee('Overdue');
    }

    public function test_produk_select_data_returns_harga_hpp(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $item = Produk::bahan()->firstOrFail();
        $item->update(['harga_hpp' => 45000.50]);

        $this->actingAs($purchasing);
        $res = $this->getJson(route('produk.select-data', ['q' => $item->sku]));
        $res->assertOk();
        $data = $res->json();

        $this->assertNotEmpty($data['items']);
        $found = collect($data['items'])->firstWhere('id', $item->id);
        $this->assertNotNull($found);
        $this->assertArrayHasKey('harga_hpp', $found);
        $this->assertEquals(45000.5, (float) $found['harga_hpp']);
    }

    public function test_stok_log_pergerakan_page_and_data_endpoint(): void
    {
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $gudang = Gudang::firstOrFail();
        $item = Produk::bahan()->firstOrFail();

        $this->actingAs($gudangUser);

        // 1. Record stock in through mutasi manual
        $this->post(route('stok.mutasi'), [
            'produk_id' => $item->id,
            'gudang_id' => $gudang->id,
            'tipe' => 'in',
            'qty' => 50,
            'catatan' => 'Tes Audit Mutasi Lead Architect',
        ])->assertSessionHas('success');

        // Verify created_by was populated
        $lastKartu = \App\Models\KartuStok::where('catatan', 'Tes Audit Mutasi Lead Architect')->firstOrFail();
        $this->assertEquals($gudangUser->id, $lastKartu->created_by);
        $this->assertEquals($gudangUser->name, $lastKartu->creator->name);

        // 2. View page renders
        $pageRes = $this->get(route('stok.log-pergerakan'));
        $pageRes->assertOk();
        $pageRes->assertSee('Log Pergerakan Stok');
        $pageRes->assertSee('Buku Audit Mutasi Fisik Barang');

        // 3. DataTables AJAX endpoint returns correct audit columns
        $dataRes = $this->getJson(route('stok.log-pergerakan.data'));
        $dataRes->assertOk();
        $json = $dataRes->json();
        $this->assertArrayHasKey('data', $json);
        $this->assertNotEmpty($json['data']);

        $row = collect($json['data'])->first();
        $this->assertArrayHasKey('tipe', $row);
        $this->assertArrayHasKey('item', $row);
        $this->assertArrayHasKey('lokasi_gudang', $row);
        $this->assertArrayHasKey('ref', $row);
        $this->assertArrayHasKey('qty', $row);
        $this->assertArrayHasKey('sebelum_sesudah', $row);
        $this->assertArrayHasKey('tanggal', $row);
        $this->assertArrayHasKey('oleh', $row);
    }

    public function test_produk_select_data_and_stok_index_share_exact_same_hpp(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $item = Produk::bahan()->firstOrFail();
        $supplier = Supplier::firstOrFail();
        $gudang = Gudang::firstOrFail();

        // 1. Buat PO dengan HPP spesifik Rp 52.500
        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-HPP-SYNC',
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'tanggal' => now(),
            'status' => 'selesai',
            'skema_bayar' => 'cash',
            'status_pembayaran' => 'lunas',
            'grand_total' => 525000,
            'created_by' => $purchasing->id,
        ]);

        $po->items()->create([
            'produk_id' => $item->id,
            'qty' => 10,
            'harga_total' => 525000,
            'hpp_per_satuan' => 52500.00,
        ]);

        $this->actingAs($purchasing);

        // 2. Cek endpoint produk/select-data (dipakai di create & edit PO)
        $selectRes = $this->getJson(route('produk.select-data', ['q' => $item->sku]));
        $selectRes->assertOk();
        $selectItems = $selectRes->json('items');
        $found = collect($selectItems)->firstWhere('id', $item->id);
        $this->assertNotNull($found);
        $this->assertEquals(52500.0, (float) $found['harga_hpp']);

        // 3. Cek endpoint stok/data (kolom HPP di resources/views/stok/index.blade.php)
        $stokRes = $this->getJson(route('stok.data'));
        $stokRes->assertOk();
        $stokRows = $stokRes->json('data');
        $stokItem = collect($stokRows)->firstWhere('sku', $item->sku);
        if ($stokItem) {
            $this->assertStringContainsString('52.500', $stokItem['hpp']);
        }

        // 4. Verifikasi method StokService::resolveHppMap
        $stokService = app(\App\Services\StokService::class);
        $hppMap = $stokService->resolveHppMap([$item->id]);
        $this->assertEquals(52500.0, (float) $hppMap[$item->id]);
    }

    public function test_antrean_approval_tab_is_restricted_to_manager_and_purchasing(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        // 1. Manager can see Antrean Approval tab
        $resManager = $this->actingAs($manager)->get(route('purchasing.index'));
        $resManager->assertOk();
        $resManager->assertSee('Antrean Approval');
        $resManager->assertSee('id="tblApproval"', false);

        // 2. Staff Purchasing can see Antrean Approval tab
        $resPurchasing = $this->actingAs($purchasing)->get(route('purchasing.index'));
        $resPurchasing->assertOk();
        $resPurchasing->assertSee('Antrean Approval');
        $resPurchasing->assertSee('id="tblApproval"', false);

        // 3. Staff Gudang (tanpa role manager/purchasing) CANNOT see Antrean Approval tab
        $resGudang = $this->actingAs($gudangUser)->get(route('purchasing.index'));
        $resGudang->assertOk();
        $resGudang->assertDontSee('Antrean Approval');
        $resGudang->assertDontSee('id="tblApproval"', false);
    }
}


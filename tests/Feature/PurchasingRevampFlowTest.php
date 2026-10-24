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

    public function test_duplicate_raw_material_in_purchasing_create_is_rejected(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $supplier = Supplier::firstOrFail();
        $gudang = Gudang::firstOrFail();
        $bahan = Produk::bahan()->firstOrFail();

        $payload = [
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'tanggal' => now()->format('Y-m-d'),
            'skema_bayar' => 'cash',
            'items' => [
                [
                    'produk_id' => $bahan->id,
                    'qty' => 10,
                    'qty_satuan_beli' => 10,
                    'satuan_beli' => $bahan->satuan,
                    'faktor_konversi' => 1,
                    'harga_total' => 100000,
                ],
                [
                    'produk_id' => $bahan->id, // Duplikat bahan yang sama
                    'qty' => 5,
                    'qty_satuan_beli' => 5,
                    'satuan_beli' => $bahan->satuan,
                    'faktor_konversi' => 1,
                    'harga_total' => 50000,
                ],
            ],
        ];

        $res = $this->actingAs($purchasing)->post(route('purchasing.store'), $payload);
        $res->assertSessionHasErrors(['items.0.produk_id', 'items.1.produk_id']);
    }

    public function test_draft_po_does_not_leak_into_hpp_until_received_at_warehouse(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $supplier = Supplier::firstOrFail();
        $gudang = Gudang::firstOrFail();
        $stokService = app(\App\Services\StokService::class);

        // 1. Buat produk bahan baru tanpa HPP
        $newBahan = Produk::create([
            'sku' => 'TEST-HPP-STAGE-' . uniqid(),
            'nama' => 'Bahan Uji HPP Stage',
            'tipe' => 'bahan',
            'satuan' => 'KG',
            'harga_hpp' => 0,
            'status' => 'aktif',
        ]);

        // Verifikasi awal HPP = 0
        $hppMapInit = $stokService->resolveHppMap([$newBahan->id]);
        $this->assertEquals(0.0, (float) ($hppMapInit[$newBahan->id] ?? 0));

        // 2. Buat PO berstatus 'draft'
        $poDraft = PurchaseOrder::create([
            'no_po' => 'PO-DRAFT-' . uniqid(),
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'tanggal' => now(),
            'status' => 'draft',
            'skema_bayar' => 'cash',
            'grand_total' => 750000,
            'created_by' => $purchasing->id,
        ]);

        $poDraft->items()->create([
            'produk_id' => $newBahan->id,
            'qty' => 10,
            'harga_total' => 750000,
            'hpp_per_satuan' => 75000.0,
        ]);

        // HPP HARUS TETAP 0 karena PO masih draft (belum diterima gudang)
        $hppMapDraft = $stokService->resolveHppMap([$newBahan->id]);
        $this->assertEquals(0.0, (float) ($hppMapDraft[$newBahan->id] ?? 0), 'PO status draft tidak boleh bocor ke HPP');

        // Pastikan juga di endpoint produk/select-data HPP tetap 0
        $this->actingAs($purchasing);
        $selectRes = $this->getJson(route('produk.select-data', ['q' => $newBahan->sku]));
        $selectRes->assertOk();
        $found = collect($selectRes->json('items'))->firstWhere('id', $newBahan->id);
        $this->assertNotNull($found);
        $this->assertEquals(0.0, (float) $found['harga_hpp'], 'Endpoint produk/select-data tidak boleh menampilkan HPP dari PO draft');

        // 3. Ubah status PO ke 'dikirim_ke_gudang' (masih belum diterima fisik)
        $poDraft->update(['status' => 'dikirim_ke_gudang']);
        $hppMapDikirim = $stokService->resolveHppMap([$newBahan->id]);
        $this->assertEquals(0.0, (float) ($hppMapDikirim[$newBahan->id] ?? 0), 'PO status dikirim_ke_gudang belum diterima sehingga HPP tidak boleh muncul');

        // 4. Tahap Diterima Gudang: PO diselesaikan (status 'selesai')
        $poDraft->update(['status' => 'selesai']);
        $hppMapReceived = $stokService->resolveHppMap([$newBahan->id]);
        $this->assertEquals(75000.0, (float) ($hppMapReceived[$newBahan->id] ?? 0), 'HPP wajib muncul saat PO sudah diterima gudang (selesai)');

        // Verifikasi kembali di endpoint produk/select-data
        $selectResAfter = $this->getJson(route('produk.select-data', ['q' => $newBahan->sku]));
        $selectResAfter->assertOk();
        $foundAfter = collect($selectResAfter->json('items'))->firstWhere('id', $newBahan->id);
        $this->assertNotNull($foundAfter);
        $this->assertEquals(75000.0, (float) $foundAfter['harga_hpp'], 'HPP di endpoint select-data wajib terupdate setelah PO diterima di gudang');
    }

    public function test_cancel_po_with_reason_stores_reason_in_catatan_and_audit_trail(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $supplier = Supplier::active()->firstOrFail();
        $gudang = Gudang::firstOrFail();

        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-CANCEL-' . uniqid(),
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'tanggal' => now(),
            'status' => 'diajukan',
            'skema_bayar' => 'tempo',
            'grand_total' => 1500000,
            'created_by' => $manager->id,
            'catatan' => 'Catatan awal sebelum batal',
        ]);

        $this->actingAs($manager);

        // Verify show view renders the cancel modal and redesigned nominal bayar
        $showRes = $this->get(route('purchasing.show', $po));
        $showRes->assertOk();
        $showRes->assertSee('Batalkan Purchase Order');
        $showRes->assertSee('Alasan Pembatalan');
        $showRes->assertSee('openCancelModal');
        $showRes->assertSee('Nominal Bayar');
        $showRes->assertSee('displayNominal');

        // Post cancel with specific reason
        $cancelReason = 'Supplier menginformasikan bahan baku reject QC pabrik vendor.';
        $response = $this->post(route('purchasing.cancel', $po), [
            'alasan' => $cancelReason,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('dibatalkan', $po->status);
        $this->assertStringContainsString('Catatan awal sebelum batal', $po->catatan);
        $this->assertStringContainsString("[Alasan Pembatalan: {$cancelReason}]", $po->catatan);

        // Verify audit log has the cancellation reason in title and metadata
        $audit = \App\Models\OperationalAudit::where('auditable_type', PurchaseOrder::class)
            ->where('auditable_id', (string) $po->id)
            ->where('event', 'cancelled')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit, 'Log audit pembatalan PO wajib tercatat');
        $this->assertStringContainsString($cancelReason, $audit->action_title);
        $this->assertIsArray($audit->metadata);
        $this->assertEquals($cancelReason, $audit->metadata['alasan_pembatalan']);
    }

    public function test_cancel_po_validation_rejects_excessive_reason(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $supplier = Supplier::active()->firstOrFail();
        $gudang = Gudang::firstOrFail();

        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-CANCEL-MAX-' . uniqid(),
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'tanggal' => now(),
            'status' => 'draft',
            'skema_bayar' => 'cash',
            'grand_total' => 500000,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager);

        // Kirim alasan > 500 karakter
        $longReason = str_repeat('A', 501);
        $res = $this->post(route('purchasing.cancel', $po), [
            'alasan' => $longReason,
        ]);

        $res->assertSessionHasErrors(['alasan']);

        $po->refresh();
        $this->assertEquals('draft', $po->status, 'Status PO tidak boleh berubah jika validasi alasan gagal');
    }

    public function test_payment_nominal_input_only_for_termin_tempo_and_supports_tempo_adjustment(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $supplier = Supplier::active()->firstOrFail();
        $gudang = Gudang::firstOrFail();

        // 1. PO Cash: Tidak menampilkan custom nominal input yang diedit, langsung pelunasan penuh
        $poCash = PurchaseOrder::create([
            'no_po' => 'PO-CASH-' . uniqid(),
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'tanggal' => now(),
            'status' => 'disetujui',
            'skema_bayar' => 'cash',
            'subtotal_produk' => 1000000,
            'grand_total' => 1000000,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager);
        $resCash = $this->get(route('purchasing.show', $poCash));
        $resCash->assertOk();
        $resCash->assertSee('Total Pembayaran Tunai');
        $resCash->assertSee('Catat Pembayaran Tunai Penuh');
        $resCash->assertDontSee('Nominal Bayar (Custom / Diedit)');

        // 2. PO Tempo: Menampilkan input custom nominal dan fitur adjustment jatuh tempo
        $originalTempoDate = now()->addDays(14)->toDateString();
        $poTempo = PurchaseOrder::create([
            'no_po' => 'PO-TEMPO-' . uniqid(),
            'supplier_id' => $supplier->id,
            'gudang_id' => $gudang->id,
            'tanggal' => now(),
            'tanggal_tempo' => $originalTempoDate,
            'status' => 'disetujui',
            'skema_bayar' => 'tempo',
            'subtotal_produk' => 2000000,
            'grand_total' => 2000000,
            'created_by' => $manager->id,
        ]);

        $resTempo = $this->get(route('purchasing.show', $poTempo));
        $resTempo->assertOk();
        $resTempo->assertSee('Nominal Bayar (Custom / Diedit)');
        $resTempo->assertSee('Adjustment Jatuh Tempo PO');
        $resTempo->assertSee('name="tanggal_tempo_baru"', false);

        // 3. Eksekusi Pembayaran Parsial Tempo dengan Custom Nominal + Adjustment Tanggal Tempo
        $newTempoDate = now()->addDays(30)->toDateString();
        $payRes = $this->post(route('purchasing.pay', $poTempo), [
            'skema' => 'tempo',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 750000, // Custom nominal yang dibayar
            'tanggal_tempo_baru' => $newTempoDate, // Adjustment tempo
        ]);

        $payRes->assertRedirect();
        $payRes->assertSessionHas('success');

        $poTempo->refresh();
        $this->assertEquals('parsial', $poTempo->status_pembayaran);
        $this->assertEquals(750000.0, (float) $poTempo->totalDibayar());
        $this->assertEquals(1250000.0, (float) $poTempo->sisaTagihan());
        $this->assertEquals($newTempoDate, $poTempo->tanggal_tempo->format('Y-m-d'), 'Tanggal tempo PO wajib diperbarui oleh fitur adjustment tempo');

        // Verifikasi audit trail mencatat adjustment tempo
        $audit = \App\Models\OperationalAudit::where('auditable_type', PurchaseOrder::class)
            ->where('auditable_id', (string) $poTempo->id)
            ->where('event', 'paid')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertArrayHasKey('tanggal_tempo', $audit->new_values);
        $this->assertEquals($originalTempoDate, $audit->old_values['tanggal_tempo']);
        $this->assertEquals($newTempoDate, $audit->new_values['tanggal_tempo']);
        $this->assertEquals($newTempoDate, $audit->metadata['tanggal_tempo_baru']);
    }
}


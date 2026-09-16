<?php

namespace Tests\Feature;

use App\Models\BarangDatang;
use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SaldoGudang;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Varian;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingUomConversionTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $purchasing;
    protected User $gudangUser;
    protected Supplier $supplier;
    protected Gudang $gudangUtama;
    protected Produk $oilBahan;
    protected Produk $waxBahan;

    protected function setUp(): void
    {
        parent::setUp();
        Model::preventLazyLoading(true);
        $this->seed();

        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $this->supplier = Supplier::active()->firstOrFail();
        $this->gudangUtama = Gudang::active()->firstOrFail();

        $kategori = Kategori::firstOrCreate(['kode' => 'OIL', 'nama' => 'Essential Oil', 'tipe' => 'bahan']);
        $varian = Varian::firstOrCreate(['kategori_id' => $kategori->id, 'kode' => 'LAV', 'nama' => 'Lavender']);

        $this->oilBahan = Produk::create([
            'sku' => 'MAT-OIL-TEST-01',
            'nama' => 'Lavender Oil Pure (Testing)',
            'tipe' => 'bahan',
            'satuan' => 'ml',
            'isi_per_kemasan' => 1,
            'kategori_id' => $kategori->id,
            'varian_id' => $varian->id,
            'harga_hpp' => 120.0,
            'is_active' => true,
        ]);

        $this->waxBahan = Produk::create([
            'sku' => 'MAT-WAX-TEST-01',
            'nama' => 'Soy Wax Flakes (Testing)',
            'tipe' => 'bahan',
            'satuan' => 'gr',
            'isi_per_kemasan' => 1,
            'kategori_id' => $kategori->id,
            'harga_hpp' => 20.0,
            'is_active' => true,
        ]);
    }

    /**
     * TC-UOM-01: Pembelian volume (Jerigen 5L) dikonversi ke satuan dasar (ml) dan HPP dihitung per ml.
     */
    public function test_po_creation_converts_volume_uom_to_base_unit_and_calculates_base_hpp(): void
    {
        $payload = [
            'no_invoice' => 'INV-UOM-001',
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudangUtama->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'cash',
            'items' => [
                [
                    'produk_id' => $this->oilBahan->id,
                    'qty_satuan_beli' => 5,
                    'satuan_beli' => 'Jerigen 5L',
                    'faktor_konversi' => 5000,
                    'qty' => 25000,
                    'harga_total' => 2500000,
                    'diskon' => 0,
                    'ppn' => 0,
                    'ongkir' => 0,
                    'adjustment' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->purchasing)
            ->post(route('purchasing.store'), $payload);

        $response->assertRedirect();
        
        $po = PurchaseOrder::where('no_invoice', 'INV-UOM-001')->firstOrFail();
        $this->assertEquals(2500000, $po->grand_total);
        
        $item = $po->items->first();
        $this->assertNotNull($item);
        $this->assertEquals(5, $item->qty_satuan_beli);
        $this->assertEquals('Jerigen 5L', $item->satuan_beli);
        $this->assertEquals(5000, $item->faktor_konversi);
        $this->assertEquals(25000, $item->qty);
        
        // HPP per ml = 2.500.000 / 25.000 = 100.0000
        $this->assertEquals(100.0, (float) $item->hpp_per_satuan);
        $this->assertEquals('5 Jerigen 5L (25.000 ml)', $item->displayQtyPurchased());
    }

    /**
     * TC-UOM-02: Pembelian massa (Sak 25kg) dengan alokasi ongkir global dikonversi ke gram dan HPP per gram.
     */
    public function test_po_creation_converts_mass_uom_to_base_unit_and_allocates_global_charges(): void
    {
        $payload = [
            'no_invoice' => 'INV-UOM-002',
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudangUtama->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'cash',
            'ongkos_kirim' => 50000,
            'items' => [
                [
                    'produk_id' => $this->waxBahan->id,
                    'qty_satuan_beli' => 2,
                    'satuan_beli' => 'Sak 25kg',
                    'faktor_konversi' => 25000,
                    'qty' => 50000,
                    'harga_total' => 1000000,
                ],
            ],
        ];

        $response = $this->actingAs($this->purchasing)
            ->post(route('purchasing.store'), $payload);

        $response->assertRedirect();
        
        $po = PurchaseOrder::where('no_invoice', 'INV-UOM-002')->firstOrFail();
        $this->assertEquals(1050000, $po->grand_total);
        
        $item = $po->items->first();
        $this->assertNotNull($item);
        $this->assertEquals(2, $item->qty_satuan_beli);
        $this->assertEquals('Sak 25kg', $item->satuan_beli);
        $this->assertEquals(25000, $item->faktor_konversi);
        $this->assertEquals(50000, $item->qty);
        $this->assertEquals(50000, $item->ongkir);
        $this->assertEquals(1050000, $item->netTotal());
        
        // HPP per gr = 1.050.000 / 50.000 = 21.0000
        $this->assertEquals(21.0, (float) $item->hpp_per_satuan);
        $this->assertEquals('2 Sak 25kg (50.000 gr)', $item->displayQtyPurchased());
    }

    /**
     * TC-UOM-03: Perubahan kuantitas & satuan beli pada form Edit PO menghitung ulang base qty dan HPP.
     */
    public function test_po_update_recalculates_base_quantity_and_hpp_when_uom_factor_changes(): void
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-UOM-EDIT',
            'no_invoice' => 'INV-UOM-EDIT',
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudangUtama->id,
            'tanggal' => now(),
            'status' => 'draft',
            'skema_bayar' => 'cash',
            'subtotal_produk' => 1000000,
            'grand_total' => 1000000,
            'created_by' => $this->purchasing->id,
        ]);

        $po->items()->create([
            'produk_id' => $this->waxBahan->id,
            'qty_satuan_beli' => 2,
            'satuan_beli' => 'Sak 25kg',
            'faktor_konversi' => 25000,
            'qty' => 50000,
            'harga_total' => 1000000,
            'net_total' => 1000000,
            'hpp_per_satuan' => 20.0,
        ]);

        // Edit menjadi 1 Sak 50kg, harga 1.100.000
        $updatePayload = [
            'no_invoice' => 'INV-UOM-EDIT-UPDATED',
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudangUtama->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'cash',
            'items' => [
                [
                    'produk_id' => $this->waxBahan->id,
                    'qty_satuan_beli' => 1,
                    'satuan_beli' => 'Sak 50kg',
                    'faktor_konversi' => 50000,
                    'qty' => 50000,
                    'harga_total' => 1100000,
                ],
            ],
        ];

        $response = $this->actingAs($this->purchasing)
            ->put(route('purchasing.update', $po), $updatePayload);

        $response->assertRedirect();
        
        $po->refresh();
        $this->assertEquals(1100000, $po->grand_total);
        $item = $po->items->first();
        $this->assertEquals(1, $item->qty_satuan_beli);
        $this->assertEquals('Sak 50kg', $item->satuan_beli);
        $this->assertEquals(50000, $item->faktor_konversi);
        $this->assertEquals(50000, $item->qty);
        // HPP per gr = 1.100.000 / 50.000 = 22.0
        $this->assertEquals(22.0, (float) $item->hpp_per_satuan);
    }

    /**
     * TC-UOM-04: Penerimaan barang datang di gudang mencatat stok & mutasi mutlak dalam satuan dasar (ml/gr).
     */
    public function test_goods_receipt_records_inventory_in_base_units_and_updates_product_hpp(): void
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-UOM-RCV',
            'no_invoice' => 'INV-UOM-RCV',
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudangUtama->id,
            'tanggal' => now(),
            'status' => 'dikirim_ke_gudang',
            'skema_bayar' => 'cash',
            'subtotal_produk' => 2500000,
            'grand_total' => 2500000,
            'created_by' => $this->purchasing->id,
        ]);

        $item = $po->items()->create([
            'produk_id' => $this->oilBahan->id,
            'qty_satuan_beli' => 5,
            'satuan_beli' => 'Jerigen 5L',
            'faktor_konversi' => 5000,
            'qty' => 25000,
            'harga_total' => 2500000,
            'net_total' => 2500000,
            'hpp_per_satuan' => 100.0,
        ]);

        // Gudang menerima 25.000 ml (satuan dasar)
        $rcvPayload = [
            'gudang_id' => $this->gudangUtama->id,
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'items' => [
                [
                    'po_item_id' => $item->id,
                    'qty_diterima' => 25000,
                ],
            ],
        ];

        $response = $this->actingAs($this->gudangUser)
            ->post(route('purchasing.receive', $po), $rcvPayload);

        $response->assertRedirect();
        
        $po->refresh();
        $this->assertEquals('selesai', $po->status);

        // Verifikasi mutasi kartu stok dalam satuan dasar (ml)
        $kartu = KartuStok::where('produk_id', $this->oilBahan->id)
            ->where('gudang_id', $this->gudangUtama->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($kartu);
        $this->assertEquals('in', $kartu->tipe);
        $this->assertEquals(25000, (float) $kartu->qty);
        $this->assertEquals(25000, (float) $kartu->saldo_setelah);

        // Verifikasi saldo gudang dalam satuan dasar (ml)
        $saldo = \App\Models\Stok::where('produk_id', $this->oilBahan->id)
            ->where('gudang_id', $this->gudangUtama->id)
            ->first();
        
        $this->assertNotNull($saldo);
        $this->assertEquals(25000, (float) $saldo->qty_saat_ini);

        // Verifikasi harga_hpp produk terupdate ke nilai satuan dasar (Rp 100/ml)
        $this->oilBahan->refresh();
        $this->assertEquals(100.0, (float) $this->oilBahan->harga_hpp);
    }
}

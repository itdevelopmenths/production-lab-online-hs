<?php

namespace Tests\Feature;

use App\Models\BarangDatang;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AverageHppTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasing;

    protected Produk $alkohol;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->alkohol = Produk::where('sku', 'ALK-01')->firstOrFail();
        $this->supplier = Supplier::where('kategori', 'lokal')->firstOrFail();
    }

    private function makePo(string $noPo, Gudang $gudang, string $status, float $qty, float $hpp): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => $noPo, 'supplier_id' => $this->supplier->id, 'gudang_id' => $gudang->id,
            'tanggal' => now()->toDateString(), 'status' => $status, 'stage_aktif' => 'perencanaan',
            'status_pembayaran' => 'belum_lunas', 'skema_bayar' => 'cash',
            'subtotal_produk' => $qty * $hpp, 'grand_total' => $qty * $hpp, 'created_by' => $this->purchasing->id,
        ]);
        PurchaseOrderItem::create([
            'po_id' => $po->id, 'produk_id' => $this->alkohol->id,
            'qty' => $qty, 'harga_total' => $qty * $hpp, 'hpp_per_satuan' => $hpp,
        ]);

        return $po;
    }

    public function test_hpp_adalah_rata_rata_tertimbang_semua_po_diterima_dan_sama_di_semua_gudang(): void
    {
        $gudangA = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
        $gudangB = Gudang::where('id', '!=', $gudangA->id)->firstOrFail();

        // 1.000 × 500 (Gudang A) + 3.000 × 600 (Gudang B) → (500.000 + 1.800.000) / 4.000 = 575
        $this->makePo('PO-AVG-1', $gudangA, 'selesai', 1000, 500);
        $poB = $this->makePo('PO-AVG-2', $gudangB, 'berjalan', 3000, 600);
        BarangDatang::create([
            'po_id' => $poB->id, 'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik', 'created_by' => $this->purchasing->id,
        ]);

        // PO yang belum diterima tidak ikut dirata-rata
        $this->makePo('PO-AVG-DRAFT', $gudangA, 'draft', 10000, 9999);

        $stok = app(StokService::class);
        $stok->setAbsolut($this->alkohol->id, $gudangA->id, 100, 'fixture');
        $stok->setAbsolut($this->alkohol->id, $gudangB->id, 200, 'fixture');

        $this->assertEquals(575.0, $stok->averagePoHppMap([$this->alkohol->id])[$this->alkohol->id]);
        $this->assertEquals(575.0, $stok->resolveHppMap([$this->alkohol->id])[$this->alkohol->id]);
        $this->assertEquals(575.0, $stok->resolveHppMap()[$this->alkohol->id]);

        $stok->syncHargaHppAverage([$this->alkohol->id]);
        $this->assertEquals(575.0, (float) DB::table('produk')->where('id', $this->alkohol->id)->value('harga_hpp'));
    }

    public function test_hpp_produk_jadi_dari_bom_memakai_average_bahan_walau_hanya_produk_jadi_diminta(): void
    {
        $gudang = Gudang::where('tipe', 'bahan_baku')->firstOrFail();
        $this->makePo('PO-AVG-BOM-1', $gudang, 'selesai', 100, 400);
        $this->makePo('PO-AVG-BOM-2', $gudang, 'selesai', 100, 600);

        $produkJadi = Produk::create([
            'sku' => 'PJ-AVG-01', 'nama' => 'Produk Jadi Avg', 'tipe' => 'produk_jadi',
            'satuan' => 'pcs', 'harga_hpp' => 0,
        ]);
        DB::table('bom')->insert([
            'produk_jadi_id' => $produkJadi->id, 'bahan_id' => $this->alkohol->id, 'qty_per_unit' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 2 × average(400, 600) = 2 × 500 = 1.000
        $map = app(StokService::class)->resolveHppMap([$produkJadi->id]);
        $this->assertEquals(1000.0, $map[$produkJadi->id]);
    }
}

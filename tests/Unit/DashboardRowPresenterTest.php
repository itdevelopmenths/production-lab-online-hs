<?php

namespace Tests\Unit;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Stok;
use App\Support\Dashboard\DashboardRowPresenter;
use Tests\TestCase;

class DashboardRowPresenterTest extends TestCase
{
    private function present(mixed $item): array
    {
        return (new DashboardRowPresenter)->present($item);
    }

    public function test_presents_purchase_order_with_stage_label(): void
    {
        $po = (new PurchaseOrder)->forceFill(['id' => 7, 'no_po' => 'PO-X', 'status' => 'berjalan', 'stage_aktif' => 'approval']);
        $po->setRelation('supplier', null);

        $row = $this->present($po);

        $this->assertSame('Persetujuan Manager', $row['badge']);
        $this->assertSame('warning', $row['varian']);
        $this->assertSame('PO-X', $row['judul']);
        $this->assertSame(route('purchasing.show', $po), $row['url']);
    }

    public function test_presents_late_po_as_danger(): void
    {
        $po = (new PurchaseOrder)->forceFill(['id' => 8, 'no_po' => 'PO-L', 'status' => 'berjalan', 'stage_aktif' => 'approval']);
        $po->setRelation('supplier', null);
        $po->is_terlambat = true;
        $po->created_at = now();

        $row = $this->present($po);

        $this->assertSame('Terlambat SLA', $row['badge']);
        $this->assertSame('danger', $row['varian']);
        $this->assertSame('bad', $row['umurStatus']);
    }

    public function test_presents_queue_object_aging(): void
    {
        $make = fn ($created) => (object) [
            'type' => 'PO', 'nomor' => 'PO-1', 'meta' => 'Supplier', 'badge_label' => 'Approval',
            'badge_variant' => 'primary', 'url' => '/x', 'action_label' => 'Review PO', 'created_at' => $created,
        ];

        $tua = $this->present($make(now()->subDays(4)));
        $this->assertSame('4 hr', $tua['umur']);
        $this->assertSame('bad', $tua['umurStatus']);
        $this->assertSame('PO', $tua['jenis']);
        $this->assertSame('Review PO', $tua['aksi']);

        $kemarin = $this->present($make(now()->subDay()));
        $this->assertSame('1 hr', $kemarin['umur']);
        $this->assertSame('warn', $kemarin['umurStatus']);

        $baru = $this->present($make(now()));
        $this->assertSame('hari ini', $baru['umur']);
        $this->assertNull($baru['umurStatus']);
    }

    public function test_presents_defect_log_badge(): void
    {
        $row = $this->present((object) [
            'is_defect_log' => true, 'produk_nama' => 'Botol', 'nomor' => 'TRF-1', 'gudang_nama' => 'Lab',
            'keterangan' => 'Pecah', 'source_badge' => 'Transfer', 'qty_rusak' => 5, 'satuan' => 'pcs', 'url' => '/t',
        ]);

        $this->assertSame('[Transfer] 5 pcs Rusak', $row['badge']);
        $this->assertSame('danger', $row['varian']);
        $this->assertSame('TRF-1 · Lab · Pecah', $row['detail']);
    }

    public function test_presents_stok_low_as_danger(): void
    {
        $stok = (new Stok)->forceFill(['produk_id' => 3, 'qty_saat_ini' => 8]);
        $stok->setRelation('produk', (new Produk)->forceFill(['nama' => 'Botol', 'sku' => 'B-1', 'satuan' => 'pcs']));
        $stok->setRelation('gudang', (new Gudang)->forceFill(['nama' => 'FF Pusat']));

        $row = $this->present($stok);

        $this->assertSame('danger', $row['varian']);
        $this->assertSame('8 pcs', $row['badge']);
        $this->assertSame('FF Pusat · B-1', $row['detail']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\LeadTimeImpor;
use App\Models\Produk;
use App\Models\ProdukSupplier;
use App\Models\Supplier;
use App\Models\SupplierProdukLtSummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemandRequests;
use Tests\TestCase;

/**
 * Perbaikan dari review akhir: Lead Time tab tidak menampilkan 0 untuk LT yang sudah ada,
 * dan simulasi bandingkan-supplier impor memakai ADU ETA + periode global.
 */
class AnalisaImporFinalReviewTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemandRequests;

    private User $purchasing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
    }

    private function botol(): Produk
    {
        return Produk::create([
            'sku' => 'BTL-REV', 'nama' => 'Botol Review', 'tipe' => 'kemas', 'satuan' => 'pcs',
            'satuan_order_moq' => 100, 'profil_analisa' => 'impor',
        ]);
    }

    private function stageRow(Produk $p): array
    {
        $this->actingAs($this->purchasing);

        return collect($this->getJson(route('analisa.impor.data'))->assertOk()->json('tables.stages'))
            ->firstWhere('produk_id', $p->id);
    }

    public function test_sku_tanpa_rincian_menampilkan_total_lt_yang_berlaku(): void
    {
        $p = $this->botol();
        $this->actingAs($this->purchasing)->postJson(route('analisa.update-manual-impor'), [
            'produk_id' => $p->id, 'lead_time_average' => 45, 'lead_time_max' => 60,
        ])->assertOk();

        $row = $this->stageRow($p);

        $this->assertTrue($row['tanpa_rincian']);
        $this->assertEquals(45.0, $row['average']['total']);
        $this->assertEquals(60.0, $row['max']['total']);
        // Form edit terisi total yang sama sehingga menyimpan tanpa ubah tidak menurunkan LT.
        $this->assertEquals(45.0, $row['manual_average']['total']);
    }

    public function test_sku_dengan_rincian_tidak_ditandai_tanpa_rincian(): void
    {
        $p = $this->botol();
        $this->actingAs($this->purchasing)->postJson(route('analisa.stages-impor.update'), [
            'produk_id' => $p->id,
            'stages' => [
                ['skenario' => 'average', 'pengiriman' => 20],
                ['skenario' => 'max', 'pengiriman' => 30],
            ],
        ])->assertOk();

        $row = $this->stageRow($p);

        $this->assertFalse($row['tanpa_rincian']);
        $this->assertEquals(20.0, $row['average']['total']);
    }

    public function test_simpan_stage_dengan_total_average_nol_ditolak(): void
    {
        $p = $this->botol();
        $this->actingAs($this->purchasing)->postJson(route('analisa.update-manual-impor'), [
            'produk_id' => $p->id, 'lead_time_average' => 45, 'lead_time_max' => 60,
        ])->assertOk();

        $this->postJson(route('analisa.stages-impor.update'), [
            'produk_id' => $p->id,
            'stages' => [['skenario' => 'average'], ['skenario' => 'max']],
        ])->assertStatus(422);

        $this->assertEquals(45.0, (float) LeadTimeImpor::where('produk_id', $p->id)->value('lead_time_average'));
    }

    public function test_bandingkan_supplier_impor_memakai_adu_eta_dan_periode_global(): void
    {
        $p = $this->botol();
        $sup = Supplier::create(['nama' => 'Sup Review', 'kode' => 'SUP-REV', 'kategori' => 'impor', 'is_active' => true]);
        ProdukSupplier::updateOrCreate(['produk_id' => $p->id, 'supplier_id' => $sup->id], ['status' => 'aktif']);
        SupplierProdukLtSummary::create([
            'supplier_id' => $sup->id, 'produk_id' => $p->id, 'jumlah_transaksi' => 2,
            'average_lt_hari' => 60.0, 'max_lt_hari' => 70.0, 'diperbarui_pada' => now(),
        ]);
        // 10/hari pada jendela default; periode global 2026-07-01..2026-09-28 (90 hari) hanya memuat entri 2026-08-15.
        $dalam = $this->seedPusatDemand($p, 10, Carbon::parse('2026-08-15'));
        $this->actingAs($this->purchasing)->postJson(route('analisa.impor-periode.update'), [
            'periode_mulai' => '2026-07-01', 'periode_akhir' => '2026-09-28',
        ])->assertOk();
        $this->actingAs($this->purchasing)->postJson(route('analisa.update-manual-impor'), [
            'produk_id' => $p->id, 'review_period' => 30,
        ])->assertOk();

        $res = $this->getJson(route('analisa.compare-suppliers', $p))->assertOk();
        $row = collect($res->json('suppliers'))->firstWhere('supplier_id', $sup->id);

        $aduBase = (float) $dalam->qty / 90;
        $aduEta = $aduBase * (1 + 60 / 30);
        $this->assertEqualsWithDelta($aduEta * 60, (float) $row['simulasi_batas_minimum'], 2.0);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AnalisaImpor;
use App\Models\LeadTimeImpor;
use App\Models\LeadTimeImporStage;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rincian 8 tahap lead time bahan impor (lead_time_impor_stage), seperti Lokal.
 */
class AnalisaImporLeadTimeStageTest extends TestCase
{
    use RefreshDatabase;

    private User $purchasing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
    }

    private function botol(string $profil = 'impor'): Produk
    {
        return Produk::create([
            'sku' => 'BTL-LT', 'nama' => 'Botol LT', 'tipe' => 'kemas', 'satuan' => 'pcs',
            'satuan_order_moq' => 100, 'profil_analisa' => $profil,
        ]);
    }

    /** @return array<string, int> delapan tahap dengan total $total (sisa ke pengiriman) */
    private function tahap(int $total): array
    {
        $dasar = ['perencanaan' => 2, 'supplier_confirm' => 2, 'approval' => 2, 'payment' => 2, 'po' => 2, 'unloading' => 2, 'input' => 2];

        return $dasar + ['pengiriman' => $total - array_sum($dasar)];
    }

    private function simpan(Produk $p, int $avg, int $max)
    {
        return $this->actingAs($this->purchasing)->postJson(route('analisa.stages-impor.update'), [
            'produk_id' => $p->id,
            'stages' => [
                ['skenario' => 'average'] + $this->tahap($avg),
                ['skenario' => 'max'] + $this->tahap($max),
            ],
        ]);
    }

    public function test_simpan_stage_impor_mengisi_lead_time_impor(): void
    {
        $p = $this->botol();

        $this->simpan($p, 40, 55)->assertOk()->assertJson(['success' => true]);

        $lt = LeadTimeImpor::where('produk_id', $p->id)->firstOrFail();
        $this->assertEquals(40.0, (float) $lt->lead_time_average);
        $this->assertEquals(55.0, (float) $lt->lead_time_max);

        $ai = AnalisaImpor::where('produk_id', $p->id)->firstOrFail();
        $this->assertEquals(40.0, (float) $ai->lead_time);
        $this->assertSame('manual', $ai->sumber_lt);
        $this->assertSame(2, LeadTimeImporStage::where('produk_id', $p->id)->count());
    }

    public function test_stage_max_lebih_kecil_dari_avg_diklem(): void
    {
        $p = $this->botol();

        $this->simpan($p, 40, 30)->assertOk();

        $this->assertEquals(40.0, (float) LeadTimeImpor::where('produk_id', $p->id)->value('lead_time_max'));
    }

    public function test_impor_data_mengembalikan_tabel_stages(): void
    {
        $p = $this->botol();
        $this->simpan($p, 40, 55)->assertOk();

        $stages = collect($this->getJson(route('analisa.impor.data'))->assertOk()->json('tables.stages'));
        $row = $stages->firstWhere('produk_id', $p->id);

        $this->assertNotNull($row);
        $this->assertEquals(40.0, $row['average']['total']);
        $this->assertEquals(55.0, $row['max']['total']);
        $this->assertSame('manual', $row['sumber_lt']);
        $this->assertEquals(40.0, $row['total_average_lead_time']);
        $this->assertEquals(55.0, $row['total_max_lead_time']);
    }

    public function test_sku_tanpa_stage_tetap_muncul(): void
    {
        $p = $this->botol();

        $this->actingAs($this->purchasing);
        $stages = collect($this->getJson(route('analisa.impor.data'))->assertOk()->json('tables.stages'));
        $row = $stages->firstWhere('produk_id', $p->id);

        $this->assertNotNull($row);
        $this->assertSame('manual', $row['sumber_lt']);
        // Belum ada rincian: total mengikuti LT yang dipakai analisa (bukan 0).
        $this->assertTrue($row['tanpa_rincian']);
        $this->assertEquals($row['total_average_lead_time'], $row['average']['total']);
        $this->assertGreaterThan(0, $row['average']['total']);
    }

    public function test_sku_non_impor_ditolak(): void
    {
        $p = $this->botol('lokal');

        $this->simpan($p, 40, 55)->assertStatus(422);

        $this->assertSame(0, LeadTimeImporStage::where('produk_id', $p->id)->count());
    }

    public function test_stage_impor_butuh_analisa_manage(): void
    {
        $p = $this->botol();
        $gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $this->actingAs($gudang)->postJson(route('analisa.stages-impor.update'), [
            'produk_id' => $p->id,
            'stages' => [['skenario' => 'average'] + $this->tahap(40)],
        ])->assertForbidden();
    }
}

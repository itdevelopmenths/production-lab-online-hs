<?php

namespace Tests\Feature;

use App\Models\AnalisaImpor;
use App\Models\Produk;
use App\Models\User;
use App\Services\AnalisaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemandRequests;
use Tests\TestCase;

/**
 * ADU ETA impor = ADU Base × (1 + Avg LT ÷ Review Period). ADU Pivot sudah dihapus dari rumus.
 */
class AnalisaImporAduEtaTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemandRequests;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
    }

    private function botol(string $sku): Produk
    {
        return Produk::create([
            'sku' => $sku, 'nama' => 'Botol P50 '.$sku, 'tipe' => 'kemas', 'satuan' => 'pcs',
            'satuan_order_moq' => 108, 'profil_analisa' => 'impor',
        ]);
    }

    public function test_rumus_helper_adu_eta(): void
    {
        $this->assertEqualsWithDelta(30.0, AnalisaService::hitungAduEta(10.0, 60.0, 30), 0.0001);
        // Review Period 0 diperlakukan 30 (tanpa pembagian nol): 10 × (1 + 45/30) = 25
        $this->assertEqualsWithDelta(25.0, AnalisaService::hitungAduEta(10.0, 45.0, 0), 0.0001);
        $this->assertEqualsWithDelta(0.0, AnalisaService::hitungAduEta(0.0, 60.0, 30), 0.0001);
    }

    public function test_generate_impor_adu_eta_pakai_lead_time_dan_review_period(): void
    {
        $p = $this->botol('BTL-ETA');
        $this->seedPusatDemand($p, 10);

        $this->actingAs($this->manager);
        $this->postJson(route('analisa.update-manual-impor'), [
            'produk_id' => $p->id, 'lead_time_average' => 60, 'lead_time_max' => 70, 'review_period' => 30,
        ])->assertOk();

        $ai = AnalisaImpor::where('produk_id', $p->id)->firstOrFail();
        $lt = (float) $ai->lead_time;
        $this->assertEqualsWithDelta(
            (float) $ai->adu_base * (1 + $lt / (int) $ai->review_period),
            (float) $ai->adu_eta,
            0.001
        );
        $this->assertEquals(round((float) $ai->adu_eta * $lt), (float) $ai->minimum_stock);
    }

    public function test_grup_pivot_tidak_lagi_mempengaruhi_adu_eta(): void
    {
        $p = $this->botol('BTL-PIV');
        $this->seedPusatDemand($p, 10);
        $this->actingAs($this->manager);
        $this->postJson(route('analisa.update-manual-impor'), ['produk_id' => $p->id, 'review_period' => 30])->assertOk();

        AnalisaImpor::where('produk_id', $p->id)->update(['grup_pivot' => 'P50', 'persentase_pivot' => 0.5]);
        app(AnalisaService::class)->generateImpor($p->id, $this->manager->id);

        $ai = AnalisaImpor::where('produk_id', $p->id)->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $ai->adu_pivot, 0.0001);
        $this->assertEqualsWithDelta(
            (float) $ai->adu_base * (1 + (float) $ai->lead_time / (int) $ai->review_period),
            (float) $ai->adu_eta,
            0.001
        );
    }
}

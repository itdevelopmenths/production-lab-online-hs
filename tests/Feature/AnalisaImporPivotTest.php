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
 * Pivot komposisi baru impor (Excel Analisa Import, sheet P50 "Pivot 4"):
 * ADU Pivot = % komposisi baru × total out grup ÷ jumlah hari,
 * ADU ETA = ADU Base + ADU Pivot × Lead Time ÷ 30.
 */
class AnalisaImporPivotTest extends TestCase
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
            'sku' => $sku, 'nama' => 'Botol P50 ' . $sku, 'tipe' => 'kemas', 'satuan' => 'pcs',
            'satuan_order_moq' => 108, 'profil_analisa' => 'impor',
        ]);
    }

    private function simpanPivot(Produk $p, ?string $grup, ?float $persen): void
    {
        $this->postJson(route('analisa.update-manual-impor'), [
            'produk_id' => $p->id,
            'lead_time_average' => 30,
            'lead_time_max' => 30,
            'review_period' => 30,
            'grup_pivot' => $grup,
            'persentase_pivot' => $persen,
        ])->assertOk()->assertJson(['success' => true]);
    }

    public function test_warna_baru_tanpa_riwayat_mendapat_adu_eta_dari_pivot_grup(): void
    {
        $this->actingAs($this->manager);

        $lama = $this->botol('BTL-GREY');
        $baru = $this->botol('BTL-HIJAU');
        $this->seedPusatDemand($lama, 10); // ADU Base warna lama = 10/hari, total grup = 10 × hari

        $this->simpanPivot($lama, 'P50 HS', 50);
        $this->simpanPivot($baru, 'p50 hs ', 50); // nama grup tidak peka huruf besar/spasi

        $aiLama = AnalisaImpor::where('produk_id', $lama->id)->firstOrFail();
        $aiBaru = AnalisaImpor::where('produk_id', $baru->id)->firstOrFail();

        // ADU Pivot = 50% × (10 × hari) ÷ hari = 5
        $this->assertEqualsWithDelta(5.0, (float) $aiLama->adu_pivot, 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $aiBaru->adu_pivot, 0.001);

        // ADU ETA = ADU Base + ADU Pivot × LT/30 (LT 30 hari)
        $this->assertEqualsWithDelta(10.0, (float) $aiLama->adu_base, 0.001);
        $this->assertEqualsWithDelta(15.0, (float) $aiLama->adu_eta, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $aiBaru->adu_base, 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $aiBaru->adu_eta, 0.001);

        // Warna baru tanpa stok kini direkomendasikan PO (sebelumnya ADU 0 -> tidak pernah PO).
        // Target = floor(5 × (30 + 0 + 30)) = 300; Proyeksi = 0 - 150 = -150; Selisih = -450 -> 540 (MOQ 108)
        $this->assertSame('po', $aiBaru->status);
        $this->assertEquals(540.0, (float) $aiBaru->po);

        $row = collect($this->getJson(route('analisa.impor.data'))->assertOk()->json('data'))->firstWhere('produk_id', $baru->id);
        $this->assertSame('p50 hs', strtolower(trim($row['grup_pivot'])));
        $this->assertEquals(50.0, $row['persentase_pivot']);
        $this->assertGreaterThan(0, $row['grup_out_total']);
    }

    public function test_tanpa_pivot_adu_eta_sama_dengan_adu_base(): void
    {
        $this->actingAs($this->manager);
        $p = $this->botol('BTL-SOLO');
        $this->seedPusatDemand($p, 12);

        $this->simpanPivot($p, null, null);

        $ai = AnalisaImpor::where('produk_id', $p->id)->firstOrFail();
        $this->assertEqualsWithDelta(0.0, (float) $ai->adu_pivot, 0.0001);
        $this->assertEqualsWithDelta((float) $ai->adu_base, (float) $ai->adu_eta, 0.0001);
    }

    public function test_rumus_helper_adu_eta(): void
    {
        $this->assertEqualsWithDelta(0.0, AnalisaService::hitungAduPivot('', 0.5, ['x' => 100], 10), 0.0001);
        $this->assertEqualsWithDelta(5.0, AnalisaService::hitungAduPivot('X', 0.5, ['x' => 100], 10), 0.0001);
        // Excel P50 Biru Clear: 19,39 + 3,878 × 81,75 / 30 = 29,958
        $this->assertEqualsWithDelta(29.958, AnalisaService::hitungAduEta(19.39, 3.878, 81.75), 0.001);
    }
}

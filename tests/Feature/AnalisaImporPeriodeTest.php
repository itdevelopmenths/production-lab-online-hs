<?php

namespace Tests\Feature;

use App\Models\AnalisaImpor;
use App\Models\AnalisaSetting;
use App\Models\Produk;
use App\Models\User;
use App\Services\AnalisaService;
use App\Services\DemandAduService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemandRequests;
use Tests\TestCase;

/**
 * Periode OUT impor bersifat global (satu untuk semua SKU); yang dipakai rumus adalah jumlah harinya.
 */
class AnalisaImporPeriodeTest extends TestCase
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

    private function botol(string $sku = 'BTL-PRD'): Produk
    {
        return Produk::create([
            'sku' => $sku, 'nama' => 'Botol '.$sku, 'tipe' => 'kemas', 'satuan' => 'pcs',
            'satuan_order_moq' => 100, 'profil_analisa' => 'impor',
        ]);
    }

    private function setPeriode(?string $mulai, ?string $akhir)
    {
        return $this->actingAs($this->purchasing)->postJson(route('analisa.impor-periode.update'), [
            'periode_mulai' => $mulai,
            'periode_akhir' => $akhir,
        ]);
    }

    public function test_default_periode_mengikuti_jendela_4_bulan(): void
    {
        $w = app(AnalisaService::class)->imporWindow();

        $this->assertSame(app(DemandAduService::class)->window()['days'], $w['days']);
        $this->assertFalse($w['custom']);
    }

    public function test_update_periode_global_berlaku_untuk_semua_sku(): void
    {
        $a = $this->botol('BTL-A');
        $b = $this->botol('BTL-B');
        $this->seedPusatDemand($a, 5);
        $this->seedPusatDemand($b, 6);

        $this->setPeriode('2026-07-01', '2026-09-28')->assertOk()->assertJson(['success' => true]);

        foreach ([$a, $b] as $p) {
            $ai = AnalisaImpor::where('produk_id', $p->id)->firstOrFail();
            $this->assertSame(90, (int) $ai->jumlah_hari_periode);
            $this->assertSame('2026-07-01', $ai->periode_mulai->toDateString());
            $this->assertSame('2026-09-28', $ai->periode_akhir->toDateString());
        }

        $meta = $this->getJson(route('analisa.impor.data'))->assertOk()->json('meta.periode');
        $this->assertTrue($meta['custom']);
        $this->assertSame(90, $meta['jumlah_hari']);
        $this->assertSame('2026-07-01', $meta['mulai']);
    }

    public function test_out_hanya_dihitung_dalam_periode(): void
    {
        $p = $this->botol();
        $dalam = $this->seedPusatDemand($p, 10, Carbon::parse('2026-08-15'));
        $this->seedPusatDemand($p, 7, Carbon::parse('2026-05-01'));

        $this->setPeriode('2026-07-01', '2026-09-28')->assertOk();

        $ai = AnalisaImpor::where('produk_id', $p->id)->firstOrFail();
        $this->assertEquals(round((float) $dalam->qty), (float) $ai->out);
        $this->assertEqualsWithDelta((float) $dalam->qty / 90, (float) $ai->adu_base, 0.01);
    }

    public function test_reset_periode_kembali_ke_default(): void
    {
        $this->botol();
        $this->setPeriode('2026-07-01', '2026-09-28')->assertOk();
        $this->assertTrue(app(AnalisaService::class)->imporWindow()['custom']);

        $this->setPeriode(null, null)->assertOk();

        $this->assertFalse(app(AnalisaService::class)->imporWindow()['custom']);
    }

    public function test_periode_akhir_sebelum_mulai_ditolak(): void
    {
        $this->setPeriode('2026-09-28', '2026-07-01')->assertStatus(422)->assertJsonValidationErrors('periode_akhir');

        $this->assertNull(AnalisaSetting::getValue(AnalisaService::SETTING_IMPOR_PERIODE_MULAI));
    }

    public function test_hanya_satu_tanggal_ditolak(): void
    {
        $this->setPeriode('2026-07-01', null)->assertStatus(422)->assertJsonValidationErrors('periode_akhir');
    }

    public function test_user_tanpa_analisa_manage_ditolak(): void
    {
        $gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $this->actingAs($gudang)->postJson(route('analisa.impor-periode.update'), [
            'periode_mulai' => '2026-07-01', 'periode_akhir' => '2026-09-28',
        ])->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Models\RiwayatAnalisa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Log Riwayat Snapshot dikelompokkan Bulan → Sesi finalisasi → item,
 * dan tab Produk Jadi (Produksi) dapat difinalisasi ke riwayat.
 */
class AnalisaRiwayatBulananTest extends TestCase
{
    use RefreshDatabase;

    private User $purchasing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
    }

    private function snapshot(string $sessionId, string $tanggal, string $tipe, string $sku, string $status = 'tidak'): void
    {
        RiwayatAnalisa::create([
            'session_id' => $sessionId,
            'tanggal' => $tanggal,
            'tipe' => $tipe,
            'item_label' => $sku,
            'batas_minimum' => 10,
            'target_stock' => 20,
            'status' => $status,
            'qty_order' => $status === 'order' ? 5 : 0,
            'dicatat_oleh' => $this->purchasing->id,
            'is_locked' => true,
        ]);
    }

    public function test_riwayat_grouped_per_month_and_session_defaulting_to_latest_month(): void
    {
        $this->snapshot('SNAP-OKT-1', '2026-10-05', 'bahan_lokal', 'ALK-01', 'order');
        $this->snapshot('SNAP-NOV-1', '2026-11-03', 'bahan_lokal', 'ALK-01', 'order');
        $this->snapshot('SNAP-NOV-1', '2026-11-03', 'bahan_lokal', 'OIL-GOH');
        $this->snapshot('SNAP-NOV-2', '2026-11-30', 'bahan_impor', 'BTL-P50');

        $res = $this->actingAs($this->purchasing)->getJson(route('analisa.riwayat.data'))->assertOk();

        // Default: bulan terbaru, termasuk snapshot di hari terakhir bulan.
        $this->assertSame('2026-11', $res->json('riwayat.bulan'));
        $this->assertSame('November 2026', $res->json('riwayat.bulan_label'));
        $this->assertCount(3, $res->json('data'));

        $bulanList = $res->json('riwayat.bulan_list');
        $this->assertSame(['2026-11', '2026-10'], array_column($bulanList, 'key'));
        $this->assertSame(2, $bulanList[0]['jumlah_sesi']);
        $this->assertSame(3, $bulanList[0]['jumlah_item']);

        $sessions = collect($res->json('riwayat.sessions'))->keyBy('session_id');
        $this->assertCount(2, $sessions);
        $this->assertSame(2, $sessions['SNAP-NOV-1']['jumlah_item']);
        $this->assertSame(1, $sessions['SNAP-NOV-1']['jumlah_order']);
        $this->assertSame(['Bahan Baku Impor'], $sessions['SNAP-NOV-2']['tipe_labels']);
        $this->assertEqualsCanonicalizing(
            ['SNAP-NOV-1', 'SNAP-NOV-1', 'SNAP-NOV-2'],
            array_column($res->json('data'), 'session_key')
        );
    }

    public function test_riwayat_month_filter_returns_older_month_rows(): void
    {
        $this->snapshot('SNAP-OKT-1', '2026-10-05', 'bahan_lokal', 'ALK-01');
        $this->snapshot('SNAP-NOV-1', '2026-11-03', 'bahan_lokal', 'ALK-01');

        $res = $this->actingAs($this->purchasing)
            ->getJson(route('analisa.riwayat.data', ['bulan' => '2026-10']))
            ->assertOk();

        $this->assertSame('2026-10', $res->json('riwayat.bulan'));
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('SNAP-OKT-1', $res->json('data.0.session_id'));

        // Bulan tidak valid jatuh ke bulan terbaru, bukan error.
        $fallback = $this->actingAs($this->purchasing)
            ->getJson(route('analisa.riwayat.data', ['bulan' => '2026-13']))
            ->assertOk();
        $this->assertSame('2026-11', $fallback->json('riwayat.bulan'));
    }

    public function test_legacy_snapshot_without_session_is_grouped_per_day(): void
    {
        RiwayatAnalisa::create([
            'tanggal' => '2026-11-10',
            'tipe' => 'bahan_lokal',
            'item_label' => 'ALK-01',
            'batas_minimum' => 1,
            'target_stock' => 2,
            'status' => 'tidak',
            'qty_order' => 0,
            'dicatat_oleh' => $this->purchasing->id,
        ]);

        $res = $this->actingAs($this->purchasing)
            ->getJson(route('analisa.riwayat.data', ['bulan' => '2026-11']))
            ->assertOk();

        $this->assertSame('LEGACY-2026-11-10', $res->json('riwayat.sessions.0.key'));
        $this->assertSame('LEGACY-2026-11-10', $res->json('data.0.session_key'));
    }

    public function test_produksi_tab_can_be_finalized_to_riwayat(): void
    {
        Carbon::setTestNow('2026-11-21 10:00:00');

        $res = $this->actingAs($this->purchasing)
            ->postJson(route('analisa.finalisasi'), ['tipe' => 'produk_jadi_produksi'])
            ->assertOk();

        $sessionId = $res->json('session_id');
        $snapshots = RiwayatAnalisa::where('session_id', $sessionId)->get();

        $this->assertNotEmpty($snapshots, 'Seeder memiliki input fulfillment GOH-P50, jadi analisa produksi harus punya baris.');
        $this->assertTrue($snapshots->every(fn ($s) => $s->tipe === 'produk_jadi_produksi'));
        $this->assertArrayHasKey('gudang_nama', $snapshots->first()->detail_payload);

        // Hanya tipe produksi yang terarsip, bukan semua analisa.
        $this->assertSame(0, RiwayatAnalisa::where('session_id', $sessionId)->where('tipe', '!=', 'produk_jadi_produksi')->count());

        $riwayat = $this->getJson(route('analisa.riwayat.data'))->assertOk();
        $this->assertSame(['Produk Jadi (Produksi)'], $riwayat->json('riwayat.sessions.0.tipe_labels'));

        Carbon::setTestNow();
    }
}

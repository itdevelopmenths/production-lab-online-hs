<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DashboardChartService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardChartServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Model::preventLazyLoading(true);
    }

    private function user(string $role): User
    {
        return User::where('email', "{$role}@heavenscent.id")->firstOrFail();
    }

    private function makePo(string $no, \DateTimeInterface $tanggal, float $total, string $status = 'berjalan'): PurchaseOrder
    {
        return PurchaseOrder::create([
            'no_po' => $no,
            'supplier_id' => Supplier::firstOrFail()->id,
            'tanggal' => $tanggal,
            'status' => $status,
            'stage_aktif' => 'approval',
            'grand_total' => $total,
            'created_by' => $this->user('manager')->id,
        ]);
    }

    private function poChart(string $role = 'manager'): array
    {
        $charts = app(DashboardChartService::class)->forRole($role, $this->user($role));

        return collect($charts)->firstWhere('kunci', 'nilai_po');
    }

    public function test_po_chart_sums_po_value_and_payments_per_month(): void
    {
        $po = $this->makePo('PO-C-1', now(), 1_000_000);
        $this->makePo('PO-C-2', now()->subMonthsNoOverflow(3), 500_000);
        Payment::create(['po_id' => $po->id, 'skema' => 'cash', 'tanggal_bayar' => now(), 'nominal' => 250_000]);

        $chart = $this->poChart();

        $this->assertCount(6, $chart['kategori']);
        $this->assertSame(1_000_000.0, $chart['seri'][0]['nilai'][5]);
        $this->assertSame(500_000.0, $chart['seri'][0]['nilai'][2]);
        $this->assertSame(250_000.0, $chart['seri'][1]['nilai'][5]);
        $this->assertSame(0.0, $chart['seri'][1]['nilai'][2]);
        $this->assertSame('Rp 1 jt', $chart['seri'][0]['teks'][5]);
        $this->assertSame(1_000_000.0, $chart['maks']);
    }

    public function test_po_chart_excludes_cancelled_and_out_of_range_orders(): void
    {
        $this->makePo('PO-C-3', now(), 900_000, 'dibatalkan');
        $this->makePo('PO-C-4', now()->subMonthsNoOverflow(8), 700_000);

        $chart = $this->poChart();

        $this->assertSame(0.0, array_sum($chart['seri'][0]['nilai']));
    }

    public function test_po_chart_with_no_data_is_all_zero_and_safe_for_division(): void
    {
        $chart = $this->poChart();

        $this->assertSame(0.0, array_sum($chart['seri'][0]['nilai']));
        $this->assertGreaterThan(0, $chart['maks']);
    }

    public function test_defect_chart_returns_six_months_of_rates(): void
    {
        $charts = app(DashboardChartService::class)->forRole('gudang', $this->user('gudang'));
        $defect = collect($charts)->firstWhere('kunci', 'defect_rate');

        $this->assertCount(6, $defect['kategori']);
        $this->assertCount(6, $defect['seri'][0]['nilai']);
        $this->assertSame('0,0%', $defect['seri'][0]['teks'][5]);
    }

    public function test_charts_per_role(): void
    {
        $svc = app(DashboardChartService::class);

        $this->assertSame(['nilai_po', 'defect_rate'], array_column($svc->forRole('manager', $this->user('manager')), 'kunci'));
        $this->assertSame(['nilai_po'], array_column($svc->forRole('purchasing', $this->user('purchasing')), 'kunci'));
        $this->assertSame(['defect_rate'], array_column($svc->forRole('gudang', $this->user('gudang')), 'kunci'));
        $this->assertSame([], $svc->forRole('fulfillment', $this->user('fulfillment')));
        $this->assertSame([], $svc->forRole('qa_viewer', $this->user('manager')));
    }
}

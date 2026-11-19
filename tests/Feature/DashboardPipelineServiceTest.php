<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DashboardPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPipelineServiceTest extends TestCase
{
    use RefreshDatabase;

    private const ROLE_EMAILS = [
        'manager' => 'manager@heavenscent.id',
        'purchasing' => 'purchasing@heavenscent.id',
        'gudang' => 'gudang@heavenscent.id',
        'operasional' => 'operasional@heavenscent.id',
        'fulfillment' => 'fulfillment@heavenscent.id',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
    }

    private function makePo(string $no, string $status, string $stage): void
    {
        PurchaseOrder::create([
            'no_po' => $no,
            'supplier_id' => Supplier::firstOrFail()->id,
            'tanggal' => now(),
            'status' => $status,
            'stage_aktif' => $stage,
            'created_by' => User::firstOrFail()->id,
        ]);
    }

    public function test_purchase_order_pipeline_groups_by_stage(): void
    {
        $this->makePo('PO-P-1', 'berjalan', 'approval');
        $this->makePo('PO-P-2', 'berjalan', 'approval');
        $this->makePo('PO-P-3', 'berjalan', 'pengiriman');
        $this->makePo('PO-P-4', 'selesai', 'input_sistem');

        $user = User::where('email', self::ROLE_EMAILS['manager'])->firstOrFail();
        $alur = app(DashboardPipelineService::class)->forRole('manager', $user);

        $this->assertSame('Alur Purchase Order', $alur['judul']);
        $this->assertCount(8, $alur['tahap']);

        $byKey = collect($alur['tahap'])->keyBy('key');
        $this->assertSame(2, $byKey['approval']['jumlah']);
        $this->assertSame('warn', $byKey['approval']['status']);
        $this->assertSame(1, $byKey['pengiriman']['jumlah']);
        $this->assertSame(0, $byKey['input_sistem']['jumlah']);
        $this->assertSame(3, collect($alur['tahap'])->sum('jumlah'));
    }

    public function test_pipeline_handles_all_zero_counts(): void
    {
        foreach (self::ROLE_EMAILS as $role => $email) {
            $user = User::where('email', $email)->firstOrFail();
            $alur = app(DashboardPipelineService::class)->forRole($role, $user);

            $this->assertNotEmpty($alur['tahap'], $role);
            foreach ($alur['tahap'] as $tahap) {
                $this->assertSame(0, $tahap['jumlah'], "{$role}:{$tahap['key']}");
                $this->assertNull($tahap['status'], "{$role}:{$tahap['key']}");
                $this->assertNotEmpty($tahap['href']);
            }
        }
    }

    public function test_pipeline_returns_null_for_unknown_role(): void
    {
        $user = User::firstOrFail();

        $this->assertNull(app(DashboardPipelineService::class)->forRole('custom_x', $user));
    }

    public function test_pipeline_runs_without_lazy_loading_for_every_role(): void
    {
        foreach (self::ROLE_EMAILS as $role => $email) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertIsArray(app(DashboardPipelineService::class)->forRole($role, $user));
        }
    }
}

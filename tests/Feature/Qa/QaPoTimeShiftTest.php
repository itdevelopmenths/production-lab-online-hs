<?php

namespace Tests\Feature\Qa;

use App\Models\Gudang;
use App\Models\OperationalAudit;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchasingStageService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QaPoTimeShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $purchasing;

    private Supplier $supplier;

    private Gudang $gudang;

    private Produk $produk1;

    private PurchasingStageService $stageService;

    protected function setUp(): void
    {
        parent::setUp();

        // Env 'local' mengaktifkan QA tools; CSRF hanya di-skip otomatis saat env 'testing'.
        $this->app['env'] = 'local';
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->seed();

        config([
            'qa.lead_time_test' => true,
            'qa.clock_path' => storage_path('framework/testing/qa-clock-'.uniqid().'.json'),
        ]);

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->supplier = Supplier::active()->firstOrFail();
        $this->gudang = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $this->produk1 = Produk::bahan()->active()->firstOrFail();
        $this->stageService = app(PurchasingStageService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        @unlink(config('qa.clock_path'));

        parent::tearDown();
    }

    private function createSamplePo(?Carbon $startedAt = null): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-'.uniqid(),
            'no_invoice' => 'INV-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudang->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'tempo',
            'status' => 'draft',
            'subtotal_produk' => 600000,
            'grand_total' => 600000,
            'created_by' => $this->purchasing->id,
        ]);

        $po->items()->create([
            'produk_id' => $this->produk1->id,
            'qty' => 100,
            'harga_total' => 600000,
            'hpp_per_satuan' => 6000,
        ]);

        $this->stageService->initializePoStages($po, $startedAt ?? now()->subDays(10), $this->purchasing);

        return $po;
    }

    private function shiftUrl(PurchaseOrder $po): string
    {
        return route('qa.purchasing.shift', $po);
    }

    public function test_shift_moves_all_stage_times_back_and_keeps_durations(): void
    {
        $po = $this->createSamplePo();

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.stages.complete', [$po, PoStage::STAGE_1]), ['qa_durasi_jam' => 2])
            ->assertSessionHasNoErrors();

        $before = PoStage::where('po_id', $po->id)->get()->keyBy('id');
        $createdBefore = $po->fresh()->created_at;
        $this->assertTrue($before->contains(fn ($s) => $s->waktu_selesai !== null));
        $this->assertTrue($before->contains(fn ($s) => $s->waktu_selesai === null));

        $this->actingAs($this->purchasing)
            ->post($this->shiftUrl($po), ['hari' => 3, 'jam' => 0])
            ->assertRedirect()
            ->assertSessionHas('success', 'Waktu PO dimundurkan 3 hari 0 jam.');

        $after = PoStage::where('po_id', $po->id)->get()->keyBy('id');
        $this->assertCount($before->count(), $after);

        foreach ($before as $id => $old) {
            $new = $after[$id];
            $this->assertTrue($new->waktu_mulai->equalTo($old->waktu_mulai->copy()->subMinutes(4320)));

            if ($old->waktu_selesai === null) {
                $this->assertNull($new->waktu_selesai);
            } else {
                $this->assertTrue($new->waktu_selesai->equalTo($old->waktu_selesai->copy()->subMinutes(4320)));
            }

            $this->assertSame($old->durasi_menit, $new->durasi_menit);
        }

        $this->assertTrue($po->fresh()->created_at->equalTo($createdBefore->copy()->subMinutes(4320)));
    }

    public function test_shift_then_duration_completes_stage_in_past(): void
    {
        $po = $this->createSamplePo(now());

        $this->actingAs($this->purchasing)
            ->post($this->shiftUrl($po), ['hari' => 2, 'jam' => 0])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.stages.complete', [$po, PoStage::STAGE_1]), ['qa_durasi_hari' => 1])
            ->assertSessionHasNoErrors();

        $stage = $po->stages()->where('stage', PoStage::STAGE_1)->firstOrFail();
        $this->assertSame(1440, $stage->durasi_menit);
    }

    public function test_shift_rejected_for_final_po(): void
    {
        $po = $this->createSamplePo();
        $po->update(['status' => 'selesai']);
        $before = PoStage::where('po_id', $po->id)->pluck('waktu_mulai', 'id');

        $this->actingAs($this->purchasing)
            ->post($this->shiftUrl($po), ['hari' => 1, 'jam' => 0])
            ->assertSessionHasErrors('qa_shift');

        $this->assertEquals($before, PoStage::where('po_id', $po->id)->pluck('waktu_mulai', 'id'));
    }

    public function test_shift_rejected_for_zero(): void
    {
        $po = $this->createSamplePo();

        $this->actingAs($this->purchasing)
            ->post($this->shiftUrl($po), ['hari' => 0, 'jam' => 0])
            ->assertSessionHasErrors('jam');
    }

    public function test_shift_route_404_when_disabled(): void
    {
        $po = $this->createSamplePo();
        $this->app['env'] = 'testing';

        $this->actingAs($this->purchasing)
            ->postJson($this->shiftUrl($po), ['hari' => 1, 'jam' => 0])
            ->assertNotFound();
    }

    public function test_shift_does_not_create_audit_entries(): void
    {
        $po = $this->createSamplePo();
        $count = OperationalAudit::count();

        $this->actingAs($this->purchasing)
            ->post($this->shiftUrl($po), ['hari' => 1, 'jam' => 5])
            ->assertSessionHasNoErrors();

        $this->assertSame($count, OperationalAudit::count());
    }

    public function test_panel_visible_only_when_enabled_and_not_final(): void
    {
        $po = $this->createSamplePo();

        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $po))
            ->assertOk()
            ->assertSee($this->shiftUrl($po), false);

        $po->update(['status' => 'dibatalkan']);

        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $po))
            ->assertOk()
            ->assertDontSee($this->shiftUrl($po), false);

        $po->update(['status' => 'draft']);
        $this->app['env'] = 'testing';

        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $po))
            ->assertOk()
            ->assertDontSee($this->shiftUrl($po), false);
    }
}

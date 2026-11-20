<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Payment;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchasingStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingPaymentGateTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasing;
    protected User $manager;
    protected Supplier $supplier;
    protected Gudang $gudang;
    protected Produk $produk1;
    protected Produk $produk2;
    protected PurchasingStageService $stageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();

        $this->supplier = Supplier::active()->firstOrFail();
        $this->gudang = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();

        $produks = Produk::bahan()->active()->take(2)->get();
        $this->produk1 = $produks[0];
        $this->produk2 = $produks[1];

        $this->stageService = app(PurchasingStageService::class);
    }

    protected function createStagedPo(string $skemaBayar = 'tempo'): PurchaseOrder
    {
        $po = $this->createPoWithoutStages($skemaBayar);
        $this->stageService->initializePoStages($po, now()->subDays(10), $this->purchasing);

        return $po;
    }

    protected function createPoWithoutStages(string $skemaBayar = 'tempo', string $status = 'draft'): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-' . uniqid(),
            'no_invoice' => 'INV-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudang->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => $skemaBayar,
            'status' => $status,
            'subtotal_produk' => 1000000,
            'grand_total' => 1000000,
            'created_by' => $this->purchasing->id,
        ]);

        $po->items()->create([
            'produk_id' => $this->produk1->id,
            'qty' => 100,
            'harga_total' => 600000,
            'hpp_per_satuan' => 6000,
        ]);

        $po->items()->create([
            'produk_id' => $this->produk2->id,
            'qty' => 50,
            'harga_total' => 400000,
            'hpp_per_satuan' => 8000,
        ]);

        return $po;
    }

    protected function payload(): array
    {
        return [
            'skema' => 'tempo',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 100000,
        ];
    }

    public function test_cannot_pay_po_in_perencanaan(): void
    {
        $po = $this->createStagedPo();

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), $this->payload())
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Payment::where('po_id', $po->id)->count());
    }

    public function test_cannot_pay_po_waiting_manager_approval(): void
    {
        $po = $this->createStagedPo();
        $this->stageService->completeStage1($po, $this->purchasing);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), $this->payload())
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Payment::where('po_id', $po->id)->count());
    }

    public function test_can_pay_after_manager_approval(): void
    {
        $po = $this->createStagedPo();
        $this->stageService->completeStage1($po, $this->purchasing);
        $this->stageService->completeStage3($po, $this->purchasing);
        $this->stageService->approveStage2($po, $this->manager);
        $this->assertSame(PoStage::STAGE_4, $po->stage_aktif);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Payment::where('po_id', $po->id)->count());
    }

    public function test_payment_blocked_again_after_manager_rejects(): void
    {
        $po = $this->createStagedPo();
        $this->stageService->completeStage1($po, $this->purchasing);
        $this->stageService->completeStage3($po, $this->purchasing);
        $this->stageService->rejectStage2($po, $this->manager, 'Harga perlu dinegosiasi ulang');
        $this->assertSame(PoStage::STAGE_3, $po->fresh()->stage_aktif);
        $this->stageService->completeStage3($po->fresh(), $this->purchasing);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), $this->payload())
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Payment::where('po_id', $po->id)->count());
    }

    public function test_manager_also_cannot_pay_unapproved_po(): void
    {
        $po = $this->createStagedPo();

        $this->actingAs($this->manager)
            ->post(route('purchasing.pay', $po), $this->payload())
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Payment::where('po_id', $po->id)->count());
    }

    public function test_legacy_po_payable_only_after_approval(): void
    {
        $po = $this->createPoWithoutStages('tempo', 'diajukan');

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), $this->payload())
            ->assertSessionHasErrors('status');
        $this->assertSame(0, Payment::where('po_id', $po->id)->count());

        $po->update(['status' => 'dikirim_ke_gudang']);
        $this->flushSession();

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), $this->payload())
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Payment::where('po_id', $po->id)->count());
    }

    /**
     * Request JSON (fetch / postJson) mendapat 422 JSON validasi (bootstrap/app.php merender JSON
     * untuk request yang expectsJson). Gate tetap menolak pembayaran.
     */
    public function test_blocked_payment_via_json_request_is_still_rejected(): void
    {
        $po = $this->createStagedPo();

        $this->actingAs($this->purchasing)
            ->postJson(route('purchasing.pay', $po), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(0, Payment::where('po_id', $po->id)->count());
    }

    public function test_is_approved_for_payment_per_stage(): void
    {
        $po = $this->createStagedPo();

        foreach (PoStage::STAGES as $stage) {
            $po->stage_aktif = $stage;
            $expected = ! in_array($stage, [PoStage::STAGE_1, PoStage::STAGE_3, PoStage::STAGE_2], true);
            $this->assertSame($expected, $po->isApprovedForPayment(), "Stage {$stage}");
        }

        $po->stage_aktif = PoStage::LEGACY_STAGE_PENGEMASAN;
        $this->assertTrue($po->isApprovedForPayment());

        $po->stage_aktif = PoStage::STAGE_1;
        $po->status = 'dibatalkan';
        $this->assertFalse($po->isApprovedForPayment());

        $po->stage_aktif = PoStage::STAGE_5;
        $this->assertFalse($po->isApprovedForPayment());

        $po->status = 'disetujui';
        $this->assertTrue($po->isApprovedForPayment());
    }

    public function test_stageless_berjalan_po_can_be_paid(): void
    {
        $po = $this->createPoWithoutStages('tempo', 'berjalan');
        $this->assertTrue($po->isApprovedForPayment());

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Payment::where('po_id', $po->id)->count());
    }

    public function test_stageless_draft_and_diajukan_po_stay_blocked(): void
    {
        foreach (['draft', 'diajukan'] as $status) {
            $po = $this->createPoWithoutStages('tempo', $status);
            $this->assertFalse($po->isApprovedForPayment(), $status);

            $this->actingAs($this->purchasing)
                ->post(route('purchasing.pay', $po), $this->payload())
                ->assertSessionHasErrors('status');
            $this->flushSession();

            $this->assertSame(0, Payment::where('po_id', $po->id)->count(), $status);
        }
    }

    public function test_staged_berjalan_po_at_perencanaan_is_still_blocked(): void
    {
        $po = $this->createStagedPo();
        $po->update(['status' => 'berjalan']);

        $this->assertSame(PoStage::STAGE_1, $po->fresh()->stage_aktif);
        $this->assertFalse($po->fresh()->isApprovedForPayment());

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), $this->payload())
            ->assertSessionHasErrors('status');
        $this->assertSame(0, Payment::where('po_id', $po->id)->count());
    }

    public function test_ap_tab_shows_bayar_link_only_for_approved_po(): void
    {
        $unapproved = $this->createStagedPo();
        $legacyBerjalan = $this->createPoWithoutStages('tempo', 'berjalan');

        $rows = collect($this->actingAs($this->purchasing)
            ->getJson(route('purchasing.data-ap'))
            ->assertOk()
            ->json('data'));

        $actionFor = fn (PurchaseOrder $po) => $rows->first(fn ($r) => ($r['no_invoice'] ?? null) === $po->no_invoice)['action'] ?? '';

        $this->assertStringNotContainsString('form-pembayaran', $actionFor($unapproved));
        $this->assertStringContainsString('form-pembayaran', $actionFor($legacyBerjalan));
    }
}

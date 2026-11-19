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
        $this->stageService->approveStage2($po, $this->manager);
        $this->assertSame(PoStage::STAGE_3, $po->stage_aktif);

        $this->actingAs($this->purchasing)
            ->post(route('purchasing.pay', $po), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Payment::where('po_id', $po->id)->count());
    }

    public function test_payment_blocked_again_after_manager_rejects(): void
    {
        $po = $this->createStagedPo();
        $this->stageService->completeStage1($po, $this->purchasing);
        $this->stageService->rejectStage2($po, $this->manager, 'Harga perlu dinegosiasi ulang');
        $this->stageService->completeStage1($po, $this->purchasing);

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
     * bootstrap/app.php only forces JSON rendering for api/* paths, so a
     * ValidationException on this web route redirects back with errors even
     * for postJson (no 422). The gate must still reject the payment.
     */
    public function test_blocked_payment_via_json_request_is_still_rejected(): void
    {
        $po = $this->createStagedPo();

        $this->actingAs($this->purchasing)
            ->postJson(route('purchasing.pay', $po), $this->payload())
            ->assertSessionHasErrors('status');

        $this->assertSame(0, Payment::where('po_id', $po->id)->count());
    }

    public function test_is_approved_for_payment_per_stage(): void
    {
        $po = $this->createStagedPo();

        foreach (PoStage::STAGES as $stage) {
            $po->stage_aktif = $stage;
            $expected = ! in_array($stage, [PoStage::STAGE_1, PoStage::STAGE_2], true);
            $this->assertSame($expected, $po->isApprovedForPayment(), "Stage {$stage}");
        }

        $po->stage_aktif = PoStage::LEGACY_STAGE_PENGEMASAN;
        $this->assertTrue($po->isApprovedForPayment());

        $po->stage_aktif = PoStage::STAGE_1;
        $po->status = 'dibatalkan';
        $this->assertFalse($po->isApprovedForPayment());

        $po->stage_aktif = PoStage::STAGE_5;
        $this->assertFalse($po->isApprovedForPayment());

        $po->status = 'berjalan';
        $po->stage_aktif = null;
        $this->assertFalse($po->isApprovedForPayment());
        $po->status = 'disetujui';
        $this->assertTrue($po->isApprovedForPayment());
    }
}

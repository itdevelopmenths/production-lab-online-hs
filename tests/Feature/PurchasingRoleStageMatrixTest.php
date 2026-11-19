<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchasingStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression net untuk "permission tidak hanya di frontend":
 * setiap aksi tahap PO diuji terhadap role purchasing, gudang, manager, operasional.
 */
class PurchasingRoleStageMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasing;

    protected User $manager;

    protected User $gudangUser;

    protected User $operasional;

    protected Supplier $supplier;

    protected Gudang $gudang;

    protected Produk $produk;

    protected PurchasingStageService $stageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $this->operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();

        $this->supplier = Supplier::active()->firstOrFail();
        $this->gudang = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $this->produk = Produk::bahan()->active()->firstOrFail();

        $this->stageService = app(PurchasingStageService::class);
    }

    /**
     * Buat PO beralur 8 tahap (skema tempo) lalu majukan sebagai manager sampai tahap yang diminta.
     */
    protected function walkTo(string $targetStage): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-'.strtoupper(uniqid()),
            'no_invoice' => 'INV-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudang->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'tempo',
            'status' => 'draft',
            'subtotal_produk' => 1000000,
            'grand_total' => 1000000,
            'created_by' => $this->purchasing->id,
        ]);
        $po->items()->create([
            'produk_id' => $this->produk->id,
            'qty' => 100,
            'harga_total' => 1000000,
            'hpp_per_satuan' => 10000,
        ]);

        $this->stageService->initializePoStages($po, now()->subDays(10), $this->manager);

        $steps = [
            PoStage::STAGE_1 => fn () => $this->stageService->completeStage1($po, $this->manager),
            PoStage::STAGE_3 => fn () => $this->stageService->completeStage3($po, $this->manager),
            PoStage::STAGE_2 => fn () => $this->stageService->approveStage2($po, $this->manager),
            PoStage::STAGE_4 => fn () => $this->stageService->completeStage4Tempo($po, $this->manager),
            PoStage::STAGE_5 => fn () => $this->stageService->completeStage5($po, $this->manager),
            PoStage::STAGE_6 => fn () => $this->stageService->completeStage6($po, $this->manager, ['ekspedisi' => 'JNE']),
            PoStage::STAGE_7 => fn () => $this->stageService->completeStage7($po, $this->manager, [
                'kondisi' => 'baik',
                'items' => [
                    ['po_item_id' => $po->items()->first()->id, 'qty_fisik' => 100],
                ],
            ]),
        ];

        foreach ($steps as $stage => $advance) {
            if ($po->fresh()->stage_aktif === $targetStage) {
                break;
            }
            $advance();
        }

        $this->assertSame($targetStage, $po->fresh()->stage_aktif);

        return $po->fresh();
    }

    /**
     * @return array<string, array{0: string, 1: callable, 2: array, 3: list<string>}>
     */
    public static function actionProvider(): array
    {
        $stage = fn (string $s) => fn (PurchaseOrder $po) => route('purchasing.stages.complete', [$po, $s]);

        return [
            'submit stage 1' => [PoStage::STAGE_1, $stage(PoStage::STAGE_1), [], ['purchasing', 'manager']],
            'approve stage 2' => [PoStage::STAGE_2, $stage(PoStage::STAGE_2), [], ['manager']],
            'reject stage 2' => [PoStage::STAGE_2, fn (PurchaseOrder $po) => route('purchasing.stages.reject-stage2', $po), ['alasan' => 'Harga belum sesuai'], ['manager']],
            'confirm supplier' => [PoStage::STAGE_3, $stage(PoStage::STAGE_3), [], ['purchasing', 'manager']],
            'dibayarkan' => [PoStage::STAGE_4, fn (PurchaseOrder $po) => route('purchasing.stages.dibayarkan', $po), [], ['purchasing', 'manager']],
            'lanjut tempo' => [PoStage::STAGE_4, fn (PurchaseOrder $po) => route('purchasing.stages.lanjut-tempo', $po), [], ['purchasing', 'manager']],
            'po terbit' => [PoStage::STAGE_5, $stage(PoStage::STAGE_5), [], ['purchasing', 'manager']],
            'pengiriman' => [PoStage::STAGE_6, $stage(PoStage::STAGE_6), ['shipping' => ['ekspedisi' => 'JNE']], ['purchasing', 'gudang', 'manager']],
            'unloading' => [PoStage::STAGE_7, $stage(PoStage::STAGE_7), ['physical_check' => ['kondisi' => 'baik']], ['gudang', 'manager']],
            'input sistem' => [PoStage::STAGE_8, $stage(PoStage::STAGE_8), ['no_resi' => 'RESI-001'], ['gudang', 'manager']],
            'record payment' => [PoStage::STAGE_4, fn (PurchaseOrder $po) => route('purchasing.pay', $po), [], ['purchasing', 'manager']],
            'cancel' => [PoStage::STAGE_1, fn (PurchaseOrder $po) => route('purchasing.cancel', $po), ['alasan' => 'Batal uji matriks'], ['purchasing', 'manager']],
        ];
    }

    #[DataProvider('actionProvider')]
    public function test_role_can_or_cannot_perform_action(string $stage, callable $url, array $payload, array $allowedRoles): void
    {
        $users = [
            'purchasing' => $this->purchasing,
            'gudang' => $this->gudangUser,
            'manager' => $this->manager,
            'operasional' => $this->operasional,
        ];

        foreach ($users as $role => $user) {
            // PO baru per role agar aksi role sebelumnya tidak menggeser tahap.
            $po = $this->walkTo($stage);

            $status = $this->actingAs($user)->post($url($po), $payload)->status();

            if (in_array($role, $allowedRoles, true)) {
                $this->assertNotSame(403, $status, "Role {$role} seharusnya lolos lapisan permission pada tahap {$stage}.");
            } else {
                $this->assertSame(403, $status, "Role {$role} seharusnya mendapat 403 pada tahap {$stage}.");
            }
        }
    }

    public function test_payment_form_hidden_until_manager_approval(): void
    {
        $po = $this->walkTo(PoStage::STAGE_1);

        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $po))
            ->assertOk()
            ->assertDontSee('id="form-pembayaran"', false)
            ->assertSee('Pembayaran tersedia setelah PO disetujui Manager');

        $this->stageService->completeStage1($po, $this->manager);
        $this->stageService->completeStage3($po->fresh(), $this->manager);
        $this->stageService->approveStage2($po->fresh(), $this->manager);

        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $po->fresh()))
            ->assertOk()
            ->assertSee('id="form-pembayaran"', false);
    }
}

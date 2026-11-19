<?php

namespace Tests\Feature;

use App\Models\BarangDatang;
use App\Models\Gudang;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchasingStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingGudangAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $purchasing;

    protected User $manager;

    protected User $gudangUser;

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

        $this->supplier = Supplier::active()->firstOrFail();
        $this->gudang = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $this->produk = Produk::bahan()->active()->firstOrFail();

        $this->stageService = app(PurchasingStageService::class);
    }

    /**
     * Buat PO beralur 8 tahap lalu jalankan sampai tahap aktif yang diminta.
     */
    protected function poAtStage(string $targetStage, ?Gudang $gudang = null): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-'.strtoupper(uniqid()),
            'no_invoice' => 'INV-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'gudang_id' => ($gudang ?? $this->gudang)->id,
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

        $this->stageService->initializePoStages($po, now()->subDays(10), $this->purchasing);

        $steps = [
            PoStage::STAGE_1 => fn () => $this->stageService->completeStage1($po, $this->purchasing),
            PoStage::STAGE_2 => fn () => $this->stageService->approveStage2($po, $this->manager),
            PoStage::STAGE_3 => fn () => $this->stageService->completeStage3($po, $this->purchasing),
            PoStage::STAGE_4 => fn () => $this->stageService->completeStage4Tempo($po, $this->purchasing),
            PoStage::STAGE_5 => fn () => $this->stageService->completeStage5($po, $this->purchasing),
            PoStage::STAGE_6 => fn () => $this->stageService->completeStage6($po, $this->purchasing, ['ekspedisi' => 'JNE']),
        ];

        foreach ($steps as $stage => $advance) {
            if ($po->stage_aktif === $targetStage) {
                break;
            }
            $advance();
        }

        $this->assertSame($targetStage, $po->fresh()->stage_aktif);

        return $po->fresh();
    }

    public function test_gudang_gets_403_on_direct_url_for_steps_1_to_4(): void
    {
        foreach ([PoStage::STAGE_1, PoStage::STAGE_2, PoStage::STAGE_3, PoStage::STAGE_4] as $stage) {
            $po = $this->poAtStage($stage);

            $response = $this->actingAs($this->gudangUser)->get(route('purchasing.show', $po));

            $response->assertForbidden();
            $this->assertStringNotContainsString($po->no_po, $response->getContent(), "no_po bocor pada tahap {$stage}");
        }
    }

    public function test_gudang_can_open_po_at_step_5_po_terbit(): void
    {
        $po = $this->poAtStage(PoStage::STAGE_5);

        $this->actingAs($this->gudangUser)->get(route('purchasing.show', $po))->assertOk();
    }

    public function test_gudang_can_open_po_at_unloading(): void
    {
        $po = $this->poAtStage(PoStage::STAGE_7);

        $this->actingAs($this->gudangUser)->get(route('purchasing.show', $po))->assertOk();
    }

    public function test_gudang_cannot_complete_stage_6_on_invisible_po(): void
    {
        $po = $this->poAtStage(PoStage::STAGE_2);

        $this->actingAs($this->gudangUser)
            ->post(route('purchasing.stages.complete', [$po, PoStage::STAGE_6]), [
                'shipping' => ['ekspedisi' => 'JNE'],
            ])
            ->assertForbidden();

        $this->assertSame(PoStage::STAGE_2, $po->fresh()->stage_aktif);
    }

    public function test_purchasing_can_open_po_at_any_stage(): void
    {
        $po = $this->poAtStage(PoStage::STAGE_1);

        $this->actingAs($this->purchasing)->get(route('purchasing.show', $po))->assertOk();
    }

    public function test_legacy_po_without_stage_rows_stays_visible_to_gudang(): void
    {
        $po = $this->poAtStage(PoStage::STAGE_1);
        $po->stages()->delete();

        $this->actingAs($this->gudangUser)->get(route('purchasing.show', $po->fresh()))->assertOk();

        $ids = collect($this->actingAs($this->gudangUser)->getJson(route('purchasing.data'))->json('data'))
            ->pluck('id');
        $this->assertTrue($ids->contains($po->id));
    }

    public function test_restricted_gudang_cannot_receive_po_for_other_warehouse(): void
    {
        $other = Gudang::create([
            'kode' => 'GD-LAIN',
            'nama' => 'Gudang Bahan Lain',
            'tipe' => 'bahan_baku',
            'status' => 'aktif',
        ]);

        // Batasi user gudang secara eksplisit hanya ke gudang pusat.
        $this->gudangUser->gudangs()->attach($this->gudang->id, ['is_primary' => true]);

        $po = $this->poAtStage(PoStage::STAGE_7, $other);

        $this->actingAs($this->gudangUser)
            ->post(route('purchasing.stages.complete', [$po, PoStage::STAGE_7]), [
                'physical_check' => [
                    'kondisi' => 'baik',
                    'items' => [
                        $po->items()->first()->id => ['qty_fisik' => 100],
                    ],
                ],
            ])
            ->assertForbidden();

        $this->assertSame(0, BarangDatang::where('po_id', $po->id)->count());
        $this->assertSame(PoStage::STAGE_7, $po->fresh()->stage_aktif);
    }

    public function test_restricted_gudang_cannot_use_legacy_receive_for_other_warehouse(): void
    {
        $other = Gudang::create([
            'kode' => 'GD-LAIN2',
            'nama' => 'Gudang Bahan Lain 2',
            'tipe' => 'bahan_baku',
            'status' => 'aktif',
        ]);
        $this->gudangUser->gudangs()->attach($this->gudang->id, ['is_primary' => true]);

        $po = $this->poAtStage(PoStage::STAGE_1, $other);
        $po->stages()->delete();
        $po->update(['status' => 'dikirim_ke_gudang']);
        $item = $po->items()->first();

        $this->actingAs($this->gudangUser)
            ->post(route('purchasing.receive', $po), [
                'tanggal_terima' => now()->toDateString(),
                'kondisi' => 'baik',
                'items' => [['po_item_id' => $item->id, 'qty_diterima' => 100]],
            ])
            ->assertForbidden();

        $this->assertSame(0, BarangDatang::where('po_id', $po->id)->count());
    }

    public function test_manager_can_complete_unloading_for_po_without_warehouse(): void
    {
        $po = $this->poAtStage(PoStage::STAGE_7);
        $po->update(['gudang_id' => null]);

        $this->actingAs($this->manager)
            ->post(route('purchasing.stages.complete', [$po, PoStage::STAGE_7]), [
                'physical_check' => ['kondisi' => 'baik'],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    public function test_gudang_list_and_show_use_same_rule(): void
    {
        $stages = [
            PoStage::STAGE_1,
            PoStage::STAGE_2,
            PoStage::STAGE_3,
            PoStage::STAGE_4,
            PoStage::STAGE_5,
            PoStage::STAGE_6,
            PoStage::STAGE_7,
        ];

        $byStage = [];
        foreach ($stages as $stage) {
            $byStage[$stage] = $this->poAtStage($stage);
        }

        $this->actingAs($this->gudangUser);

        $listed = collect($this->getJson(route('purchasing.data'))->json('data'))->pluck('id')->sort()->values()->all();

        $showable = collect($byStage)
            ->filter(fn (PurchaseOrder $po) => $this->get(route('purchasing.show', $po))->status() === 200)
            ->map(fn (PurchaseOrder $po) => $po->id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame($showable, $listed);

        $visibleStages = collect($byStage)
            ->filter(fn (PurchaseOrder $po) => in_array($po->id, $listed, true))
            ->keys()
            ->all();

        $this->assertSame([
            PoStage::STAGE_5,
            PoStage::STAGE_6,
            PoStage::STAGE_7,
        ], $visibleStages);
    }
}

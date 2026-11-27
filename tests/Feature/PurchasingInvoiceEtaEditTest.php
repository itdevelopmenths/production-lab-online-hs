<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\OperationalAudit;
use App\Models\PoStage;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Authorization\PermissionCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingInvoiceEtaEditTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSION = 'purchasing.edit_invoice_eta';

    protected User $purchasing;

    protected User $manager;

    protected User $gudang;

    protected Supplier $supplier;

    protected Gudang $gudangPusat;

    protected Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $this->supplier = Supplier::active()->firstOrFail();
        $this->gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $this->produk = Produk::bahan()->active()->firstOrFail();
    }

    protected function createPo(string $stage = PoStage::STAGE_4, string $status = 'berjalan'): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-'.uniqid(),
            'no_invoice' => 'INV-LAMA',
            'eta' => now()->addDays(3)->toDateString(),
            'supplier_id' => $this->supplier->id,
            'gudang_id' => $this->gudangPusat->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'tempo',
            'status' => $status,
            'stage_aktif' => $stage,
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

        $po->stages()->create([
            'stage' => $stage,
            'urutan' => 4,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subHours(2),
        ]);

        return $po;
    }

    /** @param  list<string>  $permissions */
    protected function userWithPermissions(string $roleName, array $permissions): User
    {
        $role = Role::create([
            'name' => $roleName,
            'guard_name' => 'web',
            'display_name' => ucfirst($roleName),
            'is_system' => false,
        ]);
        $role->givePermissionTo($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function payload(array $override = []): array
    {
        return array_merge([
            'no_invoice' => 'INV-BARU-001',
            'eta' => now()->addDays(10)->toDateString(),
        ], $override);
    }

    public function test_permission_is_in_catalog_and_defaults_to_purchasing_role(): void
    {
        $this->assertContains(self::PERMISSION, app(PermissionCatalogService::class)->getAllPermissionKeys());

        $this->assertTrue($this->purchasing->can(self::PERMISSION));
        $this->assertTrue($this->manager->can(self::PERMISSION));
        $this->assertFalse($this->gudang->can(self::PERMISSION));

        foreach (['operasional@heavenscent.id', 'fulfillment@heavenscent.id'] as $email) {
            $this->assertFalse(User::where('email', $email)->firstOrFail()->can(self::PERMISSION), $email);
        }
    }

    public function test_purchasing_can_update_invoice_and_eta_in_every_stage_after_draft(): void
    {
        $stages = [
            PoStage::STAGE_3, PoStage::STAGE_2, PoStage::STAGE_4, PoStage::STAGE_5,
            PoStage::STAGE_6, PoStage::STAGE_7, PoStage::STAGE_8,
        ];

        foreach ($stages as $stage) {
            $po = $this->createPo($stage);
            $payload = $this->payload();

            $this->actingAs($this->purchasing)
                ->put(route('purchasing.invoice-eta', $po), $payload)
                ->assertRedirect(route('purchasing.show', $po))
                ->assertSessionHas('success');

            $po->refresh();
            $this->assertSame('INV-BARU-001', $po->no_invoice, $stage);
            $this->assertSame($payload['eta'], $po->eta->toDateString(), $stage);
            $this->assertSame($stage, $po->stage_aktif, "stage tidak boleh berubah ($stage)");
        }
    }

    public function test_change_is_recorded_in_operational_audit(): void
    {
        $po = $this->createPo();

        $this->actingAs($this->purchasing)
            ->put(route('purchasing.invoice-eta', $po), $this->payload())
            ->assertSessionHasNoErrors();

        $audit = OperationalAudit::where('auditable_id', (string) $po->getKey())
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertEqualsCanonicalizing(['no_invoice', 'eta'], $audit->changed_fields);
        $this->assertSame('INV-LAMA', $audit->old_values['no_invoice']);
        $this->assertSame('INV-BARU-001', $audit->new_values['no_invoice']);
        $this->assertSame($this->purchasing->id, $audit->user_id);
    }

    public function test_rejected_when_po_is_final(): void
    {
        foreach (['selesai', 'dibatalkan'] as $status) {
            $po = $this->createPo(PoStage::STAGE_8, $status);

            $this->actingAs($this->purchasing)
                ->put(route('purchasing.invoice-eta', $po), $this->payload())
                ->assertSessionHasErrors('status');

            $this->assertSame('INV-LAMA', $po->fresh()->no_invoice, $status);
        }
    }

    public function test_rejected_when_po_is_still_draft(): void
    {
        $po = $this->createPo(PoStage::STAGE_1, 'draft');

        $this->actingAs($this->purchasing)
            ->put(route('purchasing.invoice-eta', $po), $this->payload())
            ->assertSessionHasErrors('status');

        $this->assertSame('INV-LAMA', $po->fresh()->no_invoice);
    }

    public function test_role_without_permission_is_forbidden(): void
    {
        $po = $this->createPo();

        $this->actingAs($this->gudang)
            ->put(route('purchasing.invoice-eta', $po), $this->payload())
            ->assertForbidden();

        $this->assertSame('INV-LAMA', $po->fresh()->no_invoice);
    }

    public function test_manager_can_update_through_gate_bypass(): void
    {
        $po = $this->createPo();

        $this->actingAs($this->manager)
            ->put(route('purchasing.invoice-eta', $po), $this->payload())
            ->assertRedirect(route('purchasing.show', $po));

        $this->assertSame('INV-BARU-001', $po->fresh()->no_invoice);
    }

    public function test_custom_role_granted_the_permission_can_update(): void
    {
        // Route group purchasing mensyaratkan purchasing.view; permission baru hanya menambah hak ubah.
        $user = $this->userWithPermissions('admin_pembelian', ['purchasing.view', self::PERMISSION]);

        $po = $this->createPo();

        $this->actingAs($user)
            ->put(route('purchasing.invoice-eta', $po), $this->payload())
            ->assertRedirect(route('purchasing.show', $po));

        $this->assertSame('INV-BARU-001', $po->fresh()->no_invoice);
    }

    public function test_validation_rejects_too_long_invoice_and_invalid_eta(): void
    {
        $po = $this->createPo();

        $this->actingAs($this->purchasing)
            ->put(route('purchasing.invoice-eta', $po), $this->payload([
                'no_invoice' => str_repeat('A', 51),
                'eta' => 'bukan-tanggal',
            ]))
            ->assertSessionHasErrors([
                'no_invoice' => 'No. Invoice maksimal 50 karakter.',
                'eta' => 'ETA harus berupa tanggal yang valid.',
            ]);

        $po->refresh();
        $this->assertSame('INV-LAMA', $po->no_invoice);
        $this->assertSame(now()->addDays(3)->toDateString(), $po->eta->toDateString());
    }

    public function test_invoice_and_eta_can_be_cleared(): void
    {
        $po = $this->createPo();

        $this->actingAs($this->purchasing)
            ->put(route('purchasing.invoice-eta', $po), ['no_invoice' => '', 'eta' => ''])
            ->assertSessionHasNoErrors();

        $po->refresh();
        $this->assertNull($po->no_invoice);
        $this->assertNull($po->eta);
    }

    public function test_show_page_renders_trigger_and_modal_only_when_allowed(): void
    {
        $po = $this->createPo(PoStage::STAGE_4);
        $url = route('purchasing.invoice-eta', $po);

        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $po))
            ->assertOk()
            ->assertSee('Ubah Invoice &amp; ETA', false)
            ->assertSee($url, false)
            ->assertSee('form="form-invoice-eta"', false)
            ->assertSee('value="INV-LAMA"', false)
            ->assertSee('openInvoiceEtaModal: false', false);

        $viewOnly = $this->userWithPermissions('pengamat_po', ['purchasing.view']);

        $this->actingAs($viewOnly)
            ->get(route('purchasing.show', $po))
            ->assertOk()
            ->assertDontSee($url, false)
            ->assertDontSee('Ubah Invoice &amp; ETA', false);

        foreach ([['selesai', PoStage::STAGE_8], ['dibatalkan', PoStage::STAGE_4], ['draft', PoStage::STAGE_1]] as [$status, $stage]) {
            $other = $this->createPo($stage, $status);

            $this->actingAs($this->purchasing)
                ->get(route('purchasing.show', $other))
                ->assertOk()
                ->assertDontSee(route('purchasing.invoice-eta', $other), false);
        }
    }

    public function test_modal_reopens_with_errors_after_failed_submit(): void
    {
        $po = $this->createPo();

        $this->actingAs($this->purchasing)
            ->from(route('purchasing.show', $po))
            ->followingRedirects()
            ->put(route('purchasing.invoice-eta', $po), $this->payload(['no_invoice' => str_repeat('A', 51)]))
            ->assertOk()
            ->assertSee('openInvoiceEtaModal: true', false);
    }

    public function test_eta_before_po_date_is_rejected_but_same_day_is_accepted(): void
    {
        $po = $this->createPo();
        $tanggalPo = $po->tanggal->format('d/m/Y');

        $this->actingAs($this->purchasing)
            ->put(route('purchasing.invoice-eta', $po), $this->payload(['eta' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors(['eta' => "ETA tidak boleh sebelum tanggal PO ({$tanggalPo})."]);

        $po->refresh();
        $this->assertSame('INV-LAMA', $po->no_invoice);
        $this->assertSame(now()->addDays(3)->toDateString(), $po->eta->toDateString());

        $this->actingAs($this->purchasing)
            ->put(route('purchasing.invoice-eta', $po), $this->payload(['eta' => now()->toDateString()]))
            ->assertSessionHasNoErrors();

        $this->assertSame(now()->toDateString(), $po->fresh()->eta->toDateString());
    }

    public function test_modal_eta_input_has_min_attribute_set_to_po_date(): void
    {
        $po = $this->createPo();

        $this->actingAs($this->purchasing)
            ->get(route('purchasing.show', $po))
            ->assertOk()
            ->assertSee('min="'.$po->tanggal->format('Y-m-d').'"', false)
            ->assertSee('(tanggal PO)', false);
    }

    public function test_ongkir_update_recalculates_grand_total_and_item_hpp(): void
    {
        $po = $this->createPo();

        $this->actingAs($this->purchasing)
            ->put(route('purchasing.invoice-eta', $po), $this->payload(['ongkos_kirim' => 50000]))
            ->assertSessionHasNoErrors();

        $po->refresh()->load('items');
        $this->assertEquals(50000, (float) $po->ongkos_kirim);
        $this->assertEquals(1050000, (float) $po->grand_total);
        $this->assertEquals(50000, (float) $po->items->first()->ongkir);
        $this->assertEquals(10500, (float) $po->items->first()->hpp_per_satuan);
    }

    public function test_ongkir_update_rejected_when_grand_total_below_paid(): void
    {
        $po = $this->createPo();
        $po->update(['ongkos_kirim' => 100000, 'grand_total' => 1100000]);
        $po->payments()->create([
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 1100000,
            'skema' => 'tempo',
        ]);

        $this->actingAs($this->purchasing)
            ->put(route('purchasing.invoice-eta', $po), $this->payload(['ongkos_kirim' => 0]))
            ->assertSessionHasErrors('ongkos_kirim');

        $this->assertEquals(1100000, (float) $po->fresh()->grand_total);
    }
}

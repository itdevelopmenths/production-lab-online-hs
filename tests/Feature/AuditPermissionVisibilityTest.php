<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\Role;
use App\Models\StockRequest;
use App\Models\Transfer;
use App\Models\User;
use App\Services\RequestService;
use App\Services\StokService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditPermissionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected StockRequest $stockRequest;
    protected Transfer $transfer;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed();

        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $pusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $ops = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $bahan = Produk::bahan()->active()->firstOrFail();

        app(StokService::class)->masuk($bahan->id, $pusat->id, 100, 'penyesuaian', null, 'Seed Test Bahan');

        $this->stockRequest = app(RequestService::class)->create([
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $pusat->id,
            'gudang_tujuan_id' => $ops->id,
            'status' => 'diajukan',
            'items' => [['produk_id' => $bahan->id, 'qty_diminta' => 50]],
        ], $operasional);
        app(RequestService::class)->approve($this->stockRequest, $this->manager);

        $this->transfer = app(TransferService::class)->create([
            'jenis' => 'pemenuhan_request_bahan',
            'request_id' => $this->stockRequest->id,
            'gudang_asal_id' => $pusat->id,
            'gudang_tujuan_id' => $ops->id,
            'items' => [['produk_id' => $bahan->id, 'qty_dikirim' => 10]],
        ], $gudangUser);
    }

    /** @param array<int, string> $permissions */
    protected function userWithPermissions(array $permissions): User
    {
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::create([
            'name' => 'role_'.uniqid(),
            'display_name' => 'Custom Audit Test',
            'is_system' => false,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $user->fresh();
    }

    public function test_request_page_hides_audit_card_without_audit_permission(): void
    {
        $user = $this->userWithPermissions(['request.view']);

        $this->actingAs($user)->get(route('requests.show', $this->stockRequest))
            ->assertOk()
            ->assertDontSeeText('Jejak Riwayat Audit Permintaan');
    }

    public function test_request_page_shows_audit_card_with_rt_audit(): void
    {
        $user = $this->userWithPermissions(['request.view', 'rt.audit']);

        $this->actingAs($user)->get(route('requests.show', $this->stockRequest))
            ->assertOk()
            ->assertSeeText('Jejak Riwayat Audit Permintaan');
    }

    public function test_request_page_shows_audit_card_with_global_audit_view(): void
    {
        $user = $this->userWithPermissions(['request.view', 'audit.view']);

        $this->actingAs($user)->get(route('requests.show', $this->stockRequest))
            ->assertOk()
            ->assertSeeText('Jejak Riwayat Audit Permintaan');
    }

    public function test_transfer_page_hides_audit_card_without_audit_permission(): void
    {
        $user = $this->userWithPermissions(['transfer.view']);

        $this->actingAs($user)->get(route('transfers.show', $this->transfer))
            ->assertOk()
            ->assertDontSeeText('Jejak Riwayat Audit Transfer');
    }

    public function test_transfer_page_shows_audit_card_with_rt_audit(): void
    {
        $user = $this->userWithPermissions(['transfer.view', 'rt.audit']);

        $this->actingAs($user)->get(route('transfers.show', $this->transfer))
            ->assertOk()
            ->assertSeeText('Jejak Riwayat Audit Transfer');
    }

    public function test_transfer_page_shows_audit_card_with_global_audit_view(): void
    {
        $user = $this->userWithPermissions(['transfer.view', 'audit.view']);

        $this->actingAs($user)->get(route('transfers.show', $this->transfer))
            ->assertOk()
            ->assertSeeText('Jejak Riwayat Audit Transfer');
    }

    public function test_manager_sees_audit_cards(): void
    {
        $this->actingAs($this->manager)->get(route('requests.show', $this->stockRequest))
            ->assertOk()
            ->assertSeeText('Jejak Riwayat Audit Permintaan');

        $this->actingAs($this->manager)->get(route('transfers.show', $this->transfer))
            ->assertOk()
            ->assertSeeText('Jejak Riwayat Audit Transfer');
    }

    public function test_laporan_menu_hidden_for_legacy_reports_view_only(): void
    {
        $user = $this->userWithPermissions(['request.view', 'reports.view']);

        $this->actingAs($user)->get(route('requests.index'))
            ->assertOk()
            ->assertDontSeeText('Lead Time Supplier');
    }

    public function test_laporan_menu_shown_for_report_view(): void
    {
        $user = $this->userWithPermissions(['request.view', 'report.view']);

        $this->actingAs($user)->get(route('requests.index'))
            ->assertOk()
            ->assertSeeText('Lead Time Supplier');
    }
}

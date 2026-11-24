<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Produk;
use App\Models\Transfer;
use App\Models\User;
use App\Services\StokService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferOriginWarehouseScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $gudangUser;

    private Gudang $gudangPusat;

    private Gudang $gudangLab;

    private Gudang $gudangLain;

    private Produk $bahan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);

        $this->gudangPusat = Gudang::create(['nama' => 'Gudang Pusat', 'tipe' => 'bahan_baku', 'kode' => 'GD-BB', 'is_active' => true]);
        $this->gudangLain = Gudang::create(['nama' => 'Gudang Cabang', 'tipe' => 'bahan_baku', 'kode' => 'GD-BB2', 'is_active' => true]);
        $this->gudangLab = Gudang::create(['nama' => 'Lab Operasional', 'tipe' => 'operasional', 'kode' => 'GD-LAB', 'is_active' => true]);
        $this->bahan = Produk::create(['nama' => 'Daun Teh', 'sku' => 'BB-TEH', 'tipe' => 'bahan_baku', 'satuan' => 'Kg', 'is_active' => true]);

        $manager = User::factory()->create();
        $manager->assignRole('manager');
        foreach ([$this->gudangPusat, $this->gudangLain] as $g) {
            app(StokService::class)->masuk($this->bahan->id, $g->id, 500, 'Saldo Awal Tes', null, $manager->id);
        }

        $this->gudangUser = User::factory()->create(['warehouse_access_type' => 'restricted']);
        $this->gudangUser->assignRole('gudang');
        $this->gudangUser->gudangs()->attach($this->gudangPusat->id);
    }

    private function payload(Gudang $asal): array
    {
        return [
            'jenis' => 'antar_fulfillment',
            'gudang_asal_id' => $asal->id,
            'gudang_tujuan_id' => $this->gudangLab->id,
            'action' => 'save_draft',
            'items' => [['produk_id' => $this->bahan->id, 'qty_dikirim' => 10]],
        ];
    }

    public function test_user_can_create_transfer_from_own_warehouse(): void
    {
        $this->actingAs($this->gudangUser)
            ->post(route('transfers.store'), $this->payload($this->gudangPusat))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Transfer::count());
    }

    public function test_user_cannot_create_transfer_from_other_warehouse(): void
    {
        $this->actingAs($this->gudangUser)
            ->post(route('transfers.store'), $this->payload($this->gudangLain))
            ->assertSessionHasErrors('gudang_asal_id');

        $this->assertSame(0, Transfer::count());
    }

    public function test_permission_allows_transfer_from_any_warehouse(): void
    {
        $this->gudangUser->givePermissionTo('transfer.asal_semua_gudang');

        $this->actingAs($this->gudangUser)
            ->post(route('transfers.store'), $this->payload($this->gudangLain))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Transfer::count());
    }

    public function test_create_form_lists_only_own_warehouses_as_origin(): void
    {
        $ids = fn ($response) => $response->viewData('warehousesAsal')->pluck('id')->all();

        $response = $this->actingAs($this->gudangUser)->get(route('transfers.create'))->assertOk();
        $this->assertSame([$this->gudangPusat->id], $ids($response));

        $this->gudangUser->givePermissionTo('transfer.asal_semua_gudang');
        $response = $this->actingAs($this->gudangUser)->get(route('transfers.create'))->assertOk();
        $this->assertCount(3, $ids($response));
    }
}

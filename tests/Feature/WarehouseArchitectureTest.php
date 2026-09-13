<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->manager = User::factory()->create([
            'warehouse_access_type' => 'global',
        ]);
        $this->manager->assignRole('manager');
    }

    public function test_existing_warehouses_have_proper_is_pusat_and_unified_fulfillment_types(): void
    {
        $gBahanPusat = Gudang::where('kode', 'GD-PUSAT')->first();
        $this->assertNotNull($gBahanPusat);
        $this->assertTrue($gBahanPusat->is_pusat);
        $this->assertTrue($gBahanPusat->isPusat());
        $this->assertEquals('bahan_baku', $gBahanPusat->tipe);

        $ffPusat = Gudang::where('kode', 'FF-PUSAT')->first();
        $this->assertNotNull($ffPusat);
        $this->assertTrue($ffPusat->is_pusat);
        $this->assertTrue($ffPusat->isPusat());
        $this->assertTrue($ffPusat->isFulfillment());
        $this->assertEquals('fulfillment', $ffPusat->tipe);

        $ffSby = Gudang::where('kode', 'FF-SBY')->first();
        $this->assertNotNull($ffSby);
        $this->assertFalse($ffSby->is_pusat);
        $this->assertTrue($ffSby->isFulfillment());
        $this->assertEquals('fulfillment', $ffSby->tipe);
        $this->assertEquals($ffPusat->id, $ffSby->parent_gudang_id);
    }

    public function test_can_create_gudang_pusat_via_controller(): void
    {
        $response = $this->actingAs($this->manager)->post(route('gudang.store'), [
            'kode' => 'GD-BB-NEW',
            'nama' => 'Gudang Bahan Baku Utama Baru',
            'tipe' => 'bahan_baku',
            'is_pusat' => 1,
            'status' => 'aktif',
            'allow_negative_stock' => 0,
        ]);

        $response->assertRedirect(route('gudang.index'));

        $newPusat = Gudang::where('kode', 'GD-BB-NEW')->first();
        $this->assertNotNull($newPusat);
        $this->assertTrue($newPusat->is_pusat);
        $this->assertNull($newPusat->parent_gudang_id);

        // Previous pusat of same type should be demoted
        $oldPusat = Gudang::where('kode', 'GD-PUSAT')->first();
        $this->assertFalse($oldPusat->is_pusat);

        // Model scopes should return the new one
        $currentPusat = Gudang::bahanBakuPusat()->first();
        $this->assertEquals($newPusat->id, $currentPusat->id);
    }

    public function test_can_create_fulfillment_cabang_with_parent(): void
    {
        $ffPusat = Gudang::where('kode', 'FF-PUSAT')->first();

        $response = $this->actingAs($this->manager)->post(route('gudang.store'), [
            'kode' => 'FF-BDG',
            'nama' => 'Fulfillment Bandung',
            'tipe' => 'fulfillment',
            'is_pusat' => 0,
            'parent_gudang_id' => $ffPusat->id,
            'status' => 'aktif',
            'allow_negative_stock' => 0,
        ]);

        $response->assertRedirect(route('gudang.index'));

        $ffBdg = Gudang::where('kode', 'FF-BDG')->first();
        $this->assertNotNull($ffBdg);
        $this->assertFalse($ffBdg->is_pusat);
        $this->assertEquals($ffPusat->id, $ffBdg->parent_gudang_id);
        $this->assertTrue($ffBdg->isFulfillment());

        // Scope fulfillment includes both pusat and cabang
        $allFf = Gudang::fulfillment()->pluck('kode')->toArray();
        $this->assertContains('FF-PUSAT', $allFf);
        $this->assertContains('FF-BDG', $allFf);
    }

    public function test_updating_gudang_to_pusat_clears_parent_and_demotes_other(): void
    {
        $ffPusat = Gudang::where('kode', 'FF-PUSAT')->first();
        $ffSby = Gudang::where('kode', 'FF-SBY')->first();

        $response = $this->actingAs($this->manager)->put(route('gudang.update', $ffSby), [
            'kode' => $ffSby->kode,
            'nama' => $ffSby->nama,
            'tipe' => 'fulfillment',
            'is_pusat' => 1,
            'parent_gudang_id' => $ffPusat->id, // should be forced to null
            'status' => 'aktif',
            'allow_negative_stock' => 0,
        ]);

        $response->assertRedirect(route('gudang.index'));

        $ffSby->refresh();
        $this->assertTrue($ffSby->is_pusat);
        $this->assertNull($ffSby->parent_gudang_id);

        $ffPusat->refresh();
        $this->assertFalse($ffPusat->is_pusat);
    }

    public function test_datatable_renders_pusat_badge(): void
    {
        $response = $this->actingAs($this->manager)->getJson(route('gudang.data'));

        $response->assertOk();
        $data = $response->json('data');

        $pusatRow = collect($data)->firstWhere('kode', 'GD-PUSAT');
        $this->assertNotNull($pusatRow);
        $this->assertStringContainsString('Pusat', $pusatRow['nama']);
    }
}

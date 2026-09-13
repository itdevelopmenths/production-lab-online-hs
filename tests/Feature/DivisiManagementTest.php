<?php

namespace Tests\Feature;

use App\Models\Divisi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisiManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $staffGudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->staffGudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
    }

    public function test_unauthenticated_cannot_access_divisi(): void
    {
        $response = $this->get(route('divisi.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_access_divisi(): void
    {
        $this->actingAs($this->staffGudang);

        $response = $this->get(route('divisi.index'));
        $response->assertForbidden();

        $responseJson = $this->getJson(route('divisi.data'));
        $responseJson->assertForbidden();
    }

    public function test_manager_can_access_divisi_index_and_datatables(): void
    {
        $this->actingAs($this->manager);

        $response = $this->get(route('divisi.index'));
        $response->assertOk();
        $response->assertSee('Master Divisi & Departemen');

        $jsonResponse = $this->getJson(route('divisi.data'));
        $jsonResponse->assertOk();
        $jsonResponse->assertJsonStructure(['data']);
    }

    public function test_manager_can_access_divisi_create_view(): void
    {
        $this->actingAs($this->manager);

        $response = $this->get(route('divisi.create'));
        $response->assertOk();
        $response->assertSee('Tambah Divisi Baru');
        $response->assertSee('name="nama"', false);
        $response->assertSee('name="deskripsi"', false);
    }

    public function test_manager_can_access_divisi_edit_view(): void
    {
        $this->actingAs($this->manager);

        $divisi = Divisi::firstOrFail();

        $response = $this->get(route('divisi.edit', $divisi));
        $response->assertOk();
        $response->assertSee('Edit Divisi / Departemen');
        $response->assertSee('name="nama"', false);
        $response->assertSee(e($divisi->nama), false);
    }

    public function test_manager_can_create_new_divisi_with_auto_slug_and_color(): void
    {
        $this->actingAs($this->manager);

        $response = $this->post(route('divisi.store'), [
            'nama' => 'Research & Development',
            'kode' => 'rnd',
            'color' => 'cyan',
            'deskripsi' => 'Divisi riset dan peracikan formula baru',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('divisi.index'));
        $this->assertDatabaseHas('divisi', [
            'kode' => 'rnd',
            'nama' => 'Research & Development',
            'color' => 'cyan',
            'is_active' => true,
        ]);
    }

    public function test_divisi_kode_must_be_unique(): void
    {
        $this->actingAs($this->manager);

        // 'purchasing' sudah ada dari seeder
        $response = $this->post(route('divisi.store'), [
            'nama' => 'Purchasing Duplikat',
            'kode' => 'purchasing',
            'color' => 'amber',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('kode');
    }

    public function test_manager_can_update_divisi_and_badge_color(): void
    {
        $this->actingAs($this->manager);

        $divisi = Divisi::where('kode', 'produksi')->firstOrFail();

        $response = $this->put(route('divisi.update', $divisi), [
            'nama' => 'Produksi, Laboratorium & R&D',
            'kode' => 'produksi',
            'color' => 'teal',
            'deskripsi' => 'Penggabungan unit lab dan riset',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('divisi.index'));
        $this->assertDatabaseHas('divisi', [
            'id' => $divisi->id,
            'nama' => 'Produksi, Laboratorium & R&D',
            'color' => 'teal',
        ]);
    }

    public function test_updating_divisi_name_reflects_in_user_divisi_label(): void
    {
        $this->actingAs($this->manager);

        $divisi = Divisi::where('kode', 'produksi')->firstOrFail();
        $userProduksi = User::where('divisi', 'produksi')->orWhere('divisi_id', $divisi->id)->firstOrFail();

        // Update nama divisi
        $divisi->update([
            'nama' => 'Unit Manufaktur & Formulasi Lab',
        ]);

        // Refresh user model and assert label updated dynamically
        $userProduksi->refresh();
        $this->assertEquals('Unit Manufaktur & Formulasi Lab', $userProduksi->divisiLabel());
    }

    public function test_divisi_cannot_be_deleted_if_used_by_active_users(): void
    {
        $this->actingAs($this->manager);

        $divisiGudang = Divisi::where('kode', 'gudang')->firstOrFail();
        $this->assertGreaterThan(0, $divisiGudang->users()->count());

        // Coba hapus via API / AJAX
        $response = $this->deleteJson(route('divisi.destroy', $divisiGudang));
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);

        // Data tetap ada di database
        $this->assertDatabaseHas('divisi', [
            'id' => $divisiGudang->id,
            'kode' => 'gudang',
        ]);
    }

    public function test_divisi_can_be_deleted_when_no_users_are_assigned(): void
    {
        $this->actingAs($this->manager);

        // Buat divisi kosong tanpa user
        $emptyDivisi = Divisi::create([
            'nama' => 'Divisi Sementara',
            'kode' => 'divisi_sementara',
            'color' => 'slate',
            'is_active' => true,
        ]);

        $response = $this->deleteJson(route('divisi.destroy', $emptyDivisi));
        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseMissing('divisi', [
            'id' => $emptyDivisi->id,
        ]);
    }

    public function test_user_store_with_divisi_id_syncs_properly(): void
    {
        $this->actingAs($this->manager);

        $divisiQc = Divisi::where('kode', 'qc')->firstOrFail();

        $response = $this->post(route('users.store'), [
            'name' => 'Staf QC Baru',
            'email' => 'qc.baru@heavenscent.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'divisi_id' => $divisiQc->id,
            'role' => 'operasional',
            'warehouse_access_type' => 'global',
        ]);

        $response->assertRedirect(route('users.index'));

        $newUser = User::where('email', 'qc.baru@heavenscent.id')->firstOrFail();
        $this->assertEquals($divisiQc->id, $newUser->divisi_id);
        $this->assertEquals('qc', $newUser->divisi);
        $this->assertEquals($divisiQc->nama, $newUser->divisiLabel());
    }
}

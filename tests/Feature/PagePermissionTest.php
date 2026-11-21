<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Setiap halaman/menu punya izinnya sendiri di matriks role,
 * dan izin lama (modul-level) tetap memberi akses yang sama.
 */
class PagePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function userWith(array $permissions): User
    {
        $role = Role::create([
            'name' => 'uji_'.md5(implode(',', $permissions)),
            'guard_name' => 'web',
            'display_name' => 'Role Uji',
            'is_system' => false,
        ]);
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_semua_permission_di_route_terdaftar_di_katalog(): void
    {
        $catalog = app(PermissionCatalogService::class)->getAllPermissionKeys();

        $missing = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $mw) {
                if (is_string($mw) && str_starts_with($mw, 'can:')) {
                    $perm = explode(',', substr($mw, 4))[0];
                    if (! in_array($perm, $catalog, true)) {
                        $missing[$perm] = $route->getName();
                    }
                }
            }
        }

        $this->assertSame([], $missing, 'Permission di route belum ada di PermissionCatalogService.');
    }

    public function test_izin_produk_jadi_saja_hanya_membuka_menu_produk_jadi(): void
    {
        $user = $this->userWith(['produk.produk_jadi.view', 'produk.produk_jadi.create']);
        $this->actingAs($user);

        $this->get(route('produk.index'))->assertRedirect(route('produk.index', ['kelompok' => 'produk_jadi']));
        $this->get(route('produk.index', ['kelompok' => 'produk_jadi']))
            ->assertOk()
            ->assertSee('Tambah Produk Jadi')
            ->assertDontSee('>Bahan Baku &amp; Kemasan<', false);

        $this->getJson(route('produk.data', ['kelompok' => 'bahan']))->assertForbidden();
        $this->getJson(route('produk.data', ['kelompok' => 'produk_jadi', 'draw' => 1]))->assertOk();

        $this->get(route('produk.create'))->assertForbidden();
        $this->post(route('produk.store'), ['sku' => 'X-BHN', 'nama_produk' => 'X', 'tipe' => 'bahan', 'satuan' => 'ml'])
            ->assertForbidden();

        // Kategori/Varian punya izin sendiri
        $this->get(route('kategori.index'))->assertForbidden();
        $this->get(route('varian.index'))->assertForbidden();
    }

    public function test_izin_bahan_view_tanpa_create_tidak_menampilkan_tombol_tambah(): void
    {
        $this->actingAs($this->userWith(['produk.bahan.view']));

        $this->get(route('produk.index'))->assertOk()->assertDontSee('Tambah Bahan / Kemasan');
        // Tanpa izin Produk Jadi: diarahkan kembali ke menunya sendiri, data Produk Jadi ditolak.
        $this->get(route('produk.index', ['kelompok' => 'produk_jadi']))->assertRedirect(route('produk.index'));
        $this->getJson(route('produk.data', ['kelompok' => 'produk_jadi']))->assertForbidden();
    }

    public function test_izin_produk_global_lama_tetap_membuka_semua_halaman_master(): void
    {
        $this->actingAs($this->userWith(['produk.view', 'produk.create']));

        $this->get(route('produk.index'))->assertOk()->assertSee('Tambah Bahan / Kemasan');
        $this->get(route('produk.index', ['kelompok' => 'produk_jadi']))->assertOk()->assertSee('Tambah Produk Jadi');
        $this->get(route('kategori.index'))->assertOk();
        $this->get(route('varian.index'))->assertOk();
    }

    public function test_kategori_dan_varian_bisa_diberikan_tanpa_izin_produk(): void
    {
        $this->actingAs($this->userWith(['kategori.view', 'varian.view']));

        $this->get(route('kategori.index'))->assertOk();
        $this->get(route('varian.index'))->assertOk();
        $this->get(route('produk.index'))->assertForbidden();
    }

    public function test_klasifikasi_abc_punya_izin_sendiri(): void
    {
        $this->actingAs($this->userWith(['klasifikasi_abc.view']));
        $this->get(route('klasifikasi-abc.index'))->assertOk();
        $this->get(route('analisa.index'))->assertForbidden();
        $this->postJson(route('klasifikasi-abc.store'), [])->assertForbidden();
    }

    public function test_log_pergerakan_punya_izin_sendiri(): void
    {
        $this->actingAs($this->userWith(['stok.log.view']));
        $this->get(route('stok.log-pergerakan'))->assertOk();
        $this->get(route('stok.index'))->assertForbidden();

        $this->actingAs($this->userWith(['stok.view']));
        $this->get(route('stok.log-pergerakan'))->assertOk();
    }

    public function test_barang_rusak_memakai_izin_defect_view(): void
    {
        $this->actingAs($this->userWith(['transfer.view']));
        $this->get(route('barang-rusak.index'))->assertForbidden();
        $this->get(route('transfers.index'))->assertOk()->assertDontSee(route('barang-rusak.index'), false);

        $this->actingAs($this->userWith(['defect.view']));
        $this->get(route('barang-rusak.index'))->assertOk();
    }

    public function test_role_sistem_hasil_seeder_memakai_izin_granular(): void
    {
        $gudang = Role::where('name', 'gudang')->firstOrFail();

        $this->assertTrue($gudang->hasPermissionTo('produk.bahan.view'));
        $this->assertTrue($gudang->hasPermissionTo('produk.produk_jadi.view'));
        $this->assertTrue($gudang->hasPermissionTo('kategori.view'));
        $this->assertTrue($gudang->hasPermissionTo('stok.log.view'));
        $this->assertFalse($gudang->hasPermissionTo('produk.view'));

        $user = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $this->assertTrue($user->can('produk.view'));
    }

    public function test_migrasi_backfill_memberi_izin_granular_ke_role_lama(): void
    {
        $role = Role::create(['name' => 'role_lama', 'guard_name' => 'web', 'display_name' => 'Role Lama', 'is_system' => false]);
        $role->syncPermissions(['produk.view', 'produk.edit', 'analisa.manage', 'stok.view']);

        $migration = require database_path('migrations/2026_11_24_000001_add_granular_page_permissions.php');
        $migration->up();

        $role->refresh()->load('permissions');
        foreach (['produk.bahan.view', 'produk.produk_jadi.view', 'produk.bahan.edit', 'produk.produk_jadi.edit',
            'kategori.view', 'kategori.edit', 'varian.view', 'varian.edit', 'klasifikasi_abc.manage', 'stok.log.view'] as $perm) {
            $this->assertTrue($role->hasPermissionTo($perm), "Role lama harus mendapat {$perm}");
        }
        $this->assertFalse($role->hasPermissionTo('produk.bahan.delete'));
        $this->assertFalse($role->hasPermissionTo('klasifikasi_abc.view'));
    }

    public function test_matriks_role_menampilkan_izin_halaman_baru(): void
    {
        $this->actingAs(User::where('email', 'manager@heavenscent.id')->firstOrFail());

        $this->get(route('roles.create'))
            ->assertOk()
            ->assertSee('produk.produk_jadi.view')
            ->assertSee('kategori.view')
            ->assertSee('klasifikasi_abc.manage')
            ->assertSee('stok.log.view');
    }
}

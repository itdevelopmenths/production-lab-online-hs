<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterCreateModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'manager@heavenscent.id')->firstOrFail());
    }

    public static function modules(): array
    {
        return [['divisi'], ['gudang'], ['kategori'], ['produk'], ['supplier'], ['uom'], ['varian']];
    }

    #[DataProvider('modules')]
    public function test_index_embeds_create_modal_posting_to_store(string $module): void
    {
        $this->get(route("$module.index"))
            ->assertOk()
            ->assertSee('createOpen', false)
            ->assertSee('action="'.route("$module.store").'"', false)
            ->assertSee('name="_modal" value="create"', false);
    }

    public function test_tambah_query_opens_modal_by_default(): void
    {
        $this->get(route('uom.index', ['tambah' => 1]))
            ->assertOk()
            ->assertSee('createOpen: true', false);

        $this->get(route('uom.index'))->assertSee('createOpen: false', false);
    }
}

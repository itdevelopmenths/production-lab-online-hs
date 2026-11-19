<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\KartuStok;
use App\Models\Produk;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rute legacy submit/approve/receive tidak boleh dipakai PO alur 8 tahap
 * (PO hasil store()), supaya tahap tidak bisa dilompati lewat POST langsung.
 */
class PurchasingLegacyRouteGuardTest extends TestCase
{
    use RefreshDatabase;

    private const STAGED_MESSAGE = 'PO ini memakai alur 8 tahap. Gunakan tombol pada panel Eksekusi Tahap Aktif.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Model::preventLazyLoading(true);
    }

    private function user(string $prefix): User
    {
        return User::where('email', $prefix . '@heavenscent.id')->firstOrFail();
    }

    private function createStagedPo(): PurchaseOrder
    {
        $this->actingAs($this->user('purchasing'));

        $this->post(route('purchasing.store'), [
            'supplier_id' => Supplier::active()->firstOrFail()->id,
            'tanggal' => now()->toDateString(),
            'eta' => now()->addDays(7)->toDateString(),
            'sumber_dana' => 'BCA Operasional',
            'items' => [
                [
                    'produk_id' => Produk::bahan()->active()->firstOrFail()->id,
                    'qty' => 100,
                    'harga_total' => 1000000,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::latest('id')->firstOrFail();
        $this->assertTrue($po->stages()->exists(), 'PO hasil store() harus punya baris po_stage.');

        return $po;
    }

    private function createUnstagedPo(): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'no_po' => 'PO-LEGACY-' . uniqid(),
            'supplier_id' => Supplier::active()->firstOrFail()->id,
            'gudang_id' => Gudang::where('kode', 'GD-PUSAT')->firstOrFail()->id,
            'tanggal' => now()->toDateString(),
            'skema_bayar' => 'cash',
            'status' => 'draft',
            'status_pembayaran' => 'belum_lunas',
            'subtotal_produk' => 1000000,
            'grand_total' => 1000000,
            'created_by' => $this->user('purchasing')->id,
        ]);

        $po->items()->create([
            'produk_id' => Produk::bahan()->active()->firstOrFail()->id,
            'qty' => 100,
            'harga_total' => 1000000,
            'net_total' => 1000000,
            'hpp_per_satuan' => 10000,
        ]);

        $this->assertFalse($po->stages()->exists());

        return $po;
    }

    public function test_legacy_submit_rejected_for_staged_po(): void
    {
        $po = $this->createStagedPo();

        $this->actingAs($this->user('purchasing'));
        $this->post(route('purchasing.submit', $po))->assertStatus(422);

        $this->assertSame('draft', $po->fresh()->status);
    }

    public function test_legacy_approve_rejected_for_staged_po(): void
    {
        $po = $this->createStagedPo();
        $po->update(['status' => 'diajukan']);

        $this->actingAs($this->user('manager'));
        $this->post(route('purchasing.approve', $po))->assertStatus(422);

        $this->assertSame('diajukan', $po->fresh()->status);
    }

    public function test_legacy_receive_rejected_for_staged_po(): void
    {
        $po = $this->createStagedPo();
        $po->update(['status' => 'dikirim_ke_gudang']);
        $po->load('items');

        $kartuBefore = KartuStok::whereIn('produk_id', $po->items->pluck('produk_id'))->count();

        $this->actingAs($this->user('gudang'));
        $this->post(route('purchasing.receive', $po), [
            'gudang_id' => Gudang::where('kode', 'GD-PUSAT')->firstOrFail()->id,
            'tanggal_terima' => now()->toDateString(),
            'kondisi' => 'baik',
            'items' => [
                ['po_item_id' => $po->items->first()->id, 'qty_diterima' => 100],
            ],
        ])->assertStatus(422);

        $this->assertNotSame('selesai', $po->fresh()->status);
        $this->assertSame('dikirim_ke_gudang', $po->fresh()->status);
        $this->assertSame(
            $kartuBefore,
            KartuStok::whereIn('produk_id', $po->items->pluck('produk_id'))->count()
        );
    }

    public function test_legacy_route_still_works_for_unstaged_po(): void
    {
        $po = $this->createUnstagedPo();

        $this->actingAs($this->user('purchasing'));
        $this->post(route('purchasing.submit', $po))->assertSessionHas('success');
        $this->assertSame('diajukan', $po->fresh()->status);

        $this->actingAs($this->user('manager'));
        $this->post(route('purchasing.approve', $po))->assertSessionHas('success');
        $this->assertSame('dikirim_ke_gudang', $po->fresh()->status);
    }
}

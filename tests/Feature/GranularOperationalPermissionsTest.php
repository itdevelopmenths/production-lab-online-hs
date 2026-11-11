<?php

namespace Tests\Feature;

use App\Models\BatchProduksi;
use App\Models\Gudang;
use App\Models\Produk;
use App\Models\Role;
use App\Models\StockRequest;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Authorization\InventoryTransferAuthorizationService;
use App\Services\RequestService;
use App\Services\StokService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GranularOperationalPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $operasional;
    protected User $fulfillment;
    protected User $gudangUser;
    protected Gudang $gudangPusat;
    protected Gudang $gudangOps;
    protected Gudang $gudangFf;
    protected Produk $bahan;
    protected Produk $produkJadi;
    protected StokService $stokService;
    protected TransferService $transferService;
    protected RequestService $requestService;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed();

        $this->manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->operasional = User::where('email', 'operasional@heavenscent.id')->firstOrFail();
        $this->fulfillment = User::where('email', 'fulfillment@heavenscent.id')->firstOrFail();
        $this->gudangUser = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $this->gudangPusat = Gudang::where('kode', 'GD-PUSAT')->firstOrFail();
        $this->gudangOps = Gudang::where('kode', 'GD-OPS')->firstOrFail();
        $this->gudangFf = Gudang::where('kode', 'FF-PUSAT')->firstOrFail();

        $this->bahan = Produk::bahan()->active()->firstOrFail();
        $this->produkJadi = Produk::produkJadi()->active()->firstOrFail();

        $this->stokService = app(StokService::class);
        $this->transferService = app(TransferService::class);
        $this->requestService = app(RequestService::class);
    }

    public function test_authorization_service_classifies_transfer_payload_with_smuggled_bahan_item(): void
    {
        $authz = app(InventoryTransferAuthorizationService::class);

        $categories = $authz->resolveCategoriesForTransferPayload(
            'kirim_produk_jadi',
            null,
            [$this->bahan->id]
        );

        sort($categories);
        $this->assertSame(['bahan', 'produk_jadi'], $categories);
        $this->assertFalse($authz->canCreateTransferWithCategories($this->operasional, $categories));
        $this->assertTrue($authz->canCreateTransferWithCategories($this->gudangUser, $categories));

        $mismatchError = $authz->resolveExplicitJenisItemMismatch('kirim_produk_jadi', [$this->bahan->id]);
        $this->assertNotNull($mismatchError);

        $validMismatch = $authz->resolveExplicitJenisItemMismatch('kirim_produk_jadi', [$this->produkJadi->id]);
        $this->assertNull($validMismatch);
    }

    public function test_authorization_service_resolves_antar_fulfillment_by_items(): void
    {
        $authz = app(InventoryTransferAuthorizationService::class);

        $pjCategories = $authz->resolveCategoriesForTransferPayload('antar_fulfillment', null, [$this->produkJadi->id]);
        $this->assertSame(['produk_jadi'], $pjCategories);
        $this->assertTrue($authz->canCreateTransferWithCategories($this->operasional, $pjCategories));

        $bahanCategories = $authz->resolveCategoriesForTransferPayload('antar_fulfillment', null, [$this->bahan->id]);
        $this->assertSame(['bahan'], $bahanCategories);
        $this->assertFalse($authz->canCreateTransferWithCategories($this->operasional, $bahanCategories));
        $this->assertTrue($authz->canCreateTransferWithCategories($this->gudangUser, $bahanCategories));

        $emptyTransfer = new Transfer([
            'jenis' => 'antar_fulfillment',
            'status' => 'draft',
        ]);
        $emptyTransfer->setRelation('items', collect());
        $emptyTransfer->setRelation('stockRequest', null);
        $this->assertSame(['produk_jadi'], $authz->resolveCategoriesForTransfer($emptyTransfer));
    }

    public function test_authorization_service_ship_receive_cancel_without_lazy_loading_violation(): void
    {
        $authz = app(InventoryTransferAuthorizationService::class);

        $this->stokService->masuk($this->bahan->id, $this->gudangPusat->id, 100, 'penyesuaian', null, 'Seed Test Bahan');
        $this->stokService->masuk($this->produkJadi->id, $this->gudangOps->id, 50, 'penyesuaian', null, 'Seed Test PJ');

        $approvedReq = $this->requestService->create([
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'status' => 'diajukan',
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_diminta' => 50],
            ],
        ], $this->operasional);
        $this->requestService->approve($approvedReq, $this->manager);

        $bahanDraft = $this->transferService->create([
            'jenis' => 'pemenuhan_request_bahan',
            'request_id' => $approvedReq->id,
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_dikirim' => 10],
            ],
        ], $this->gudangUser);

        $bahanShipped = $this->transferService->create([
            'jenis' => 'retur_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_dikirim' => 10],
            ],
        ], $this->gudangUser);
        $this->transferService->ship($bahanShipped, $this->gudangUser);

        // Freshly loaded instances (unloaded relations) must not trigger lazy-loading violation
        $freshBahanDraft = Transfer::findOrFail($bahanDraft->id);
        $freshBahanShipped = Transfer::findOrFail($bahanShipped->id);

        $this->assertFalse($authz->canShipTransfer($this->operasional, $freshBahanDraft));
        $this->assertTrue($authz->canShipTransfer($this->gudangUser, $freshBahanDraft));
        $this->assertTrue($authz->canReceiveTransfer($this->operasional, $freshBahanShipped));
        $this->assertFalse($authz->canReceiveTransfer($this->fulfillment, $freshBahanShipped));
        $this->assertFalse($authz->canCancelTransfer($this->operasional, $freshBahanDraft));
        $this->assertFalse($authz->canCancelTransfer($this->gudangUser, $freshBahanDraft));
        $this->assertTrue($authz->canCancelTransfer($this->manager, $freshBahanDraft));
        $this->assertTrue($authz->canCancelTransfer($this->manager, $freshBahanShipped));

        // Custom role with transfer.cancel + transfer.produk_jadi.create can cancel its own PJ draft/dikirim, not Bahan
        $customRole = Role::create([
            'name' => 'pj_dispatcher_cancel',
            'display_name' => 'PJ Dispatcher',
            'is_system' => false,
            'guard_name' => 'web',
        ]);
        $customRole->syncPermissions(['transfer.view', 'transfer.produk_jadi.create', 'transfer.cancel']);

        $customUser = User::factory()->create();
        $customUser->assignRole($customRole);

        $ownPjDraft = $this->transferService->create([
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $this->gudangOps->id,
            'gudang_tujuan_id' => $this->gudangFf->id,
            'items' => [
                ['produk_id' => $this->produkJadi->id, 'qty_dikirim' => 5],
            ],
        ], $customUser);

        $ownBahanDraft = $this->transferService->create([
            'jenis' => 'retur_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_dikirim' => 5],
            ],
        ], $customUser);

        $this->assertTrue($authz->canCancelTransfer($customUser, Transfer::findOrFail($ownPjDraft->id)));
        $this->assertFalse($authz->canCancelTransfer($customUser, Transfer::findOrFail($ownBahanDraft->id)));
        $this->assertFalse($authz->canCancelTransfer($this->operasional, Transfer::findOrFail($ownPjDraft->id)));
    }

    public function test_authorization_service_validates_request_item_categories(): void
    {
        $authz = app(InventoryTransferAuthorizationService::class);

        $this->assertNull($authz->resolveRequestItemCategoryViolations('request_bahan', [$this->bahan->id]));
        $this->assertNotNull($authz->resolveRequestItemCategoryViolations('request_bahan', [$this->produkJadi->id]));

        $this->assertNull($authz->resolveRequestItemCategoryViolations('request_produk_jadi', [$this->produkJadi->id]));
        $this->assertNotNull($authz->resolveRequestItemCategoryViolations('request_produk_jadi', [$this->bahan->id]));
    }

    public function test_operasional_can_create_request_bahan_and_sees_batch_request_button_but_not_create_transfer_button(): void
    {
        $pjWithBom = Produk::produkJadi()->has('bom')->firstOrFail();

        $this->actingAs($this->operasional)->post(route('batches.store'), [
            'produk_id' => $pjWithBom->id,
            'qty_rencana' => 10,
            'gudang_operasional_id' => $this->gudangOps->id,
            'gudang_tujuan_rencana_id' => $this->gudangFf->id,
            'tanggal' => now()->toDateString(),
        ])->assertRedirect();

        $batch = BatchProduksi::latest('id')->firstOrFail();

        $this->actingAs($this->operasional)
            ->get(route('batches.show', $batch))
            ->assertOk()
            ->assertSeeText('Buat Request Bahan');

        // Operasional can create request_bahan via HTTP
        $this->actingAs($this->operasional)
            ->post(route('requests.store'), [
                'jenis' => 'request_bahan',
                'gudang_pemohon_id' => $this->gudangOps->id,
                'gudang_tujuan_id' => $this->gudangPusat->id,
                'batch_id' => $batch->id,
                'action' => 'submit',
                'items' => [
                    ['produk_id' => $this->bahan->id, 'qty_diminta' => 15],
                ],
            ])
            ->assertRedirect();

        // Operasional gets 403 trying to create request_produk_jadi
        $this->actingAs($this->operasional)
            ->post(route('requests.store'), [
                'jenis' => 'request_produk_jadi',
                'gudang_pemohon_id' => $this->gudangFf->id,
                'gudang_tujuan_id' => $this->gudangOps->id,
                'items' => [
                    ['produk_id' => $this->produkJadi->id, 'qty_diminta' => 5],
                ],
            ])
            ->assertForbidden();

        // Fulfillment lacks request.bahan.create so ?batch_id prefill must return 403
        $this->actingAs($this->fulfillment)
            ->get(route('requests.create', ['batch_id' => $batch->id]))
            ->assertForbidden();

        // Operasional smuggling a produk_jadi item under request_bahan gets 403
        $this->actingAs($this->operasional)
            ->post(route('requests.store'), [
                'jenis' => 'request_bahan',
                'gudang_pemohon_id' => $this->gudangOps->id,
                'gudang_tujuan_id' => $this->gudangPusat->id,
                'items' => [
                    ['produk_id' => $this->produkJadi->id, 'qty_diminta' => 5],
                ],
            ])
            ->assertForbidden();

        // Manager submitting mismatched category triggers validation error on 'items'
        $this->actingAs($this->manager)
            ->post(route('requests.store'), [
                'jenis' => 'request_bahan',
                'gudang_pemohon_id' => $this->gudangOps->id,
                'gudang_tujuan_id' => $this->gudangPusat->id,
                'items' => [
                    ['produk_id' => $this->produkJadi->id, 'qty_diminta' => 5],
                ],
            ])
            ->assertSessionHasErrors('items');

        $approvedReq = StockRequest::latest('id')->firstOrFail();
        $this->requestService->approve($approvedReq, $this->manager);

        // Operasional cannot create Bahan Baku transfers, so must NOT see Buat Transfer on approved request_bahan
        $this->actingAs($this->operasional)
            ->get(route('requests.show', $approvedReq))
            ->assertOk()
            ->assertDontSeeText('Buat Transfer');

        $opsData = $this->actingAs($this->operasional)
            ->getJson(route('requests.data'))
            ->assertOk()
            ->json('data');
        $opsRow = collect($opsData)->firstWhere('id', $approvedReq->id);
        $this->assertNotNull($opsRow);
        $this->assertStringNotContainsString('Buat Transfer', $opsRow['action']);

        // Gudang HAS transfer.bahan.create, so DOES see Buat Transfer on show & data
        $this->actingAs($this->gudangUser)
            ->get(route('requests.show', $approvedReq))
            ->assertOk()
            ->assertSeeText('Buat Transfer');

        $gudangData = $this->actingAs($this->gudangUser)
            ->getJson(route('requests.data'))
            ->assertOk()
            ->json('data');
        $gudangRow = collect($gudangData)->firstWhere('id', $approvedReq->id);
        $this->assertNotNull($gudangRow);
        $this->assertStringContainsString('Buat Transfer', $gudangRow['action']);
    }

    public function test_custom_role_with_request_produk_jadi_create_works_without_role_name(): void
    {
        $plannerRole = Role::create([
            'name' => 'store_planner',
            'display_name' => 'Store Planner Custom',
            'is_system' => false,
            'guard_name' => 'web',
        ]);
        $plannerRole->syncPermissions(['request.view', 'request.produk_jadi.create', 'request.submit']);

        $planner = User::factory()->create();
        $planner->assignRole($plannerRole);

        $this->actingAs($planner)
            ->get(route('requests.create'))
            ->assertOk()
            ->assertSee('value="request_produk_jadi"', false)
            ->assertDontSee('value="request_bahan"', false)
            ->assertDontSee('Pilih Batch Produksi');

        $this->actingAs($planner)
            ->post(route('requests.store'), [
                'jenis' => 'request_produk_jadi',
                'gudang_pemohon_id' => $this->gudangFf->id,
                'gudang_tujuan_id' => $this->gudangOps->id,
                'action' => 'save_draft',
                'items' => [
                    ['produk_id' => $this->produkJadi->id, 'qty_diminta' => 12],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('requests', [
            'jenis' => 'request_produk_jadi',
            'created_by' => $planner->id,
        ]);
    }

    public function test_operasional_is_blocked_from_creating_or_shipping_bahan_transfers_via_every_entry_point(): void
    {
        $this->stokService->masuk($this->bahan->id, $this->gudangPusat->id, 200, 'penyesuaian', null, 'Seed BB Pusat');
        $this->stokService->masuk($this->bahan->id, $this->gudangOps->id, 50, 'penyesuaian', null, 'Seed BB Ops');

        $approvedBahanReq = $this->requestService->create([
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $this->gudangOps->id,
            'gudang_tujuan_id' => $this->gudangPusat->id,
            'status' => 'diajukan',
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_diminta' => 25],
            ],
        ], $this->operasional);
        $this->requestService->approve($approvedBahanReq, $this->manager);

        // 1. Entry point GET transfers.create?request_id=<request_bahan> -> 403
        $this->actingAs($this->operasional)
            ->get(route('transfers.create', ['request_id' => $approvedBahanReq->id]))
            ->assertForbidden();

        // 2. Entry point POST transfers.store with request_id=<request_bahan> -> 403
        $this->actingAs($this->operasional)
            ->post(route('transfers.store'), [
                'request_id' => $approvedBahanReq->id,
                'jenis' => 'pemenuhan_request_bahan',
                'gudang_asal_id' => $this->gudangPusat->id,
                'gudang_tujuan_id' => $this->gudangOps->id,
                'items' => [
                    ['produk_id' => $this->bahan->id, 'qty_dikirim' => 10],
                ],
            ])
            ->assertForbidden();

        // 3. Entry point explicit jenis pemenuhan_request_bahan / retur_bahan -> 403
        foreach (['pemenuhan_request_bahan', 'retur_bahan'] as $bahanJenis) {
            $this->actingAs($this->operasional)
                ->post(route('transfers.store'), [
                    'jenis' => $bahanJenis,
                    'gudang_asal_id' => $this->gudangOps->id,
                    'gudang_tujuan_id' => $this->gudangPusat->id,
                    'items' => [
                        ['produk_id' => $this->bahan->id, 'qty_dikirim' => 5],
                    ],
                ])
                ->assertForbidden();
        }

        // 4. Item smuggling under kirim_produk_jadi & antar_fulfillment -> 403
        foreach (['kirim_produk_jadi', 'antar_fulfillment'] as $smuggledJenis) {
            $this->actingAs($this->operasional)
                ->post(route('transfers.store'), [
                    'jenis' => $smuggledJenis,
                    'gudang_asal_id' => $this->gudangOps->id,
                    'gudang_tujuan_id' => $this->gudangFf->id,
                    'items' => [
                        ['produk_id' => $this->bahan->id, 'qty_dikirim' => 5],
                    ],
                ])
                ->assertForbidden();
        }

        // 5. GET transfers.create renders only Produk Jadi jenis & blocks Bahan options
        $this->actingAs($this->operasional)
            ->get(route('transfers.create'))
            ->assertOk()
            ->assertSee('value="kirim_produk_jadi"', false)
            ->assertSee('value="antar_fulfillment"', false)
            ->assertSee('value="retur_produk_jadi"', false)
            ->assertDontSee('value="pemenuhan_request_bahan"', false)
            ->assertDontSee('value="retur_bahan"', false)
            ->assertSee('canTransferBahan: false', false);

        // 6. Draft Bahan Baku created by Gudang cannot be shipped by Operasional
        $bahanDraft = $this->transferService->create([
            'jenis' => 'pemenuhan_request_bahan',
            'request_id' => $approvedBahanReq->id,
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_dikirim' => 10],
            ],
        ], $this->gudangUser);

        $this->actingAs($this->operasional)
            ->get(route('transfers.show', $bahanDraft))
            ->assertOk()
            ->assertDontSeeText('Kirim Sekarang');

        $this->actingAs($this->operasional)
            ->post(route('transfers.ship', $bahanDraft))
            ->assertForbidden();
    }

    public function test_operasional_can_receive_incoming_bahan_transfer_and_cannot_cancel_transfer_by_default(): void
    {
        $this->stokService->masuk($this->bahan->id, $this->gudangPusat->id, 100, 'penyesuaian', null, 'Seed BB Pusat');
        $this->stokService->masuk($this->produkJadi->id, $this->gudangOps->id, 40, 'penyesuaian', null, 'Seed PJ Ops');

        $bahanShipped = $this->transferService->create([
            'jenis' => 'retur_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_dikirim' => 12],
            ],
        ], $this->gudangUser);
        $this->transferService->ship($bahanShipped, $this->gudangUser);

        $opsBefore = $this->stokService->saldo($this->bahan->id, $this->gudangOps->id);

        $this->actingAs($this->operasional)
            ->get(route('transfers.show', $bahanShipped))
            ->assertOk()
            ->assertSeeText('Konfirmasi Penerimaan Barang');

        $tItem = $bahanShipped->items()->firstOrFail();

        $this->actingAs($this->operasional)
            ->post(route('transfers.receive', $bahanShipped), [
                'items' => [
                    [
                        'id' => $tItem->id,
                        'qty_baik' => 12,
                        'qty_rusak' => 0,
                        'keterangan_rusak' => null,
                    ],
                ],
            ])
            ->assertRedirect(route('transfers.show', $bahanShipped))
            ->assertSessionHas('success');

        $this->assertEquals('selesai', $bahanShipped->fresh()->status);
        $this->assertEquals($opsBefore + 12, $this->stokService->saldo($this->bahan->id, $this->gudangOps->id));

        // Own Produk Jadi draft: operasional can ship, cannot cancel (manager-only default)
        $pjDraft = $this->transferService->create([
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $this->gudangOps->id,
            'gudang_tujuan_id' => $this->gudangFf->id,
            'items' => [
                ['produk_id' => $this->produkJadi->id, 'qty_dikirim' => 8],
            ],
        ], $this->operasional);

        $this->actingAs($this->operasional)
            ->get(route('transfers.show', $pjDraft))
            ->assertOk()
            ->assertSeeText('Kirim Sekarang')
            ->assertDontSeeText('Batalkan Transfer');

        $this->actingAs($this->operasional)
            ->post(route('transfers.cancel', $pjDraft), ['alasan' => 'Batal coba'])
            ->assertForbidden();

        $this->actingAs($this->operasional)
            ->post(route('transfers.ship', $pjDraft))
            ->assertRedirect(route('transfers.show', $pjDraft))
            ->assertSessionHas('success');

        $this->assertEquals('dikirim', $pjDraft->fresh()->status);
    }

    public function test_operasional_can_dispatch_finished_goods_from_batch_and_manual_transfer(): void
    {
        $pjWithBom = Produk::produkJadi()->has('bom')->with('bom')->firstOrFail();

        foreach ($pjWithBom->bom as $bomRow) {
            $this->stokService->masuk($bomRow->bahan_id, $this->gudangOps->id, 5000, 'penyesuaian', null, 'Seed Bahan Batch');
        }
        $this->stokService->masuk($pjWithBom->id, $this->gudangOps->id, 100, 'penyesuaian', null, 'Seed PJ Ops');

        $this->actingAs($this->operasional)->post(route('batches.store'), [
            'produk_id' => $pjWithBom->id,
            'qty_rencana' => 10,
            'gudang_operasional_id' => $this->gudangOps->id,
            'gudang_tujuan_rencana_id' => $this->gudangFf->id,
            'tanggal' => now()->toDateString(),
        ])->assertRedirect();

        $batch = BatchProduksi::latest('id')->firstOrFail();
        $this->actingAs($this->operasional)->post(route('batches.release', $batch))->assertRedirect();
        $this->actingAs($this->operasional)->post(route('batches.complete', $batch), [
            'qty_baik' => 10,
            'qty_rusak' => 0,
        ])->assertRedirect();

        $batch->refresh();
        $this->assertEquals('selesai', $batch->status);

        $this->actingAs($this->operasional)
            ->get(route('batches.show', $batch))
            ->assertOk()
            ->assertSeeText('Kirim ke Fulfillment')
            ->assertSeeText('Buat Transfer Produk Jadi');

        $this->actingAs($this->operasional)
            ->get(route('transfers.create', ['jenis' => 'kirim_produk_jadi', 'batch_id' => $batch->id]))
            ->assertOk();

        $this->actingAs($this->operasional)
            ->post(route('batches.kirim', $batch))
            ->assertRedirect();

        $batchTransfer = Transfer::where('referensi_batch_id', $batch->id)->firstOrFail();
        $this->assertEquals('kirim_produk_jadi', $batchTransfer->jenis);
        $this->assertEquals('draft', $batchTransfer->status);

        $this->actingAs($this->operasional)
            ->post(route('transfers.store'), [
                'jenis' => 'kirim_produk_jadi',
                'gudang_asal_id' => $this->gudangOps->id,
                'gudang_tujuan_id' => $this->gudangFf->id,
                'action' => 'ship',
                'items' => [
                    ['produk_id' => $pjWithBom->id, 'qty_dikirim' => 5],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $shippedManual = Transfer::latest('id')->firstOrFail();
        $this->assertEquals('dikirim', $shippedManual->status);
    }

    public function test_custom_role_permissions_for_transfer_categories_and_scoped_cancel(): void
    {
        $this->stokService->masuk($this->bahan->id, $this->gudangPusat->id, 100, 'penyesuaian', null, 'Seed BB Pusat');
        $this->stokService->masuk($this->produkJadi->id, $this->gudangOps->id, 50, 'penyesuaian', null, 'Seed PJ Ops');

        $bahanDispatcherRole = Role::create([
            'name' => 'bahan_dispatcher_cancel',
            'display_name' => 'Dispatcher Bahan + Batal',
            'is_system' => false,
            'guard_name' => 'web',
        ]);
        $bahanDispatcherRole->syncPermissions([
            'transfer.view',
            'transfer.bahan.create',
            'transfer.bahan.ship',
            'transfer.cancel',
        ]);

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole($bahanDispatcherRole);

        // Can create & ship Bahan transfer
        $this->actingAs($dispatcher)
            ->post(route('transfers.store'), [
                'jenis' => 'retur_bahan',
                'gudang_asal_id' => $this->gudangPusat->id,
                'gudang_tujuan_id' => $this->gudangOps->id,
                'action' => 'save_draft',
                'items' => [
                    ['produk_id' => $this->bahan->id, 'qty_dikirim' => 10],
                ],
            ])
            ->assertRedirect();

        $ownBahanTransfer = Transfer::where('created_by', $dispatcher->id)->latest('id')->firstOrFail();

        $this->actingAs($dispatcher)
            ->post(route('transfers.cancel', $ownBahanTransfer), ['alasan' => 'Revisi qty'])
            ->assertRedirect();
        $this->assertEquals('dibatalkan', $ownBahanTransfer->fresh()->status);

        // Blocked from Produk Jadi create & another user's Bahan transfer cancel & Produk Jadi cancel
        $this->actingAs($dispatcher)
            ->post(route('transfers.store'), [
                'jenis' => 'kirim_produk_jadi',
                'gudang_asal_id' => $this->gudangOps->id,
                'gudang_tujuan_id' => $this->gudangFf->id,
                'items' => [
                    ['produk_id' => $this->produkJadi->id, 'qty_dikirim' => 5],
                ],
            ])
            ->assertForbidden();

        $otherBahanDraft = $this->transferService->create([
            'jenis' => 'retur_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'items' => [['produk_id' => $this->bahan->id, 'qty_dikirim' => 5]],
        ], $this->gudangUser);

        $pjDraftByManager = $this->transferService->create([
            'jenis' => 'kirim_produk_jadi',
            'gudang_asal_id' => $this->gudangOps->id,
            'gudang_tujuan_id' => $this->gudangFf->id,
            'items' => [['produk_id' => $this->produkJadi->id, 'qty_dikirim' => 5]],
        ], $dispatcher); // Note: even if somehow created_by matches, missing transfer.produk_jadi.create blocks cancel

        $this->actingAs($dispatcher)
            ->post(route('transfers.cancel', $otherBahanDraft), ['alasan' => 'Tidak berhak'])
            ->assertForbidden();

        $this->actingAs($dispatcher)
            ->post(route('transfers.cancel', $pjDraftByManager), ['alasan' => 'Kategori PJ dilarang'])
            ->assertForbidden();

        // Request-only custom role has no access to transfers or batches.kirim
        $requestOnlyRole = Role::create([
            'name' => 'request_only_role',
            'display_name' => 'Request Bahan Only',
            'is_system' => false,
            'guard_name' => 'web',
        ]);
        $requestOnlyRole->syncPermissions(['request.view', 'request.bahan.create', 'batch.view']);

        $requestOnlyUser = User::factory()->create();
        $requestOnlyUser->assignRole($requestOnlyRole);

        $this->actingAs($requestOnlyUser)->get(route('transfers.create'))->assertForbidden();
        $this->actingAs($requestOnlyUser)->post(route('transfers.store'), [])->assertForbidden();

        $batch = BatchProduksi::create([
            'no_batch' => 'BCH-TEST-CUSTOM-001',
            'produk_id' => $this->produkJadi->id,
            'qty_rencana' => 10,
            'qty_baik' => 10,
            'qty_rusak' => 0,
            'gudang_operasional_id' => $this->gudangOps->id,
            'gudang_tujuan_rencana_id' => $this->gudangFf->id,
            'status' => 'selesai',
            'tanggal' => now()->toDateString(),
            'created_by' => $this->operasional->id,
        ]);

        $this->actingAs($requestOnlyUser)->post(route('batches.kirim', $batch))->assertForbidden();
    }

    public function test_ship_and_receive_errors_preserve_product_name_and_enforce_inline_ship_and_retur_bahan_guards(): void
    {
        $this->stokService->masuk($this->bahan->id, $this->gudangPusat->id, 10, 'penyesuaian', null, 'Seed Minim BB');
        $this->stokService->masuk($this->produkJadi->id, $this->gudangOps->id, 50, 'penyesuaian', null, 'Seed PJ');

        // Minor #1b: GET transfers.create?jenis=retur_bahan -> 403 for operasional
        $this->actingAs($this->operasional)
            ->get(route('transfers.create', ['jenis' => 'retur_bahan']))
            ->assertForbidden();

        // Minor #1a: custom role with transfer.produk_jadi.create WITHOUT transfer.produk_jadi.ship gets 403 on action=ship
        $createOnlyRole = Role::create([
            'name' => 'pj_creator_no_ship',
            'display_name' => 'PJ Creator Only',
            'is_system' => false,
            'guard_name' => 'web',
        ]);
        $createOnlyRole->syncPermissions(['transfer.view', 'transfer.produk_jadi.create']);
        $creatorOnly = User::factory()->create();
        $creatorOnly->assignRole($createOnlyRole);

        $this->actingAs($creatorOnly)
            ->post(route('transfers.store'), [
                'jenis' => 'kirim_produk_jadi',
                'gudang_asal_id' => $this->gudangOps->id,
                'gudang_tujuan_id' => $this->gudangFf->id,
                'action' => 'ship',
                'items' => [
                    ['produk_id' => $this->produkJadi->id, 'qty_dikirim' => 5],
                ],
            ])
            ->assertForbidden();

        // Important #1: ship() insufficient stock error preserves full Produk->nama despite authz loadMissing
        $oversizedDraft = $this->transferService->create([
            'jenis' => 'retur_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_dikirim' => 9999999],
            ],
        ], $this->gudangUser);

        $shipResponse = $this->actingAs($this->gudangUser)
            ->post(route('transfers.ship', $oversizedDraft))
            ->assertRedirect()
            ->assertSessionHas('error');
        $shipError = (string) $shipResponse->getSession()->get('error');
        $this->assertStringContainsString($this->bahan->nama, $shipError);

        // Important #1: receive() QC mismatch error preserves full Produk->nama
        $validShipped = $this->transferService->create([
            'jenis' => 'retur_bahan',
            'gudang_asal_id' => $this->gudangPusat->id,
            'gudang_tujuan_id' => $this->gudangOps->id,
            'items' => [
                ['produk_id' => $this->bahan->id, 'qty_dikirim' => 5],
            ],
        ], $this->gudangUser);
        $this->transferService->ship($validShipped, $this->gudangUser);
        $tItem = $validShipped->items()->firstOrFail();

        $receiveResponse = $this->actingAs($this->operasional)
            ->post(route('transfers.receive', $validShipped), [
                'items' => [
                    [
                        'id' => $tItem->id,
                        'qty_baik' => 3,
                        'qty_rusak' => 0,
                        'keterangan_rusak' => null,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
        $receiveError = (string) $receiveResponse->getSession()->get('error');
        $this->assertStringContainsString($this->bahan->nama, $receiveError);
    }
}

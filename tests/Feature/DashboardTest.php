<?php

namespace Tests\Feature;

use App\Models\Gudang;
use App\Models\PoRevisi;
use App\Models\PoStage;
use App\Models\PurchaseOrder;
use App\Models\RequestTransfer;
use App\Models\StockRequest;
use App\Models\Supplier;
use App\Models\SupplierLtStageSummary;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(true);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_dashboard_displays_stats_and_item_perlu_order(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $this->actingAs($manager);

        $response = $this->get('/');
        $response->assertStatus(200);

        // Memastikan kartu Item Perlu Order muncul dengan nilai terhitung
        $response->assertSee('Item Perlu Order');
        $response->assertSee('Analisa Stok');
    }

    public function test_dashboard_filters_actionable_items_by_role(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();

        $supplier = Supplier::firstOrFail();

        // 1. PO berstatus 'diajukan' (menunggu approval Manager)
        $poDiajukan = PurchaseOrder::create([
            'no_po' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'tanggal' => now(),
            'status' => 'diajukan',
            'created_by' => $purchasing->id,
        ]);

        // 2. PO berstatus 'dikirim_ke_gudang' (menunggu konfirmasi Gudang)
        $poGudang = PurchaseOrder::create([
            'no_po' => 'PO-TEST-002',
            'supplier_id' => $supplier->id,
            'tanggal' => now(),
            'status' => 'dikirim_ke_gudang',
            'created_by' => $purchasing->id,
        ]);

        // Login sebagai Manager: harus melihat PO-TEST-001 yang menunggu approval
        $this->actingAs($manager);
        $resManager = $this->get('/');
        $resManager->assertStatus(200);
        $resManager->assertSee('PO-TEST-001');

        // Login sebagai Gudang: harus melihat PO-TEST-002 yang siap diterima
        $this->actingAs($gudang);
        $resGudang = $this->get('/');
        $resGudang->assertStatus(200);
        $resGudang->assertSee('PO-TEST-002');
    }

    public function test_manager_sees_po_in_stage_approval_and_po_with_pending_revisions(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $supplier = Supplier::firstOrFail();

        // 1. PO PRD v2.4 pada stage 'approval'
        $poApproval = PurchaseOrder::create([
            'no_po' => 'PO-PRD24-APP',
            'supplier_id' => $supplier->id,
            'tanggal' => now(),
            'status' => 'berjalan',
            'stage_aktif' => 'approval',
            'created_by' => $purchasing->id,
        ]);

        // 2. PO pada stage 'pengemasan' tetapi memiliki revisi berstatus 'menunggu'
        $poRevisi = PurchaseOrder::create([
            'no_po' => 'PO-PRD24-REV',
            'supplier_id' => $supplier->id,
            'tanggal' => now(),
            'status' => 'berjalan',
            'stage_aktif' => 'pengemasan',
            'created_by' => $purchasing->id,
        ]);
        PoRevisi::create([
            'po_id' => $poRevisi->id,
            'revisi_ke' => 1,
            'stage_saat_ajukan' => 'pengemasan',
            'status' => 'menunggu',
            'alasan' => 'Perubahan kuantiti item',
            'data_lama' => ['qty' => 10],
            'data_usulan' => ['qty' => 15],
            'diajukan_oleh' => $purchasing->id,
            'diajukan_pada' => now(),
        ]);

        // Manager melihat PO-PRD24-APP dan PO-PRD24-REV
        $this->actingAs($manager);
        $resManager = $this->get('/');
        $resManager->assertStatus(200);
        $resManager->assertSee('PO-PRD24-APP');
        $resManager->assertSee('Persetujuan Manager');
        $resManager->assertSee('PO-PRD24-REV');
        $resManager->assertSee('Menunggu Revisi');

        // Gudang TIDAK melihat PO stage approval atau pengemasan
        $this->actingAs($gudang);
        $resGudang = $this->get('/');
        $resGudang->assertStatus(200);
        $resGudang->assertDontSee('PO-PRD24-APP');
        $resGudang->assertDontSee('PO-PRD24-REV');
    }

    public function test_gudang_sees_po_in_receiving_stages(): void
    {
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $supplier = Supplier::firstOrFail();

        $poPengiriman = PurchaseOrder::create([
            'no_po' => 'PO-GDG-KIRIM',
            'supplier_id' => $supplier->id,
            'tanggal' => now(),
            'status' => 'berjalan',
            'stage_aktif' => 'pengiriman',
            'created_by' => $purchasing->id,
        ]);

        $poUnloading = PurchaseOrder::create([
            'no_po' => 'PO-GDG-UNLOAD',
            'supplier_id' => $supplier->id,
            'tanggal' => now(),
            'status' => 'berjalan',
            'stage_aktif' => 'unloading',
            'created_by' => $purchasing->id,
        ]);

        $poInput = PurchaseOrder::create([
            'no_po' => 'PO-GDG-INPUT',
            'supplier_id' => $supplier->id,
            'tanggal' => now(),
            'status' => 'berjalan',
            'stage_aktif' => 'input_sistem',
            'created_by' => $purchasing->id,
        ]);

        $this->actingAs($gudang);
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('PO-GDG-KIRIM');
        $response->assertSee('Pengiriman &amp; Logistik', false);
        $response->assertSee('PO-GDG-UNLOAD');
        $response->assertSee('Unloading di Gudang');
        $response->assertSee('PO-GDG-INPUT');
        $response->assertSee('Input Sistem &amp; Posting Stok', false);
    }

    public function test_terlambat_badge_appears_when_duration_exceeds_supplier_average(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $purchasing = User::where('email', 'purchasing@heavenscent.id')->firstOrFail();
        $supplier = Supplier::firstOrFail();

        // Siapkan benchmark rata-rata stage approval = 60 menit
        SupplierLtStageSummary::updateOrCreate(
            ['supplier_id' => $supplier->id, 'stage' => 'approval'],
            [
                'jumlah_transaksi' => 5,
                'total_menit' => 300,
                'average_hari' => 0.1,
                'max_hari' => 0.2,
            ]
        );

        $poLate = PurchaseOrder::create([
            'no_po' => 'PO-LATE-999',
            'supplier_id' => $supplier->id,
            'tanggal' => now()->subDays(2),
            'status' => 'berjalan',
            'stage_aktif' => 'approval',
            'created_by' => $purchasing->id,
        ]);

        // Buat PoStage yang telah berjalan 300 menit (> 60 menit avg)
        $poLate->stages()->create([
            'stage' => 'approval',
            'urutan' => 2,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => now()->subMinutes(300),
        ]);

        $this->actingAs($manager);
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('PO-LATE-999');
        $response->assertSee('Terlambat');
    }

    public function test_logistics_actions_include_stock_requests_and_transfers_without_lazy_loading(): void
    {
        $manager = User::where('email', 'manager@heavenscent.id')->firstOrFail();
        $gudang = User::where('email', 'gudang@heavenscent.id')->firstOrFail();
        $gudangPusat = Gudang::firstOrFail();
        $gudangTujuan = Gudang::where('id', '!=', $gudangPusat->id)->first() ?? $gudangPusat;

        // StockRequest menunggu persetujuan Manager
        $req = StockRequest::create([
            'no_request' => 'REQ-TEST-MGR',
            'jenis' => 'request_bahan',
            'gudang_asal_id' => $gudangPusat->id,
            'gudang_tujuan_id' => $gudangTujuan->id,
            'status' => 'diajukan',
            'created_by' => $manager->id,
        ]);

        // Transfer yang sedang dikirim, perlu diterima oleh Gudang
        $trf = Transfer::create([
            'no_transaksi' => 'TRF-TEST-GDG',
            'jenis' => 'pemenuhan_request_bahan',
            'gudang_asal_id' => $gudangPusat->id,
            'gudang_tujuan_id' => $gudangTujuan->id,
            'status' => 'dikirim',
            'created_by' => $manager->id,
        ]);

        // Login Manager: melihat request yang diajukan dengan tautan ke requests.show
        $this->actingAs($manager);
        $resManager = $this->get('/');
        $resManager->assertStatus(200);
        $resManager->assertSee('REQ-TEST-MGR');
        $resManager->assertSee(route('requests.show', $req));

        // Login Gudang: melihat transfer dalam perjalanan dengan tautan ke transfers.show
        $this->actingAs($gudang);
        $resGudang = $this->get('/');
        $resGudang->assertStatus(200);
        $resGudang->assertSee('TRF-TEST-GDG');
        $resGudang->assertSee(route('transfers.show', $trf));
    }
}

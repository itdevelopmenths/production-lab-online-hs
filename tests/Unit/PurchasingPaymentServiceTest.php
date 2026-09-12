<?php

namespace Tests\Unit;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderTermin;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchasingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchasingPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchasingPaymentService $paymentService;
    private PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = new PurchasingPaymentService();

        $user = User::factory()->create();
        $supplier = Supplier::create([
            'kode' => 'SUP-TEST',
            'nama' => 'Supplier Test',
            'kategori' => 'bahan_baku',
            'status' => 'aktif',
        ]);

        $this->po = PurchaseOrder::create([
            'no_po' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'tanggal' => now()->toDateString(),
            'grand_total' => 1000000,
            'subtotal_produk' => 1000000,
            'status' => 'disetujui',
            'status_pembayaran' => 'belum_lunas',
            'created_by' => $user->id,
        ]);
    }

    public function test_reject_overpayment(): void
    {
        $this->expectException(ValidationException::class);

        // Grand total Rp 1.000.000, bayar Rp 1.500.000 -> harus ditolak
        $this->paymentService->recordPayment($this->po, [
            'skema' => 'termin',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 1500000,
        ]);
    }

    public function test_reject_underpayment_on_full_settlement(): void
    {
        $this->expectException(ValidationException::class);

        // Skema pelunasan tapi nominal hanya Rp 500.000 dari total Rp 1.000.000 -> harus ditolak
        $this->paymentService->recordPayment($this->po, [
            'skema' => 'pelunasan',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 500000,
        ]);
    }

    public function test_successful_partial_and_full_payment_with_termins(): void
    {
        // Setup 2 termin: Termin 1 (Rp 400.000), Termin 2 (Rp 600.000)
        $this->paymentService->createTerminsForPo($this->po, [
            [
                'termin_ke' => 1,
                'tanggal_tempo' => now()->addDays(7)->toDateString(),
                'nominal_tagihan' => 400000,
                'keterangan' => 'DP 40%',
            ],
            [
                'termin_ke' => 2,
                'tanggal_tempo' => now()->addDays(30)->toDateString(),
                'nominal_tagihan' => 600000,
                'keterangan' => 'Pelunasan 60%',
            ],
        ]);

        $this->assertCount(2, $this->po->termins);

        // 1. Bayar Rp 400.000 (Termin 1 lunas)
        $this->paymentService->recordPayment($this->po, [
            'skema' => 'termin',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 400000,
        ]);

        $this->po->refresh();
        $this->assertEquals('parsial', $this->po->status_pembayaran);
        $this->assertEquals(600000, $this->po->sisaTagihan());

        $termin1 = $this->po->termins()->where('termin_ke', 1)->first();
        $this->assertEquals('lunas', $termin1->status);

        $termin2 = $this->po->termins()->where('termin_ke', 2)->first();
        $this->assertEquals('belum_lunas', $termin2->status);

        // 2. Bayar Rp 600.000 (Pelunasan penuh)
        $this->paymentService->recordPayment($this->po, [
            'skema' => 'pelunasan',
            'tanggal_bayar' => now()->toDateString(),
            'nominal' => 600000,
        ]);

        $this->po->refresh();
        $this->assertEquals('lunas', $this->po->status_pembayaran);
        $this->assertEquals(0, $this->po->sisaTagihan());
        $this->assertTrue($this->po->isLunas());
    }
}

<?php

namespace Tests\Unit;

use App\Services\Purchasing\PurchasingCostCalculator;
use PHPUnit\Framework\TestCase;

class PurchasingCostCalculatorTest extends TestCase
{
    private PurchasingCostCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PurchasingCostCalculator();
    }

    public function test_calculate_item_net_and_hpp(): void
    {
        // 100 kg bahan, harga kotor Rp 1.000.000, diskon 50.000, ppn 110.000, ongkir 40.000, adj 0
        // Net = 1.000.000 - 50.000 + 110.000 + 40.000 = 1.100.000
        // HPP / unit = 1.100.000 / 100 = 11.000
        $net = $this->calculator->calculateItemNet(1000000, 50000, 110000, 40000, 0);
        $this->assertEquals(1100000, $net);

        $hpp = $this->calculator->calculateHppPerSatuan($net, 100);
        $this->assertEquals(11000, $hpp);
    }

    public function test_calculate_hpp_with_zero_qty_returns_zero(): void
    {
        $hpp = $this->calculator->calculateHppPerSatuan(500000, 0);
        $this->assertEquals(0, $hpp);
    }

    public function test_calculate_po_totals_with_prorata_header_allocation(): void
    {
        $items = [
            [
                'produk_id' => 1,
                'qty' => 50,
                'harga_total' => 600000, // 60%
                'diskon' => 0,
                'ppn' => 0,
                'ongkir' => 0,
                'adjustment' => 0,
            ],
            [
                'produk_id' => 2,
                'qty' => 20,
                'harga_total' => 400000, // 40%
                'diskon' => 0,
                'ppn' => 0,
                'ongkir' => 0,
                'adjustment' => 0,
            ],
        ];

        // Header: Diskon 100.000, Ongkir 50.000
        $totals = $this->calculator->calculatePoTotals($items, 100000, 0, 50000, 0);

        $this->assertEquals(1000000, $totals['subtotal_produk']);
        $this->assertEquals(100000, $totals['diskon_total']);
        $this->assertEquals(50000, $totals['ongkos_kirim']);
        $this->assertEquals(950000, $totals['grand_total']);

        // Item 1 (60%): Diskon 60.000, Ongkir 30.000 -> Net = 600.000 - 60.000 + 30.000 = 570.000
        // HPP = 570.000 / 50 = 11.400
        $this->assertEquals(570000, $totals['items'][0]['net_total']);
        $this->assertEquals(11400, $totals['items'][0]['hpp_per_satuan']);

        // Item 2 (40%): Diskon 40.000, Ongkir 20.000 -> Net = 400.000 - 40.000 + 20.000 = 380.000
        // HPP = 380.000 / 20 = 19.000
        $this->assertEquals(380000, $totals['items'][1]['net_total']);
        $this->assertEquals(19000, $totals['items'][1]['hpp_per_satuan']);
    }
}

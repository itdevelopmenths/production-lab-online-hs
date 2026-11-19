<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Dashboard\Rupiah;
use Carbon\Carbon;

/**
 * Data grafik batang dashboard (6 bulan terakhir, bulan berjalan di akhir).
 * Pengelompokan per bulan dilakukan di PHP supaya portabel antara PostgreSQL dan SQLite.
 */
class DashboardChartService
{
    private const JUMLAH_BULAN = 6;

    public function __construct(private readonly TransferService $transferService) {}

    /**
     * @return list<array{kunci: string, judul: string, kategori: list<string>, seri: list<array{label: string, nada: string, nilai: list<float>, teks: list<string>}>, maks: float, maksTeks: string, kosong: string, href: string}>
     */
    public function forRole(string $role, User $user): array
    {
        $charts = [];

        if (in_array($role, ['manager', 'purchasing'], true)) {
            $charts[] = $this->nilaiPo();
        }

        if (in_array($role, ['manager', 'gudang'], true) && $user->can('defect.view')) {
            $charts[] = $this->defectRate();
        }

        return $charts;
    }

    /** @return list<Carbon> awal tiap bulan, dari yang terlama sampai bulan berjalan */
    private function bulan(): array
    {
        $bulan = [];
        for ($i = self::JUMLAH_BULAN - 1; $i >= 0; $i--) {
            $bulan[] = now()->startOfMonth()->subMonthsNoOverflow($i);
        }

        return $bulan;
    }

    /** @param  list<Carbon>  $bulan */
    private function kategori(array $bulan): array
    {
        return array_map(fn (Carbon $b) => $b->copy()->locale('id')->isoFormat('MMM'), $bulan);
    }

    private function nilaiPo(): array
    {
        $bulan = $this->bulan();
        $awal = $bulan[0]->copy();
        $akhir = now()->endOfMonth();

        $po = PurchaseOrder::query()
            ->where('status', '!=', 'dibatalkan')
            ->whereBetween('tanggal', [$awal, $akhir])
            ->get(['tanggal', 'grand_total']);

        $bayar = Payment::query()
            ->whereBetween('tanggal_bayar', [$awal, $akhir])
            ->get(['tanggal_bayar', 'nominal']);

        $perBulan = fn ($rows, string $tanggal, string $nilai) => array_map(
            fn (Carbon $b) => (float) $rows
                ->filter(fn ($r) => $r->{$tanggal} && $r->{$tanggal}->isSameMonth($b))
                ->sum(fn ($r) => (float) $r->{$nilai}),
            $bulan
        );

        $nilaiPo = $perBulan($po, 'tanggal', 'grand_total');
        $nilaiBayar = $perBulan($bayar, 'tanggal_bayar', 'nominal');
        $maks = max(array_merge($nilaiPo, $nilaiBayar, [0.0]));

        return [
            'kunci' => 'nilai_po',
            'judul' => 'Nilai PO 6 Bulan',
            'kategori' => $this->kategori($bulan),
            'seri' => [
                ['label' => 'PO', 'nada' => 'data', 'nilai' => $nilaiPo, 'teks' => array_map([Rupiah::class, 'ringkas'], $nilaiPo)],
                ['label' => 'Dibayar', 'nada' => 'muted', 'nilai' => $nilaiBayar, 'teks' => array_map([Rupiah::class, 'ringkas'], $nilaiBayar)],
            ],
            'maks' => $maks > 0 ? $maks : 1.0,
            'maksTeks' => Rupiah::ringkas($maks),
            'kosong' => 'Belum ada nilai PO',
            'href' => route('purchasing.index'),
        ];
    }

    private function defectRate(): array
    {
        $bulan = $this->bulan();
        $rate = array_map(function (Carbon $b) {
            $summary = $this->transferService
                ->getCombinedMonthlyWarehouseDefectAnalytics((int) $b->year, (int) $b->month)['summary'];

            return (float) ($summary['defect_rate_percent'] ?? 0.0);
        }, $bulan);

        $maks = max(array_merge($rate, [0.0]));

        return [
            'kunci' => 'defect_rate',
            'judul' => 'Defect Rate 6 Bulan',
            'kategori' => $this->kategori($bulan),
            'seri' => [
                [
                    'label' => 'Defect',
                    'nada' => 'data',
                    'nilai' => $rate,
                    'teks' => array_map(fn (float $r) => number_format($r, 1, ',', '.').'%', $rate),
                ],
            ],
            'maks' => $maks > 0 ? $maks : 1.0,
            'maksTeks' => number_format($maks, 1, ',', '.').'%',
            'kosong' => 'Tidak ada barang rusak',
            'href' => route('reports.defect'),
        ];
    }
}

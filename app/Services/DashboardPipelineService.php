<?php

namespace App\Services;

use App\Models\BatchProduksi;
use App\Models\PoStage;
use App\Models\PurchaseOrder;
use App\Models\StockRequest;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Authorization\LocationScopeService;

/**
 * Data "alur" (pipeline) dashboard per role: jumlah dokumen di tiap tahap.
 * Semua hitungan memakai query agregat, tanpa memuat model.
 */
class DashboardPipelineService
{
    private const LABEL_TAHAP_PO = [
        PoStage::STAGE_1 => 'Perencanaan',
        PoStage::STAGE_2 => 'Approval',
        PoStage::STAGE_3 => 'Konfirmasi',
        PoStage::STAGE_4 => 'Pembayaran',
        PoStage::STAGE_5 => 'PO Terbit',
        PoStage::STAGE_6 => 'Pengiriman',
        PoStage::STAGE_7 => 'Unloading',
        PoStage::STAGE_8 => 'Input Sistem',
    ];

    public function __construct(private readonly LocationScopeService $locationScope)
    {
    }

    /**
     * @return array{judul: string, tahap: list<array{key: string, label: string, jumlah: int, href: string, status: ?string}>}|null
     */
    public function forRole(string $role, User $user): ?array
    {
        return match ($role) {
            'manager', 'purchasing' => $this->alurPurchaseOrder(),
            'gudang' => $this->alurGudang($user),
            'operasional' => $this->alurProduksi(),
            'fulfillment' => $this->alurProdukJadi(),
            default => null,
        };
    }

    private function alurPurchaseOrder(): array
    {
        $perTahap = PurchaseOrder::query()
            ->whereNotIn('status', ['selesai', 'dibatalkan'])
            ->selectRaw('stage_aktif, count(*) as jumlah')
            ->groupBy('stage_aktif')
            ->pluck('jumlah', 'stage_aktif');

        $tahap = [];
        foreach (self::LABEL_TAHAP_PO as $key => $label) {
            $tahap[] = $this->tahap(
                $key,
                $label,
                (int) ($perTahap[$key] ?? 0),
                route('purchasing.index'),
                $key === PoStage::STAGE_2 ? 'warn' : null
            );
        }

        return ['judul' => 'Alur Purchase Order', 'tahap' => $tahap];
    }

    private function alurGudang(User $user): array
    {
        $poAktif = PurchaseOrder::query()->whereNotIn('status', ['selesai', 'dibatalkan']);
        $perTahap = (clone $poAktif)
            ->whereIn('stage_aktif', [PoStage::STAGE_6, PoStage::STAGE_7, PoStage::STAGE_8])
            ->selectRaw('stage_aktif, count(*) as jumlah')
            ->groupBy('stage_aktif')
            ->pluck('jumlah', 'stage_aktif');

        $whIds = $this->locationScope->getAccessibleWarehouseIds($user);

        $draft = Transfer::query()->where('status', 'draft');
        if (! empty($whIds)) {
            $draft->whereIn('gudang_asal_id', $whIds);
        }

        $dikirim = Transfer::query()->where('status', 'dikirim');
        if (! empty($whIds)) {
            $dikirim->where(fn ($q) => $q->whereIn('gudang_tujuan_id', $whIds)->orWhereIn('gudang_asal_id', $whIds));
        }

        $po = route('purchasing.index');
        $trf = route('transfers.index');

        return [
            'judul' => 'Alur Gudang',
            'tahap' => [
                $this->tahap('pengiriman', 'Pengiriman PO', (int) ($perTahap[PoStage::STAGE_6] ?? 0), $po),
                $this->tahap('unloading', 'Unloading', (int) ($perTahap[PoStage::STAGE_7] ?? 0), $po, 'warn'),
                $this->tahap('input_sistem', 'Input Sistem', (int) ($perTahap[PoStage::STAGE_8] ?? 0), $po, 'warn'),
                $this->tahap('transfer_draft', 'Transfer Draft', $draft->count(), $trf, 'warn'),
                $this->tahap('transfer_dikirim', 'Transfer Dikirim', $dikirim->count(), $trf),
            ],
        ];
    }

    private function alurProduksi(): array
    {
        return [
            'judul' => 'Alur Produksi',
            'tahap' => [
                $this->tahap('request_diajukan', 'Request Bahan Diajukan', StockRequest::where('status', 'diajukan')->count(), route('requests.index')),
                $this->tahap('batch_rencana', 'Batch Rencana', BatchProduksi::where('status', 'rencana')->count(), route('batches.index')),
                $this->tahap('batch_rilis', 'Batch Rilis', BatchProduksi::where('status', 'release')->count(), route('batches.index')),
                $this->tahap('batch_selesai', 'Selesai Bulan Ini', BatchProduksi::where('status', 'selesai')
                    ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])->count(), route('batches.index')),
            ],
        ];
    }

    private function alurProdukJadi(): array
    {
        $kirimLab = Transfer::query()->where('jenis', 'kirim_produk_jadi');
        $trf = route('transfers.index');

        return [
            'judul' => 'Alur Produk Jadi',
            'tahap' => [
                $this->tahap('batch_rilis', 'Batch Rilis di Lab', BatchProduksi::where('status', 'release')->count(), route('batches.index')),
                $this->tahap('kirim_lab', 'Kirim dari Lab', (clone $kirimLab)->where('status', 'dikirim')->count(), $trf, 'warn'),
                $this->tahap('diterima_bulan_ini', 'Diterima Bulan Ini', (clone $kirimLab)->where('status', 'selesai')
                    ->whereBetween('waktu_diterima', [now()->startOfMonth(), now()->endOfMonth()])->count(), $trf),
                $this->tahap('antar_toko', 'Antar Toko Aktif', Transfer::where('jenis', 'antar_fulfillment')
                    ->whereIn('status', ['draft', 'dikirim'])->count(), $trf),
            ],
        ];
    }

    /** Status hanya menyala bila ada isinya, supaya tahap kosong tetap netral. */
    private function tahap(string $key, string $label, int $jumlah, string $href, ?string $status = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'jumlah' => $jumlah,
            'href' => $href,
            'status' => $jumlah > 0 ? $status : null,
        ];
    }
}

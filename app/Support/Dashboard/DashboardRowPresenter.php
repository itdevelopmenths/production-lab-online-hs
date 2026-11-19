<?php

namespace App\Support\Dashboard;

use App\Helpers\NumberHelper;
use App\Models\BatchProduksi;
use App\Models\PoStage;
use App\Models\PurchaseOrder;
use App\Models\StockRequest;
use App\Models\Stok;
use App\Models\Transfer;
use App\Models\TransferItem;
use Carbon\CarbonInterface;

/**
 * Mengubah model/objek baris dashboard menjadi array tampilan seragam
 * (dipakai komponen queue dan stream). Aturan teks sama dengan Blade lama.
 */
class DashboardRowPresenter
{
    /** Umur dokumen (hari) yang dianggap bermasalah. */
    public const AGING_BAD_DAYS = 3;

    /**
     * @return array{url: string, judul: string, detail: string, badge: ?string, varian: string, umur: ?string, umurStatus: ?string, jenis: ?string, aksi: ?string, terlambat: bool}
     */
    public function present(mixed $item): array
    {
        $row = [
            'url' => '#',
            'judul' => '-',
            'detail' => '-',
            'badge' => null,
            'varian' => 'neutral',
            'umur' => null,
            'umurStatus' => null,
            'jenis' => null,
            'aksi' => null,
            'terlambat' => false,
        ];

        if ($item instanceof PurchaseOrder) {
            $row = array_merge($row, $this->purchaseOrder($item));
        } elseif ($item instanceof Transfer) {
            $row = array_merge($row, $this->dokumenGudang($item, route('transfers.show', $item), $item->no_transaksi, [
                'draft' => 'warning', 'dikirim' => 'info', 'selesai' => 'success',
            ]));
        } elseif ($item instanceof StockRequest) {
            $row = array_merge($row, $this->dokumenGudang($item, route('requests.show', $item), $item->no_request, [
                'draft' => 'neutral', 'diajukan' => 'warning', 'disetujui' => 'primary', 'selesai' => 'success',
            ]));
        } elseif ($item instanceof BatchProduksi) {
            $row = array_merge($row, $this->batch($item));
        } elseif ($item instanceof Stok) {
            $row = array_merge($row, $this->stok($item));
        } elseif ($item instanceof TransferItem) {
            $row = array_merge($row, $this->transferItemRusak($item));
        } elseif (is_object($item) && ! empty($item->is_defect_log)) {
            $row = array_merge($row, $this->defectLog($item));
        } elseif (is_object($item)) {
            $row = array_merge($row, $this->objekAntrean($item));
        }

        $createdAt = is_object($item) ? ($item->created_at ?? null) : null;
        $terlambat = is_object($item) && ! empty($item->is_terlambat);
        $row['terlambat'] = $terlambat;
        [$row['umur'], $row['umurStatus']] = $this->umur($createdAt, $terlambat);

        return $row;
    }

    /** @return array{0: ?string, 1: ?string} */
    private function umur(mixed $createdAt, bool $terlambat): array
    {
        if (! $createdAt instanceof CarbonInterface) {
            return [null, $terlambat ? 'bad' : null];
        }

        $hari = max(0, (int) $createdAt->diffInDays(now()));
        $label = $hari === 0 ? 'hari ini' : "{$hari} hr";
        $status = match (true) {
            $terlambat, $hari >= self::AGING_BAD_DAYS => 'bad',
            $hari >= 1 => 'warn',
            default => null,
        };

        return [$label, $status];
    }

    private function ucwords(?string $nilai): string
    {
        return ucwords(str_replace('_', ' ', (string) $nilai));
    }

    private function purchaseOrder(PurchaseOrder $po): array
    {
        $badge = $po->stage_aktif
            ? (PoStage::STAGE_LABELS[$po->stage_aktif] ?? $this->ucwords($po->stage_aktif))
            : $this->ucwords($po->status);

        $varian = match ($po->status) {
            'diajukan' => 'warning',
            'disetujui' => 'primary',
            'dikirim_ke_gudang' => 'info',
            'selesai' => 'success',
            default => 'neutral',
        };
        if ($po->stage_aktif) {
            $varian = match ($po->stage_aktif) {
                'approval' => 'warning',
                'pengiriman', 'unloading', 'input_sistem' => 'info',
                default => 'neutral',
            };
        }
        if (! empty($po->is_terlambat)) {
            $varian = 'danger';
            $badge = 'Terlambat SLA';
        }

        return [
            'url' => route('purchasing.show', $po),
            'judul' => (string) $po->no_po,
            'detail' => ($po->supplier?->nama ?? '-') . ' · ' . ($po->tanggal?->format('d M Y') ?? ''),
            'badge' => $badge,
            'varian' => $varian,
        ];
    }

    /** Transfer dan StockRequest: asal → tujuan, badge dari status. */
    private function dokumenGudang(Transfer|StockRequest $dok, string $url, ?string $nomor, array $varianPerStatus): array
    {
        return [
            'url' => $url,
            'judul' => (string) $nomor,
            'detail' => ($dok->gudangAsal?->nama ?? '-') . ' → ' . ($dok->gudangTujuan?->nama ?? '-'),
            'badge' => $this->ucwords($dok->status),
            'varian' => $varianPerStatus[$dok->status] ?? 'neutral',
        ];
    }

    private function batch(BatchProduksi $batch): array
    {
        return [
            'url' => route('batches.show', $batch),
            'judul' => (string) $batch->no_batch,
            'detail' => ($batch->produk?->nama ?? '-') . ' · ' . number_format($batch->qty_rencana, 0) . ' ' . ($batch->produk?->satuan ?? 'pcs'),
            'badge' => $this->ucwords($batch->status),
            'varian' => match ($batch->status) {
                'rencana' => 'warning',
                'release' => 'primary',
                'selesai' => 'success',
                default => 'neutral',
            },
        ];
    }

    private function stok(Stok $stok): array
    {
        return [
            'url' => route('stok.index'),
            'judul' => $stok->produk?->nama ?? ('SKU #' . $stok->produk_id),
            'detail' => ($stok->gudang?->nama ?? 'Gudang') . ' · ' . ($stok->produk?->sku ?? ''),
            'badge' => number_format($stok->qty_saat_ini, 0) . ' ' . ($stok->produk?->satuan ?? ''),
            'varian' => $stok->qty_saat_ini <= 10 ? 'danger' : 'warning',
        ];
    }

    private function transferItemRusak(TransferItem $item): array
    {
        return [
            'url' => $item->transfer ? route('transfers.show', $item->transfer) : route('transfers.index'),
            'judul' => $item->produk?->nama ?? 'Item Rusak',
            'detail' => implode(' · ', array_filter([
                $item->transfer?->no_transaksi,
                $item->transfer?->gudangTujuan?->nama ?? 'Gudang',
                $item->keterangan_rusak ?: 'Kerusakan fisik',
            ])),
            'badge' => NumberHelper::formatQty($item->qty_rusak) . ' ' . ($item->produk?->satuan ?? 'pcs') . ' Rusak',
            'varian' => 'danger',
        ];
    }

    private function defectLog(object $log): array
    {
        return [
            'url' => $log->url ?? route('dashboard'),
            'judul' => $log->produk_nama ?? 'Item Rusak',
            'detail' => implode(' · ', array_filter([
                $log->nomor ?? null,
                $log->gudang_nama ?? 'Gudang',
                $log->keterangan ?? 'Kerusakan fisik',
            ])),
            'badge' => '[' . ($log->source_badge ?? 'Defect') . '] ' . NumberHelper::formatQty($log->qty_rusak ?? 0) . ' ' . ($log->satuan ?? 'pcs') . ' Rusak',
            'varian' => 'danger',
        ];
    }

    private function objekAntrean(object $obj): array
    {
        return [
            'url' => $obj->url ?? '#',
            'judul' => $obj->nomor ?? $obj->title ?? '-',
            'detail' => $obj->meta ?? $obj->subtitle ?? '-',
            'badge' => $obj->badge_label ?? null,
            'varian' => $obj->badge_variant ?? 'neutral',
            'jenis' => $obj->type ?? null,
            'aksi' => $obj->action_label ?? null,
        ];
    }
}

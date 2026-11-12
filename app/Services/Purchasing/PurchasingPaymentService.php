<?php

namespace App\Services\Purchasing;

use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderTermin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasingPaymentService
{
    /**
     * Catat pembayaran baru dengan validasi integritas finansial ketat.
     *
     * @param  array{
     *     skema: string,
     *     tanggal_bayar: string,
     *     nominal: float|int,
     *     bukti_file?: string|null,
     *     termin_id?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function recordPayment(PurchaseOrder $po, array $data): Payment
    {
        $nominal = round((float) ($data['nominal'] ?? 0), 2);
        $skema = $data['skema'] ?? 'termin';

        if ($nominal <= 0) {
            throw ValidationException::withMessages([
                'nominal' => 'Nominal pembayaran harus lebih besar dari Rp 0.',
            ]);
        }

        return DB::transaction(function () use ($po, $data, $nominal, $skema) {
            $lockedPo = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            if ($lockedPo->isCancelled()) {
                throw ValidationException::withMessages([
                    'status' => 'Purchase Order yang sudah dibatalkan tidak dapat menerima pembayaran.',
                ]);
            }

            if ($lockedPo->hasPendingRevision()) {
                throw ValidationException::withMessages([
                    'revisi' => 'Pembayaran dikunci karena masih ada usulan revisi harga atau komitmen yang menunggu keputusan Manager.',
                ]);
            }

            if (! empty($data['termin_id'])) {
                $ownsTermin = $lockedPo->termins()->where('id', $data['termin_id'])->exists();
                if (! $ownsTermin) {
                    throw ValidationException::withMessages([
                        'termin_id' => 'Termin pembayaran tidak valid atau bukan milik Purchase Order ini.',
                    ]);
                }
            }

            $totalNilai = round($lockedPo->totalNilai(), 2);
            $totalDibayar = round((float) $lockedPo->payments()->sum('nominal'), 2);
            $sisaTagihan = round(max(0, $totalNilai - $totalDibayar), 2);

            if ($sisaTagihan <= 0) {
                throw ValidationException::withMessages([
                    'nominal' => 'Purchase Order ini sudah lunas atau tidak memiliki sisa tagihan.',
                ]);
            }

            // Validasi 1: Cegah pembayaran melebihi sisa tagihan (Overpayment)
            if ($nominal > $sisaTagihan) {
                throw ValidationException::withMessages([
                    'nominal' => sprintf(
                        'Nominal pembayaran (Rp %s) melebihi sisa tagihan PO (Rp %s). Kelebihan bayar tidak diperbolehkan.',
                        number_format($nominal, 0, ',', '.'),
                        number_format($sisaTagihan, 0, ',', '.')
                    ),
                ]);
            }

            // Validasi 2: Cegah pembayaran kurang pada skema Pelunasan / Tunai
            if (in_array($skema, ['pelunasan', 'cash'], true) && $nominal < $sisaTagihan) {
                throw ValidationException::withMessages([
                    'nominal' => sprintf(
                        "Skema '%s' harus melunasi sisa tagihan tepat sebesar Rp %s. Jika ingin mencicil sebagian, pilih skema 'Tempo' atau 'Termin'.",
                        $skema === 'cash' ? 'Tunai (Cash / Transfer Penuh)' : 'Pelunasan Penuh',
                        number_format($sisaTagihan, 0, ',', '.')
                    ),
                ]);
            }

            $payment = $lockedPo->payments()->create([
                'skema' => $skema,
                'tanggal_bayar' => $data['tanggal_bayar'],
                'nominal' => $nominal,
                'bukti_file' => $data['bukti_file'] ?? null,
            ]);

            // Alokasikan ke termin jika ada
            $this->allocatePaymentToTermins($lockedPo, $nominal, $data['termin_id'] ?? null);

            // Refresh status pembayaran PO secara otoritatif
            $lockedPo->refreshStatusPembayaran();
            $lockedPo->save();

            $po->forceFill($lockedPo->getAttributes());
            $po->unsetRelation('payments');
            $po->unsetRelation('termins');
            $po->unsetRelation('revisis');

            return $payment;
        });
    }

    /**
     * Alokasikan nominal pembayaran ke termin-termin tagihan.
     */
    public function allocatePaymentToTermins(PurchaseOrder $po, float $nominal, ?string $targetTerminId = null): void
    {
        $remainingToAllocate = $nominal;

        if ($targetTerminId) {
            $termin = $po->termins()->find($targetTerminId);
            if ($termin) {
                $deficit = $termin->sisaNominal();
                $alloc = min($remainingToAllocate, $deficit);
                $termin->nominal_dibayar = round((float) $termin->nominal_dibayar + $alloc, 2);
                $termin->updateStatusAutomatically();
                $termin->save();
                $remainingToAllocate -= $alloc;
            }
        }

        if ($remainingToAllocate > 0) {
            $unpaidTermins = $po->termins()
                ->where('status', '!=', 'lunas')
                ->orderBy('termin_ke')
                ->get();

            foreach ($unpaidTermins as $term) {
                if ($remainingToAllocate <= 0) {
                    break;
                }

                $deficit = $term->sisaNominal();
                if ($deficit <= 0) {
                    continue;
                }

                $alloc = min($remainingToAllocate, $deficit);
                $term->nominal_dibayar = round((float) $term->nominal_dibayar + $alloc, 2);
                $term->updateStatusAutomatically();
                $term->save();

                $remainingToAllocate -= $alloc;
            }
        }
    }

    /**
     * Buat jadwal termin untuk Purchase Order.
     *
     * @param  array<int, array{
     *     termin_ke?: int,
     *     tanggal_tempo: string,
     *     nominal_tagihan: float|int,
     *     keterangan?: string|null
     * }>  $terminsData
     */
    public function createTerminsForPo(PurchaseOrder $po, array $terminsData): void
    {
        if (empty($terminsData)) {
            return;
        }

        $po->termins()->delete();

        foreach ($terminsData as $index => $row) {
            $terminKe = (int) ($row['termin_ke'] ?? ($index + 1));
            $tagihan = round((float) ($row['nominal_tagihan'] ?? 0), 2);
            $tempo = $row['tanggal_tempo'];

            if ($tagihan <= 0) {
                continue;
            }

            PurchaseOrderTermin::create([
                'po_id' => $po->id,
                'termin_ke' => $terminKe,
                'tanggal_tempo' => $tempo,
                'nominal_tagihan' => $tagihan,
                'nominal_dibayar' => 0,
                'status' => 'belum_lunas',
                'keterangan' => $row['keterangan'] ?? "Termin {$terminKe}",
            ]);
        }
    }

    /**
     * Periksa dan perbarui status overdue untuk seluruh termin dan PO aktif.
     */
    public function refreshAllOverdueStatuses(): int
    {
        $overdueCount = 0;
        $poIds = collect();

        // 1. Perbarui status termin yang jatuh tempo sebelum hari ini (kecuali PO dibatalkan)
        $termins = PurchaseOrderTermin::where('status', '!=', 'lunas')
            ->whereDate('tanggal_tempo', '<', now()->toDateString())
            ->whereHas('purchaseOrder', fn ($q) => $q->where('status', '!=', 'dibatalkan'))
            ->get();

        foreach ($termins as $termin) {
            $termin->updateStatusAutomatically();
            $termin->save();
            $poIds->push($termin->po_id);
            $overdueCount++;
        }

        // 2. Kumpulkan PO tempo yang memiliki ETA lewat hari ini dan belum lunas (kecuali PO dibatalkan)
        $posTempo = PurchaseOrder::whereNotIn('status_pembayaran', ['lunas', 'batal'])
            ->where('status', '!=', 'dibatalkan')
            ->whereNotNull('eta')
            ->whereDate('eta', '<', now()->toDateString())
            ->pluck('id');

        foreach ($posTempo as $id) {
            $poIds->push($id);
        }

        // 3. Sinkronkan status pembayaran di purchase_orders
        foreach ($poIds->unique() as $poId) {
            $po = PurchaseOrder::find($poId);
            if ($po && ! $po->isCancelled() && ! $po->isLunas()) {
                $po->refreshStatusPembayaran();
                $po->save();
            }
        }

        return $overdueCount;
    }
}

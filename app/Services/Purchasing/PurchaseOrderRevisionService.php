<?php

namespace App\Services\Purchasing;

use App\Models\PoLogPerubahan;
use App\Models\PoPengiriman;
use App\Models\PoRevisi;
use App\Models\PoStage;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderRevisionService
{
    protected PurchasingCostCalculator $calculator;

    public function __construct(PurchasingCostCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Direct edit field operasional tanpa approval (TC-33).
     * Mencatat perubahan ke PO_LOG_PERUBAHAN.
     */
    public function updateOperationalFields(PurchaseOrder $po, array $fields, User $actor): void
    {
        DB::transaction(function () use ($po, $fields, $actor) {
            $allowedPoFields = ['catatan', 'eta_supplier', 'no_referensi_supplier', 'no_invoice'];
            $allowedPengirimanFields = ['ekspedisi', 'moda', 'no_resi', 'no_kontainer', 'jumlah_koli', 'berat_kg', 'catatan_pengemasan', 'eta_tiba'];

            $pengiriman = PoPengiriman::firstOrCreate(['po_id' => $po->id]);

            foreach ($fields as $key => $newValue) {
                if (in_array($key, $allowedPoFields, true)) {
                    $oldValue = (string) ($po->{$key} ?? '');
                    $newValStr = (string) ($newValue ?? '');

                    if ($oldValue !== $newValStr) {
                        PoLogPerubahan::create([
                            'po_id' => $po->id,
                            'nama_field' => $key,
                            'nilai_lama' => $oldValue ?: null,
                            'nilai_baru' => $newValStr ?: null,
                            'diubah_oleh' => $actor->id,
                            'diubah_pada' => now(),
                        ]);
                        $po->{$key} = $newValue;
                    }
                } elseif (in_array($key, $allowedPengirimanFields, true)) {
                    $oldValue = (string) ($pengiriman->{$key} ?? '');
                    $newValStr = (string) ($newValue ?? '');

                    if ($oldValue !== $newValStr) {
                        PoLogPerubahan::create([
                            'po_id' => $po->id,
                            'nama_field' => 'pengiriman.' . $key,
                            'nilai_lama' => $oldValue ?: null,
                            'nilai_baru' => $newValStr ?: null,
                            'diubah_oleh' => $actor->id,
                            'diubah_pada' => now(),
                        ]);
                        $pengiriman->{$key} = $newValue;
                    }
                }
            }

            $po->save();
            $pengiriman->save();
        });
    }

    /**
     * Mengajukan revisi data komitmen (qty/harga/termin/item) (TC-34, TC-37).
     * Ditolak jika PO berada di Stage 8, Stage 9, selesai, atau dibatalkan.
     */
    public function submitCommitmentRevision(PurchaseOrder $po, array $proposedData, string $alasan, User $actor): PoRevisi
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan revisi wajib diisi.',
            ]);
        }

        // TC-37: Data terkunci di Stage 8, Stage 9, selesai, atau dibatalkan
        if (in_array($po->status, ['selesai', 'dibatalkan'], true) ||
            in_array($po->stage_aktif, [PoStage::STAGE_8, PoStage::STAGE_9], true)) {
            throw ValidationException::withMessages([
                'status' => 'Revisi komitmen tidak diizinkan pada stage Unloading, Input Sistem, atau dokumen yang sudah final.',
            ]);
        }

        // Cek revisi aktif yang masih menunggu keputusan
        if ($po->revisis()->where('status', PoRevisi::STATUS_MENUNGGU)->exists()) {
            throw ValidationException::withMessages([
                'revisi' => 'Masih ada usulan revisi yang menunggu keputusan Manager.',
            ]);
        }

        $po->loadMissing('items');
        $oldSnapshot = [
            'grand_total' => (float) $po->grand_total,
            'subtotal_produk' => (float) $po->subtotal_produk,
            'items' => $po->items->map(fn ($item) => [
                'id' => $item->id,
                'produk_id' => $item->produk_id,
                'qty' => (float) $item->qty,
                'harga_total' => (float) $item->harga_total,
                'satuan_beli' => $item->satuan_beli,
                'qty_satuan_beli' => (float) $item->qty_satuan_beli,
                'faktor_konversi' => (float) $item->faktor_konversi,
            ])->toArray(),
        ];

        return $po->revisis()->create([
            'revisi_ke' => (int) $po->revisi_ke + 1,
            'stage_saat_ajukan' => $po->stage_aktif,
            'status' => PoRevisi::STATUS_MENUNGGU,
            'alasan' => $alasan,
            'data_lama' => $oldSnapshot,
            'data_usulan' => $proposedData,
            'diajukan_oleh' => $actor->id,
            'diajukan_pada' => now(),
        ]);
    }

    /**
     * Persetujuan revisi oleh Manager (TC-35).
     * Memperbarui data aktif PO, menghitung ulang kalkulasi finansial, menaikkan revisi_ke, tanpa mengubah waktu stage.
     */
    public function approveRevision(PoRevisi $revisi, User $manager): void
    {
        if ($revisi->status !== PoRevisi::STATUS_MENUNGGU) {
            throw ValidationException::withMessages([
                'status' => 'Revisi ini sudah diputuskan sebelumnya.',
            ]);
        }

        DB::transaction(function () use ($revisi, $manager) {
            $po = $revisi->purchaseOrder;
            $usulan = $revisi->data_usulan;

            // 1. Terapkan pembaruan items jika ada
            if (! empty($usulan['items']) && is_array($usulan['items'])) {
                $po->items()->delete();
                foreach ($usulan['items'] as $itemData) {
                    $po->items()->create([
                        'produk_id' => $itemData['produk_id'],
                        'qty' => $itemData['qty'],
                        'harga_total' => $itemData['harga_total'],
                        'satuan_beli' => $itemData['satuan_beli'] ?? null,
                        'qty_satuan_beli' => $itemData['qty_satuan_beli'] ?? null,
                        'faktor_konversi' => $itemData['faktor_konversi'] ?? 1.0,
                        'diskon' => $itemData['diskon'] ?? 0,
                        'ppn' => $itemData['ppn'] ?? 0,
                        'ongkir' => $itemData['ongkir'] ?? 0,
                        'adjustment' => $itemData['adjustment'] ?? 0,
                    ]);
                }
            }

            // 2. Hitung ulang total biaya PO
            $po->load('items');
            $rawItems = $po->items->map(fn ($it) => [
                'produk_id' => $it->produk_id,
                'qty' => $it->qty,
                'harga_total' => $it->harga_total,
                'diskon' => $it->diskon,
                'ppn' => $it->ppn,
                'ongkir' => $it->ongkir,
                'adjustment' => $it->adjustment,
            ])->toArray();

            $totals = $this->calculator->calculatePoTotals(
                $rawItems,
                (float) ($usulan['diskon_total'] ?? $po->diskon_total),
                (float) ($usulan['ppn_nominal'] ?? $po->ppn_nominal),
                (float) ($usulan['ongkos_kirim'] ?? $po->ongkos_kirim),
                (float) ($usulan['adjustment'] ?? $po->adjustment)
            );

            $po->subtotal_produk = $totals['subtotal_produk'];
            $po->diskon_total = $totals['diskon_total'];
            $po->ppn_nominal = $totals['ppn_nominal'];
            $po->ongkos_kirim = $totals['ongkos_kirim'];
            $po->adjustment = $totals['adjustment'];
            $po->grand_total = $totals['grand_total'];
            $po->revisi_ke = (int) $po->revisi_ke + 1;
            $po->refreshStatusPembayaran();
            $po->save();

            // 3. Update status revisi
            $revisi->status = PoRevisi::STATUS_DISETUJUI;
            $revisi->diputuskan_oleh = $manager->id;
            $revisi->diputuskan_pada = now();
            $revisi->save();
        });
    }

    /**
     * Penolakan revisi oleh Manager (TC-36).
     * Wajib menyertakan alasan penolakan. Data asli tetap utuh.
     */
    public function rejectRevision(PoRevisi $revisi, User $manager, string $alasan): void
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan penolakan revisi wajib diisi.',
            ]);
        }

        if ($revisi->status !== PoRevisi::STATUS_MENUNGGU) {
            throw ValidationException::withMessages([
                'status' => 'Revisi ini sudah diputuskan sebelumnya.',
            ]);
        }

        $revisi->status = PoRevisi::STATUS_DITOLAK;
        $revisi->catatan_keputusan = $alasan;
        $revisi->diputuskan_oleh = $manager->id;
        $revisi->diputuskan_pada = now();
        $revisi->save();
    }
}

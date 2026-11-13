<?php

namespace App\Services\Purchasing;

use App\Models\PoLogPerubahan;
use App\Models\PoPengiriman;
use App\Models\PoRevisi;
use App\Models\PoStage;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        if (in_array($po->status, ['selesai', 'dibatalkan'], true)) {
            throw ValidationException::withMessages([
                'status' => 'PO sudah final dan tidak dapat diubah field operasionalnya.',
            ]);
        }

        DB::transaction(function () use ($po, $fields, $actor) {
            $lockedPo = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            if ($lockedPo->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Field operasional PO yang sudah selesai atau dibatalkan tidak dapat diubah.',
                ]);
            }

            $allowedPoFields = ['catatan', 'eta', 'eta_supplier', 'no_referensi_supplier', 'no_invoice'];
            $allowedPengirimanFields = ['ekspedisi', 'moda', 'no_resi', 'no_kontainer', 'jumlah_koli', 'berat_kg', 'catatan_pengemasan', 'eta_tiba'];

            $pengiriman = PoPengiriman::firstOrCreate(['po_id' => $lockedPo->id]);

            $this->syncOperationalFieldGroup($lockedPo, $lockedPo, $fields, $allowedPoFields, ['eta', 'eta_supplier'], '', $actor->id);
            $this->syncOperationalFieldGroup($lockedPo, $pengiriman, $fields, $allowedPengirimanFields, ['eta_tiba'], 'pengiriman.', $actor->id);

            if ($lockedPo->isDirty('eta')) {
                $lockedPo->refreshStatusPembayaran();
            }

            $lockedPo->save();
            $pengiriman->save();
            $po->forceFill($lockedPo->getAttributes());
        });
    }

    /**
     * Sinkronisasi field operasional yang diizinkan pada target model dan catat log perubahan.
     */
    private function syncOperationalFieldGroup(
        PurchaseOrder $po,
        object $target,
        array $fields,
        array $allowedFields,
        array $dateFields,
        string $logPrefix,
        int $actorId,
    ): void {
        foreach ($fields as $key => $newValue) {
            if (! in_array($key, $allowedFields, true)) {
                continue;
            }

            [$oldValue, $newValStr, $assigned] = $this->resolveOperationalFieldDiff(
                $target,
                $key,
                $newValue,
                in_array($key, $dateFields, true),
            );

            if ($oldValue !== $newValStr) {
                PoLogPerubahan::create([
                    'po_id' => $po->id,
                    'nama_field' => $logPrefix . $key,
                    'nilai_lama' => $oldValue ?: null,
                    'nilai_baru' => $newValStr ?: null,
                    'diubah_oleh' => $actorId,
                    'diubah_pada' => now(),
                ]);
                $target->{$key} = $assigned;
            }
        }
    }

    /**
     * Normalisasi nilai lama dan nilai baru untuk perbandingan diff dan penyimpanan field operasional.
     *
     * @return array{0: string, 1: string, 2: mixed}
     */
    private function resolveOperationalFieldDiff(object $target, string $key, mixed $newValue, bool $isDate): array
    {
        if (! $isDate) {
            return [(string) ($target->{$key} ?? ''), (string) ($newValue ?? ''), $newValue];
        }

        $oldValue = $target->{$key} ? Carbon::parse($target->{$key})->format('Y-m-d') : '';
        if (! filled($newValue)) {
            return [$oldValue, '', null];
        }

        try {
            $assigned = Carbon::parse($newValue)->format('Y-m-d');
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                "fields.{$key}" => "Format tanggal untuk {$key} tidak valid.",
            ]);
        }

        return [$oldValue, $assigned, $assigned];
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

        return DB::transaction(function () use ($po, $proposedData, $alasan, $actor) {
            $lockedPo = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            // TC-37: Data terkunci di Stage 7 (Unloading), Stage 8 (Input Sistem), selesai, atau dibatalkan
            if ($lockedPo->isFinal() ||
                in_array($lockedPo->stage_aktif, [PoStage::STAGE_7, PoStage::STAGE_8], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Revisi komitmen tidak diizinkan pada stage Unloading, Input Sistem, atau dokumen yang sudah final.',
                ]);
            }

            // Cek revisi aktif yang masih menunggu keputusan
            if ($lockedPo->revisis()->where('status', PoRevisi::STATUS_MENUNGGU)->exists()) {
                throw ValidationException::withMessages([
                    'revisi' => 'Masih ada usulan revisi yang menunggu keputusan Manager.',
                ]);
            }

            $sanitizedProposedData = $this->normalizeAndValidateProposedRevision($lockedPo, $proposedData);

            $lockedPo->loadMissing('items');
            $oldSnapshot = [
                'grand_total' => (float) $lockedPo->grand_total,
                'subtotal_produk' => (float) $lockedPo->subtotal_produk,
                'diskon_total' => (float) $lockedPo->diskon_total,
                'ppn_nominal' => (float) $lockedPo->ppn_nominal,
                'ongkos_kirim' => (float) $lockedPo->ongkos_kirim,
                'adjustment' => (float) $lockedPo->adjustment,
                'items' => $lockedPo->items->map(fn ($item) => [
                    'id' => $item->id,
                    'po_item_id' => $item->id,
                    'produk_id' => $item->produk_id,
                    'qty' => (float) $item->qty,
                    'harga_total' => (float) $item->harga_total,
                    'satuan_beli' => $item->satuan_beli,
                    'qty_satuan_beli' => (float) $item->qty_satuan_beli,
                    'faktor_konversi' => (float) $item->faktor_konversi,
                ])->toArray(),
            ];

            return $lockedPo->revisis()->create([
                'revisi_ke' => (int) $lockedPo->revisi_ke + 1,
                'stage_saat_ajukan' => $lockedPo->stage_aktif,
                'status' => PoRevisi::STATUS_MENUNGGU,
                'alasan' => $alasan,
                'data_lama' => $oldSnapshot,
                'data_usulan' => $sanitizedProposedData,
                'diajukan_oleh' => $actor->id,
                'diajukan_pada' => now(),
            ]);
        });
    }

    /**
     * Persetujuan revisi oleh Manager (TC-35).
     * Memperbarui data aktif PO, menghitung ulang kalkulasi finansial, menaikkan revisi_ke, tanpa mengubah waktu stage.
     */
    public function approveRevision(PoRevisi $revisi, User $manager): void
    {
        DB::transaction(function () use ($revisi, $manager) {
            $lockedRevisi = PoRevisi::lockForUpdate()->findOrFail($revisi->id);

            if ($lockedRevisi->status !== PoRevisi::STATUS_MENUNGGU) {
                throw ValidationException::withMessages([
                    'status' => 'Revisi ini sudah diputuskan sebelumnya.',
                ]);
            }

            $po = PurchaseOrder::lockForUpdate()->findOrFail($lockedRevisi->po_id);

            if ($po->isFinal() || in_array($po->stage_aktif, [PoStage::STAGE_7, PoStage::STAGE_8], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Revisi tidak dapat disetujui karena PO sudah dibatalkan, selesai, atau memasuki tahap Unloading/Input Sistem.',
                ]);
            }

            $usulan = $this->normalizeAndValidateProposedRevision($po, $lockedRevisi->data_usulan ?? []);

            // 1. Terapkan pembaruan items jika ada (in-place update untuk menjaga integritas UUID)
            if (! empty($usulan['items']) && is_array($usulan['items'])) {
                $po->load('items');
                $existingItems = $po->items->keyBy('id');

                foreach ($usulan['items'] as $itemData) {
                    $itemId = $itemData['po_item_id'] ?? null;
                    $item = $itemId ? $existingItems->get($itemId) : null;

                    if (! $item && ! empty($itemData['produk_id'])) {
                        $item = $existingItems->firstWhere('produk_id', $itemData['produk_id']);
                    }

                    if (! $item) {
                        throw ValidationException::withMessages([
                            'items' => 'Item usulan revisi tidak valid atau tidak terdaftar di PO asli.',
                        ]);
                    }

                    $faktor = (float) ($itemData['faktor_konversi'] ?? $item->faktor_konversi ?? 1.0);
                    if ($faktor <= 0) {
                        $faktor = 1.0;
                    }

                    $qtyBeli = isset($itemData['qty_satuan_beli']) && $itemData['qty_satuan_beli'] !== null && $itemData['qty_satuan_beli'] !== ''
                        ? (float) $itemData['qty_satuan_beli']
                        : null;

                    $qtyDasar = $qtyBeli !== null ? round($qtyBeli * $faktor, 2) : (float) ($itemData['qty'] ?? 0);

                    $item->update([
                        'qty' => $qtyDasar,
                        'harga_total' => $itemData['harga_total'],
                        'satuan_beli' => $itemData['satuan_beli'] ?? $item->satuan_beli,
                        'qty_satuan_beli' => $qtyBeli ?? $item->qty_satuan_beli,
                        'faktor_konversi' => $faktor,
                        'diskon' => $itemData['diskon'] ?? 0,
                        'ppn' => $itemData['ppn'] ?? 0,
                        'ongkir' => $itemData['ongkir'] ?? 0,
                        'adjustment' => $itemData['adjustment'] ?? 0,
                    ]);
                }
            }

            // 2. Hitung ulang total biaya PO secara otoritatif
            $po->load('items');
            $rawItems = $po->items->map(fn ($it) => [
                'produk_id' => $it->produk_id,
                'qty' => $it->qty,
                'harga_total' => $it->harga_total,
                'satuan_beli' => $it->satuan_beli,
                'qty_satuan_beli' => $it->qty_satuan_beli,
                'faktor_konversi' => $it->faktor_konversi,
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

            $this->assertValidRevisionFinancials($po, $totals);

            // Perbarui breakdown biaya dan HPP satuan per item
            foreach ($po->items as $it) {
                $calcItem = collect($totals['items'] ?? [])->firstWhere('produk_id', $it->produk_id);
                if ($calcItem) {
                    $it->update([
                        'hpp_per_satuan' => $calcItem['hpp_per_satuan'] ?? $it->hpp_per_satuan,
                        'diskon' => $calcItem['diskon'] ?? $it->diskon,
                        'ppn' => $calcItem['ppn'] ?? $it->ppn,
                        'ongkir' => $calcItem['ongkir'] ?? $it->ongkir,
                        'adjustment' => $calcItem['adjustment'] ?? $it->adjustment,
                    ]);
                }
            }

            $po->subtotal_produk = $totals['subtotal_produk'];
            $po->diskon_total = $totals['diskon_total'];
            $po->ppn_nominal = $totals['ppn_nominal'];
            $po->ongkos_kirim = $totals['ongkos_kirim'];
            $po->adjustment = $totals['adjustment'];
            $po->grand_total = $totals['grand_total'];
            $po->revisi_ke = (int) $po->revisi_ke + 1;
            $po->unsetRelation('payments');
            $po->refreshStatusPembayaran();
            $po->save();

            // 3. Update status revisi
            $lockedRevisi->status = PoRevisi::STATUS_DISETUJUI;
            $lockedRevisi->diputuskan_oleh = $manager->id;
            $lockedRevisi->diputuskan_pada = now();
            $lockedRevisi->save();

            $revisi->forceFill($lockedRevisi->getAttributes());
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

        DB::transaction(function () use ($revisi, $manager, $alasan) {
            $lockedRevisi = PoRevisi::lockForUpdate()->findOrFail($revisi->id);

            if ($lockedRevisi->status !== PoRevisi::STATUS_MENUNGGU) {
                throw ValidationException::withMessages([
                    'status' => 'Revisi ini sudah diputuskan sebelumnya.',
                ]);
            }

            $po = PurchaseOrder::lockForUpdate()->findOrFail($lockedRevisi->po_id);

            if ($po->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Revisi tidak dapat diputuskan karena PO sudah dibatalkan atau selesai.',
                ]);
            }

            $lockedRevisi->status = PoRevisi::STATUS_DITOLAK;
            $lockedRevisi->catatan_keputusan = $alasan;
            $lockedRevisi->diputuskan_oleh = $manager->id;
            $lockedRevisi->diputuskan_pada = now();
            $lockedRevisi->save();

            $revisi->forceFill($lockedRevisi->getAttributes());
        });
    }

    /**
     * Validasi integritas payload revisi komitmen & hitung ulang total secara otoritatif di server
     * agar nilai tampilan Manager (show) dan nilai final saat approve tidak dapat dimanipulasi (anti-spoofing).
     */
    private function normalizeAndValidateProposedRevision(PurchaseOrder $po, array $proposedData): array
    {
        $po->loadMissing('items');
        $existingById = $po->items->keyBy('id');
        $existingByProduk = $po->items->keyBy('produk_id');

        $this->assertOptionalNumericFields($proposedData, ['diskon_total', 'ppn_nominal', 'ongkos_kirim'], 'data_usulan', true);
        $this->assertOptionalNumericFields($proposedData, ['adjustment'], 'data_usulan', false);

        $simulatedItems = $po->items->mapWithKeys(fn ($existingItem) => [
            $existingItem->id => [
                'produk_id' => $existingItem->produk_id,
                'qty' => (float) $existingItem->qty,
                'qty_satuan_beli' => $existingItem->qty_satuan_beli !== null ? (float) $existingItem->qty_satuan_beli : null,
                'satuan_beli' => $existingItem->satuan_beli,
                'faktor_konversi' => (float) ($existingItem->faktor_konversi ?: 1.0),
                'harga_total' => (float) $existingItem->harga_total,
                'diskon' => (float) ($existingItem->diskon ?? 0),
                'ppn' => (float) ($existingItem->ppn ?? 0),
                'ongkir' => (float) ($existingItem->ongkir ?? 0),
                'adjustment' => (float) ($existingItem->adjustment ?? 0),
            ],
        ])->all();

        if (array_key_exists('items', $proposedData)) {
            if (! is_array($proposedData['items']) || count($proposedData['items']) === 0) {
                throw ValidationException::withMessages([
                    'data_usulan.items' => 'Daftar item revisi komitmen tidak boleh kosong.',
                ]);
            }

            $normalizedItems = [];
            $seenItemIds = [];

            foreach ($proposedData['items'] as $idx => $row) {
                $normalizedRow = $this->normalizeProposedItem($row, $idx, $existingById, $existingByProduk, $seenItemIds);
                $seenItemIds[] = $normalizedRow['po_item_id'];
                $normalizedItems[] = $normalizedRow;
                $simulatedItems[$normalizedRow['po_item_id']] = $normalizedRow;
            }

            $proposedData['items'] = $normalizedItems;
        }

        $totals = $this->calculator->calculatePoTotals(
            array_values($simulatedItems),
            (float) ($proposedData['diskon_total'] ?? $po->diskon_total),
            (float) ($proposedData['ppn_nominal'] ?? $po->ppn_nominal),
            (float) ($proposedData['ongkos_kirim'] ?? $po->ongkos_kirim),
            (float) ($proposedData['adjustment'] ?? $po->adjustment)
        );

        $this->assertValidRevisionFinancials($po, $totals);

        // Overwrite header totals dengan hasil kalkulasi server untuk mencegah manipulasi tampilan Manager
        foreach (['subtotal_produk', 'diskon_total', 'ppn_nominal', 'ongkos_kirim', 'adjustment', 'grand_total'] as $summaryField) {
            $proposedData[$summaryField] = $totals[$summaryField];
        }

        return $proposedData;
    }

    /**
     * Validasi field numerik opsional (non-negatif jika diwajibkan) untuk header atau baris item revisi.
     */
    private function assertOptionalNumericFields(array $source, array $fields, string $prefix, bool $requireNonNegative): void
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $source) || $source[$field] === null || $source[$field] === '') {
                continue;
            }

            $val = $source[$field];
            if (! is_numeric($val) || ($requireNonNegative && (float) $val < 0)) {
                $constraint = $requireNonNegative ? 'angka >= 0' : 'angka yang valid';
                throw ValidationException::withMessages([
                    "{$prefix}.{$field}" => "Nilai {$field} revisi harus berupa {$constraint}.",
                ]);
            }
        }
    }

    /**
     * Verifikasi identitas item terhadap PO asli serta normalisasi qty konversi & harga item revisi.
     */
    private function normalizeProposedItem(
        mixed $row,
        int|string $idx,
        Collection $existingById,
        Collection $existingByProduk,
        array $seenItemIds
    ): array {
        $prefix = "data_usulan.items.{$idx}";

        if (! is_array($row)) {
            throw ValidationException::withMessages([$prefix => 'Format item revisi tidak valid.']);
        }

        $itemId = $row['po_item_id'] ?? null;
        $produkId = $row['produk_id'] ?? null;
        $matchedItem = $itemId ? $existingById->get($itemId) : null;

        if ($itemId && ! $matchedItem) {
            throw ValidationException::withMessages(["{$prefix}.po_item_id" => 'Item PO yang direvisi tidak ditemukan pada dokumen asli.']);
        }

        if ($matchedItem && $produkId !== null && $produkId !== '' && (int) $produkId !== (int) $matchedItem->produk_id) {
            throw ValidationException::withMessages(["{$prefix}.produk_id" => 'Jenis bahan baku terkunci sesuai PO asli dan tidak dapat diganti.']);
        }

        if (! $matchedItem) {
            if ($produkId === null || $produkId === '' || ! $existingByProduk->has((int) $produkId)) {
                throw ValidationException::withMessages(["{$prefix}.produk_id" => 'Jenis bahan baku terkunci sesuai PO asli (tidak dapat menambah produk di luar PO).']);
            }
            $matchedItem = $existingByProduk->get((int) $produkId);
        }

        if (in_array($matchedItem->id, $seenItemIds, true)) {
            throw ValidationException::withMessages(["{$prefix}.produk_id" => 'Terdapat duplikasi item bahan baku dalam usulan revisi.']);
        }

        if (! isset($row['harga_total']) || ! is_numeric($row['harga_total']) || (float) $row['harga_total'] < 0) {
            throw ValidationException::withMessages(["{$prefix}.harga_total" => 'Harga total item revisi harus angka >= 0.']);
        }

        $rawFaktor = $row['faktor_konversi'] ?? $matchedItem->faktor_konversi ?? 1.0;
        if (! is_numeric($rawFaktor) || (float) $rawFaktor <= 0) {
            throw ValidationException::withMessages(["{$prefix}.faktor_konversi" => 'Faktor konversi item revisi harus lebih dari 0.']);
        }
        $faktor = (float) $rawFaktor;

        $hasQtyBeli = isset($row['qty_satuan_beli']) && $row['qty_satuan_beli'] !== null && $row['qty_satuan_beli'] !== '';
        if ($hasQtyBeli) {
            if (! is_numeric($row['qty_satuan_beli']) || (float) $row['qty_satuan_beli'] <= 0) {
                throw ValidationException::withMessages(["{$prefix}.qty_satuan_beli" => 'Kuantitas satuan beli revisi harus lebih dari 0.']);
            }
            $qtyBeli = (float) $row['qty_satuan_beli'];
            $qtyDasar = round($qtyBeli * $faktor, 2);
        } else {
            if (! isset($row['qty']) || ! is_numeric($row['qty']) || (float) $row['qty'] <= 0) {
                throw ValidationException::withMessages(["{$prefix}.qty" => 'Kuantitas item revisi harus lebih dari 0.']);
            }
            $qtyBeli = null;
            $qtyDasar = round((float) $row['qty'], 2);
        }

        $this->assertOptionalNumericFields($row, ['diskon', 'ppn', 'ongkir'], $prefix, true);
        $this->assertOptionalNumericFields($row, ['adjustment'], $prefix, false);

        return [
            'po_item_id' => $matchedItem->id,
            'produk_id' => $matchedItem->produk_id,
            'qty' => $qtyDasar,
            'satuan_beli' => $row['satuan_beli'] ?? $matchedItem->satuan_beli,
            'qty_satuan_beli' => $qtyBeli,
            'faktor_konversi' => $faktor,
            'harga_total' => round((float) $row['harga_total'], 2),
            'diskon' => round((float) ($row['diskon'] ?? 0), 2),
            'ppn' => round((float) ($row['ppn'] ?? 0), 2),
            'ongkir' => round((float) ($row['ongkir'] ?? 0), 2),
            'adjustment' => round((float) ($row['adjustment'] ?? 0), 2),
        ];
    }

    private function assertValidRevisionFinancials(PurchaseOrder $po, array $totals): void
    {
        if (($totals['raw_grand_total'] ?? $totals['grand_total']) < 0) {
            throw ValidationException::withMessages([
                'data_usulan' => 'Total bersih PO hasil revisi tidak boleh bernilai negatif (diskon/potongan melebihi nilai barang).',
            ]);
        }

        $alreadyPaid = round((float) $po->payments()->sum('nominal'), 2);
        if ($alreadyPaid > 0 && (float) $totals['grand_total'] < $alreadyPaid) {
            throw ValidationException::withMessages([
                'data_usulan' => 'Grand total usulan revisi (Rp ' . number_format((float) $totals['grand_total'], 0, ',', '.') . ') tidak boleh lebih kecil dari total yang sudah dibayar (Rp ' . number_format($alreadyPaid, 0, ',', '.') . ').',
            ]);
        }
    }
}

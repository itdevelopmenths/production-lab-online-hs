<?php

namespace App\Services\Purchasing;

use App\Models\BarangDatang;
use App\Models\PoPengiriman;
use App\Models\PoRevisi;
use App\Models\PoStage;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\StokService;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasingStageService
{
    protected SupplierLeadTimeService $leadTimeService;
    protected StokService $stokService;

    public function __construct(SupplierLeadTimeService $leadTimeService, StokService $stokService)
    {
        $this->leadTimeService = $leadTimeService;
        $this->stokService = $stokService;
    }

    /**
     * Inisialisasi Stage 1 saat pembuatan PO Draft.
     */
    public function initializePoStages(PurchaseOrder $po, ?DateTimeInterface $startTime = null, ?User $actor = null): PoStage
    {
        $startTime = $startTime ? Carbon::parse($startTime) : now();

        $stage1 = $po->stages()->create([
            'stage' => PoStage::STAGE_1,
            'urutan' => 1,
            'putaran' => 1,
            'status' => 'berjalan',
            'waktu_mulai' => $startTime,
            'pic_user_id' => $actor?->id ?: $po->created_by,
            'dicatat_oleh' => $actor?->id ?: $po->created_by,
            'dicatat_pada' => now(),
        ]);

        $po->stage_aktif = PoStage::STAGE_1;
        $po->save();

        return $stage1;
    }

    /**
     * Validasi urutan stage tanpa lompatan (BR-01, TC-02).
     */
    public function validateExpectedStage(PurchaseOrder $po, string $expectedStage): PoStage
    {
        if ($po->status === 'selesai') {
            throw ValidationException::withMessages([
                'stage' => 'Purchase Order ini telah berstatus Selesai. Seluruh tahapan telah rampung dan tidak dapat diproses ulang.',
            ]);
        }

        if ($po->status === 'dibatalkan') {
            throw ValidationException::withMessages([
                'stage' => 'Purchase Order ini telah Dibatalkan dan tidak dapat diproses lebih lanjut.',
            ]);
        }

        if ($po->stage_aktif !== $expectedStage) {
            throw ValidationException::withMessages([
                'stage' => sprintf(
                    "Operasi tidak valid. Stage aktif saat ini adalah '%s', bukan '%s'. Urutan stage harus dipatuhi secara ketat.",
                    PoStage::STAGE_LABELS[$po->stage_aktif] ?? $po->stage_aktif,
                    PoStage::STAGE_LABELS[$expectedStage] ?? $expectedStage
                ),
            ]);
        }

        $activeStageRow = $po->stages()
            ->where('stage', $expectedStage)
            ->where('status', 'berjalan')
            ->latest('putaran')
            ->first();

        if (! $activeStageRow) {
            $completedStageRow = $po->stages()
                ->where('stage', $expectedStage)
                ->where('status', 'selesai')
                ->latest('putaran')
                ->first();

            if ($completedStageRow) {
                throw ValidationException::withMessages([
                    'stage' => sprintf(
                        "Tahap '%s' sudah selesai diproses sebelumnya.",
                        PoStage::STAGE_LABELS[$expectedStage] ?? $expectedStage
                    ),
                ]);
            }

            throw ValidationException::withMessages([
                'stage' => "Data stage '{$expectedStage}' yang aktif tidak ditemukan.",
            ]);
        }

        return $activeStageRow;
    }

    /**
     * Validasi integritas rentang waktu aktual (BR-04, TC-04, TC-05).
     */
    public function validateTimeWindow(DateTimeInterface $waktuMulai, DateTimeInterface $waktuSelesai): void
    {
        $mulai = Carbon::parse($waktuMulai);
        $selesai = Carbon::parse($waktuSelesai);

        if ($selesai->lt($mulai)) {
            throw ValidationException::withMessages([
                'waktu_selesai' => 'Waktu selesai tidak boleh lebih awal dari waktu mulai stage.',
            ]);
        }

        // Toleransi clock skew 5 detik
        if ($selesai->gt(now()->addSeconds(5))) {
            throw ValidationException::withMessages([
                'waktu_selesai' => 'Waktu selesai tidak boleh melebihi waktu sistem saat ini.',
            ]);
        }
    }

    /**
     * Validasi blokir penyelesaian stage jika ada revisi menunggu (TC-34).
     */
    public function validatePendingRevision(PurchaseOrder $po): void
    {
        if ($po->revisis()->where('status', PoRevisi::STATUS_MENUNGGU)->exists()) {
            throw ValidationException::withMessages([
                'revisi' => 'Stage ini tidak dapat diselesaikan karena masih ada revisi komitmen yang menunggu keputusan Manager.',
            ]);
        }
    }

    /**
     * Hitung durasi menit antara dua timestamp.
     */
    public static function calculateDurationMinutes(DateTimeInterface $start, DateTimeInterface $end): int
    {
        $s = Carbon::parse($start);
        $e = Carbon::parse($end);
        return max(0, (int) round(($e->timestamp - $s->timestamp) / 60));
    }

    /**
     * Selesaikan Stage 1 (Perencanaan & Pengajuan) -> Buka Stage 2 (Approval Manager).
     */
    public function completeStage1(PurchaseOrder $po, User $actor, ?DateTimeInterface $waktuSelesai = null, ?string $catatan = null): PoStage
    {
        return DB::transaction(function () use ($po, $actor, $waktuSelesai, $catatan) {
            $this->validatePendingRevision($po);
            $current = $this->validateExpectedStage($po, PoStage::STAGE_1);
            $selesai = $waktuSelesai ? Carbon::parse($waktuSelesai) : now();
            $this->validateTimeWindow($current->waktu_mulai, $selesai);

            $current->update([
                'status' => 'selesai',
                'waktu_selesai' => $selesai,
                'durasi_menit' => self::calculateDurationMinutes($current->waktu_mulai, $selesai),
                'dicatat_oleh' => $actor->id,
                'dicatat_pada' => now(),
                'catatan' => $catatan ?: $current->catatan,
            ]);

            // Buka Stage 2 dengan waktu mulai persis sama dengan waktu selesai Stage 1 (TC-06)
            $nextStage = $po->stages()->create([
                'stage' => PoStage::STAGE_2,
                'urutan' => 2,
                'putaran' => $current->putaran,
                'status' => 'berjalan',
                'waktu_mulai' => $selesai,
                'dicatat_oleh' => $actor->id,
                'dicatat_pada' => now(),
            ]);

            $po->stage_aktif = PoStage::STAGE_2;
            $po->status = 'berjalan';
            $po->save();

            return $nextStage;
        });
    }

    /**
     * Selesaikan Stage 2 (Approval Manager) -> Buka Stage 3 (Konfirmasi Supplier).
     */
    public function approveStage2(PurchaseOrder $po, User $manager, ?DateTimeInterface $waktuSelesai = null, ?string $catatan = null): PoStage
    {
        return DB::transaction(function () use ($po, $manager, $waktuSelesai, $catatan) {
            $this->validatePendingRevision($po);
            $current = $this->validateExpectedStage($po, PoStage::STAGE_2);
            $selesai = $waktuSelesai ? Carbon::parse($waktuSelesai) : now();
            $this->validateTimeWindow($current->waktu_mulai, $selesai);

            $current->update([
                'status' => 'selesai',
                'waktu_selesai' => $selesai,
                'durasi_menit' => self::calculateDurationMinutes($current->waktu_mulai, $selesai),
                'pic_user_id' => $manager->id,
                'dicatat_oleh' => $manager->id,
                'dicatat_pada' => now(),
                'catatan' => $catatan ?: 'Disetujui oleh Manager',
            ]);

            // Buka Stage 3
            $nextStage = $po->stages()->create([
                'stage' => PoStage::STAGE_3,
                'urutan' => 3,
                'putaran' => $current->putaran,
                'status' => 'berjalan',
                'waktu_mulai' => $selesai,
                'dicatat_oleh' => $manager->id,
                'dicatat_pada' => now(),
            ]);

            $po->stage_aktif = PoStage::STAGE_3;
            $po->save();

            return $nextStage;
        });
    }

    /**
     * Reject Stage 2 oleh Manager -> Tandai 'dikembalikan' & Buka Putaran Baru di Stage 1 (BR-06, TC-12).
     */
    public function rejectStage2(PurchaseOrder $po, User $manager, string $alasan, ?DateTimeInterface $waktuSelesai = null): PoStage
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan penolakan / pengembalian PO wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($po, $manager, $alasan, $waktuSelesai) {
            $current = $this->validateExpectedStage($po, PoStage::STAGE_2);
            $selesai = $waktuSelesai ? Carbon::parse($waktuSelesai) : now();
            $this->validateTimeWindow($current->waktu_mulai, $selesai);

            $current->update([
                'status' => 'dikembalikan',
                'waktu_selesai' => $selesai,
                'durasi_menit' => self::calculateDurationMinutes($current->waktu_mulai, $selesai),
                'pic_user_id' => $manager->id,
                'dicatat_oleh' => $manager->id,
                'dicatat_pada' => now(),
                'catatan' => $alasan,
            ]);

            $newPutaran = $current->putaran + 1;

            // Buka Stage 1 pada putaran baru
            $newStage1 = $po->stages()->create([
                'stage' => PoStage::STAGE_1,
                'urutan' => 1,
                'putaran' => $newPutaran,
                'status' => 'berjalan',
                'waktu_mulai' => $selesai,
                'pic_user_id' => $po->created_by,
                'dicatat_oleh' => $manager->id,
                'dicatat_pada' => now(),
                'catatan' => 'Putaran ' . $newPutaran . ' setelah penolakan: ' . $alasan,
            ]);

            $po->stage_aktif = PoStage::STAGE_1;
            $po->save();

            return $newStage1;
        });
    }

    /**
     * Selesaikan Stage 3 (Konfirmasi Supplier) -> Buka Stage 4 (Pembayaran).
     */
    public function completeStage3(PurchaseOrder $po, User $purchasing, array $confirmationData = [], ?DateTimeInterface $waktuSelesai = null, ?string $catatan = null): PoStage
    {
        return DB::transaction(function () use ($po, $purchasing, $confirmationData, $waktuSelesai, $catatan) {
            $this->validatePendingRevision($po);
            $current = $this->validateExpectedStage($po, PoStage::STAGE_3);
            $selesai = $waktuSelesai ? Carbon::parse($waktuSelesai) : now();
            $this->validateTimeWindow($current->waktu_mulai, $selesai);

            // Simpan konfirmasi supplier jika ada
            if (! empty($confirmationData['items']) && is_array($confirmationData['items'])) {
                foreach ($confirmationData['items'] as $itemConfirm) {
                    if (isset($itemConfirm['id'])) {
                        $poItem = $po->items()->find($itemConfirm['id']);
                        if ($poItem) {
                            $poItem->qty_konfirmasi = $itemConfirm['qty_konfirmasi'] ?? $poItem->qty;
                            $poItem->harga_konfirmasi = $itemConfirm['harga_konfirmasi'] ?? $poItem->harga_total;
                            $poItem->save();
                        }
                    }
                }
            }

            if (! empty($confirmationData['eta_supplier'])) {
                $po->eta_supplier = $confirmationData['eta_supplier'];
            }
            $noReferensi = $confirmationData['no_referensi_supplier'] ?? $confirmationData['no_referensi'] ?? null;
            if (! empty($noReferensi)) {
                $po->no_referensi_supplier = $noReferensi;
            }

            $current->update([
                'status' => 'selesai',
                'waktu_selesai' => $selesai,
                'durasi_menit' => self::calculateDurationMinutes($current->waktu_mulai, $selesai),
                'pic_user_id' => $purchasing->id,
                'dicatat_oleh' => $purchasing->id,
                'dicatat_pada' => now(),
                'catatan' => $catatan ?: $current->catatan,
            ]);

            // Buka Stage 4
            $nextStage = $po->stages()->create([
                'stage' => PoStage::STAGE_4,
                'urutan' => 4,
                'putaran' => $current->putaran,
                'status' => 'berjalan',
                'waktu_mulai' => $selesai,
                'dicatat_oleh' => $purchasing->id,
                'dicatat_pada' => now(),
            ]);

            $po->stage_aktif = PoStage::STAGE_4;
            $po->save();

            return $nextStage;
        });
    }

    /**
     * Selesaikan Stage 4 via tombol 'Dibayarkan' (TC-26, TC-27).
     * Wajib memiliki minimal 1 payment dengan nominal > 0.
     * Dapat lanjut ke Stage 5 meskipun status_pembayaran belum lunas (misal DP).
     */
    public function completeStage4WithPayment(PurchaseOrder $po, User $actor, ?DateTimeInterface $waktuSelesai = null, ?string $catatan = null): PoStage
    {
        return DB::transaction(function () use ($po, $actor, $waktuSelesai, $catatan) {
            $this->validatePendingRevision($po);
            $current = $this->validateExpectedStage($po, PoStage::STAGE_4);

            // TC-27: Tombol Dibayarkan butuh minimal 1 record payments
            $hasPayment = $po->payments()->where('nominal', '>', 0)->exists();
            if (! $hasPayment) {
                throw ValidationException::withMessages([
                    'payment' => 'Tombol Dibayarkan tidak dapat diproses karena belum ada pembayaran yang dicatat.',
                ]);
            }

            $selesai = $waktuSelesai ? Carbon::parse($waktuSelesai) : now();
            $this->validateTimeWindow($current->waktu_mulai, $selesai);

            $current->update([
                'status' => 'selesai',
                'waktu_selesai' => $selesai,
                'durasi_menit' => self::calculateDurationMinutes($current->waktu_mulai, $selesai),
                'pic_user_id' => $actor->id,
                'dicatat_oleh' => $actor->id,
                'dicatat_pada' => now(),
                'catatan' => $catatan ?: 'Pembayaran/DP telah dicatat',
            ]);

            // Buka Stage 5
            $nextStage = $po->stages()->create([
                'stage' => PoStage::STAGE_5,
                'urutan' => 5,
                'putaran' => $current->putaran,
                'status' => 'berjalan',
                'waktu_mulai' => $selesai,
                'dicatat_oleh' => $actor->id,
                'dicatat_pada' => now(),
            ]);

            $po->stage_aktif = PoStage::STAGE_5;
            $po->save();

            return $nextStage;
        });
    }

    /**
     * Selesaikan Stage 4 via tombol 'Lanjut Tempo' (TC-28).
     * Hanya berlaku untuk PO skema tempo. Membuka Stage 5 tanpa record payment.
     */
    public function completeStage4Tempo(PurchaseOrder $po, User $actor, ?DateTimeInterface $waktuSelesai = null, ?string $catatan = null): PoStage
    {
        return DB::transaction(function () use ($po, $actor, $waktuSelesai, $catatan) {
            $this->validatePendingRevision($po);
            $current = $this->validateExpectedStage($po, PoStage::STAGE_4);

            if ($po->skema_bayar !== 'tempo') {
                throw ValidationException::withMessages([
                    'skema_bayar' => "Tombol 'Lanjut Tempo' hanya berlaku untuk PO dengan skema pembayaran Tempo.",
                ]);
            }

            $selesai = $waktuSelesai ? Carbon::parse($waktuSelesai) : now();
            $this->validateTimeWindow($current->waktu_mulai, $selesai);

            $current->update([
                'status' => 'selesai',
                'waktu_selesai' => $selesai,
                'durasi_menit' => self::calculateDurationMinutes($current->waktu_mulai, $selesai),
                'pic_user_id' => $actor->id,
                'dicatat_oleh' => $actor->id,
                'dicatat_pada' => now(),
                'catatan' => $catatan ?: 'Lanjut skema tempo tanpa uang muka',
            ]);

            // Buka Stage 5
            $nextStage = $po->stages()->create([
                'stage' => PoStage::STAGE_5,
                'urutan' => 5,
                'putaran' => $current->putaran,
                'status' => 'berjalan',
                'waktu_mulai' => $selesai,
                'dicatat_oleh' => $actor->id,
                'dicatat_pada' => now(),
            ]);

            $po->stage_aktif = PoStage::STAGE_5;
            $po->save();

            return $nextStage;
        });
    }

    /**
     * Selesaikan Stage 5 (Penerbitan PO Resmi) -> Buka Stage 6 (Pengemasan).
     */
    public function completeStage5(PurchaseOrder $po, User $purchasing, ?DateTimeInterface $waktuSelesai = null, ?string $catatan = null): PoStage
    {
        return DB::transaction(function () use ($po, $purchasing, $waktuSelesai, $catatan) {
            $this->validatePendingRevision($po);
            $current = $this->validateExpectedStage($po, PoStage::STAGE_5);
            $selesai = $waktuSelesai ? Carbon::parse($waktuSelesai) : now();
            $this->validateTimeWindow($current->waktu_mulai, $selesai);

            $current->update([
                'status' => 'selesai',
                'waktu_selesai' => $selesai,
                'durasi_menit' => self::calculateDurationMinutes($current->waktu_mulai, $selesai),
                'pic_user_id' => $purchasing->id,
                'dicatat_oleh' => $purchasing->id,
                'dicatat_pada' => now(),
                'catatan' => $catatan ?: 'PO resmi diterbitkan ke supplier',
            ]);

            // Buka Stage 6 (Pengiriman & Logistik) langsung - konsolidasi alur 8-tahap
            $nextStage = $po->stages()->create([
                'stage' => PoStage::STAGE_6,
                'urutan' => 6,
                'putaran' => $current->putaran,
                'status' => 'berjalan',
                'waktu_mulai' => $selesai,
                'dicatat_oleh' => $purchasing->id,
                'dicatat_pada' => now(),
            ]);

            $po->stage_aktif = PoStage::STAGE_6;
            $po->save();

            return $nextStage;
        });
    }

    /**
     * Selesaikan Stage 6 (Pengiriman & Logistik) -> Buka Stage 7 (Unloading di Gudang).
     * Wajib mengisi ekspedisi (BR-11, TC-08).
     */
    public function completeStage6(PurchaseOrder $po, User $actor, array $shippingData = [], ?DateTimeInterface $waktuSelesai = null, ?string $catatan = null): PoStage
    {
        return DB::transaction(function () use ($po, $actor, $shippingData, $waktuSelesai, $catatan) {
            $this->validatePendingRevision($po);
            $current = $this->validateExpectedStage($po, PoStage::STAGE_6);
            $selesai = $waktuSelesai ? Carbon::parse($waktuSelesai) : now();
            $this->validateTimeWindow($current->waktu_mulai, $selesai);

            $pengiriman = PoPengiriman::firstOrCreate(['po_id' => $po->id]);
            if (isset($shippingData['ekspedisi'])) {
                $pengiriman->ekspedisi = $shippingData['ekspedisi'];
            }
            if (isset($shippingData['moda'])) {
                $pengiriman->moda = $shippingData['moda'];
            }
            if (isset($shippingData['no_resi'])) {
                $pengiriman->no_resi = $shippingData['no_resi'];
            }
            if (isset($shippingData['no_kontainer'])) {
                $pengiriman->no_kontainer = $shippingData['no_kontainer'];
            }
            if (isset($shippingData['eta_tiba'])) {
                $pengiriman->eta_tiba = $shippingData['eta_tiba'];
            }

            // TC-08: Ekspedisi wajib diisi
            if (empty(trim((string) $pengiriman->ekspedisi))) {
                throw ValidationException::withMessages([
                    'ekspedisi' => 'Informasi ekspedisi wajib diisi sebelum pengiriman diselesaikan.',
                ]);
            }
            $pengiriman->save();

            $current->update([
                'status' => 'selesai',
                'waktu_selesai' => $selesai,
                'durasi_menit' => self::calculateDurationMinutes($current->waktu_mulai, $selesai),
                'pic_user_id' => $actor->id,
                'dicatat_oleh' => $actor->id,
                'dicatat_pada' => now(),
                'catatan' => $catatan ?: 'Barang telah dikirim oleh ekspedisi',
            ]);

            // Buka Stage 7 (Unloading di Gudang)
            $nextStage = $po->stages()->create([
                'stage' => PoStage::STAGE_7,
                'urutan' => 7,
                'putaran' => $current->putaran,
                'status' => 'berjalan',
                'waktu_mulai' => $selesai,
                'dicatat_oleh' => $actor->id,
                'dicatat_pada' => now(),
            ]);

            $po->stage_aktif = PoStage::STAGE_7;
            $po->save();

            return $nextStage;
        });
    }

    /**
     * Selesaikan Stage 7 (Unloading di Gudang) (BR-08, TC-10).
     * Mencatat barang_datang dan qty_fisik dengan status_posting = 'belum'.
     * TIDAK menambah stok atau menulis kartu_stok pada stage ini.
     */
    public function completeStage7(PurchaseOrder $po, User $gudangUser, array $physicalCheckData, ?DateTimeInterface $waktuSelesai = null, ?string $catatan = null): BarangDatang
    {
        return DB::transaction(function () use ($po, $gudangUser, $physicalCheckData, $waktuSelesai, $catatan) {
            $this->validatePendingRevision($po);
            $current = $this->validateExpectedStage($po, PoStage::STAGE_7);
            $selesai = $waktuSelesai ? Carbon::parse($waktuSelesai) : now();
            $this->validateTimeWindow($current->waktu_mulai, $selesai);

            $itemsData = $physicalCheckData['items'] ?? [];
            $preparedItems = [];

            foreach ($po->items as $poItem) {
                $inputItem = $itemsData[$poItem->id]
                    ?? collect($itemsData)->firstWhere('po_item_id', $poItem->id)
                    ?? ($physicalCheckData[$poItem->id] ?? []);

                if (isset($inputItem['qty_fisik']) && $inputItem['qty_fisik'] !== '') {
                    if (! is_numeric($inputItem['qty_fisik']) || (float) $inputItem['qty_fisik'] < 0) {
                        throw ValidationException::withMessages([
                            "physical_check.items.{$poItem->id}.qty_fisik" => 'Kuantitas fisik diterima tidak boleh bernilai negatif.',
                        ]);
                    }
                    $qtyFisik = (float) $inputItem['qty_fisik'];
                } else {
                    $qtyFisik = (float) $poItem->qty;
                }

                $preparedItems[] = [
                    'po_item_id' => $poItem->id,
                    'qty_fisik' => $qtyFisik,
                    'qty_diterima' => null, // Belum final
                    'selisih' => round($qtyFisik - (float) $poItem->qty, 2),
                    'keterangan_selisih' => !empty($inputItem['keterangan_selisih']) ? trim((string) $inputItem['keterangan_selisih']) : null,
                ];
            }

            // Simpan BarangDatang dengan status belum posting
            $bardat = BarangDatang::create([
                'po_id' => $po->id,
                'tanggal_terima' => $selesai->toDateString(),
                'waktu_tiba' => $selesai,
                'kondisi' => $physicalCheckData['kondisi'] ?? 'baik',
                'status_posting' => 'belum',
                'created_by' => $gudangUser->id,
            ]);

            foreach ($preparedItems as $prepItem) {
                $bardat->items()->create($prepItem);
            }

            $current->update([
                'status' => 'selesai',
                'waktu_selesai' => $selesai,
                'durasi_menit' => self::calculateDurationMinutes($current->waktu_mulai, $selesai),
                'pic_user_id' => $gudangUser->id,
                'dicatat_oleh' => $gudangUser->id,
                'dicatat_pada' => now(),
                'catatan' => $catatan ?: 'Unloading dan verifikasi fisik selesai',
            ]);

            // Buka Stage 8 (Input Sistem & Posting Stok)
            $po->stages()->create([
                'stage' => PoStage::STAGE_8,
                'urutan' => 8,
                'putaran' => $current->putaran,
                'status' => 'berjalan',
                'waktu_mulai' => $selesai,
                'dicatat_oleh' => $gudangUser->id,
                'dicatat_pada' => now(),
            ]);

            $po->stage_aktif = PoStage::STAGE_8;
            $po->save();

            return $bardat;
        });
    }

    /**
     * Selesaikan Stage 8 (Input Sistem & Posting Stok) (BR-10, TC-01, TC-11).
     * Dalam transaksi atomik:
     * 1. Tambah stok via StokService::masuk
     * 2. Tulis kartu_stok referensi_tipe = 'purchase_order'
     * 3. Set status_posting = 'diposting'
     * 4. Set PO status = 'selesai'
     * 5. Panggil SupplierLeadTimeService::recordCompletedPo()
     */
    public function completeStage8(PurchaseOrder $po, User $gudangUser, ?DateTimeInterface $waktuSelesai = null, ?string $catatan = null, ?string $noResi = null): void
    {
        DB::transaction(function () use ($po, $gudangUser, $waktuSelesai, $catatan, $noResi) {
            $this->validatePendingRevision($po);
            $current = $this->validateExpectedStage($po, PoStage::STAGE_8);
            $selesai = $waktuSelesai ? Carbon::parse($waktuSelesai) : now();
            $this->validateTimeWindow($current->waktu_mulai, $selesai);

            // Simpan / update No. Resi jika diisi pada Stage 8
            if ($noResi !== null && trim($noResi) !== '') {
                $pengiriman = PoPengiriman::firstOrCreate(['po_id' => $po->id]);
                $pengiriman->no_resi = trim($noResi);
                $pengiriman->save();
            }

            $bardat = $po->barangDatang()->where('status_posting', 'belum')->latest()->first();
            if (! $bardat) {
                $bardat = $po->barangDatang()->latest()->first();
            }

            if (! $bardat) {
                throw ValidationException::withMessages([
                    'barang_datang' => 'Dokumen barang datang untuk PO ini tidak ditemukan.',
                ]);
            }

            $bardat->loadMissing('items.poItem');

            $gudangId = $po->gudang_id;
            if (! $gudangId) {
                throw ValidationException::withMessages([
                    'gudang_id' => 'Gudang tujuan PO wajib ditentukan sebelum posting stok.',
                ]);
            }

            // 1. Posting stok dan set qty_diterima
            foreach ($bardat->items as $item) {
                $finalQty = $item->qty_fisik !== null ? (float) $item->qty_fisik : (float) ($item->poItem?->qty ?? 0);
                $item->qty_diterima = $finalQty;
                $item->save();

                if ($finalQty > 0) {
                    $produkId = $item->poItem->produk_id;
                    $this->stokService->masuk(
                        $produkId,
                        $gudangId,
                        $finalQty,
                        'pembelian',
                        $po->id,
                        'Penerimaan PO ' . $po->no_po,
                        $selesai
                    );
                }
            }

            // 2. Update status posting dokumen penerimaan
            $bardat->status_posting = 'diposting';
            $bardat->diposting_oleh = $gudangUser->id;
            $bardat->diposting_pada = now();
            $bardat->save();

            // 3. Selesaikan Stage 8
            $current->update([
                'status' => 'selesai',
                'waktu_selesai' => $selesai,
                'durasi_menit' => self::calculateDurationMinutes($current->waktu_mulai, $selesai),
                'pic_user_id' => $gudangUser->id,
                'dicatat_oleh' => $gudangUser->id,
                'dicatat_pada' => now(),
                'catatan' => $catatan ?: 'Input sistem dan posting stok berhasil',
            ]);

            // 4. Update status dokumen PO menjadi selesai
            $po->status = 'selesai';
            $po->save();

            // 5. Rekam Lead Time komparatif 2 level
            $this->leadTimeService->recordCompletedPo($po, $gudangUser->id);
        });
    }

    /**
     * Backward-compatibility wrapper for legacy stage calls
     */
    public function completeStage9(PurchaseOrder $po, User $gudangUser, ?DateTimeInterface $waktuSelesai = null, ?string $catatan = null, ?string $noResi = null): void
    {
        $this->completeStage8($po, $gudangUser, $waktuSelesai, $catatan, $noResi);
    }

    /**
     * Selesaikan pengemasan untuk PO arsip lama jika ada yang masih berjalan di tahap pengemasan.
     */
    public function completeLegacyPengemasan(PurchaseOrder $po, User $actor, array $packingData = [], ?DateTimeInterface $waktuSelesai = null, ?string $catatan = null): PoStage
    {
        return DB::transaction(function () use ($po, $actor, $packingData, $waktuSelesai, $catatan) {
            $this->validatePendingRevision($po);
            $current = $this->validateExpectedStage($po, PoStage::LEGACY_STAGE_PENGEMASAN);
            $selesai = $waktuSelesai ? Carbon::parse($waktuSelesai) : now();
            $this->validateTimeWindow($current->waktu_mulai, $selesai);

            $pengiriman = PoPengiriman::firstOrCreate(['po_id' => $po->id]);
            if (isset($packingData['jumlah_koli'])) $pengiriman->jumlah_koli = $packingData['jumlah_koli'];
            if (isset($packingData['berat_kg'])) $pengiriman->berat_kg = $packingData['berat_kg'];
            if (isset($packingData['catatan_pengemasan'])) $pengiriman->catatan_pengemasan = $packingData['catatan_pengemasan'];
            $pengiriman->save();

            $current->update([
                'status' => 'selesai',
                'waktu_selesai' => $selesai,
                'durasi_menit' => self::calculateDurationMinutes($current->waktu_mulai, $selesai),
                'pic_user_id' => $actor->id,
                'dicatat_oleh' => $actor->id,
                'dicatat_pada' => now(),
                'catatan' => $catatan ?: 'Pengemasan selesai',
            ]);

            // Buka Stage 6 (Pengiriman)
            $nextStage = $po->stages()->create([
                'stage' => PoStage::STAGE_6,
                'urutan' => 6,
                'putaran' => $current->putaran,
                'status' => 'berjalan',
                'waktu_mulai' => $selesai,
                'dicatat_oleh' => $actor->id,
                'dicatat_pada' => now(),
            ]);

            $po->stage_aktif = PoStage::STAGE_6;
            $po->save();

            return $nextStage;
        });
    }

    /**
     * Batalkan PO oleh Manager (BR-07, TC-15).
     * Hanya boleh sebelum Stage 8 selesai. Alasan pembatalan wajib diisi.
     */
    public function cancelPo(PurchaseOrder $po, User $manager, string $alasan, array $extraPoUpdates = []): void
    {
        $alasan = trim($alasan);
        if ($alasan === '') {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembatalan PO wajib diisi.',
            ]);
        }

        DB::transaction(function () use ($po, $manager, $alasan, $extraPoUpdates) {
            $lockedPo = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            // Cek apakah stage 7 (unloading fisik) sudah selesai
            $unloadingSelesai = $lockedPo->stages()
                ->where('stage', PoStage::STAGE_7)
                ->where('status', 'selesai')
                ->exists();

            if ($unloadingSelesai || $lockedPo->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'PO tidak dapat dibatalkan karena tahap Unloading fisik di gudang sudah selesai atau dokumen sudah final.',
                ]);
            }

            $lockedPo->status = 'dibatalkan';
            $lockedPo->status_pembayaran = 'batal';
            $lockedPo->alasan_batal = mb_substr($alasan, 0, 255);
            $lockedPo->dibatalkan_oleh = $manager->id;
            if (array_key_exists('catatan', $extraPoUpdates)) {
                $lockedPo->catatan = $extraPoUpdates['catatan'];
            }
            $lockedPo->save();

            // Tandai stage aktif sebagai dibatalkan
            $lockedPo->stages()->where('status', 'berjalan')->update([
                'status' => 'dibatalkan',
                'catatan' => 'Dibatalkan: ' . $alasan,
                'waktu_selesai' => now(),
                'dicatat_oleh' => $manager->id,
                'dicatat_pada' => now(),
            ]);

            // Auto-batalkan seluruh revisi komitmen yang masih menunggu keputusan Manager
            $lockedPo->revisis()
                ->where('status', PoRevisi::STATUS_MENUNGGU)
                ->update([
                    'status' => PoRevisi::STATUS_DIBATALKAN,
                    'diputuskan_oleh' => $manager->id,
                    'diputuskan_pada' => now(),
                    'catatan_keputusan' => 'Otomatis dibatalkan karena PO dibatalkan: ' . mb_substr($alasan, 0, 180),
                ]);

            $po->forceFill($lockedPo->getAttributes());
            $po->unsetRelation('stages');
            $po->unsetRelation('revisis');
        });
    }
}

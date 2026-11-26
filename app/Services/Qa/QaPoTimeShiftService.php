<?php

namespace App\Services\Qa;

use App\Models\PoStage;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Memundurkan seluruh waktu tahap sebuah PO (khusus QA) sehingga durasi stage
 * bisa disimulasikan tanpa menunggu waktu nyata. Durasi antar stage tidak berubah.
 */
class QaPoTimeShiftService
{
    public const FINAL_STATUSES = ['selesai', 'dibatalkan'];

    /**
     * @return int jumlah baris po_stage yang digeser
     */
    public function shiftBack(PurchaseOrder $po, int $minutes, string $actor): int
    {
        if ($minutes <= 0) {
            throw ValidationException::withMessages([
                'jam' => 'Total pergeseran harus lebih dari 0.',
            ]);
        }

        if (in_array($po->status, self::FINAL_STATUSES, true)) {
            throw ValidationException::withMessages([
                'qa_shift' => 'PO yang sudah final tidak dapat digeser.',
            ]);
        }

        $shifted = DB::transaction(function () use ($po, $minutes) {
            $stages = PoStage::where('po_id', $po->id)->get();

            foreach ($stages as $stage) {
                $values = ['waktu_mulai' => $stage->waktu_mulai?->copy()->subMinutes($minutes)];

                if ($stage->waktu_selesai !== null) {
                    $values['waktu_selesai'] = $stage->waktu_selesai->copy()->subMinutes($minutes);
                }

                PoStage::whereKey($stage->id)->update($values);
            }

            if ($po->created_at !== null) {
                PurchaseOrder::whereKey($po->id)->update([
                    'created_at' => $po->created_at->copy()->subMinutes($minutes),
                ]);
            }

            return $stages->count();
        });

        Log::info('QA shift PO', [
            'po_id' => $po->id,
            'minutes' => $minutes,
            'stages' => $shifted,
            'actor' => $actor,
        ]);

        return $shifted;
    }
}

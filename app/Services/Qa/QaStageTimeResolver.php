<?php

namespace App\Services\Qa;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Menentukan waktu selesai stage PO. Saat QA tools aktif, QA bisa mengisi
 * durasi stage (hari/jam) sehingga waktu selesai = waktu mulai + durasi.
 */
class QaStageTimeResolver
{
    public function __construct(private readonly QaClock $clock) {}

    public function resolve(Request $request, PurchaseOrder $po, string $stageKey): Carbon
    {
        if (! $this->clock->enabled() || ! ($request->filled('qa_durasi_hari') || $request->filled('qa_durasi_jam'))) {
            return $request->filled('waktu_selesai') ? Carbon::parse($request->input('waktu_selesai')) : now();
        }

        $data = $request->validate([
            'qa_durasi_hari' => ['nullable', 'integer', 'min:0', 'max:365'],
            'qa_durasi_jam' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $stage = $po->stages()
            ->where('stage', $stageKey)
            ->where('status', 'berjalan')
            ->latest('putaran')
            ->first();

        // Biarkan service melempar error urutan stage seperti biasa.
        if (! $stage) {
            return now();
        }

        $minutes = ((int) ($data['qa_durasi_hari'] ?? 0)) * 1440 + ((int) ($data['qa_durasi_jam'] ?? 0)) * 60;
        $waktuSelesai = Carbon::parse($stage->waktu_mulai)->addMinutes($minutes);

        if ($waktuSelesai->greaterThan(now())) {
            throw ValidationException::withMessages([
                'qa_durasi' => 'Durasi QA melewati waktu sekarang. Mundurkan PO atau majukan QA Clock terlebih dahulu.',
            ]);
        }

        return $waktuSelesai;
    }
}

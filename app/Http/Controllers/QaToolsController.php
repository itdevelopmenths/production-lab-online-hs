<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\Qa\QaClock;
use App\Services\Qa\QaPoTimeShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Alat bantu QA (khusus non-production). Semua aksi 404 bila QA time tools nonaktif.
 */
class QaToolsController extends Controller
{
    public function __construct(
        private readonly QaClock $clock,
        private readonly QaPoTimeShiftService $poTimeShift,
    ) {}

    public function clock(): View
    {
        $this->ensureEnabled();

        return view('qa.clock', [
            'realNow' => $this->clock->realNow(),
            'fakeNow' => $this->clock->fakeNow(),
            'offsetMinutes' => $this->clock->offsetMinutes(),
        ]);
    }

    public function advanceClock(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $data = Validator::make($request->all(), [
            'hari' => ['nullable', 'integer', 'min:0', 'max:365'],
            'jam' => ['nullable', 'integer', 'min:0', 'max:999'],
        ])->after(function ($validator) use ($request) {
            if (! $validator->errors()->any() && (int) $request->input('hari') * 24 + (int) $request->input('jam') <= 0) {
                $validator->errors()->add('hari', 'Isi minimal 1 hari atau 1 jam untuk memajukan jam.');
            }
        })->validate();

        $minutes = ((int) ($data['hari'] ?? 0) * 24 + (int) ($data['jam'] ?? 0)) * 60;

        $this->clock->advance($minutes, $this->actor());

        return $this->back('Jam QA dimajukan. Sekarang: '.$this->clock->fakeNow()->format('d M Y H:i').'.');
    }

    public function setClock(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $timezone = config('app.timezone');
        $now = $this->clock->realNow();

        $data = $request->validate([
            'waktu' => ['required', 'date', function (string $attribute, mixed $value, \Closure $fail) use ($timezone, $now) {
                if (Carbon::parse($value, $timezone)->lt($now->copy()->startOfMinute())) {
                    $fail('Waktu QA tidak boleh lebih awal dari jam asli.');
                }
            }],
        ]);

        $minutes = max(0, (int) floor($now->diffInMinutes(Carbon::parse($data['waktu'], $timezone), false)));

        $this->clock->setOffsetMinutes($minutes, $this->actor());

        return $this->back('Jam QA diatur ke '.$this->clock->fakeNow()->format('d M Y H:i').'.');
    }

    public function resetClock(): RedirectResponse
    {
        $this->ensureEnabled();

        $this->clock->reset($this->actor());

        return $this->back('Jam QA dikembalikan ke jam asli.');
    }

    public function shiftPo(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->ensureEnabled();

        $data = $request->validate([
            'hari' => ['nullable', 'integer', 'min:0', 'max:365'],
            'jam' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $hari = (int) ($data['hari'] ?? 0);
        $jam = (int) ($data['jam'] ?? 0);

        $this->poTimeShift->shiftBack($purchaseOrder, ($hari * 24 + $jam) * 60, $this->actor());

        return redirect()->back()->with('success', "Waktu PO dimundurkan {$hari} hari {$jam} jam.");
    }

    private function ensureEnabled(): void
    {
        abort_unless($this->clock->enabled(), 404);
    }

    private function actor(): string
    {
        return auth()->user()->email;
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('qa.clock')->with('success', $message);
    }
}

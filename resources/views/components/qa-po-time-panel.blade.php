@props(['po'])

@php
    $qaEnabled = app(\App\Services\Qa\QaClock::class)->enabled();
    $qaFinal = in_array($po->status, \App\Services\Qa\QaPoTimeShiftService::FINAL_STATUSES, true);
@endphp

@if($qaEnabled && ! $qaFinal)
    @php
        $qaActiveStage = $po->stages->where('status', 'berjalan')->sortByDesc('putaran')->first();
        $qaElapsedMinutes = $qaActiveStage?->waktu_mulai ? max(0, (int) $qaActiveStage->waktu_mulai->diffInMinutes(now())) : null;
    @endphp

    <form method="POST" action="{{ route('qa.purchasing.shift', $po) }}" class="mt-4">
        @csrf
        <x-card title="QA: Mundurkan waktu PO" subtitle="Menggeser seluruh waktu stage PO ke belakang agar durasi stage bisa disimulasikan. Hanya tersedia bila APP_ENV=local." :noPadding="true">
            <div class="p-4 sm:p-5 space-y-4">
                @if($qaActiveStage)
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div>
                            <dt class="text-[11px] text-text-muted">Stage aktif</dt>
                            <dd class="font-semibold text-[#101828] dark:text-white">{{ \App\Models\PoStage::STAGE_LABELS[$qaActiveStage->stage] ?? $qaActiveStage->stage }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-text-muted">Waktu mulai</dt>
                            <dd class="font-semibold tabular-nums text-[#101828] dark:text-white">{{ $qaActiveStage->waktu_mulai?->format('d M Y H:i') ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-text-muted">Lama berjalan</dt>
                            <dd class="font-semibold tabular-nums text-[#101828] dark:text-white">
                                @if($qaElapsedMinutes !== null)
                                    {{ intdiv($qaElapsedMinutes, 1440) }} hari {{ intdiv($qaElapsedMinutes % 1440, 60) }} jam {{ $qaElapsedMinutes % 60 }} menit
                                @else
                                    -
                                @endif
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="text-xs text-text-muted">Tidak ada stage yang sedang berjalan.</p>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-form-field label="Hari" name="hari" help="0 sampai 365">
                        <x-input type="number" name="hari" :value="old('hari', 0)" min="0" max="365" variant="seamless" />
                    </x-form-field>
                    <x-form-field label="Jam" name="jam" help="0 sampai 999">
                        <x-input type="number" name="jam" :value="old('jam', 0)" min="0" max="999" variant="seamless" />
                    </x-form-field>
                </div>

                @error('qa_shift')
                    <p class="text-[11px] text-destructive font-medium">{{ $message }}</p>
                @enderror
            </div>
            <x-slot:footer>
                <div class="flex items-center justify-end">
                    <x-button type="submit" variant="primary" size="sm">Mundurkan</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
@endif

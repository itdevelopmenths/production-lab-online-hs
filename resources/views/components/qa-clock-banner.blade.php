@php
    $qaClock = app(\App\Services\Qa\QaClock::class);
    $qaOffset = $qaClock->enabled() ? $qaClock->offsetMinutes() : 0;
@endphp

@if ($qaClock->enabled())
    @if ($qaOffset > 0)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-[8px] border border-red-200 dark:border-red-500/20 bg-red-50/80 dark:bg-red-500/10 px-4 py-2.5 text-xs font-medium text-red-900 dark:text-red-300 shadow-theme-xs" role="status">
            <span class="min-w-0 break-words">QA CLOCK: {{ $qaClock->fakeNow()->format('d M Y H:i') }} (+{{ intdiv($qaOffset, 1440) }} hari {{ intdiv($qaOffset % 1440, 60) }} jam)</span>
            <a href="{{ route('qa.clock') }}" class="underline underline-offset-2 hover:text-red-700 dark:hover:text-white">Atur</a>
        </div>
    @else
        <div class="mb-4 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-[8px] border border-[#E8E8E8] dark:border-border bg-[#FBFBFB] dark:bg-gray-900/60 px-4 py-2.5 text-xs text-[#6A7282] dark:text-gray-400 shadow-theme-xs" role="status">
            <span class="min-w-0 break-words">Mode QA aktif · jam asli</span>
            <a href="{{ route('qa.clock') }}" class="underline underline-offset-2 hover:text-[#101828] dark:hover:text-white">Atur</a>
        </div>
    @endif
@endif

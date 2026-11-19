@props([
    'title',
    'value',
    'subtitle' => null,
    'prefix' => null,
    'trend' => null,
    'trendType' => 'up',
    'icon' => null,
    'iconColor' => 'indigo',
    'variant' => 'default',
    'href' => null,
    'footerText' => null,
    'badge' => null,
])

@php
    $colorKey = match ($iconColor) {
        'emerald', 'green', 'success' => 'emerald',
        'blue', 'sky', 'info' => 'blue',
        'amber', 'orange', 'warning', 'gold' => 'amber',
        'rose', 'red', 'danger' => 'rose',
        'purple', 'violet' => 'purple',
        'gray', 'neutral', 'zinc' => 'gray',
        default => 'indigo',
    };

    $iconBoxClasses = match ($colorKey) {
        'emerald' => 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        'blue' => 'bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400',
        'amber' => 'bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400',
        'rose' => 'bg-red-50 dark:bg-red-500/15 text-red-600 dark:text-red-400',
        'purple' => 'bg-purple-50 dark:bg-purple-500/15 text-purple-600 dark:text-purple-400',
        'gray' => 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
        default => 'bg-[#eef2ff] dark:bg-indigo-500/15 text-[#4f39f6] dark:text-indigo-400',
    };

    $trendClasses = $trendType === 'up'
        ? 'text-emerald-700 bg-emerald-50 border border-emerald-200/80 dark:text-emerald-400 dark:bg-emerald-500/10 dark:border-emerald-500/20'
        : 'text-red-700 bg-red-50 border border-red-200/80 dark:text-red-400 dark:bg-red-500/10 dark:border-red-500/20';
@endphp

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-900 text-[#101828] dark:text-white border border-[#e8e8e8] dark:border-gray-800 rounded-[12px] px-[20px] py-[16px] flex flex-col gap-[12px] justify-between shadow-[0px_1px_2px_0px_rgba(16,24,40,0.04)] hover:border-gray-300 dark:hover:border-gray-700 transition-all relative']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
            <p class="text-[12px] leading-[18px] font-medium tracking-[-0.132px] text-[#6a7282] dark:text-gray-400 truncate">{!! $title !!}</p>
            <div class="mt-1 flex items-baseline gap-1.5 flex-wrap">
                @if ($prefix)
                    <span class="text-sm font-medium text-[#6a7282] dark:text-gray-400">{{ $prefix }}</span>
                @endif
                <p class="text-[28px] leading-[1.4] font-semibold text-[#101828] dark:text-white tracking-[-0.308px] break-words tabular-nums">{{ $value }}</p>
            </div>
            @if ($subtitle)
                <p class="text-[#6a7282] dark:text-gray-400 mt-1 text-[12px] leading-[18px]">{!! $subtitle !!}</p>
            @endif
        </div>

        @if ($icon)
            <span class="size-[32px] rounded-[8px] shrink-0 flex items-center justify-center [&>svg]:size-[16px] {{ $iconBoxClasses }}">
                {!! $icon !!}
            </span>
        @elseif ($badge)
            <span class="shrink-0">{!! $badge !!}</span>
        @endif
    </div>

    @if ($href || $trend)
        <div class="pt-2.5 border-t border-[#e8e8e8] dark:border-gray-800 flex items-center justify-between text-xs font-medium text-[#101828] dark:text-gray-200">
            @if ($href)
                <a href="{{ $href }}" class="inline-flex items-center gap-1 font-medium text-[#101828] dark:text-gray-200 hover:text-[#6a7282] dark:hover:text-white transition-colors">
                    {{ $footerText ?? 'Lihat Rincian' }}
                    <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span class="text-[11px] text-[#6a7282] dark:text-gray-400">{{ $footerText ?? 'Perbandingan periode' }}</span>
            @endif

            @if ($trend)
                <span class="inline-flex items-center text-[11px] font-semibold px-2 py-0.5 rounded-full tabular-nums {{ $trendClasses }}">
                    {{ $trend }}
                </span>
            @endif
        </div>
    @endif
</div>

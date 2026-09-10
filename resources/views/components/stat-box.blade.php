@props([
    'title',
    'value',
    'subtitle' => null,
    'trend' => null,
    'trendType' => 'up', // up, down, neutral
    'icon' => null,
    'variant' => 'default', // default, primary, success, danger, warning, info, gold
    'href' => null,
    'footerText' => null,
    'badge' => null,
])

@php
    $accentBorder = match ($variant) {
        'primary' => 'border-t-2 border-t-primary-600',
        'success' => 'border-t-2 border-t-emerald-600',
        'danger' => 'border-t-2 border-t-rose-600',
        'warning' => 'border-t-2 border-t-amber-500',
        'info' => 'border-t-2 border-t-blue-500',
        'gold' => 'border-t-2 border-t-gold-500',
        default => '',
    };

    $valueColor = match ($variant) {
        'primary' => 'text-primary-700',
        'success' => 'text-emerald-700',
        'danger' => 'text-rose-700',
        'warning' => 'text-amber-700',
        'info' => 'text-blue-700',
        'gold' => 'text-gold-700',
        default => 'text-gray-900',
    };
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-sm border border-gray-200 shadow-xs p-4 flex flex-col justify-between relative overflow-hidden ' . $accentBorder]) }}>
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider truncate">{!! $title !!}</p>
            <p class="text-2xl font-bold {{ $valueColor }} mt-1.5 tracking-tight">{{ $value }}</p>
            @if ($subtitle)
                <p class="text-xs text-gray-500 mt-1 leading-snug">{!! $subtitle !!}</p>
            @endif
        </div>

        @if ($trend)
            <span class="inline-flex items-center text-xs font-semibold px-1.5 py-0.5 rounded-sm shrink-0 {{ $trendType === 'up' ? 'text-emerald-700 bg-emerald-50' : ($trendType === 'down' ? 'text-rose-700 bg-rose-50' : 'text-gray-700 bg-gray-100') }}">
                @if ($trendType === 'up')
                    <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                @elseif ($trendType === 'down')
                    <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                @endif
                {{ $trend }}
            </span>
        @elseif ($badge)
            <span class="shrink-0">{!! $badge !!}</span>
        @endif
    </div>

    @if ($icon)
        <div class="absolute -right-2 -bottom-2 text-gray-100/80 pointer-events-none w-16 h-16 opacity-40">
            {!! $icon !!}
        </div>
    @endif

    @if ($href)
        <div class="mt-3 pt-2.5 border-t border-gray-100 flex items-center justify-between text-xs font-medium text-primary-600 hover:text-primary-800">
            <a href="{{ $href }}" class="stretched-link inline-flex items-center gap-1">
                {{ $footerText ?? 'Lihat Rincian' }}
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    @endif
</div>

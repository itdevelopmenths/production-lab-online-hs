@props([
    'title',
    'value',
    'subtitle' => null,
    'trend' => null,
    'trendType' => 'up',
    'icon' => null,
    'variant' => 'default',
    'href' => null,
    'footerText' => null,
    'badge' => null,
])

<div {{ $attributes->merge(['class' => 'bg-card text-card-foreground rounded-xl border border-border shadow-xs p-5 flex flex-col justify-between transition-all hover:border-border/80 relative']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-muted-foreground text-sm font-normal truncate">{!! $title !!}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums text-foreground tracking-tight">{{ $value }}</p>
            @if ($subtitle)
                <p class="text-muted-foreground mt-1 text-xs leading-normal">{!! $subtitle !!}</p>
            @endif
        </div>

        @if ($icon)
            <span class="bg-muted text-muted-foreground rounded-lg p-2.5 size-10 flex items-center justify-center shrink-0">
                {!! $icon !!}
            </span>
        @elseif ($badge)
            <span class="shrink-0">{!! $badge !!}</span>
        @endif
    </div>

    @if ($href)
        <div class="mt-4 pt-3 border-t border-border/60 flex items-center justify-between text-xs font-medium text-foreground hover:text-muted-foreground transition-colors">
            <a href="{{ $href }}" class="inline-flex items-center gap-1.5 font-medium">
                {{ $footerText ?? 'Lihat Rincian' }}
                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @if ($trend)
                <span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-md {{ $trendType === 'up' ? 'text-emerald-700 bg-emerald-50' : 'text-red-700 bg-red-50' }}">
                    {{ $trend }}
                </span>
            @endif
        </div>
    @endif
</div>

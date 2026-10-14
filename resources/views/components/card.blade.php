@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'variant' => 'default',
    'bodyClass' => 'p-5',
    'noPadding' => false,
    'tools' => null,
    'footer' => null,
])

<div {{ $attributes->merge(['class' => 'bg-card text-card-foreground rounded-xl border border-border shadow-xs flex flex-col overflow-hidden transition-all']) }}>
    @if ($title || isset($header) || isset($tools))
        <div class="p-5 border-b border-border/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-card">
            @if (isset($header))
                {{ $header }}
            @else
                <div class="flex items-center gap-3 min-w-0">
                    @if ($icon)
                        <span class="text-muted-foreground shrink-0">{!! $icon !!}</span>
                    @endif
                    <div>
                        <h3 class="text-lg font-semibold leading-tight text-foreground tracking-tight">{!! $title !!}</h3>
                        @if ($subtitle)
                            <p class="text-sm text-muted-foreground mt-1 leading-normal">{!! $subtitle !!}</p>
                        @endif
                    </div>
                </div>
            @endif

            @if (isset($tools))
                <div class="flex items-center gap-2.5 shrink-0">
                    {{ $tools }}
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $noPadding ? 'p-0' : $bodyClass }} flex-1">
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="px-5 py-3.5 bg-muted/30 border-t border-border/60 text-xs text-muted-foreground rounded-b-xl">
            {{ $footer }}
        </div>
    @endif
</div>

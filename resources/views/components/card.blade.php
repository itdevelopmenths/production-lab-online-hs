@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'variant' => 'default',
    'bodyClass' => 'p-4',
    'noPadding' => false,
    'tools' => null,
    'footer' => null,
    'overflow' => null,
])

@php
    $classAttr = $attributes->get('class', '');
    $hasExplicitOverflow = (bool) preg_match('/\boverflow-(visible|hidden|auto|scroll)\b/', $classAttr);
    $overflowClass = $overflow ? "overflow-{$overflow}" : ($hasExplicitOverflow ? '' : 'overflow-hidden');
@endphp

<div {{ $attributes->merge(['class' => 'bg-card text-card-foreground rounded-lg border border-border shadow-2xs flex flex-col ' . $overflowClass . ' transition-all']) }}>
    @if ($title || isset($header) || isset($tools))
        <div class="px-4 py-2.5 border-b border-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-card rounded-t-lg">
            @if (isset($header))
                {{ $header }}
            @else
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    @if ($icon)
                        <span class="text-muted-foreground shrink-0">{!! $icon !!}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-semibold leading-tight text-foreground tracking-tight">{!! $title !!}</h3>
                        @if ($subtitle)
                            <p class="text-xs text-muted-foreground mt-0.5 leading-normal">{!! $subtitle !!}</p>
                        @endif
                    </div>
                </div>
            @endif

            @if (isset($tools))
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    {{ $tools }}
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $noPadding ? 'p-0' : $bodyClass }} flex-1">
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="px-4 py-2 bg-muted/20 border-t border-border text-xs text-muted-foreground rounded-b-lg">
            {{ $footer }}
        </div>
    @endif
</div>

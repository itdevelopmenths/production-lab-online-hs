@props([
    'variant' => 'info', // info, primary, success, warning, danger, default
    'title' => null,
    'icon' => null,
])

@php
    $themeClasses = match ($variant) {
        'primary' => 'border-primary/30 bg-muted/40 text-foreground',
        'info' => 'border-sky-200 bg-sky-50/70 text-sky-900',
        'success' => 'border-emerald-200 bg-emerald-50/70 text-emerald-900',
        'warning' => 'border-amber-200 bg-amber-50/70 text-amber-900',
        'danger' => 'border-red-200 bg-red-50/70 text-red-900',
        default => 'border-border bg-muted/30 text-foreground',
    };
@endphp

<div {{ $attributes->merge(['class' => "border rounded-xl p-4 shadow-xs text-sm $themeClasses"]) }}>
    @if ($title || $icon)
        <div class="flex items-center gap-2 mb-1.5 font-semibold text-sm">
            @if ($icon)
                <span class="shrink-0">{!! $icon !!}</span>
            @endif
            @if ($title)
                <span>{!! $title !!}</span>
            @endif
        </div>
    @endif
    <div class="text-xs leading-relaxed opacity-90">
        {{ $slot }}
    </div>
</div>

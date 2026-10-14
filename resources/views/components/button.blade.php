@props([
    'variant' => 'primary', // primary (default), secondary, outline, destructive/danger, ghost, success, warning, link
    'size' => 'default', // default, sm, xs, lg, icon
    'href' => null,
    'icon' => null,
    'type' => 'button',
])

@php
    $baseClasses = 'focus-visible:ring-ring inline-flex shrink-0 items-center justify-center gap-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0 cursor-pointer select-none';

    $sizeClasses = match ($size) {
        'xs' => 'h-7 px-2.5 text-xs rounded-md',
        'sm' => 'h-8 px-3 text-xs rounded-md',
        'lg' => 'h-10 px-6',
        'icon' => 'size-8 rounded-lg',
        default => 'h-9 px-4 py-2 text-sm',
    };

    $variantClasses = match ($variant) {
        'secondary', 'outline' => 'bg-card hover:bg-accent hover:text-foreground text-foreground border border-border shadow-xs',
        'destructive', 'danger' => 'bg-destructive text-destructive-foreground hover:bg-destructive/90 shadow-sm',
        'ghost' => 'hover:bg-accent hover:text-foreground text-foreground',
        'link' => 'text-foreground underline-offset-4 hover:underline p-0 h-auto shadow-none',
        'success' => 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm',
        'warning' => 'bg-amber-500 text-white hover:bg-amber-600 shadow-sm',
        default => 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm font-medium', // primary
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "$baseClasses $sizeClasses $variantClasses"]) }}>
        @if ($icon)
            <span class="shrink-0">{!! $icon !!}</span>
        @endif
        <span>{!! $slot !!}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => "$baseClasses $sizeClasses $variantClasses"]) }}>
        @if ($icon)
            <span class="shrink-0">{!! $icon !!}</span>
        @endif
        <span>{!! $slot !!}</span>
    </button>
@endif

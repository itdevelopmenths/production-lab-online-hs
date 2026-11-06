@props([
    'variant' => 'primary', // primary (default), secondary, outline, destructive/danger, ghost, success, warning, link
    'size' => 'default', // default, sm, xs, md, lg, icon
    'href' => null,
    'icon' => null,
    'type' => 'button',
])

@php
    $baseClasses = 'focus-visible:ring-ring inline-flex shrink-0 items-center justify-center gap-1.5 rounded-md text-xs font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-3.5 [&_svg]:shrink-0 cursor-pointer select-none';

    $sizeClasses = match ($size) {
        'xs' => 'h-7 px-2 text-xs rounded-md',
        'sm' => 'h-8 px-2.5 text-xs rounded-md',
        'md' => 'h-9 px-3.5 text-xs rounded-md',
        'lg' => 'h-10 px-4 text-sm rounded-md',
        'icon' => 'size-8 rounded-md',
        default => 'h-8.5 px-3 text-xs rounded-md',
    };

    $variantClasses = match ($variant) {
        'secondary', 'outline' => 'bg-card hover:bg-accent hover:text-foreground text-foreground border border-border shadow-2xs',
        'destructive', 'danger' => 'bg-destructive text-destructive-foreground hover:bg-destructive/90 shadow-2xs',
        'ghost' => 'hover:bg-accent hover:text-foreground text-foreground',
        'link' => 'text-foreground underline-offset-4 hover:underline p-0 h-auto shadow-none',
        'success' => 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-2xs',
        'warning' => 'bg-amber-500 text-white hover:bg-amber-600 shadow-2xs',
        default => 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-2xs font-medium', // primary
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

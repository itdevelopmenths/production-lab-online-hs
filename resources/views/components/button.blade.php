@props([
    'variant' => 'primary', // primary, secondary, success, danger, warning, outline, link
    'size' => 'sm', // xs, sm, md
    'href' => null,
    'icon' => null,
    'type' => 'button',
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium rounded-sm transition focus:outline-none focus:ring-1 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed select-none tracking-wide cursor-pointer';

    $sizeClasses = match ($size) {
        'xs' => 'text-[11px] px-2 py-1 gap-1',
        'md' => 'text-sm px-4 py-2 gap-2 shadow-xs',
        default => 'text-xs px-3 py-1.5 gap-1.5 shadow-xs', // sm
    };

    $variantClasses = match ($variant) {
        'secondary' => 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-gray-400',
        'success' => 'bg-emerald-600 text-white border border-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500',
        'danger' => 'bg-rose-600 text-white border border-rose-600 hover:bg-rose-700 focus:ring-rose-500',
        'warning' => 'bg-amber-500 text-white border border-amber-500 hover:bg-amber-600 focus:ring-amber-500',
        'outline' => 'bg-transparent text-gray-700 border border-gray-300 hover:bg-gray-100 focus:ring-gray-400',
        'link' => 'bg-transparent text-primary-600 hover:text-primary-800 hover:underline p-0 shadow-none border-transparent focus:ring-0',
        default => 'bg-primary-600 text-white border border-primary-600 hover:bg-primary-700 focus:ring-primary-500', // primary
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

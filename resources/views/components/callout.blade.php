@props([
    'variant' => 'info', // info, primary, success, warning, danger, default
    'title' => null,
    'icon' => null,
])

@php
    $borderColor = match ($variant) {
        'primary' => 'border-l-primary-600',
        'info' => 'border-l-blue-500',
        'success' => 'border-l-emerald-600',
        'warning' => 'border-l-amber-500',
        'danger' => 'border-l-rose-500',
        'default' => 'border-l-gray-500',
        default => 'border-l-blue-500',
    };
@endphp

<div {{ $attributes->merge(['class' => "bg-gray-50 border border-gray-200 border-l-4 $borderColor rounded-r-sm p-3 shadow-2xs text-xs text-gray-700"]) }}>
    @if ($title || $icon)
        <div class="flex items-center gap-1.5 mb-1.5 font-bold text-xs text-gray-800">
            @if ($icon)
                <span class="shrink-0">{!! $icon !!}</span>
            @endif
            @if ($title)
                <span>{!! $title !!}</span>
            @endif
        </div>
    @endif
    <div>
        {{ $slot }}
    </div>
</div>

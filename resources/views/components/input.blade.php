@props([
    'name' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'icon' => null,
    'addonRight' => null,
    'size' => 'sm',
])

@php
    $hasError = $name && $errors->has($name);
    
    $borderClass = $hasError 
        ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500 text-rose-900 bg-rose-50/20' 
        : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500 bg-white text-gray-800';

    $sizeClasses = match($size) {
        'xs' => 'text-[11px] px-2.5 py-1',
        'md' => 'text-sm px-3.5 py-2.5',
        default => 'text-xs px-3 py-2', // sm
    };

    $paddingLeft = $icon ? 'pl-9' : '';
    $paddingRight = $addonRight ? 'pr-12' : '';
@endphp

<div class="relative w-full">
    @if ($icon)
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
            {!! $icon !!}
        </span>
    @endif

    <input
        type="{{ $type }}"
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        value="{{ $value ?? ($name ? old($name) : null) }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
        {{ $attributes->merge([
            'class' => "w-full rounded-sm border shadow-xs outline-none transition placeholder-gray-400 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed focus:ring-1 $borderClass $sizeClasses $paddingLeft $paddingRight"
        ]) }}
    />

    @if ($addonRight)
        <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs text-gray-400 font-medium">
            {{ $addonRight }}
        </span>
    @endif

    {{ $slot }}
</div>

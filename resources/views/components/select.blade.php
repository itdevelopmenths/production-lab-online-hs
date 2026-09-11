@props([
    'name' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
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
@endphp

<div class="relative w-full">
    <select
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        {{ $attributes->merge([
            'class' => "w-full rounded-sm border shadow-xs outline-none transition disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed focus:ring-1 cursor-pointer pr-8 $borderClass $sizeClasses"
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>
</div>

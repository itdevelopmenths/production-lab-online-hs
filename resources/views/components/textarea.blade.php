@props([
    'name' => null,
    'rows' => 3,
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
])

@php
    $hasError = $name && $errors->has($name);
    
    $borderClass = $hasError 
        ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500 text-rose-900 bg-rose-50/20' 
        : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500 bg-white text-gray-800';
@endphp

<div class="relative w-full">
    <textarea
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        rows="{{ $rows }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
        {{ $attributes->merge([
            'class' => "w-full rounded-sm border shadow-xs outline-none transition placeholder-gray-400 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed focus:ring-1 text-xs px-3 py-2 $borderClass"
        ]) }}
    >{{ $value ?? ($name ? old($name) : null) }}{{ $slot }}</textarea>
</div>

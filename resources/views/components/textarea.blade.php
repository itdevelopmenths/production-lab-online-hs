@props([
    'name' => null,
    'rows' => 3,
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'variant' => 'boxed', // boxed | seamless
])

@php
    $hasError = isset($errors) && $name && $errors->has($name);

    if ($variant === 'seamless') {
        $baseTextareaClass = 'w-full bg-transparent border-0 p-0 shadow-none outline-none text-[13px] font-normal text-text-active placeholder:text-text-placeholder focus:ring-0 disabled:text-text-muted disabled:cursor-not-allowed leading-relaxed';
    } else {
        $borderClass = $hasError
            ? 'border-red-500 text-red-600 dark:text-red-400 bg-red-50/40 dark:bg-red-500/10 focus:border-red-600 focus:ring-1 focus:ring-red-500/20'
            : 'border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 text-[#101828] dark:text-white focus:border-[#111111] dark:focus:border-white focus:ring-1 focus:ring-black/10 dark:focus:ring-white/15';

        $baseTextareaClass = "w-full rounded-[6px] border shadow-theme-xs outline-none transition placeholder:text-text-placeholder disabled:bg-gray-50 dark:disabled:bg-gray-800/50 disabled:text-text-muted disabled:cursor-not-allowed text-[13px] px-3.5 py-2.5 $borderClass";
    }
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
            'class' => $baseTextareaClass
        ]) }}
    >{{ $value ?? ($name ? old($name) : null) }}{{ $slot }}</textarea>
</div>

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
    'size' => 'default',
    'variant' => 'boxed', // boxed | seamless
])

@php
    $hasError = isset($errors) && $name && $errors->has($name);

    if ($variant === 'seamless') {
        $baseInputClass = 'w-full bg-transparent border-0 p-0 shadow-none outline-none text-[14px] font-medium text-text-active placeholder:text-text-placeholder focus:ring-0 disabled:text-text-muted disabled:cursor-not-allowed';
        $paddingLeft = $icon ? 'pl-6' : '';
        $paddingRight = $addonRight ? 'pr-10' : '';
    } else {
        $borderClass = $hasError
            ? 'border-red-500 text-red-600 dark:text-red-400 bg-red-50/40 dark:bg-red-500/10 focus:border-red-600 focus:ring-1 focus:ring-red-500/20'
            : 'border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 text-[#101828] dark:text-white focus:border-[#111111] dark:focus:border-white focus:ring-1 focus:ring-black/10 dark:focus:ring-white/15';

        $sizeClasses = match($size) {
            'xs' => 'text-xs px-2.5 py-1 rounded-[6px] h-7',
            'sm' => 'text-xs px-3.5 py-1.5 rounded-[6px] h-8.5',
            'lg' => 'text-sm px-4 py-2.5 rounded-[6px] h-11',
            default => 'text-[13px] px-3.5 py-2 rounded-[6px] h-9.5',
        };

        $paddingLeft = $icon ? 'pl-9' : '';
        $paddingRight = $addonRight ? 'pr-10' : '';
        $baseInputClass = "w-full border shadow-theme-xs outline-none transition placeholder:text-text-placeholder disabled:bg-gray-50 dark:disabled:bg-gray-800/50 disabled:text-text-muted disabled:cursor-not-allowed $borderClass $sizeClasses";
    }
@endphp

<div class="relative w-full">
    @if ($icon)
        <span class="absolute inset-y-0 left-0 {{ $variant === 'seamless' ? '' : 'pl-3' }} flex items-center pointer-events-none text-text-muted [&_svg]:size-4">
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
            'class' => trim("$baseInputClass $paddingLeft $paddingRight")
        ]) }}
    />

    @if ($addonRight)
        <span class="absolute inset-y-0 right-0 {{ $variant === 'seamless' ? 'pr-1' : 'pr-3.5' }} flex items-center pointer-events-none text-xs text-text-muted font-medium">
            {{ $addonRight }}
        </span>
    @endif

    {{ $slot }}
</div>

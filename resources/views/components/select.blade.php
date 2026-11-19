@props([
    'name' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'size' => 'default',
    'variant' => 'boxed', // boxed | seamless
])

@php
    $hasError = isset($errors) && $name && $errors->has($name);

    if ($variant === 'seamless') {
        $baseSelectClass = 'w-full bg-transparent border-0 p-0 shadow-none outline-none text-[14px] font-medium text-text-active focus:ring-0 disabled:text-text-muted disabled:cursor-not-allowed cursor-pointer pr-6';
    } else {
        $borderClass = $hasError
            ? 'border-red-500 text-red-600 dark:text-red-400 bg-red-50/40 dark:bg-red-500/10 focus:border-red-600 focus:ring-1 focus:ring-red-500/20'
            : 'border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 text-[#101828] dark:text-white focus:border-[#111111] dark:focus:border-white focus:ring-1 focus:ring-black/10 dark:focus:ring-white/15';

        $sizeClasses = match($size) {
            'xs' => 'text-xs px-2.5 py-1 rounded-[6px] h-7',
            'sm' => 'text-xs pl-3.5 py-1.5 rounded-[6px] h-8.5',
            'lg' => 'text-sm pl-4 py-2.5 rounded-[6px] h-11',
            default => 'text-[13px] pl-3.5 py-2 rounded-[6px] h-9.5',
        };

        $baseSelectClass = "w-full border shadow-theme-xs outline-none transition disabled:bg-gray-50 dark:disabled:bg-gray-800/50 disabled:text-text-muted disabled:cursor-not-allowed cursor-pointer pr-8 $borderClass $sizeClasses";
    }
@endphp

<div class="relative w-full">
    <select
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        {{ $attributes->merge([
            'class' => $baseSelectClass
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>
</div>

@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'disabled' => false,
    'align' => 'center', // center | top
    'help' => null,
    'labelWidth' => '150px',
])

@php
    $hasError = isset($errors) && $name && $errors->has($name);
    $alignClass = $align === 'top' ? 'sm:items-start' : 'sm:items-center';
    $disabledClass = $disabled ? 'opacity-60 bg-gray-50 dark:bg-gray-800/40 cursor-not-allowed' : '';
    $borderClass = $hasError
        ? 'border-red-500 dark:border-red-500/70 focus-within:border-red-600'
        : 'border-border dark:border-border focus-within:border-brand-500 dark:focus-within:border-white';
    $labelWidthClass = $labelWidth === '150px' ? 'sm:w-[150px]' : '';
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1 w-full']) }}>
    <div class="bg-white dark:bg-gray-900 border rounded-[8px] flex flex-col sm:flex-row {{ $alignClass }} px-3 py-2 gap-2.5 w-full relative transition-all duration-200 {{ $borderClass }} {{ $disabledClass }}">
        @if ($label)
            <label @if($name) for="{{ $name }}" @endif
                   @if($labelWidth !== '150px') style="--hs-label-w: {{ $labelWidth }};" @endif
                   class="shrink-0 font-medium text-[13px] sm:text-[14px] text-text-active text-left select-none leading-snug {{ $labelWidthClass }} {{ $labelWidth !== '150px' ? 'sm:w-[var(--hs-label-w)]' : '' }} {{ $align === 'top' ? 'sm:mt-1' : '' }}">
                <span>{!! $label !!}</span>@if ($required)<span class="text-red-500 ml-0.5">*</span>@endif
            </label>
        @endif

        <div class="flex-1 w-full relative flex items-center min-w-0">
            {{ $slot }}
        </div>
    </div>

    @if ($help)
        <p class="px-1 text-[11px] text-text-muted leading-tight">{!! $help !!}</p>
    @endif

    @if ($hasError)
        <p class="px-1 text-xs text-red-600 dark:text-red-400 font-medium flex items-center gap-1.5">
            <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ $errors->first($name) }}</span>
        </p>
    @endif
</div>

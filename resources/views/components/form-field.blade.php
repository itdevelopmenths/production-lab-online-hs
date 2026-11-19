@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'disabled' => false,
    'align' => 'center', // legacy: diabaikan, label selalu di atas input
    'help' => null,
    'labelWidth' => null, // legacy: diabaikan, label selalu di atas input
])

@php
    $hasError = isset($errors) && $name && $errors->has($name);
    $disabledClass = $disabled ? 'opacity-60 bg-gray-50 dark:bg-gray-800/40 cursor-not-allowed' : 'bg-white dark:bg-gray-900';
    $borderClass = $hasError
        ? 'border-red-400 dark:border-red-500/70 focus-within:border-red-500'
        : 'border-border focus-within:border-gray-900 dark:focus-within:border-white';
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5 w-full']) }}>
    @if ($label)
        <label @if($name) for="{{ $name }}" @endif class="text-[13px] text-text-muted leading-snug select-none">
            <span>{!! $label !!}</span>@if ($required)<span class="text-red-500 ml-0.5">*</span>@endif
        </label>
    @endif

    <div class="border rounded-lg flex items-center min-h-10 px-3 py-1.5 w-full relative transition-colors duration-150 {{ $borderClass }} {{ $disabledClass }}">
        <div class="flex-1 w-full relative flex items-center min-w-0">
            {{ $slot }}
        </div>
    </div>

    @if ($hasError)
        <p class="text-xs text-red-600 dark:text-red-400 flex items-center gap-1.5">
            <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ $errors->first($name) }}</span>
        </p>
    @elseif ($help)
        <p class="text-[11px] text-text-muted leading-snug">{!! $help !!}</p>
    @endif
</div>

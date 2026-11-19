@props([
    'variant' => 'primary', // primary (default), glossy, secondary, outline, destructive, danger, warning, success, info, ghost, link
    'size' => 'default', // xs, sm, md, default, lg, icon
    'href' => null,
    'icon' => null,
    'type' => 'button',
])

@php
    $normalizedVariant = match ($variant) {
        'glossy', 'primary' => 'primary',
        'secondary', 'outline' => 'secondary',
        'destructive', 'danger' => 'danger',
        'warning' => 'warning',
        'success' => 'success',
        'info' => 'info',
        'ghost' => 'ghost',
        'link' => 'link',
        default => 'primary',
    };

    $baseClasses = 'relative overflow-hidden group focus-visible:outline-none inline-flex shrink-0 items-center justify-center border rounded-[6px] font-medium tracking-[-0.154px] leading-[1.4] whitespace-nowrap transition-all duration-150 active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50 cursor-pointer select-none [&_svg]:shrink-0';

    $sizeClasses = match ($size) {
        'xs' => 'h-7 px-2.5 py-0.5 text-xs gap-1 rounded-[6px] [&_svg]:size-[13px]',
        'sm' => 'h-8 px-3 py-1.5 text-xs sm:text-[13px] gap-1.5 rounded-[6px] [&_svg]:size-[14px]',
        'md', 'default' => 'h-[38px] px-3.5 py-2 text-xs sm:text-sm gap-1.5 rounded-[6px] [&_svg]:size-[15px]',
        'lg' => 'h-11 px-4 sm:px-5 py-2.5 text-sm sm:text-base gap-2 rounded-[6px] [&_svg]:size-[16px]',
        'icon' => 'size-[32px] p-0 rounded-[6px] [&_svg]:size-[14px]',
        default => 'h-[38px] px-3.5 py-2 text-xs sm:text-sm gap-1.5 rounded-[6px] [&_svg]:size-[15px]',
    };

    $variantClasses = match ($normalizedVariant) {
        'primary' => 'btn-glossy focus-visible:shadow-[0px_0px_0px_2px_rgba(255,255,255,0.9),0px_0px_0px_4px_rgba(17,17,17,0.35)] disabled:!bg-none disabled:!bg-[#ededed] disabled:!text-[#a3a3a3] disabled:!border-[#e3e3e3] disabled:!shadow-none dark:disabled:!bg-zinc-800 dark:disabled:!text-zinc-500 dark:disabled:!border-zinc-700',
        'secondary' => 'bg-white text-black dark:bg-zinc-800 dark:text-zinc-100 border-[#e8e8e8] dark:border-zinc-700 hover:bg-[#fafafa] hover:border-[#dedede] dark:hover:bg-zinc-700 active:bg-[#f3f3f3] shadow-theme-xs',
        'warning' => 'bg-card text-foreground border-border hover:bg-gray-50 dark:bg-card dark:border-border dark:text-foreground dark:hover:bg-gray-50',
        'danger' => 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400 dark:hover:bg-red-500/20',
        'success' => 'bg-card text-foreground border-border hover:bg-gray-50 dark:bg-card dark:border-border dark:text-foreground dark:hover:bg-gray-50',
        'info' => 'bg-card text-foreground border-border hover:bg-gray-50 dark:bg-card dark:border-border dark:text-foreground dark:hover:bg-gray-50',
        'ghost' => 'border-transparent bg-transparent text-text-active hover:bg-gray-100 dark:hover:bg-gray-800',
        'link' => 'border-transparent bg-transparent text-text-active underline-offset-4 hover:underline p-0 h-auto shadow-none',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "$baseClasses $sizeClasses $variantClasses"]) }}>
        @if ($icon)
            <span class="relative z-10 shrink-0 inline-flex items-center">{!! $icon !!}</span>
        @endif
        <span class="relative z-10 inline-flex items-center gap-1.5">{!! $slot !!}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => "$baseClasses $sizeClasses $variantClasses"]) }}>
        @if ($icon)
            <span class="relative z-10 shrink-0 inline-flex items-center">{!! $icon !!}</span>
        @endif
        <span class="relative z-10 inline-flex items-center gap-1.5">{!! $slot !!}</span>
    </button>
@endif

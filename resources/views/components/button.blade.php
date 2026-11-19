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
        'primary' => 'text-white bg-gradient-to-b from-[#1e1e1e] to-[#0a0a0a] border-[rgba(255,255,255,0.3)] shadow-[inset_0px_2px_2px_0px_rgba(255,255,255,0.05),inset_0px_2px_2px_0px_rgba(255,255,255,0.08)] hover:from-[#2c2c2c] hover:to-[#141414] hover:border-[rgba(255,255,255,0.4)] active:from-[#0a0a0a] active:to-[#1a1a1a] active:border-[rgba(255,255,255,0.18)] active:shadow-[inset_0px_1px_3px_0px_rgba(0,0,0,0.4)] focus-visible:shadow-[0px_0px_0px_1px_#ffffff,0px_0px_0px_2px_rgba(46,46,46,0.22)] dark:from-white dark:to-gray-100 dark:text-[#0A0A0A] dark:border-white/80 dark:hover:from-gray-100 dark:hover:to-gray-200 disabled:from-[#ededed] disabled:to-[#e3e3e3] disabled:text-[#a3a3a3] disabled:border-[rgba(255,255,255,0.6)] dark:disabled:from-zinc-800 dark:disabled:to-zinc-800 dark:disabled:text-zinc-500',
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
        @if ($normalizedVariant === 'primary')
            <div class="absolute inset-0 rounded-[inherit] overflow-hidden pointer-events-none z-0 opacity-65 group-hover:opacity-100 group-active:opacity-30">
                <div class="absolute left-[-8%] right-[-8%] -bottom-[10px] h-[18px] rounded-full"
                     style="background: linear-gradient(90deg, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.5) 25%, rgba(213,229,248,0.5) 75%, rgba(213,229,248,0.02) 100%); filter: blur(6px);"></div>
            </div>
        @endif
        @if ($icon)
            <span class="relative z-10 shrink-0 inline-flex items-center">{!! $icon !!}</span>
        @endif
        <span class="relative z-10 inline-flex items-center gap-1.5">{!! $slot !!}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => "$baseClasses $sizeClasses $variantClasses"]) }}>
        @if ($normalizedVariant === 'primary')
            <div class="absolute inset-0 rounded-[inherit] overflow-hidden pointer-events-none z-0 opacity-65 group-hover:opacity-100 group-active:opacity-30">
                <div class="absolute left-[-8%] right-[-8%] -bottom-[10px] h-[18px] rounded-full"
                     style="background: linear-gradient(90deg, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.5) 25%, rgba(213,229,248,0.5) 75%, rgba(213,229,248,0.02) 100%); filter: blur(6px);"></div>
            </div>
        @endif
        @if ($icon)
            <span class="relative z-10 shrink-0 inline-flex items-center">{!! $icon !!}</span>
        @endif
        <span class="relative z-10 inline-flex items-center gap-1.5">{!! $slot !!}</span>
    </button>
@endif

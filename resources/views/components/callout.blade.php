@props([
    'variant' => 'info', // info, primary, success, warning, danger, default
    'title' => null,
    'icon' => null,
])

@php
    $themeClasses = match ($variant) {
        'primary' => 'border-black/20 bg-gray-100/70 text-[#101828] dark:border-white/20 dark:bg-gray-800/60 dark:text-white',
        'info' => 'border-border bg-card text-foreground dark:bg-card dark:border-border dark:text-foreground',
        'success' => 'border-border bg-card text-foreground dark:bg-card dark:border-border dark:text-foreground',
        'warning' => 'border-border bg-card text-foreground dark:bg-card dark:border-border dark:text-foreground',
        'danger' => 'border-red-200 bg-red-50/70 text-red-900 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-300',
        default => 'border-[#E8E8E8] bg-[#FBFBFB] text-[#101828] dark:border-border dark:bg-gray-900/60 dark:text-gray-200',
    };
@endphp

<div {{ $attributes->merge(['class' => "border rounded-[8px] p-3.5 shadow-theme-xs text-xs $themeClasses"]) }}>
    @if ($title || $icon)
        <div class="flex items-center gap-1.5 mb-1 font-medium text-xs">
            @if ($icon)
                <span class="shrink-0 [&_svg]:size-3.5">{!! $icon !!}</span>
            @endif
            @if ($title)
                <span>{!! $title !!}</span>
            @endif
        </div>
    @endif
    <div class="text-[11px] leading-relaxed opacity-90">
        {{ $slot }}
    </div>
</div>

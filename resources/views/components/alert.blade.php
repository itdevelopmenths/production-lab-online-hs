@props([
    'type' => 'warning', // warning, danger, success, info
    'title' => null,
    'icon' => true,
])

@php
    $classes = match ($type) {
        'danger' => 'bg-red-50/80 border-red-200 text-red-900 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-300',
        'warning' => 'bg-card border-border text-foreground dark:bg-card dark:border-border dark:text-foreground',
        'success' => 'bg-card border-border text-foreground dark:bg-card dark:border-border dark:text-foreground',
        'info' => 'bg-card border-border text-foreground dark:bg-card dark:border-border dark:text-foreground',
        default => 'bg-[#FBFBFB] border-[#E8E8E8] text-[#101828] dark:bg-gray-900 dark:border-border dark:text-white',
    };

    $iconSvg = match ($type) {
        'danger' => '<svg class="size-4 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
        'warning' => '<svg class="size-4 text-foreground dark:text-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
        'success' => '<svg class="size-4 text-foreground dark:text-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
        'info' => '<svg class="size-4 text-foreground dark:text-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-[10px] border px-4 py-3 text-sm flex items-center justify-between gap-3 shadow-theme-xs ' . $classes]) }}>
    <div class="flex items-center gap-2.5 min-w-0">
        @if ($icon)
            {!! $iconSvg !!}
        @endif
        <div class="truncate">
            @if ($title)
                <span class="font-medium mr-1.5">{{ $title }}</span>
            @endif
            <span>{{ $slot }}</span>
        </div>
    </div>

    @if (isset($action))
        <div class="shrink-0">
            {{ $action }}
        </div>
    @endif
</div>

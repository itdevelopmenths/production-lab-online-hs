@props([
    'type' => 'warning', // warning, danger, success, info
    'title' => null,
    'icon' => true,
])

@php
    $classes = match ($type) {
        'danger' => 'bg-red-50/80 border-red-200 text-red-900',
        'warning' => 'bg-amber-50/80 border-amber-200 text-amber-900',
        'success' => 'bg-emerald-50/80 border-emerald-200 text-emerald-900',
        'info' => 'bg-sky-50/80 border-sky-200 text-sky-900',
        default => 'bg-muted/60 border-border text-foreground',
    };

    $iconSvg = match ($type) {
        'danger' => '<svg class="size-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
        'warning' => '<svg class="size-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
        'success' => '<svg class="size-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
        'info' => '<svg class="size-4 text-sky-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 text-sm flex items-center justify-between gap-3 shadow-xs ' . $classes]) }}>
    <div class="flex items-center gap-2.5 min-w-0">
        @if ($icon)
            {!! $iconSvg !!}
        @endif
        <div class="truncate">
            @if ($title)
                <span class="font-semibold mr-1.5">{{ $title }}</span>
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

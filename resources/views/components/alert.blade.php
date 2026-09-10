@props([
    'type' => 'warning', // warning, danger, success, info
    'title' => null,
    'icon' => true,
])

@php
    $classes = match ($type) {
        'danger' => 'bg-rose-50 border-rose-200 text-rose-800',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-900',
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
        'info' => 'bg-blue-50 border-blue-200 text-blue-900',
        default => 'bg-gray-50 border-gray-200 text-gray-800',
    };

    $iconSvg = match ($type) {
        'danger' => '<svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
        'warning' => '<svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
        'success' => '<svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
        'info' => '<svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-sm border px-3.5 py-2.5 text-xs flex items-center justify-between gap-3 ' . $classes]) }}>
    <div class="flex items-center gap-2.5 min-w-0">
        @if ($icon)
            {!! $iconSvg !!}
        @endif
        <div class="truncate">
            @if ($title)
                <span class="font-bold mr-1">{{ $title }}</span>
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

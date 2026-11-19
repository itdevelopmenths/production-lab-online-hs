@props([
    'title' => null,
    'cols' => 2, // 1, 2, 3, 4
])

@php
    $gridColsClass = match ((int) $cols) {
        1 => 'grid-cols-1',
        3 => 'grid-cols-1 md:grid-cols-3',
        4 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
        default => 'grid-cols-1 md:grid-cols-2',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-2 w-full']) }}>
    @if ($title)
        <div class="pl-[10px] text-left">
            <span class="text-[12px] font-medium text-text-muted tracking-[0.12px] uppercase">{{ $title }}</span>
        </div>
    @endif

    <div class="w-full gap-[10px] grid {{ $gridColsClass }}">
        {{ $slot }}
    </div>
</div>

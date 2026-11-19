@props([
    'title' => null,
    'subtitle' => null,
    'cols' => 2, // 1, 2, 3, 4
    'card' => false, // true: bungkus sebagai kartu putih (dipakai di halaman form di atas latar abu-abu muda)
])

@php
    $gridColsClass = match ((int) $cols) {
        1 => 'grid-cols-1',
        3 => 'grid-cols-1 md:grid-cols-3',
        4 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
        default => 'grid-cols-1 md:grid-cols-2',
    };
    $wrapClass = $card
        ? 'bg-white dark:bg-gray-900 border border-border rounded-xl p-4'
        : '';
    $headClass = $card ? '' : 'pb-2 border-b border-border';
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 w-full ' . $wrapClass]) }}>
    @if ($title)
        <div class="{{ $headClass }} text-left">
            <h3 class="text-sm font-medium text-text-active leading-snug">{{ $title }}</h3>
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-text-muted leading-snug">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    <div class="w-full gap-x-4 gap-y-3 grid {{ $gridColsClass }}">
        {{ $slot }}
    </div>
</div>

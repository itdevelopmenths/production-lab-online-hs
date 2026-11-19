@props(['kpi'])

@php
    $status = $kpi['status'] ?? null;
    $href = $kpi['href'] ?? null;
    $statusLabel = match ($status) {
        'bad' => 'perlu tindakan',
        'warn' => 'perhatian',
        default => null,
    };
    $dotClass = $status === 'bad' ? 'bg-red-500' : 'bg-amber-500';
    $valueClass = $status === 'bad'
        ? 'text-red-600 dark:text-red-400'
        : 'text-[#101828] dark:text-white';
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    @if (! empty($kpi['hint'])) title="{{ $kpi['hint'] }}" @endif
    class="group flex-1 min-w-0 flex flex-col gap-3 rounded-[12px] border border-[#e8e8e8] dark:border-border bg-white dark:bg-gray-900 px-4 py-3.5 shadow-[0px_1px_2px_0px_rgba(16,24,40,0.04)] transition-colors {{ $href ? 'hover:border-gray-300 dark:hover:border-gray-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#111111] dark:focus-visible:outline-white' : '' }}"
>
    <div class="flex items-start justify-between gap-3">
        <p class="flex items-center gap-1.5 min-w-0 text-[12px] leading-[18px] font-medium text-[#6a7282] dark:text-gray-400">
            @if ($statusLabel)
                <span class="size-1.5 shrink-0 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
                <span class="sr-only">{{ $statusLabel }}:</span>
            @endif
            <span class="truncate">{!! $kpi['title'] !!}</span>
        </p>
        @if (! empty($kpi['icon']))
            <span class="shrink-0 text-[#9aa1b0] dark:text-gray-500 [&>svg]:size-4 [&_path]:[stroke-width:1.5]" aria-hidden="true">{!! $kpi['icon'] !!}</span>
        @endif
    </div>

    <div class="min-w-0">
        <p @if (! empty($kpi['valueTitle'])) title="{{ $kpi['valueTitle'] }}" @endif class="text-[28px] leading-[1.2] font-medium tracking-[-0.03em] tabular-nums break-words {{ $valueClass }}">{{ $kpi['value'] }}</p>
        <p class="mt-1 min-h-[18px] text-[12px] leading-[18px] text-[#6a7282] dark:text-gray-400 truncate">{{ $kpi['baseline'] ?? '' }}</p>
    </div>
</{{ $tag }}>

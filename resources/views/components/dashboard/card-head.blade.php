@props(['title', 'count' => null, 'subtitle' => null, 'url' => null])

{{-- Header kartu dashboard: judul boleh membungkus (tidak dipotong), subtitle panjang jadi tooltip. --}}
<div class="flex w-full items-start justify-between gap-3">
    <div class="flex items-center gap-2.5 min-w-0 flex-1">
        <h3 class="min-w-0 text-[15px] leading-snug font-medium text-[#101828] dark:text-white">{!! $title !!}</h3>
        @if ($count !== null)
            <span class="shrink-0 rounded-full bg-[#f3f4f6] dark:bg-gray-800 px-[7px] py-px text-[12px] font-medium tabular-nums text-[#6a7282] dark:text-gray-400">{{ $count }}</span>
        @endif
    </div>
    @if ($subtitle || $url)
        <div class="flex shrink-0 items-center gap-3 pt-0.5">
            @if ($subtitle)
                <span tabindex="0" role="img" title="{{ $subtitle }}" aria-label="{{ $subtitle }}" class="cursor-help text-[#9aa1b0] hover:text-[#101828] dark:text-gray-500 dark:hover:text-white">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            @endif
            @if ($url)
                <a href="{{ $url }}" class="inline-flex items-center gap-1 whitespace-nowrap text-xs font-medium text-text-active transition-colors hover:text-text-muted">Lihat Semua &rarr;</a>
            @endif
        </div>
    @endif
</div>

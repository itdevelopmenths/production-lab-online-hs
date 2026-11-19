@props(['stream', 'icon' => null])

@php
    $presenter = app(\App\Support\Dashboard\DashboardRowPresenter::class);
    $rows = collect($stream['items'] ?? [])->map(fn ($item) => $presenter->present($item));
@endphp

<x-card :title="$stream['title']" :count="$stream['total'] ?? $rows->count()" :noPadding="true">
    @if ($icon)
        <x-slot:icon>{!! $icon !!}</x-slot:icon>
    @endif

    <x-slot:tools>
        @if (! empty($stream['subtitle']))
            <span tabindex="0" role="img" title="{{ $stream['subtitle'] }}" aria-label="{{ $stream['subtitle'] }}" class="text-[#9aa1b0] hover:text-[#101828] dark:text-gray-500 dark:hover:text-white cursor-help">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
        @endif
        @if (! empty($stream['viewAllUrl']))
            <a href="{{ $stream['viewAllUrl'] }}" class="text-xs font-medium text-text-active hover:text-text-muted transition-colors inline-flex items-center gap-1">
                Lihat Semua &rarr;
            </a>
        @endif
    </x-slot:tools>

    @if ($rows->isEmpty())
        <div class="py-10 text-center text-text-muted text-xs flex flex-col items-center justify-center">
            <svg class="size-6 mb-1.5 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Tidak ada aktivitas.
        </div>
    @else
        <ul class="divide-y divide-[#e8e8e8] dark:divide-border/60">
            @foreach ($rows as $row)
                <li>
                    <a href="{{ $row['url'] }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[#111111] dark:focus-visible:outline-white">
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="font-medium tabular-nums text-[13px] text-[#101828] dark:text-white truncate">{{ $row['judul'] }}</span>
                            <span class="text-[11px] text-[#6a7282] dark:text-gray-400 truncate mt-0.5">{{ $row['detail'] }}</span>
                        </div>
                        @if ($row['badge'])
                            <x-badge :variant="$row['varian']" :dot="true" :pill="true" size="sm" class="shrink-0">{{ $row['badge'] }}</x-badge>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>

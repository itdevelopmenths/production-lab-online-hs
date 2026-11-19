@props(['queue'])

@php
    $presenter = app(\App\Support\Dashboard\DashboardRowPresenter::class);
    $rows = collect($queue['items'] ?? [])->map(fn ($item) => $presenter->present($item));
    $umurClass = fn (?string $s) => match ($s) {
        'bad' => 'text-red-600 dark:text-red-400 font-medium',
        'warn' => 'text-amber-600 dark:text-amber-400',
        default => 'text-[#6a7282] dark:text-gray-400',
    };
@endphp

<x-card :title="$queue['title']" :count="$queue['total'] ?? $rows->count()" :noPadding="true">
    <x-slot:icon>
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
    </x-slot:icon>

    <x-slot:tools>
        @if (! empty($queue['subtitle']))
            <span tabindex="0" role="img" title="{{ $queue['subtitle'] }}" aria-label="{{ $queue['subtitle'] }}" class="text-[#9aa1b0] hover:text-[#101828] dark:text-gray-500 dark:hover:text-white cursor-help">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
        @endif
        @if (! empty($queue['viewAllUrl']))
            <a href="{{ $queue['viewAllUrl'] }}" class="text-xs font-medium text-text-active hover:text-text-muted transition-colors inline-flex items-center gap-1">
                Lihat Semua &rarr;
            </a>
        @endif
    </x-slot:tools>

    @if ($rows->isEmpty())
        <div class="py-10 text-center text-text-muted text-xs flex flex-col items-center justify-center">
            <svg class="size-6 mb-1.5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Antrean bersih.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="border-b border-[#e8e8e8] dark:border-border text-left text-[12px] font-medium text-[#6a7282] dark:text-gray-400">
                        <th scope="col" class="px-5 py-2.5 font-medium">Jenis</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">Dokumen</th>
                        <th scope="col" class="px-3 py-2.5 font-medium hidden md:table-cell">Detail</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">Status</th>
                        <th scope="col" class="px-3 py-2.5 font-medium text-right hidden md:table-cell">Umur</th>
                        <th scope="col" class="px-5 py-2.5 font-medium text-right"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e8e8e8] dark:divide-border/60">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors">
                            <td class="px-5 py-3 text-[#6a7282] dark:text-gray-400 whitespace-nowrap">{{ $row['jenis'] ?? '-' }}</td>
                            <td class="px-3 py-3 min-w-0">
                                <span class="font-medium tabular-nums text-[#101828] dark:text-white">{{ $row['judul'] }}</span>
                                <span class="block md:hidden text-[11px] text-[#6a7282] dark:text-gray-400 truncate">{{ $row['detail'] }}</span>
                            </td>
                            <td class="px-3 py-3 text-[#6a7282] dark:text-gray-400 hidden md:table-cell max-w-[22rem] truncate">{{ $row['detail'] }}</td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if ($row['badge'])
                                        <x-badge :variant="$row['varian']" :dot="true" :pill="true" size="sm">{{ $row['badge'] }}</x-badge>
                                    @endif
                                    @if ($row['terlambat'])
                                        <x-badge variant="danger" :dot="true" :pill="true" size="sm">Terlambat</x-badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3 text-right tabular-nums whitespace-nowrap hidden md:table-cell {{ $umurClass($row['umurStatus']) }}">{{ $row['umur'] ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                <x-button :href="$row['url']" variant="secondary" size="xs">{{ $row['aksi'] ?? 'Buka' }}</x-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>

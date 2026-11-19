@props(['alur' => null])

@if ($alur)
    @php
        $maks = max(1, (int) collect($alur['tahap'])->max('jumlah'));
    @endphp

    <x-card :title="$alur['judul']" :noPadding="true">
        <div tabindex="0" role="region" aria-label="{{ $alur['judul'] }}" class="overflow-x-auto">
            <ol class="grid grid-flow-col auto-cols-[minmax(8rem,1fr)] divide-x divide-[#e8e8e8] dark:divide-border">
                @foreach ($alur['tahap'] as $i => $tahap)
                    @php
                        $status = $tahap['status'];
                        $barClass = match ($status) {
                            'bad' => 'bg-red-500',
                            'warn' => 'bg-amber-500',
                            default => 'bg-[var(--hs-data)]',
                        };
                        $angkaClass = match ($status) {
                            'bad' => 'text-red-600 dark:text-red-400',
                            'warn' => 'text-amber-600 dark:text-amber-400',
                            default => 'text-[#101828] dark:text-white',
                        };
                        $persen = $tahap['jumlah'] > 0 ? max(6, (int) round($tahap['jumlah'] / $maks * 100)) : 0;
                    @endphp
                    <li class="min-w-0">
                        <a href="{{ $tahap['href'] }}" class="block px-3.5 py-3.5 transition-colors hover:bg-[#fafafa] dark:hover:bg-gray-800/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[#111111] dark:focus-visible:outline-white">
                            <p class="truncate text-[12px] leading-[18px] font-medium text-[#6a7282] dark:text-gray-400">{{ $tahap['label'] }}</p>
                            <p class="mt-1 text-[20px] leading-[1.3] font-medium tabular-nums {{ $angkaClass }}">
                                {{ $tahap['jumlah'] }}
                                @if ($status)
                                    <span class="sr-only">{{ $status === 'bad' ? 'perlu tindakan' : 'perhatian' }}</span>
                                @endif
                            </p>
                            <div class="mt-2.5 h-[3px] rounded-full bg-[#f3f4f6] dark:bg-gray-800" aria-hidden="true">
                                <div class="h-full rounded-full {{ $barClass }}" style="width: {{ $persen }}%"></div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>
    </x-card>
@endif

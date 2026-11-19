@props(['chart'])

@php
    $kategori = $chart['kategori'];
    $seri = $chart['seri'];
    $terakhir = count($kategori) - 1;
    $banyakSeri = count($seri) > 1;
    $maks = max(1.0, (float) $chart['maks']);
    $kosong = collect($seri)->every(fn ($s) => array_sum($s['nilai']) <= 0);

    // Ringkasan untuk pembaca layar: "Jun PO Rp 1 jt Dibayar Rp 0; Jul ...".
    $ringkasan = collect($kategori)->map(function ($bulan, $i) use ($seri) {
        $nilai = collect($seri)->map(fn ($s) => $s['label'] . ' ' . $s['teks'][$i])->implode(', ');

        return "{$bulan}: {$nilai}";
    })->implode('; ');

    $warna = fn (string $nada, bool $berjalan) => match (true) {
        $nada === 'data' && $berjalan => 'border border-dashed border-[var(--hs-data)] bg-[var(--hs-data)]/40',
        $nada === 'data' => 'bg-[var(--hs-data)]',
        $berjalan => 'border border-dashed border-gray-400 dark:border-gray-500 bg-gray-300/50 dark:bg-gray-600/40',
        default => 'bg-gray-300 dark:bg-gray-600',
    };
@endphp

<x-card :noPadding="true">
    <x-slot:header>
        <x-dashboard.card-head :title="$chart['judul']" :url="$chart['href'] ?? null" />
    </x-slot:header>

    <div class="px-5 pt-4 pb-4">
        @if ($banyakSeri)
            <div class="mb-3 flex items-center gap-4 text-[11px] font-medium text-[#6a7282] dark:text-gray-400">
                @foreach ($seri as $s)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2 rounded-[2px] {{ $s['nada'] === 'data' ? 'bg-[var(--hs-data)]' : 'bg-gray-300 dark:bg-gray-600' }}" aria-hidden="true"></span>
                        {{ $s['label'] }}
                    </span>
                @endforeach
            </div>
        @endif

        <div role="img" aria-label="{{ $chart['judul'] }}. {{ $ringkasan }}">
            <div class="relative h-40">
                {{-- Garis bantu: puncak, tengah, dasar --}}
                <div class="absolute inset-0 flex flex-col justify-between" aria-hidden="true">
                    <div class="flex items-center gap-2"><span class="w-[4.5rem] shrink-0 whitespace-nowrap text-[10px] tabular-nums text-[#9aa1b0] dark:text-gray-500">{{ $chart['maksTeks'] }}</span><span class="h-px flex-1 bg-[#eef0f3] dark:bg-gray-800"></span></div>
                    <div class="flex items-center gap-2"><span class="w-[4.5rem] shrink-0"></span><span class="h-px flex-1 bg-[#eef0f3] dark:bg-gray-800"></span></div>
                    <div class="flex items-center gap-2"><span class="w-[4.5rem] shrink-0"></span><span class="h-px flex-1 bg-[#d8dce3] dark:bg-gray-700"></span></div>
                </div>

                @if ($kosong)
                    <p class="absolute inset-0 flex items-center justify-center pl-[5rem] text-xs text-[#6a7282] dark:text-gray-400">{{ $chart['kosong'] ?? 'Tidak ada data' }}</p>
                @endif

                <div class="absolute inset-0 flex pl-[5rem]" aria-hidden="true">
                    @foreach ($kategori as $i => $bulan)
                        @php $berjalan = $i === $terakhir; @endphp
                        <div class="flex min-w-0 flex-1 items-end justify-center gap-1" title="{{ $bulan }}{{ $berjalan ? ' (berjalan)' : '' }} · {{ collect($seri)->map(fn ($s) => $s['label'] . ': ' . $s['teks'][$i])->implode(' · ') }}">
                            @foreach ($seri as $s)
                                @php
                                    $nilai = (float) $s['nilai'][$i];
                                    $persen = $nilai > 0 ? max(2, round($nilai / $maks * 100, 1)) : 0;
                                @endphp
                                <div class="flex h-full w-full max-w-[18px] flex-col items-center justify-end">
                                    @if (! $banyakSeri && $nilai > 0)
                                        <span class="mb-0.5 text-[10px] tabular-nums text-[#6a7282] dark:text-gray-400">{{ $s['teks'][$i] }}</span>
                                    @endif
                                    @if ($nilai > 0)
                                        <div class="w-full rounded-t-[3px] {{ $warna($s['nada'], $berjalan) }}" style="height: {{ $persen }}%"></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-2 flex pl-[5rem] text-[11px] text-[#6a7282] dark:text-gray-400" aria-hidden="true">
                @foreach ($kategori as $i => $bulan)
                    <span class="min-w-0 flex-1 text-center {{ $i === $terakhir ? 'font-medium text-[#101828] dark:text-white' : '' }}">{{ $bulan }}</span>
                @endforeach
            </div>
        </div>
    </div>
</x-card>

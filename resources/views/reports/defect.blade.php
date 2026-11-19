<x-app-layout title="Laporan Defect & Kerusakan">
    <x-page-header
        title="Laporan Defect & Kerusakan Mutu"
        subtitle="Monitoring batch produksi dan pergerakan logistik transfer yang mengalami unit rusak/cacat"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Defect' => null]"
        :backUrl="route('reports.index')"
    />

    {{-- Filter Toolbar --}}
    <form method="GET" action="{{ route('reports.defect') }}" class="mb-6 bg-white dark:bg-gray-900 p-4 rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)] flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="filter-tahun" class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Tahun Evaluasi</label>
                <select name="tahun" id="filter-tahun" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-28 max-w-full outline-none cursor-pointer rounded-[6px]">
                    @for($y = now()->year + 1; $y >= now()->year - 2; $y--)
                        <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label for="filter-bulan" class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Bulan</label>
                <select name="bulan" id="filter-bulan" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-36 max-w-full outline-none cursor-pointer rounded-[6px]">
                    <option value="">— Semua Bulan —</option>
                    @php
                        $namaBulan = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                        ];
                    @endphp
                    @foreach($namaBulan as $mNum => $mLabel)
                        <option value="{{ $mNum }}" @selected($month == $mNum)>{{ $mLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-gudang" class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Gudang / Lokasi</label>
                <select name="gudang_id" id="filter-gudang" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-40 max-w-full outline-none cursor-pointer rounded-[6px]">
                    <option value="">— Semua Gudang —</option>
                    @foreach($gudangs as $g)
                        <option value="{{ $g->id }}" @selected($gudangId == $g->id)>{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pt-5">
                <x-button type="submit" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    </x-slot:icon>
                    Filter
                </x-button>
            </div>
        </div>

        <div>
            <a href="{{ route('reports.defect') }}" class="text-xs text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white font-medium transition-colors">
                Reset Filter
            </a>
        </div>
    </form>

    @php
        $totalBatches = $batches->count();
        $totalRusak = $batches->sum('qty_rusak');
        $validDefects = $batches->map(fn($b) => $b->defectRate())->filter(fn($d) => $d !== null);
        $avgDefect = $validDefects->isNotEmpty() ? $validDefects->avg() : null;

        $trfSummary = $transferAnalytics['summary'] ?? [];
        $trfTotalRusak = $trfSummary['total_qty_rusak'] ?? 0;
        $trfRate = $trfSummary['defect_rate_percent'] ?? 0;
        $trfTopGudang = $trfSummary['gudang_tertinggi_nama'] ?? '—';
        $trfTopQty = $trfSummary['gudang_tertinggi_qty'] ?? 0;
        $trfTerdampakCount = $trfSummary['transfer_terdampak_count'] ?? 0;
    @endphp

    {{-- KPI Summary Cards: Produksi vs Transfer (Preserves lg:grid-cols-6) --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 mb-6">
        <x-stat-box title="Batch Produksi Defect" :value="number_format($totalBatches)" subtitle="Batch" iconColor="gray" />
        <x-stat-box title="Rusak Produksi" :value="number_format($totalRusak, 0, ',', '.')" subtitle="Pcs" iconColor="rose" />
        <x-stat-box title="Defect Rate Produksi" :value="$avgDefect !== null ? number_format($avgDefect, 1) . '%' : '0.0%'" iconColor="rose" />
        <x-stat-box title="Rusak Pengiriman (Transfer)" :value="number_format($trfTotalRusak, 0, ',', '.')" subtitle="Pcs" iconColor="rose" />
        <x-stat-box title="Gudang Paling Terdampak" :value="$trfTopGudang" :subtitle="number_format($trfTopQty, 0, ',', '.') . ' Pcs rusak'" iconColor="amber" />
        <x-stat-box title="Defect Rate Transfer" :value="number_format($trfRate, 2) . '%'" :subtitle="$trfTerdampakCount . ' transfer terdampak'" iconColor="amber" />
    </div>

    <div class="space-y-6">
        {{-- Table Card 1: Batch Produksi --}}
        <x-card title="Rincian Batch Produksi Terdampak Defect" subtitle="Daftar batch selesai yang mencatatkan kuantitas unit rusak lebih dari nol" :noPadding="true">
            <x-slot:icon>
                <svg class="size-5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </x-slot:icon>
            <div tabindex="0" role="region" aria-label="Tabel Rincian Batch Produksi Terdampak Defect" class="overflow-x-auto focus:outline-none focus:ring-1 focus:ring-[#111111]/20 dark:focus:ring-white/20">
                <table class="w-full text-sm text-left border-collapse min-w-[700px]">
                    <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[13px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                        <tr>
                            <th class="px-4 py-3 w-40">No. Batch</th>
                            <th class="px-4 py-3">Produk Jadi</th>
                            <th class="px-4 py-3 text-right">Output Baik</th>
                            <th class="px-4 py-3 text-right">Output Rusak</th>
                            <th class="px-4 py-3 text-center">Tingkat Defect</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                        @forelse($batches as $b)
                        @php
                            $defect = $b->defectRate();
                            $produkLabel = $b->outputs->isNotEmpty()
                                ? $b->outputs->map(fn($o) => $o->produk?->nama)->filter()->join(', ')
                                : ($b->produk?->nama ?? '—');
                        @endphp
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors">
                            <td class="px-4 py-3 tabular-nums font-medium text-[#101828] dark:text-white">
                                @can('batch.view')
                                    <a href="{{ route('batches.show', $b) }}" class="underline decoration-gray-400 hover:decoration-current">{{ $b->no_batch }}</a>
                                @else
                                    {{ $b->no_batch }}
                                @endcan
                            </td>
                            <td class="px-4 py-3 font-medium text-[#101828] dark:text-white">{{ $produkLabel }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-[#101828] dark:text-white">{{ number_format($b->qty_baik, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-rose-600 dark:text-rose-400 font-medium">{{ number_format($b->qty_rusak, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($defect !== null)
                                    <x-badge variant="danger" size="xs" :pill="true">{{ number_format($defect, 1) }}%</x-badge>
                                @else
                                    <span class="text-[#667085] dark:text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-xs text-[#667085] dark:text-gray-400">
                                <svg class="mx-auto h-8 w-8 text-foreground mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Kualitas sempurna! Tidak ditemukan batch produksi dengan unit rusak pada filter terpilih.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Section 2: Rekapitulasi Kerusakan Pengiriman (Transfer) Bulanan per Gudang --}}
        <x-card title="Rekapitulasi Kerusakan Pengiriman (Transfer) Bulanan per Gudang" subtitle="Matriks persebaran kuantitas unit rusak pengiriman logistik antar-gudang (Tahun {{ $year }})" :noPadding="true">
            <x-slot:icon>
                <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            </x-slot:icon>
            <div tabindex="0" role="region" aria-label="Matriks Kerusakan Pengiriman Transfer Bulanan per Gudang" class="overflow-x-auto focus:outline-none focus:ring-1 focus:ring-[#111111]/20 dark:focus:ring-white/20">
                <table class="w-full text-xs text-left border-collapse min-w-[980px]">
                    <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 uppercase text-[10px] font-medium tracking-wider border-b border-[#E8E8E8] dark:border-gray-800">
                        <tr>
                            <th class="px-3 py-3 w-44">Gudang Tujuan</th>
                            @for($m = 1; $m <= 12; $m++)
                                <th class="px-2 py-3 text-right">{{ substr($namaBulan[$m], 0, 3) }}</th>
                            @endfor
                            <th class="px-3 py-3 text-right bg-gray-100 dark:bg-gray-800 font-medium text-[#101828] dark:text-white">Total Tahunan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                        @forelse($transferAnalytics['monthly_by_warehouse'] ?? [] as $mWh)
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors">
                            <td class="px-3 py-2.5 font-medium text-[#101828] dark:text-white">
                                <div class="font-medium">{{ $mWh['nama'] }}</div>
                                <div class="text-[10px] tabular-nums text-[#667085] dark:text-gray-400">{{ $mWh['kode'] }}</div>
                            </td>
                            @for($m = 1; $m <= 12; $m++)
                                @php
                                    $val = $mWh['months'][$m] ?? 0;
                                @endphp
                                <td class="px-2 py-2.5 text-right tabular-nums {{ $val > 0 ? 'text-rose-600 dark:text-rose-400 font-medium' : 'text-[#667085]/60 dark:text-gray-600' }}">
                                    {{ $val > 0 ? number_format($val, 0, ',', '.') : '—' }}
                                </td>
                            @endfor
                            <td class="px-3 py-2.5 text-right tabular-nums font-medium bg-[#fafafa] dark:bg-gray-800/50 {{ ($mWh['total_tahunan'] ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-[#667085] dark:text-gray-400' }}">
                                {{ number_format($mWh['total_tahunan'] ?? 0, 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="14" class="px-4 py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                Tidak ada data matriks gudang untuk tahun {{ $year }}.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Sub-tabel Rincian SKU Rusak per Gudang --}}
            @php
                $anySkuRusak = false;
                foreach ($transferAnalytics['monthly_by_warehouse'] ?? [] as $w) {
                    if (!empty($w['sku_rusak_list'])) {
                        $anySkuRusak = true;
                        break;
                    }
                }
            @endphp
            @if($anySkuRusak)
                <div class="p-4 sm:p-5 border-t border-[#E8E8E8] dark:border-gray-800 bg-[#F5F5F5] dark:bg-gray-950">
                    <h5 class="text-xs font-semibold text-[#101828] dark:text-white mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Rincian Komoditas SKU Rusak Akumulasi Pengiriman ({{ $year }})
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($transferAnalytics['monthly_by_warehouse'] ?? [] as $w)
                            @if(!empty($w['sku_rusak_list']))
                                <div class="p-3.5 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 space-y-1.5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                                    <div class="font-medium text-xs text-[#101828] dark:text-white flex items-center justify-between border-b border-[#E8E8E8] dark:border-gray-800 pb-1.5">
                                        <span>{{ $w['nama'] }}</span>
                                        <span class="tabular-nums text-rose-600 dark:text-rose-400 text-[11px]">{{ number_format($w['total_tahunan'], 0, ',', '.') }} Pcs</span>
                                    </div>
                                    <div class="divide-y divide-[#E8E8E8]/60 dark:divide-gray-800 text-[11px]">
                                        @foreach($w['sku_rusak_list'] as $sku)
                                            <div class="py-1.5 flex items-center justify-between">
                                                <div class="truncate pr-2">
                                                    <span class="font-medium text-[#101828] dark:text-white">{{ $sku['nama'] }}</span>
                                                    <span class="tabular-nums text-[10px] text-[#667085] dark:text-gray-400 block">{{ $sku['sku'] }}</span>
                                                </div>
                                                <span class="tabular-nums font-medium text-rose-600 dark:text-rose-400 shrink-0">{{ number_format($sku['qty_rusak'], 0, ',', '.') }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>

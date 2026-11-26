<x-app-layout title="Variance Pemakaian (Stock Opname)">
    <x-page-header
        title="Variance Pemakaian (Stock Opname)"
        subtitle="Analisis deviasi perbandingan antara formula standar teoritis BOM vs realisasi aktual di lab"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Variance Opname' => null]"
        :backUrl="route('reports.index')"
    />

    @php
        $totalOpname = $opname->count();
        $borosCount = $opname->filter(fn($o) => $o->variance() > 0)->count();
        $hematCount = $opname->filter(fn($o) => $o->variance() <= 0)->count();
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-stat-box title="Total Pemeriksaan Opname" :value="number_format($totalOpname)" subtitle="Record" iconColor="gray" />
        <x-stat-box title="Selisih Lebih (Pemborosan)" :value="number_format($borosCount)" subtitle="Batch" :iconColor="$borosCount > 0 ? 'rose' : 'gray'" />
        <x-stat-box title="Sesuai / Efisien (&le; 0)" :value="number_format($hematCount)" subtitle="Batch" iconColor="emerald" />
    </div>

    <!-- Table Card -->
    <x-card class="tbl-modern" title="Data Deviasi Pemakaian Bahan Baku" subtitle="Daftar rekapitulasi audit opname bahan dari batch produksi" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Data Deviasi Pemakaian Bahan Baku" class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[13px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-3 w-40">No. Batch</th>
                        <th class="px-4 py-3">Nama Bahan / Material</th>
                        <th class="px-4 py-3 text-right">Teoritis (BOM)</th>
                        <th class="px-4 py-3 text-right">Realisasi Aktual</th>
                        <th class="px-4 py-3 text-right">Deviasi Variance</th>
                        <th class="px-4 py-3">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                    @forelse($opname as $o)
                    @php
                        $var = $o->variance();
                    @endphp
                    <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors">
                        <td class="px-4 py-3 tabular-nums font-medium text-[#101828] dark:text-white">
                            {{ $o->batch->no_batch ?? '-' }}
                        </td>
                        <td class="px-4 py-3 font-medium text-[#101828] dark:text-white">
                            {{ $o->bahan->nama ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-[#667085] dark:text-gray-400">
                            {{ \App\Helpers\NumberHelper::formatQty($o->pemakaian_teoritis) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-[#101828] dark:text-white font-medium">
                            {{ \App\Helpers\NumberHelper::formatQty($o->pemakaian_aktual) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium">
                            @if($var > 0)
                                <span class="text-rose-600 dark:text-rose-400 font-medium">+{{ \App\Helpers\NumberHelper::formatQty($var) }}</span>
                            @elseif($var < 0)
                                <span class="text-foreground dark:text-foreground font-medium">{{ \App\Helpers\NumberHelper::formatQty($var) }}</span>
                            @else
                                <span class="text-[#667085] dark:text-gray-400">0,00</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-[#667085] dark:text-gray-400 text-xs">
                            {{ $o->keterangan ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-xs text-[#667085] dark:text-gray-400">
                            <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Belum ada catatan audit variance opname.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

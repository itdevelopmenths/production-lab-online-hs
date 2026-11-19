<x-app-layout title="Pemakaian Bahan Baku">
    <x-page-header
        title="Laporan Pemakaian Bahan Baku"
        subtitle="Akumulasi konsumsi bahan baku dan kemasan yang telah dikeluarkan untuk proses manufaktur batch"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Pemakaian Bahan' => null]"
        :backUrl="route('reports.index')"
    />

    @php
        $totalItems = $rows->count();
        $topItem = $rows->sortByDesc('total_keluar')->first();
        $totalQty = $rows->sum('total_keluar');
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-stat-box title="Total SKU Bahan Terpakai" :value="number_format($totalItems)" subtitle="Item" iconColor="gray" />
        <x-stat-box title="Akumulasi Total Keluar" :value="number_format($totalQty, 2, ',', '.')" subtitle="Unit/Gram/Pcs" iconColor="blue" />
        <x-stat-box title="Konsumsi Tertinggi" :value="$topItem?->produk?->nama ?? '-'" iconColor="amber" />
    </div>

    <!-- Table Card -->
    <x-card class="tbl-modern" title="Konsumsi Bahan Baku Manufaktur" subtitle="Data agregat pengeluaran bahan dari kartu stok dengan referensi batch produksi" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Konsumsi Bahan Baku Manufaktur" class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[13px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-3 w-40">Kode SKU</th>
                        <th class="px-4 py-3">Nama Bahan / Material</th>
                        <th class="px-4 py-3 text-right">Total Keluar (Produksi)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                    @forelse($rows as $r)
                    <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors">
                        <td class="px-4 py-3 tabular-nums font-medium text-[#101828] dark:text-white">
                            {{ $r->produk->sku ?? '-' }}
                        </td>
                        <td class="px-4 py-3 font-medium text-[#101828] dark:text-white">
                            {{ $r->produk->nama ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-[#101828] dark:text-white font-medium">
                            {{ number_format($r->total_keluar, 2, ',', '.') }}
                            <span class="text-[#667085] dark:text-gray-400 font-normal text-xs ml-1">{{ $r->produk->satuan ?? '' }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-12 text-center text-xs text-[#667085] dark:text-gray-400">
                            <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            Belum ada catatan pemakaian bahan baku.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

<x-app-layout title="Pemakaian Bahan Baku">
    <x-page-header
        title="Laporan Pemakaian Bahan Baku"
        subtitle="Akumulasi konsumsi bahan baku dan kemasan yang telah dikeluarkan untuk proses manufaktur batch"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Pemakaian Bahan' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('reports.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Pusat Laporan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalItems = $rows->count();
        $topItem = $rows->sortByDesc('total_keluar')->first();
        $totalQty = $rows->sum('total_keluar');
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total SKU Bahan Terpakai</p>
            <p class="text-lg font-bold font-mono text-gray-900 mt-0.5">{{ number_format($totalItems) }} <span class="text-xs font-normal text-gray-500">Item</span></p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Akumulasi Total Keluar</p>
            <p class="text-lg font-bold font-mono text-primary-700 mt-0.5">{{ number_format($totalQty, 2, ',', '.') }} <span class="text-xs font-normal text-gray-500">Unit/Gram/Pcs</span></p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Konsumsi Tertinggi</p>
            <p class="text-sm font-semibold text-gray-900 truncate mt-1" title="{{ $topItem?->produk?->nama }}">
                {{ $topItem?->produk?->nama ?? '-' }}
            </p>
        </div>
    </div>

    <!-- Table Card -->
    <x-card title="Konsumsi Bahan Baku Manufaktur" subtitle="Data agregat pengeluaran bahan dari kartu stok dengan referensi batch produksi" variant="primary">
        <div class="-mx-4 -my-4 overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider w-36">Kode SKU</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider">Nama Bahan / Material</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Total Keluar (Produksi)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $r)
                    <tr class="hover:bg-primary-50/20 transition-colors {{ $loop->even ? 'bg-gray-50/40' : 'bg-white' }}">
                        <td class="px-3.5 py-2 font-mono font-medium text-primary-700">
                            {{ $r->produk->sku ?? '-' }}
                        </td>
                        <td class="px-3.5 py-2 font-medium text-gray-900">
                            {{ $r->produk->nama ?? '-' }}
                        </td>
                        <td class="px-3.5 py-2 text-right font-mono text-gray-900 font-semibold">
                            {{ number_format($r->total_keluar, 2, ',', '.') }}
                            <span class="text-gray-500 font-normal text-[11px] ml-1">{{ $r->produk->satuan ?? '' }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-xs text-gray-400">
                            <svg class="mx-auto h-7 w-7 text-gray-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            Belum ada catatan pemakaian bahan baku.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

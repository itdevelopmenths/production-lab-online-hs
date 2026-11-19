<x-app-layout title="Pergerakan Stok Fulfillment">
    <x-page-header
        title="Pergerakan Stok Fulfillment"
        subtitle="Log audit mutasi dan sirkulasi persediaan produk jadi pada fasilitas gudang fulfillment pusat dan cabang"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Pergerakan Fulfillment' => null]"
        :backUrl="route('reports.index')"
    />

    @php
        $totalLogs = $rows->count();
        $totalIn = $rows->where('tipe', 'in')->sum('qty');
        $totalOut = $rows->where('tipe', 'out')->sum('qty');
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-stat-box title="Total Transaksi Mutasi" :value="number_format($totalLogs)" subtitle="Log" iconColor="gray" />
        <x-stat-box title="Total Masuk (IN)" :value="'+' . number_format($totalIn, 0, ',', '.')" subtitle="Pcs" iconColor="emerald" />
        <x-stat-box title="Total Keluar (OUT)" :value="'-' . number_format($totalOut, 0, ',', '.')" subtitle="Pcs" iconColor="amber" />
    </div>

    <!-- Table Card -->
    <x-card title="Riwayat Mutasi Stok Fulfillment" subtitle="Data pergerakan kartu stok pada gudang bertipe fulfillment" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Riwayat Mutasi Stok Fulfillment" class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[13px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-3 w-32">Tanggal</th>
                        <th class="px-4 py-3">Lokasi Gudang</th>
                        <th class="px-4 py-3">Produk Jadi</th>
                        <th class="px-4 py-3 text-center w-24">Tipe</th>
                        <th class="px-4 py-3 text-right">Kuantitas</th>
                        <th class="px-4 py-3 text-right">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                    @forelse($rows as $r)
                    @php
                        $isMasuk = strtolower($r->tipe) === 'in';
                    @endphp
                    <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors">
                        <td class="px-4 py-3 tabular-nums text-[#667085] dark:text-gray-400">
                            {{ $r->tanggal->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3 font-medium text-[#101828] dark:text-white">
                            {{ $r->gudang->nama ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-[#101828] dark:text-white">
                            {{ $r->produk->nama ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($isMasuk)
                                <x-badge variant="success" size="xs" :pill="true">MASUK</x-badge>
                            @else
                                <x-badge variant="warning" size="xs" :pill="true">KELUAR</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium {{ $isMasuk ? 'text-foreground dark:text-foreground' : 'text-foreground dark:text-foreground' }}">
                            {{ $isMasuk ? '+' : '-' }}{{ number_format($r->qty, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-[#101828] dark:text-white font-medium">
                            {{ number_format($r->saldo_setelah, 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-xs text-[#667085] dark:text-gray-400">
                            <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            Belum ada catatan mutasi stok fulfillment.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

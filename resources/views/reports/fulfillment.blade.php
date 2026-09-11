<x-app-layout title="Pergerakan Stok Fulfillment">
    <x-page-header
        title="Pergerakan Stok Fulfillment"
        subtitle="Log audit mutasi dan sirkulasi persediaan produk jadi pada fasilitas gudang fulfillment pusat dan cabang"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Pergerakan Fulfillment' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('reports.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Pusat Laporan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalLogs = $rows->count();
        $totalIn = $rows->where('tipe', 'in')->sum('qty');
        $totalOut = $rows->where('tipe', 'out')->sum('qty');
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Transaksi Mutasi</p>
            <p class="text-lg font-bold font-mono text-gray-900 mt-0.5">{{ number_format($totalLogs) }} <span class="text-xs font-normal text-gray-500">Log</span></p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Masuk (IN)</p>
            <p class="text-lg font-bold font-mono text-emerald-600 mt-0.5">+{{ number_format($totalIn, 0, ',', '.') }} <span class="text-xs font-normal text-gray-500">Pcs</span></p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Keluar (OUT)</p>
            <p class="text-lg font-bold font-mono text-amber-600 mt-0.5">-{{ number_format($totalOut, 0, ',', '.') }} <span class="text-xs font-normal text-gray-500">Pcs</span></p>
        </div>
    </div>

    <!-- Table Card -->
    <x-card title="Riwayat Mutasi Stok Fulfillment" subtitle="Data pergerakan kartu stok pada gudang bertipe fulfillment" variant="primary">
        <div class="-mx-4 -my-4 overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider w-28">Tanggal</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider">Lokasi Gudang</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider">Produk Jadi</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-center w-24">Tipe</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Kuantitas</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $r)
                    @php
                        $isMasuk = strtolower($r->tipe) === 'in';
                    @endphp
                    <tr class="hover:bg-primary-50/20 transition-colors {{ $loop->even ? 'bg-gray-50/40' : 'bg-white' }}">
                        <td class="px-3.5 py-2 font-mono text-gray-700">
                            {{ $r->tanggal->format('d/m/Y') }}
                        </td>
                        <td class="px-3.5 py-2 font-medium text-gray-900">
                            {{ $r->gudang->nama ?? '-' }}
                        </td>
                        <td class="px-3.5 py-2 text-gray-800">
                            {{ $r->produk->nama ?? '-' }}
                        </td>
                        <td class="px-3.5 py-2 text-center">
                            @if($isMasuk)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    MASUK
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                    KELUAR
                                </span>
                            @endif
                        </td>
                        <td class="px-3.5 py-2 text-right font-mono font-medium {{ $isMasuk ? 'text-emerald-600' : 'text-amber-600' }}">
                            {{ $isMasuk ? '+' : '-' }}{{ number_format($r->qty, 0, ',', '.') }}
                        </td>
                        <td class="px-3.5 py-2 text-right font-mono text-gray-900 font-semibold">
                            {{ number_format($r->saldo_setelah, 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-400">
                            <svg class="mx-auto h-7 w-7 text-gray-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            Belum ada catatan mutasi stok fulfillment.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

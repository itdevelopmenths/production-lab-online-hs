<x-app-layout title="Purchasing & Hutang Dagang">
    <x-page-header
        title="Laporan Purchasing & Hutang Dagang"
        subtitle="Rekapitulasi komitmen nilai purchase order, realisasi pembayaran, dan outstanding liabilitas hutang supplier"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Purchasing & Hutang' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('reports.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Pusat Laporan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalPoCount = $pos->count();
        $totalNilai = $pos->sum('total_nilai');
        $totalDibayar = $pos->sum('total_dibayar');
        $totalSisa = $pos->sum(fn($p) => ($p->total_nilai ?? 0) - ($p->total_dibayar ?? 0));
    @endphp

    <!-- Financial KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Pesanan (PO)</p>
            <p class="text-lg font-bold font-mono text-gray-900 mt-0.5">{{ number_format($totalPoCount) }} <span class="text-xs font-normal text-gray-500">PO</span></p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Nilai Komitmen</p>
            <p class="text-lg font-bold font-mono text-gray-900 mt-0.5">Rp {{ number_format($totalNilai, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Realisasi Bayar</p>
            <p class="text-lg font-bold font-mono text-emerald-600 mt-0.5">Rp {{ number_format($totalDibayar, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Sisa Hutang Berjalan</p>
            <p class="text-lg font-bold font-mono {{ $totalSisa > 0 ? 'text-rose-600' : 'text-gray-400' }} mt-0.5">
                Rp {{ number_format($totalSisa, 0, ',', '.') }}
            </p>
        </div>
    </div>

    <!-- Table Card -->
    <x-card title="Rekapitulasi Purchase Order & Liabilitas" subtitle="Daftar transaksi pengadaan bahan baku beserta status pembayaran termin" variant="primary">
        <div class="-mx-4 -my-4 overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider w-36">No. PO</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider">Supplier</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-center">Status PO</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Nilai Total</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Sudah Dibayar</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Sisa Tagihan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($pos as $po)
                    @php
                        $sisa = ($po->total_nilai ?? 0) - ($po->total_dibayar ?? 0);
                    @endphp
                    <tr class="hover:bg-primary-50/20 transition-colors {{ $loop->even ? 'bg-gray-50/40' : 'bg-white' }}">
                        <td class="px-3.5 py-2 font-mono font-medium text-primary-700">
                            @can('purchasing.view')
                                <a href="{{ route('purchasing.show', $po) }}" class="hover:underline">{{ $po->no_po }}</a>
                            @else
                                {{ $po->no_po }}
                            @endcan
                        </td>
                        <td class="px-3.5 py-2 font-medium text-gray-900">
                            {{ $po->supplier->nama }}
                        </td>
                        <td class="px-3.5 py-2 text-center">
                            @php
                                $badgeClass = match($po->status) {
                                    'draft' => 'bg-gray-100 text-gray-700 border-gray-200',
                                    'menunggu_approval' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'approved' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'dikirim_ke_gudang' => 'bg-purple-50 text-purple-700 border-purple-200',
                                    'selesai' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'dibatalkan' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    default => 'bg-gray-100 text-gray-700 border-gray-200'
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold border {{ $badgeClass }} uppercase tracking-wider">
                                {{ ucwords(str_replace('_', ' ', $po->status)) }}
                            </span>
                        </td>
                        <td class="px-3.5 py-2 text-right font-mono text-gray-900">
                            Rp {{ number_format($po->total_nilai, 0, ',', '.') }}
                        </td>
                        <td class="px-3.5 py-2 text-right font-mono text-emerald-700 font-medium">
                            Rp {{ number_format($po->total_dibayar ?? 0, 0, ',', '.') }}
                        </td>
                        <td class="px-3.5 py-2 text-right font-mono font-semibold {{ $sisa > 0 ? 'text-rose-600' : 'text-gray-400' }}">
                            Rp {{ number_format($sisa, 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-400">
                            <svg class="mx-auto h-7 w-7 text-gray-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Belum ada riwayat purchase order.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

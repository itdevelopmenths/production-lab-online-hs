<x-app-layout title="Laporan Produksi & Yield">
    <x-page-header
        title="Laporan Produksi & Yield"
        subtitle="Rekapitulasi batch manufaktur yang telah selesai, perbandingan output baik vs rusak, dan persentase yield"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Produksi' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('reports.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Pusat Laporan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalBatches = $batches->count();
        $totalBaik = $batches->sum('qty_baik');
        $totalRusak = $batches->sum('qty_rusak');
        $validYields = $batches->map(fn($b) => $b->yield())->filter(fn($y) => $y !== null);
        $avgYield = $validYields->isNotEmpty() ? $validYields->avg() : null;
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Batch Selesai</p>
            <p class="text-lg font-bold font-mono text-gray-900 mt-0.5">{{ number_format($totalBatches) }}</p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Output Baik</p>
            <p class="text-lg font-bold font-mono text-emerald-600 mt-0.5">{{ number_format($totalBaik, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Output Rusak</p>
            <p class="text-lg font-bold font-mono {{ $totalRusak > 0 ? 'text-rose-600' : 'text-gray-400' }} mt-0.5">{{ number_format($totalRusak, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rata-Rata Yield</p>
            <p class="text-lg font-bold font-mono text-primary-700 mt-0.5">
                {{ $avgYield !== null ? number_format($avgYield, 1) . '%' : '-' }}
            </p>
        </div>
    </div>

    <!-- Table Card -->
    <x-card title="Rekapitulasi Batch Produksi" subtitle="Menampilkan riwayat batch berstatus selesai" variant="primary">
        <div class="-mx-4 -my-4 overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider">No. Batch</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider">Produk Jadi</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider">Tanggal</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Rencana</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Output Baik</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Output Rusak</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-center">Capaian Yield</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($batches as $b)
                    @php
                        $yieldVal = $b->yield();
                    @endphp
                    <tr class="hover:bg-primary-50/20 transition-colors {{ $loop->even ? 'bg-gray-50/40' : 'bg-white' }}">
                        <td class="px-3.5 py-2 font-mono font-medium text-primary-700">
                            @can('batch.view')
                                <a href="{{ route('batches.show', $b) }}" class="hover:underline">{{ $b->no_batch }}</a>
                            @else
                                {{ $b->no_batch }}
                            @endcan
                        </td>
                        <td class="px-3.5 py-2 font-medium text-gray-900">{{ $b->produk->nama }}</td>
                        <td class="px-3.5 py-2 text-gray-600">{{ $b->tanggal->format('d/m/Y') }}</td>
                        <td class="px-3.5 py-2 text-right font-mono text-gray-800">{{ number_format($b->qty_rencana, 0, ',', '.') }}</td>
                        <td class="px-3.5 py-2 text-right font-mono text-emerald-700 font-semibold">{{ number_format($b->qty_baik, 0, ',', '.') }}</td>
                        <td class="px-3.5 py-2 text-right font-mono {{ $b->qty_rusak > 0 ? 'text-rose-600 font-semibold' : 'text-gray-400' }}">{{ number_format($b->qty_rusak, 0, ',', '.') }}</td>
                        <td class="px-3.5 py-2 text-center">
                            @if($yieldVal !== null)
                                @if($yieldVal >= 98)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        {{ number_format($yieldVal, 1) }}%
                                    </span>
                                @elseif($yieldVal >= 90)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        {{ number_format($yieldVal, 1) }}%
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        {{ number_format($yieldVal, 1) }}%
                                    </span>
                                @endif
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-400">
                            <svg class="mx-auto h-7 w-7 text-gray-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Belum ada batch produksi yang berstatus selesai.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

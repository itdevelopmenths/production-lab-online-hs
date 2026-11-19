<x-app-layout title="Laporan Produksi & Yield">
    <x-page-header
        title="Laporan Produksi & Yield"
        subtitle="Rekapitulasi batch manufaktur yang telah selesai, perbandingan output baik vs rusak, dan persentase yield"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Produksi' => null]"
        :backUrl="route('reports.index')"
    />

    @php
        $totalBatches = $batches->count();
        $totalBaik = $batches->sum('qty_baik');
        $totalRusak = $batches->sum('qty_rusak');
        $validYields = $batches->map(fn($b) => $b->yield())->filter(fn($y) => $y !== null);
        $avgYield = $validYields->isNotEmpty() ? $validYields->avg() : null;
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-box title="Total Batch Selesai" :value="number_format($totalBatches)" iconColor="gray" />
        <x-stat-box title="Total Output Baik" :value="number_format($totalBaik, 0, ',', '.')" iconColor="emerald" />
        <x-stat-box title="Total Output Rusak" :value="number_format($totalRusak, 0, ',', '.')" :iconColor="$totalRusak > 0 ? 'rose' : 'gray'" />
        <x-stat-box title="Rata-Rata Yield" :value="$avgYield !== null ? number_format($avgYield, 1) . '%' : '-'" iconColor="blue" />
    </div>

    <!-- Table Card -->
    <x-card title="Rekapitulasi Batch Produksi" subtitle="Menampilkan riwayat batch berstatus selesai" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Rekapitulasi Batch Produksi dan Capaian Yield" class="overflow-x-auto focus:outline-none focus:ring-1 focus:ring-[#111111]/20 dark:focus:ring-white/20">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[13px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-3">No. Batch</th>
                        <th class="px-4 py-3">Produk Jadi</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3 text-right">Rencana</th>
                        <th class="px-4 py-3 text-right">Output Baik</th>
                        <th class="px-4 py-3 text-right">Output Rusak</th>
                        <th class="px-4 py-3 text-center">Capaian Yield</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                    @forelse($batches as $b)
                    @php
                        $yieldVal = $b->yield();
                        $produkLabel = $b->outputs->isNotEmpty()
                            ? $b->outputs->map(fn($o) => $o->produk?->nama)->filter()->join(', ')
                            : ($b->produk?->nama ?? '—');
                        $qtyRencana = $b->outputs->isNotEmpty() && $b->outputs->sum('qty_rencana') > 0
                            ? $b->outputs->sum('qty_rencana')
                            : $b->qty_rencana;
                        $yieldVariant = $yieldVal === null ? 'neutral' : ($yieldVal >= 98 ? 'success' : ($yieldVal >= 90 ? 'warning' : 'danger'));
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
                        <td class="px-4 py-3 text-[#667085] dark:text-gray-400">{{ $b->tanggal->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-[#101828] dark:text-white">{{ number_format($qtyRencana, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-foreground dark:text-foreground font-medium">{{ number_format($b->qty_baik, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums {{ $b->qty_rusak > 0 ? 'text-rose-600 dark:text-rose-400 font-medium' : 'text-[#667085] dark:text-gray-400' }}">{{ number_format($b->qty_rusak, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($yieldVal !== null)
                                <x-badge :variant="$yieldVariant" size="xs" :pill="true">{{ number_format($yieldVal, 1) }}%</x-badge>
                            @else
                                <span class="text-[#667085] dark:text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-xs text-[#667085] dark:text-gray-400">
                            <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Belum ada batch produksi yang berstatus selesai.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

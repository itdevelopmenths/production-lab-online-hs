<x-app-layout title="Laporan Defect & Kerusakan">
    <x-page-header
        title="Laporan Defect & Kerusakan Mutu"
        subtitle="Monitoring batch produksi yang mengalami unit rusak/cacat dan evaluasi tingkat defect rate"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Defect' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('reports.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Pusat Laporan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalBatches = $batches->count();
        $totalRusak = $batches->sum('qty_rusak');
        $validDefects = $batches->map(fn($b) => $b->defectRate())->filter(fn($d) => $d !== null);
        $avgDefect = $validDefects->isNotEmpty() ? $validDefects->avg() : null;
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Batch Mengalami Defect</p>
            <p class="text-lg font-bold font-mono text-gray-900 mt-0.5">{{ number_format($totalBatches) }} <span class="text-xs font-normal text-gray-500">Batch</span></p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Unit Rusak / Reject</p>
            <p class="text-lg font-bold font-mono text-rose-600 mt-0.5">{{ number_format($totalRusak, 0, ',', '.') }} <span class="text-xs font-normal text-gray-500">Pcs</span></p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Rata-Rata Defect Rate</p>
            <p class="text-lg font-bold font-mono text-rose-700 mt-0.5">
                {{ $avgDefect !== null ? number_format($avgDefect, 1) . '%' : '0.0%' }}
            </p>
        </div>
    </div>

    <!-- Table Card -->
    <x-card title="Rincian Batch Produksi Terdampak Defect" subtitle="Daftar batch selesai yang mencatatkan kuantitas unit rusak lebih dari nol" variant="primary">
        <div class="-mx-4 -my-4 overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider w-40">No. Batch</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider">Produk Jadi</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Output Baik</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Output Rusak</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-center">Tingkat Defect</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($batches as $b)
                    @php
                        $defect = $b->defectRate();
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
                        <td class="px-3.5 py-2 text-right font-mono text-gray-800">{{ number_format($b->qty_baik, 0, ',', '.') }}</td>
                        <td class="px-3.5 py-2 text-right font-mono text-rose-600 font-semibold">{{ number_format($b->qty_rusak, 0, ',', '.') }}</td>
                        <td class="px-3.5 py-2 text-center">
                            @if($defect !== null)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                    {{ number_format($defect, 1) }}%
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-400">
                            <svg class="mx-auto h-7 w-7 text-emerald-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Kualitas sempurna! Tidak ditemukan batch produksi dengan unit rusak.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

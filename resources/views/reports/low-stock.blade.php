<x-app-layout title="Variance Pemakaian (Stock Opname)">
    <x-page-header
        title="Variance Pemakaian (Stock Opname)"
        subtitle="Analisis deviasi perbandingan antara formula standar teoritis BOM vs realisasi aktual di lab"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Variance Opname' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('reports.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Pusat Laporan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalOpname = $opname->count();
        $borosCount = $opname->filter(fn($o) => $o->variance() > 0)->count();
        $hematCount = $opname->filter(fn($o) => $o->variance() <= 0)->count();
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Pemeriksaan Opname</p>
            <p class="text-lg font-bold font-mono text-gray-900 mt-0.5">{{ number_format($totalOpname) }} <span class="text-xs font-normal text-gray-500">Record</span></p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Selisih Lebih (Pemborosan)</p>
            <p class="text-lg font-bold font-mono {{ $borosCount > 0 ? 'text-rose-600' : 'text-gray-400' }} mt-0.5">
                {{ number_format($borosCount) }} <span class="text-xs font-normal text-gray-500">Batch</span>
            </p>
        </div>
        <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Sesuai / Efisien (<= 0)</p>
            <p class="text-lg font-bold font-mono text-emerald-600 mt-0.5">
                {{ number_format($hematCount) }} <span class="text-xs font-normal text-gray-500">Batch</span>
            </p>
        </div>
    </div>

    <!-- Table Card -->
    <x-card title="Data Deviasi Pemakaian Bahan Baku" subtitle="Daftar rekapitulasi audit opname bahan dari batch produksi" variant="primary">
        <div class="-mx-4 -my-4 overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider w-36">No. Batch</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider">Nama Bahan / Material</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Teoritis (BOM)</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Realisasi Aktual</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider text-right">Deviasi Variance</th>
                        <th class="px-3.5 py-2.5 font-semibold text-gray-600 uppercase tracking-wider">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($opname as $o)
                    @php
                        $var = $o->variance();
                    @endphp
                    <tr class="hover:bg-primary-50/20 transition-colors {{ $loop->even ? 'bg-gray-50/40' : 'bg-white' }}">
                        <td class="px-3.5 py-2 font-mono font-medium text-primary-700">
                            {{ $o->batch->no_batch ?? '-' }}
                        </td>
                        <td class="px-3.5 py-2 font-medium text-gray-900">
                            {{ $o->bahan->nama ?? '-' }}
                        </td>
                        <td class="px-3.5 py-2 text-right font-mono text-gray-700">
                            {{ number_format($o->pemakaian_teoritis, 2, ',', '.') }}
                        </td>
                        <td class="px-3.5 py-2 text-right font-mono text-gray-900 font-medium">
                            {{ number_format($o->pemakaian_aktual, 2, ',', '.') }}
                        </td>
                        <td class="px-3.5 py-2 text-right font-mono font-semibold">
                            @if($var > 0)
                                <span class="text-rose-600">+{{ number_format($var, 2, ',', '.') }}</span>
                            @elseif($var < 0)
                                <span class="text-emerald-600">{{ number_format($var, 2, ',', '.') }}</span>
                            @else
                                <span class="text-gray-400">0,00</span>
                            @endif
                        </td>
                        <td class="px-3.5 py-2 text-gray-500 text-xs">
                            {{ $o->keterangan ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-400">
                            <svg class="mx-auto h-7 w-7 text-gray-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Belum ada catatan audit variance opname.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

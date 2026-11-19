<x-app-layout title="Laporan Lead Time Supplier">
    <x-page-header
        title="Laporan Lead Time Supplier"
        subtitle="Analisis performa durasi pengiriman supplier 9-stage, deviasi per produk, dan jejak histori transaksi PO"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Lead Time Supplier' => null]"
        :backUrl="route('reports.index')"
    />

    {{-- Filter Toolbar --}}
    <form method="GET" action="{{ route('reports.lead-time') }}" class="mb-6 bg-white dark:bg-gray-900 p-4 rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)] flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="filter-supplier" class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Mitra Supplier</label>
                <select name="supplier_id" id="filter-supplier" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-40 max-w-full outline-none cursor-pointer rounded-[6px]">
                    <option value="">— Semua Supplier —</option>
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}" @selected($supplierId == $sup->id)>
                            {{ $sup->nama }} ({{ ucfirst($sup->kategori ?? 'Lokal') }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-kategori" class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Kategori Supplier</label>
                <select name="kategori" id="filter-kategori" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-36 max-w-full outline-none cursor-pointer rounded-[6px]">
                    <option value="">— Semua Kategori —</option>
                    <option value="lokal" @selected($kategori === 'lokal')>Supplier Lokal</option>
                    <option value="impor" @selected($kategori === 'impor')>Supplier Impor</option>
                </select>
            </div>

            <div class="pt-5">
                <x-button type="submit" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    </x-slot:icon>
                    Terapkan Filter
                </x-button>
            </div>
        </div>

        <div>
            <a href="{{ route('reports.lead-time') }}" class="text-xs text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white font-medium transition-colors">
                Reset Filter
            </a>
        </div>
    </form>

    {{-- KPI Lead Time Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-box
            title="Rata-Rata Lead Time"
            :value="number_format($avgLtHari, 1) . ' Hari'"
            :subtitle="'≈ ' . number_format($avgLtJam, 1) . ' Jam Operasional'"
            iconColor="gray"
        />
        <x-stat-box
            title="Supplier Tercepat"
            :value="$fastestSupplier['supplier_nama'] ?? '—'"
            :subtitle="$fastestSupplier ? number_format($fastestSupplier['average_lt_hari'], 1) . ' Hari (' . number_format($fastestSupplier['average_lt_jam'], 1) . ' Jam)' : 'Belum ada data'"
            iconColor="emerald"
        />
        <x-stat-box
            title="Supplier Terlama"
            :value="$slowestSupplier['supplier_nama'] ?? '—'"
            :subtitle="$slowestSupplier ? number_format($slowestSupplier['average_lt_hari'], 1) . ' Hari (' . number_format($slowestSupplier['average_lt_jam'], 1) . ' Jam)' : 'Belum ada data'"
            iconColor="amber"
        />
        <x-stat-box
            title="Histori PO & Validitas"
            :value="number_format($totalHistory) . ' PO'"
            :subtitle="$totalValid . ' Valid · ' . $totalInvalid . ' Diinvalidasi'"
            iconColor="blue"
        />
    </div>

    {{-- Level-1: Tabel Ringkasan Supplier & 9-Stage Breakdown --}}
    <div class="space-y-6 mb-8">
        <x-card title="Level-1: Ringkasan Performa Lead Time Supplier & 9-Stage Breakdown" subtitle="Rata-rata durasi aktual pengiriman per supplier beserta rincian akumulasi pada 9 tahapan alur PO" :noPadding="true">
            <x-slot:icon>
                <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </x-slot:icon>
            <div tabindex="0" role="region" aria-label="Tabel Ringkasan Lead Time Level-1 Supplier dan Breakdown 9 Stage" class="overflow-x-auto focus:outline-none focus:ring-1 focus:ring-[#111111]/20 dark:focus:ring-white/20">
                <table class="w-full text-sm text-left border-collapse min-w-[900px]">
                    <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[13px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                        <tr>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3 text-center">Kategori</th>
                            <th class="px-4 py-3 text-right">PO Selesai</th>
                            <th class="px-4 py-3 text-right">Rata-Rata (Hari)</th>
                            <th class="px-4 py-3 text-right">Rata-Rata (Jam)</th>
                            <th class="px-4 py-3 text-right">Maks (Hari)</th>
                            <th class="px-4 py-3 text-left min-w-72">Breakdown 9 Stage (Avg Hari)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                        @forelse($supplierSummaries as $s)
                        @php
                            $stages = $stageSummaries->get($s['supplier_id'], collect());
                        @endphp
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors">
                            <td class="px-4 py-3 font-medium text-[#101828] dark:text-white">
                                {{ $s['supplier_nama'] }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-badge :variant="$s['kategori'] === 'impor' ? 'purple' : 'success'" size="xs" :pill="true">
                                    {{ strtoupper($s['kategori']) }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-[#101828] dark:text-white">
                                {{ number_format($s['jumlah_transaksi']) }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-[#101828] dark:text-white">
                                {{ number_format($s['average_lt_hari'], 1) }} d
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-[#667085] dark:text-gray-400">
                                {{ number_format($s['average_lt_jam'], 1) }} h
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-[#667085] dark:text-gray-400">
                                {{ number_format($s['max_lt_hari'], 1) }} d
                            </td>
                            <td class="px-4 py-3">
                                @if($stages->isNotEmpty())
                                    <div class="grid grid-cols-3 gap-1.5 text-[10px]">
                                        @foreach($stages as $stg)
                                            <div class="p-1.5 rounded-md bg-[#F5F5F5] dark:bg-gray-800 border border-[#E8E8E8] dark:border-gray-700">
                                                <span class="text-[#667085] dark:text-gray-400 tabular-nums font-medium">{{ $stg->stage }}:</span>
                                                <span class="font-medium text-[#101828] dark:text-white tabular-nums">{{ number_format($stg->average_hari, 1) }}d</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-[#667085] dark:text-gray-400 italic text-[11px]">Belum ada breakdown tahapan stage</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                Tidak ada data ringkasan lead time supplier yang sesuai filter.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Level-2: Tabel Supplier - SKU Produk --}}
        <x-card title="Level-2: Rata-Rata Lead Time per Pasangan Supplier & SKU Produk" subtitle="Evaluasi granular durasi lead time pengadaan untuk setiap komoditas barang dari masing-masing supplier" :noPadding="true">
            <x-slot:icon>
                <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </x-slot:icon>
            <div tabindex="0" role="region" aria-label="Tabel Ringkasan Lead Time Level-2 Supplier dan SKU Produk" class="overflow-x-auto focus:outline-none focus:ring-1 focus:ring-[#111111]/20 dark:focus:ring-white/20">
                <table class="w-full text-sm text-left border-collapse min-w-[760px]">
                    <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[13px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                        <tr>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3">Nama Produk / Bahan</th>
                            <th class="px-4 py-3 text-right">PO Selesai</th>
                            <th class="px-4 py-3 text-right">Rata-Rata (Hari)</th>
                            <th class="px-4 py-3 text-right">Rata-Rata (Jam)</th>
                            <th class="px-4 py-3 text-right">Maks (Hari)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                        @forelse($produkSummaries as $sp)
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors">
                            <td class="px-4 py-3 font-medium text-[#101828] dark:text-white">
                                {{ $sp['supplier_nama'] }}
                            </td>
                            <td class="px-4 py-3 tabular-nums text-[#667085] dark:text-gray-400">
                                {{ $sp['sku'] }}
                            </td>
                            <td class="px-4 py-3 font-medium text-[#101828] dark:text-white">
                                {{ $sp['nama_produk'] }}
                                @if(!empty($sp['satuan']))
                                    <span class="text-[11px] text-[#667085] dark:text-gray-400 tabular-nums">({{ $sp['satuan'] }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-[#101828] dark:text-white">
                                {{ number_format($sp['jumlah_transaksi']) }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-[#101828] dark:text-white">
                                {{ number_format($sp['average_lt_hari'], 1) }} d
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-[#667085] dark:text-gray-400">
                                {{ number_format($sp['average_lt_jam'], 1) }} h
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-[#667085] dark:text-gray-400">
                                {{ number_format($sp['max_lt_hari'], 1) }} d
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                Tidak ada data lead time tingkat produk untuk kriteria filter ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Tabel Histori Transaksi PoLeadTime --}}
        <x-card title="Buku Audit Transaksi Lead Time Purchase Order" subtitle="Histori lengkap pencatatan lead time transaksi PO beserta status validitas perhitungan KPI" :noPadding="true">
            <x-slot:icon>
                <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </x-slot:icon>
            <div tabindex="0" role="region" aria-label="Tabel Histori Transaksi Lead Time Purchase Order" class="overflow-x-auto focus:outline-none focus:ring-1 focus:ring-[#111111]/20 dark:focus:ring-white/20">
                <table class="w-full text-sm text-left border-collapse min-w-[950px]">
                    <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[13px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                        <tr>
                            <th class="px-4 py-3">No. PO</th>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3">Waktu Mulai &rarr; Selesai</th>
                            <th class="px-4 py-3 text-right">Total LT</th>
                            <th class="px-4 py-3 text-center">Breakdown Domain</th>
                            <th class="px-4 py-3 text-center">Status Validitas</th>
                            <th class="px-4 py-3">Catatan / Validator</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                        @forelse($transaksiList as $lt)
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors">
                            <td class="px-4 py-3 tabular-nums font-medium text-[#101828] dark:text-white">
                                @can('purchasing.view')
                                    <a href="{{ route('purchasing.show', $lt->po_id) }}" class="underline decoration-gray-400 hover:decoration-current">
                                        {{ $lt->purchaseOrder?->no_po ?? 'PO#' . substr($lt->po_id, 0, 8) }}
                                    </a>
                                @else
                                    {{ $lt->purchaseOrder?->no_po ?? 'PO#' . substr($lt->po_id, 0, 8) }}
                                @endcan
                            </td>
                            <td class="px-4 py-3 font-medium text-[#101828] dark:text-white">
                                {{ $lt->supplier?->nama ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-[#667085] dark:text-gray-400 tabular-nums text-xs">
                                {{ $lt->waktu_mulai?->format('d/m/Y H:i') ?? '—' }} &rarr; {{ $lt->waktu_selesai?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-[#101828] dark:text-white">
                                {{ number_format((float) $lt->total_lt_hari, 1) }} d
                                <span class="text-[11px] text-[#667085] dark:text-gray-400 font-normal">({{ number_format((float) \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($lt->total_lt_menit), 1) }}h)</span>
                            </td>
                            <td class="px-4 py-3 text-center tabular-nums text-[10px]">
                                <span class="text-foreground dark:text-foreground bg-card dark:bg-card px-1.5 py-0.5 rounded border border-border dark:border-border" title="Internal LT">Int: {{ number_format((float) \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($lt->lt_internal_menit), 1) }}d</span>
                                <span class="text-foreground dark:text-foreground bg-card dark:bg-card px-1.5 py-0.5 rounded border border-border dark:border-border" title="Supplier LT">Sup: {{ number_format((float) \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($lt->lt_supplier_menit), 1) }}d</span>
                                <span class="text-foreground dark:text-foreground bg-card dark:bg-card px-1.5 py-0.5 rounded border border-border dark:border-border" title="Logistik LT">Log: {{ number_format((float) \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($lt->lt_logistik_menit), 1) }}d</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($lt->is_valid)
                                    <x-badge variant="success" size="xs" :pill="true">VALID</x-badge>
                                @else
                                    <x-badge variant="danger" size="xs" :pill="true">DIINVALIDASI</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-[#667085] dark:text-gray-400">
                                @if(! $lt->is_valid && $lt->alasan_tidak_valid)
                                    <div class="text-rose-600 dark:text-rose-400 font-medium">{{ $lt->alasan_tidak_valid }}</div>
                                @endif
                                @if($lt->validator)
                                    <div class="text-[11px] text-[#667085] dark:text-gray-400">Oleh: {{ $lt->validator->name }}</div>
                                @endif
                                @if($lt->is_valid && ! $lt->alasan_tidak_valid && ! $lt->validator)
                                    <span class="text-[#667085] dark:text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                Belum ada riwayat transaksi lead time yang tercatat.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-app-layout>

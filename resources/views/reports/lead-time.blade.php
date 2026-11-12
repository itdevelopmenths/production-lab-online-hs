<x-app-layout title="Laporan Lead Time Supplier">
    <x-page-header
        title="Laporan Lead Time Supplier"
        subtitle="Analisis performa durasi pengiriman supplier 9-stage, deviasi per produk, dan jejak histori transaksi PO"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Lead Time Supplier' => null]"
        :backUrl="route('reports.index')"
    />

    {{-- Filter Toolbar --}}
    <form method="GET" action="{{ route('reports.lead-time') }}" class="mb-6 bg-card p-4 rounded-xl border border-border shadow-xs flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="filter-supplier" class="block text-xs font-medium text-muted-foreground mb-1">Mitra Supplier</label>
                <select name="supplier_id" id="filter-supplier" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground w-full sm:w-auto min-w-40 max-w-full outline-none cursor-pointer">
                    <option value="">— Semua Supplier —</option>
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}" @selected($supplierId == $sup->id)>
                            {{ $sup->nama }} ({{ ucfirst($sup->kategori ?? 'Lokal') }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-kategori" class="block text-xs font-medium text-muted-foreground mb-1">Kategori Supplier</label>
                <select name="kategori" id="filter-kategori" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground w-full sm:w-auto min-w-36 max-w-full outline-none cursor-pointer">
                    <option value="">— Semua Kategori —</option>
                    <option value="lokal" @selected($kategori === 'lokal')>🇮🇩 Supplier Lokal</option>
                    <option value="impor" @selected($kategori === 'impor')>🌐 Supplier Impor</option>
                </select>
            </div>

            <div class="pt-5">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Terapkan Filter
                </button>
            </div>
        </div>

        <div>
            <a href="{{ route('reports.lead-time') }}" class="text-xs text-muted-foreground hover:text-foreground font-medium transition-colors">
                Reset Filter
            </a>
        </div>
    </form>

    {{-- KPI Lead Time Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Rata-Rata Lead Time</p>
            <p class="text-xl font-bold font-mono text-primary mt-1">
                {{ number_format($avgLtHari, 1) }} <span class="text-xs font-normal text-muted-foreground">Hari</span>
            </p>
            <p class="text-[11px] text-muted-foreground mt-0.5">≈ {{ number_format($avgLtJam, 1) }} Jam Operasional</p>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Supplier Tercepat</p>
            <p class="text-base font-bold text-emerald-600 truncate mt-1" title="{{ $fastestSupplier['supplier_nama'] ?? '—' }}">
                {{ $fastestSupplier['supplier_nama'] ?? '—' }}
            </p>
            <p class="text-[11px] text-muted-foreground mt-0.5 font-mono">
                @if($fastestSupplier)
                    {{ number_format($fastestSupplier['average_lt_hari'], 1) }} Hari ({{ number_format($fastestSupplier['average_lt_jam'], 1) }} Jam)
                @else
                    Belum ada data
                @endif
            </p>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Supplier Terlama</p>
            <p class="text-base font-bold text-amber-600 truncate mt-1" title="{{ $slowestSupplier['supplier_nama'] ?? '—' }}">
                {{ $slowestSupplier['supplier_nama'] ?? '—' }}
            </p>
            <p class="text-[11px] text-muted-foreground mt-0.5 font-mono">
                @if($slowestSupplier)
                    {{ number_format($slowestSupplier['average_lt_hari'], 1) }} Hari ({{ number_format($slowestSupplier['average_lt_jam'], 1) }} Jam)
                @else
                    Belum ada data
                @endif
            </p>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Histori PO & Validitas</p>
            <p class="text-xl font-bold font-mono text-foreground mt-1">
                {{ number_format($totalHistory) }} <span class="text-xs font-normal text-muted-foreground">PO</span>
            </p>
            <p class="text-[11px] text-muted-foreground mt-0.5">
                <span class="text-emerald-600 font-semibold">{{ $totalValid }} Valid</span> ·
                <span class="{{ $totalInvalid > 0 ? 'text-destructive font-semibold' : 'text-muted-foreground' }}">{{ $totalInvalid }} Diinvalidasi</span>
            </p>
        </div>
    </div>

    {{-- Level-1: Tabel Ringkasan Supplier & 9-Stage Breakdown --}}
    <div class="space-y-6 mb-8">
        <x-card title="Level-1: Ringkasan Performa Lead Time Supplier & 9-Stage Breakdown" subtitle="Rata-rata durasi aktual pengiriman per supplier beserta rincian akumulasi pada 9 tahapan alur PO">
            <div tabindex="0" role="region" aria-label="Tabel Ringkasan Lead Time Level-1 Supplier dan Breakdown 9 Stage" class="-mx-4 -my-4 overflow-x-auto focus:outline-none focus:ring-1 focus:ring-primary/30">
                <table class="w-full text-xs text-left border-collapse min-w-[900px]">
                    <thead class="bg-muted/70 text-muted-foreground uppercase text-[11px] font-semibold tracking-wider border-b border-border">
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
                    <tbody class="divide-y divide-border">
                        @forelse($supplierSummaries as $s)
                        @php
                            $stages = $stageSummaries->get($s['supplier_id'], collect());
                        @endphp
                        <tr class="hover:bg-muted/40 transition-colors">
                            <td class="px-4 py-3 font-semibold text-foreground">
                                {{ $s['supplier_nama'] }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold border {{ $s['kategori'] === 'impor' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' }} uppercase">
                                    {{ $s['kategori'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-foreground">
                                {{ number_format($s['jumlah_transaksi']) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-primary">
                                {{ number_format($s['average_lt_hari'], 1) }} d
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-muted-foreground">
                                {{ number_format($s['average_lt_jam'], 1) }} h
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-muted-foreground">
                                {{ number_format($s['max_lt_hari'], 1) }} d
                            </td>
                            <td class="px-4 py-3">
                                @if($stages->isNotEmpty())
                                    <div class="grid grid-cols-3 gap-1.5 text-[10px]">
                                        @foreach($stages as $stg)
                                            <div class="p-1 rounded bg-muted/50 border border-border/60">
                                                <span class="text-muted-foreground font-mono font-semibold">{{ $stg->stage }}:</span>
                                                <span class="font-bold text-foreground font-mono">{{ number_format($stg->average_hari, 1) }}d</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted-foreground italic text-[11px]">Belum ada breakdown tahapan stage</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-muted-foreground text-xs">
                                Tidak ada data ringkasan lead time supplier yang sesuai filter.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Level-2: Tabel Supplier - SKU Produk --}}
        <x-card title="Level-2: Rata-Rata Lead Time per Pasangan Supplier & SKU Produk" subtitle="Evaluasi granular durasi lead time pengadaan untuk setiap komoditas barang dari masing-masing supplier">
            <div tabindex="0" role="region" aria-label="Tabel Ringkasan Lead Time Level-2 Supplier dan SKU Produk" class="-mx-4 -my-4 overflow-x-auto focus:outline-none focus:ring-1 focus:ring-primary/30">
                <table class="w-full text-xs text-left border-collapse min-w-[760px]">
                    <thead class="bg-muted/70 text-muted-foreground uppercase text-[11px] font-semibold tracking-wider border-b border-border">
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
                    <tbody class="divide-y divide-border">
                        @forelse($produkSummaries as $sp)
                        <tr class="hover:bg-muted/40 transition-colors">
                            <td class="px-4 py-2.5 font-medium text-foreground">
                                {{ $sp['supplier_nama'] }}
                            </td>
                            <td class="px-4 py-2.5 font-mono text-muted-foreground">
                                {{ $sp['sku'] }}
                            </td>
                            <td class="px-4 py-2.5 font-medium text-foreground">
                                {{ $sp['nama_produk'] }}
                                @if(!empty($sp['satuan']))
                                    <span class="text-[10px] text-muted-foreground font-mono">({{ $sp['satuan'] }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-foreground">
                                {{ number_format($sp['jumlah_transaksi']) }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono font-bold text-primary">
                                {{ number_format($sp['average_lt_hari'], 1) }} d
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-muted-foreground">
                                {{ number_format($sp['average_lt_jam'], 1) }} h
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-muted-foreground">
                                {{ number_format($sp['max_lt_hari'], 1) }} d
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-muted-foreground text-xs">
                                Tidak ada data lead time tingkat produk untuk kriteria filter ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Tabel Histori Transaksi PoLeadTime --}}
        <x-card title="Buku Audit Transaksi Lead Time Purchase Order" subtitle="Histori lengkap pencatatan lead time transaksi PO beserta status validitas perhitungan KPI">
            <div tabindex="0" role="region" aria-label="Tabel Histori Transaksi Lead Time Purchase Order" class="-mx-4 -my-4 overflow-x-auto focus:outline-none focus:ring-1 focus:ring-primary/30">
                <table class="w-full text-xs text-left border-collapse min-w-[950px]">
                    <thead class="bg-muted/70 text-muted-foreground uppercase text-[11px] font-semibold tracking-wider border-b border-border">
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
                    <tbody class="divide-y divide-border">
                        @forelse($transaksiList as $lt)
                        <tr class="hover:bg-muted/40 transition-colors">
                            <td class="px-4 py-2.5 font-mono font-medium text-foreground">
                                @can('purchasing.view')
                                    <a href="{{ route('purchasing.show', $lt->po_id) }}" class="text-primary hover:underline font-semibold">
                                        {{ $lt->purchaseOrder?->no_po ?? 'PO#' . substr($lt->po_id, 0, 8) }}
                                    </a>
                                @else
                                    {{ $lt->purchaseOrder?->no_po ?? 'PO#' . substr($lt->po_id, 0, 8) }}
                                @endcan
                            </td>
                            <td class="px-4 py-2.5 font-medium text-foreground">
                                {{ $lt->supplier?->nama ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-muted-foreground font-mono text-[11px]">
                                {{ $lt->waktu_mulai?->format('d/m/Y H:i') ?? '—' }} &rarr; {{ $lt->waktu_selesai?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono font-bold text-foreground">
                                {{ number_format((float) $lt->total_lt_hari, 1) }} d
                                <span class="text-[10px] text-muted-foreground font-normal">({{ number_format((float) \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($lt->total_lt_menit), 1) }}h)</span>
                            </td>
                            <td class="px-4 py-2.5 text-center font-mono text-[10px]">
                                <span class="text-blue-700 bg-blue-50 px-1 py-0.5 rounded border border-blue-200" title="Internal LT">Int: {{ number_format((float) \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($lt->lt_internal_menit), 1) }}d</span>
                                <span class="text-amber-700 bg-amber-50 px-1 py-0.5 rounded border border-amber-200" title="Supplier LT">Sup: {{ number_format((float) \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($lt->lt_supplier_menit), 1) }}d</span>
                                <span class="text-purple-700 bg-purple-50 px-1 py-0.5 rounded border border-purple-200" title="Logistik LT">Log: {{ number_format((float) \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($lt->lt_logistik_menit), 1) }}d</span>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                @if($lt->is_valid)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        VALID
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        DIINVALIDASI
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-[11px] text-muted-foreground">
                                @if(! $lt->is_valid && $lt->alasan_tidak_valid)
                                    <div class="text-rose-600 font-medium">{{ $lt->alasan_tidak_valid }}</div>
                                @endif
                                @if($lt->validator)
                                    <div class="text-[10px] text-muted-foreground">Oleh: {{ $lt->validator->name }}</div>
                                @endif
                                @if($lt->is_valid && ! $lt->alasan_tidak_valid && ! $lt->validator)
                                    <span class="text-muted-foreground">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-muted-foreground text-xs">
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

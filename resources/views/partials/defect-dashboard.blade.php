@can('defect.view')
@if(!empty($defectAnalytics))
{{-- DASHBOARD WIDGET: REKAPITULASI BARANG RUSAK / DEFECT PER BULAN PER GUDANG --}}
<div x-data="{ viewMode: 'matrix' }" class="space-y-4">
    <x-card class="border-border shadow-2xs" :noPadding="true">
        <x-slot:header>
            <div class="flex flex-col lg:flex-row lg:items-center justify-between w-full gap-3 py-1">
                <div class="flex items-center gap-3">
                    <div class="size-8 rounded-lg bg-muted text-foreground flex items-center justify-center border border-border shrink-0">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-semibold text-foreground tracking-tight">Dashboard Barang Rusak & Defect Mutasi</h2>
                            <x-badge variant="neutral" size="sm">Audit Logistik</x-badge>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">Akumulasi barang rusak per bulan per fasilitas gudang dari penerimaan transfer fisik</p>
                    </div>
                </div>

                {{-- Filter Toolbar --}}
                <form method="GET" action="{{ $formAction ?? request()->url() }}" class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center rounded-md border border-border bg-card px-2 py-1 text-xs">
                        <span class="text-muted-foreground mr-1.5 font-medium">Tahun:</span>
                        <select name="year" class="bg-transparent text-xs font-semibold text-foreground outline-none cursor-pointer">
                            @foreach(range(date('Y') - 2, date('Y') + 1) as $yr)
                                <option value="{{ $yr }}" {{ ($year ?? date('Y')) == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="inline-flex items-center rounded-md border border-border bg-card px-2 py-1 text-xs">
                        <span class="text-muted-foreground mr-1.5 font-medium">Bulan:</span>
                        <select name="month" class="bg-transparent text-xs font-semibold text-foreground outline-none cursor-pointer">
                            <option value="">Semua Bulan (12 Bulan)</option>
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ ($month ?? null) == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="inline-flex items-center rounded-md border border-border bg-card px-2 py-1 text-xs">
                        <span class="text-muted-foreground mr-1.5 font-medium">Gudang:</span>
                        <select name="gudang_id" class="bg-transparent text-xs font-semibold text-foreground outline-none cursor-pointer">
                            <option value="">Semua Fasilitas</option>
                            @foreach($warehouses ?? [] as $wh)
                                <option value="{{ $wh->id }}" {{ ($gudangId ?? null) == $wh->id ? 'selected' : '' }}>{{ $wh->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="h-7 px-3 rounded-md bg-primary text-primary-foreground text-xs font-medium hover:bg-primary/90 transition shadow-2xs cursor-pointer inline-flex items-center gap-1.5">
                        <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filter
                    </button>

                    @if(!empty($month) || !empty($gudangId))
                        <a href="{{ $formAction ?? request()->url() }}?year={{ $year ?? date('Y') }}" class="h-7 px-2.5 rounded-md border border-border text-xs text-muted-foreground hover:text-foreground hover:bg-muted flex items-center transition-colors">
                            Reset
                        </a>
                    @endif
                </form>
            </div>
        </x-slot:header>

        <div class="p-4 space-y-4">
            {{-- 5 High-Density Stat Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="p-3 rounded-lg bg-card border border-border shadow-2xs flex flex-col justify-between">
                    <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Total Selesai</span>
                    <div class="text-xl font-bold font-mono tabular-nums text-foreground mt-1">{{ number_format($defectAnalytics['summary']['total_transfers'] ?? 0) }}</div>
                    <span class="text-[11px] text-muted-foreground mt-0.5">Transaksi mutasi</span>
                </div>

                <div class="p-3 rounded-lg bg-card border border-border shadow-2xs flex flex-col justify-between">
                    <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Total Dikirim</span>
                    <div class="text-xl font-bold font-mono tabular-nums text-foreground mt-1">{{ number_format($defectAnalytics['summary']['total_qty_dikirim'] ?? 0, 2) }}</div>
                    <span class="text-[11px] text-muted-foreground mt-0.5">Volume mutasi fisik</span>
                </div>

                <div class="p-3 rounded-lg bg-card border border-border shadow-2xs flex flex-col justify-between">
                    <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Total Barang Rusak</span>
                    <div class="text-xl font-bold font-mono tabular-nums mt-1 {{ ($defectAnalytics['summary']['total_qty_rusak'] ?? 0) > 0 ? 'text-destructive' : 'text-foreground' }}">
                        {{ number_format($defectAnalytics['summary']['total_qty_rusak'] ?? 0, 2) }}
                    </div>
                    <span class="text-[11px] text-muted-foreground mt-0.5">Penerimaan rusak</span>
                </div>

                <div class="p-3 rounded-lg bg-card border border-border shadow-2xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Defect Rate</span>
                        @if(($defectAnalytics['summary']['defect_rate_percent'] ?? 0) > 0)
                            <span class="size-1.5 rounded-full bg-destructive animate-pulse"></span>
                        @endif
                    </div>
                    <div class="text-xl font-bold font-mono tabular-nums mt-1 {{ ($defectAnalytics['summary']['defect_rate_percent'] ?? 0) > 0 ? 'text-destructive' : 'text-foreground' }}">
                        {{ number_format($defectAnalytics['summary']['defect_rate_percent'] ?? 0, 2) }}%
                    </div>
                    <span class="text-[11px] text-muted-foreground mt-0.5">Rasio rusak vs kirim</span>
                </div>

                <div class="p-3 rounded-lg bg-card border border-border shadow-2xs col-span-2 md:col-span-1 flex flex-col justify-between">
                    <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Gudang Terdampak</span>
                    <div class="text-sm font-bold text-foreground mt-1 truncate" title="{{ $defectAnalytics['summary']['gudang_tertinggi_nama'] ?? '—' }}">
                        {{ $defectAnalytics['summary']['gudang_tertinggi_nama'] ?? '—' }}
                    </div>
                    <span class="text-[11px] text-muted-foreground mt-0.5">
                        @if(($defectAnalytics['summary']['gudang_tertinggi_qty'] ?? 0) > 0)
                            <strong class="text-destructive font-mono">{{ number_format($defectAnalytics['summary']['gudang_tertinggi_qty'], 2) }}</strong> unit rusak
                        @else
                            Nihil laporan kerusakan
                        @endif
                    </span>
                </div>
            </div>

            {{-- Segmented View Mode Tabs --}}
            <div class="flex items-center justify-between pt-2 border-t border-border/80">
                <div class="inline-flex items-center p-0.5 rounded-lg border border-border bg-muted/40">
                    <button type="button"
                            @click="viewMode = 'matrix'"
                            :class="viewMode === 'matrix' ? 'bg-card text-foreground font-semibold shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                            class="px-3 py-1 rounded-md text-xs transition-colors cursor-pointer flex items-center gap-1.5">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Matriks Tren Bulanan</span>
                    </button>
                    <button type="button"
                            @click="viewMode = 'sku'"
                            :class="viewMode === 'sku' ? 'bg-card text-foreground font-semibold shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                            class="px-3 py-1 rounded-md text-xs transition-colors cursor-pointer flex items-center gap-1.5">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <span>Rincian SKU per Fasilitas</span>
                        <span class="font-mono text-[10px] px-1.5 py-0.2 rounded-full bg-muted text-muted-foreground">
                            {{ count($defectAnalytics['monthly_by_warehouse'] ?? []) }}
                        </span>
                    </button>
                </div>

                <div class="text-[11px] text-muted-foreground hidden sm:block">
                    Periode Audit: <strong class="text-foreground">Tahun {{ $year ?? date('Y') }}</strong>
                    @if(!empty($month))
                        &middot; <strong class="text-foreground">{{ \Carbon\Carbon::create(2026, $month, 1)->translatedFormat('F') }}</strong>
                    @endif
                </div>
            </div>

            {{-- Tab 1: Matriks Kerusakan per Bulan --}}
            <div x-show="viewMode === 'matrix'" class="border border-border rounded-lg overflow-hidden">
                <div class="overflow-x-auto max-h-80 overflow-y-auto">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-muted/80 border-b border-border text-muted-foreground font-medium">
                            <tr>
                                <th class="px-3.5 py-2.5 text-left w-32">Bulan</th>
                                <th class="px-3.5 py-2.5 text-right w-24">Transfer</th>
                                <th class="px-3.5 py-2.5 text-right w-32">Qty Dikirim</th>
                                <th class="px-3.5 py-2.5 text-right w-32">Qty Baik</th>
                                <th class="px-3.5 py-2.5 text-right w-32 text-destructive font-bold">Qty Rusak</th>
                                <th class="px-3.5 py-2.5 text-right w-24">Defect %</th>
                                <th class="px-3.5 py-2.5 text-left">Rincian per Gudang Penerima</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-card">
                            @php
                                $matrix = $defectAnalytics['monthly_matrix'] ?? [];
                            @endphp
                            @forelse($matrix as $mRow)
                                @if(empty($month) || $mRow['month'] == $month)
                                <tr class="hover:bg-muted/30 {{ $mRow['qty_rusak'] > 0 ? 'bg-destructive/5' : '' }}">
                                    <td class="px-3.5 py-2.5 font-medium text-foreground">{{ $mRow['month_name'] }}</td>
                                    <td class="px-3.5 py-2.5 text-right font-medium text-muted-foreground tabular-nums">{{ $mRow['transfers_count'] }}</td>
                                    <td class="px-3.5 py-2.5 text-right font-medium tabular-nums">{{ number_format($mRow['qty_dikirim'], 2) }}</td>
                                    <td class="px-3.5 py-2.5 text-right font-medium text-foreground tabular-nums">{{ number_format($mRow['qty_baik'], 2) }}</td>
                                    <td class="px-3.5 py-2.5 text-right font-bold text-destructive tabular-nums">
                                        {{ $mRow['qty_rusak'] > 0 ? number_format($mRow['qty_rusak'], 2) : '-' }}
                                    </td>
                                    <td class="px-3.5 py-2.5 text-right font-semibold tabular-nums {{ $mRow['defect_rate_percent'] > 0 ? 'text-destructive' : 'text-muted-foreground' }}">
                                        {{ number_format($mRow['defect_rate_percent'], 2) }}%
                                    </td>
                                    <td class="px-3.5 py-2.5">
                                        @if(!empty($mRow['warehouses']))
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach($mRow['warehouses'] as $whBreakdown)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] {{ $whBreakdown['qty_rusak'] > 0 ? 'bg-destructive/10 text-destructive font-semibold border border-destructive/20' : 'bg-muted text-muted-foreground' }}">
                                                        <span>{{ $whBreakdown['gudang_nama'] }}:</span>
                                                        <span class="tabular-nums font-mono">{{ number_format($whBreakdown['qty_rusak'], 2) }} rusak</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted-foreground">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">Tidak ada data transfer pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tab 2: Rincian SKU per Fasilitas Gudang --}}
            <div x-show="viewMode === 'sku'" class="border border-border rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-muted/80 border-b border-border text-muted-foreground font-medium">
                            <tr>
                                <th class="px-3.5 py-2.5 text-left w-56">Gudang Penerima</th>
                                <th class="px-3.5 py-2.5 text-right w-32">Total Rusak</th>
                                <th class="px-3.5 py-2.5 text-left">Daftar SKU Rusak & Kuantitas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-card">
                            @forelse($defectAnalytics['monthly_by_warehouse'] ?? [] as $whDefect)
                                <tr class="hover:bg-muted/30 {{ ($whDefect['total_tahunan'] ?? 0) > 0 ? 'bg-destructive/5' : '' }}">
                                    <td class="px-3.5 py-3 align-top">
                                        <div class="font-bold text-foreground text-xs">{{ $whDefect['nama'] }}</div>
                                        <div class="font-mono text-[10px] text-muted-foreground">{{ $whDefect['kode'] }}</div>
                                    </td>
                                    <td class="px-3.5 py-3 text-right align-top tabular-nums">
                                        <span class="font-bold font-mono {{ ($whDefect['total_tahunan'] ?? 0) > 0 ? 'text-destructive' : 'text-muted-foreground' }}">
                                            {{ number_format($whDefect['total_tahunan'] ?? 0, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-3.5 py-3 align-top">
                                        @if(!empty($whDefect['sku_rusak_list']))
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach($whDefect['sku_rusak_list'] as $skuItem)
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] bg-destructive/10 border border-destructive/20 text-destructive">
                                                        <span class="font-mono font-bold">{{ $skuItem['sku'] }}</span>
                                                        <span class="text-foreground">{{ $skuItem['nama'] }}:</span>
                                                        <span class="font-bold font-mono bg-card px-1.5 py-0.5 rounded border border-border tabular-nums">{{ number_format($skuItem['qty_rusak'], 2) }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted-foreground text-[11px] italic">Tidak ada catatan barang rusak</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">Tidak ada data gudang tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-card>
</div>
@endif
@endcan


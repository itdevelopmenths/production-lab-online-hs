{{-- DASHBOARD WIDGET: REKAPITULASI BARANG RUSAK / DEFECT PER BULAN PER GUDANG --}}
<x-card class="border-border">
    <x-slot:header>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between w-full gap-3">
            <div class="flex items-center gap-2.5">
                <div class="size-8 rounded-md bg-muted text-foreground flex items-center justify-center border border-border shrink-0">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-foreground tracking-tight">Dashboard Barang Rusak & Defect Mutasi</h2>
                    <p class="text-xs text-muted-foreground">Akumulasi barang rusak per bulan per gudang dari penerimaan transfer</p>
                </div>
            </div>

            {{-- Filter Form --}}
            <form method="GET" action="{{ $formAction ?? request()->url() }}" class="flex flex-wrap items-center gap-2">
                <select name="year" class="h-8 rounded-md border border-input bg-background text-xs px-2 text-foreground">
                    @foreach(range(date('Y') - 2, date('Y') + 1) as $yr)
                        <option value="{{ $yr }}" {{ ($year ?? date('Y')) == $yr ? 'selected' : '' }}>Tahun {{ $yr }}</option>
                    @endforeach
                </select>

                <select name="month" class="h-8 rounded-md border border-input bg-background text-xs px-2 text-foreground">
                    <option value="">Semua Bulan</option>
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" {{ ($month ?? null) == $m ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F') }}
                        </option>
                    @endforeach
                </select>

                <select name="gudang_id" class="h-8 rounded-md border border-input bg-background text-xs px-2 text-foreground">
                    <option value="">Semua Gudang</option>
                    @foreach($warehouses ?? [] as $wh)
                        <option value="{{ $wh->id }}" {{ ($gudangId ?? null) == $wh->id ? 'selected' : '' }}>{{ $wh->nama }}</option>
                    @endforeach
                </select>

                <button type="submit" class="h-8 px-3 rounded-md bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition shadow-2xs cursor-pointer">
                    Filter
                </button>

                @if(!empty($month) || !empty($gudangId))
                    <a href="{{ $formAction ?? request()->url() }}?year={{ $year ?? date('Y') }}" class="h-8 px-2.5 rounded-md border border-border text-xs text-muted-foreground hover:bg-muted flex items-center transition-colors">
                        Reset
                    </a>
                @endif
            </form>
        </div>
    </x-slot:header>

    {{-- 5 Stat Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-2.5 mb-4">
        <div class="p-3 rounded-lg bg-card border border-border shadow-2xs">
            <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Total Selesai</span>
            <div class="text-xl font-bold font-mono tabular-nums text-foreground mt-0.5">{{ number_format($defectAnalytics['summary']['total_transfers'] ?? 0) }}</div>
            <span class="text-[10px] text-muted-foreground">Transaksi mutasi</span>
        </div>

        <div class="p-3 rounded-lg bg-card border border-border shadow-2xs">
            <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Total Dikirim</span>
            <div class="text-xl font-bold font-mono tabular-nums text-foreground mt-0.5">{{ number_format($defectAnalytics['summary']['total_qty_dikirim'] ?? 0, 2) }}</div>
            <span class="text-[10px] text-muted-foreground">Volume mutasi fisik</span>
        </div>

        <div class="p-3 rounded-lg bg-card border border-border shadow-2xs">
            <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                Total Barang Rusak
            </span>
            <div class="text-xl font-bold font-mono tabular-nums text-destructive mt-0.5">{{ number_format($defectAnalytics['summary']['total_qty_rusak'] ?? 0, 2) }}</div>
            <span class="text-[10px] text-muted-foreground">Penerimaan rusak</span>
        </div>

        <div class="p-3 rounded-lg bg-card border border-border shadow-2xs">
            <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Defect Rate</span>
            <div class="text-xl font-bold font-mono tabular-nums text-foreground mt-0.5">
                {{ number_format($defectAnalytics['summary']['defect_rate_percent'] ?? 0, 2) }}%
            </div>
            <span class="text-[10px] text-muted-foreground">Rasio rusak vs kirim</span>
        </div>

        <div class="p-3 rounded-lg bg-card border border-border shadow-2xs col-span-2 md:col-span-1">
            <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                Gudang Terdampak
            </span>
            <div class="text-sm font-bold text-foreground mt-0.5 truncate" title="{{ $defectAnalytics['summary']['gudang_tertinggi_nama'] ?? '—' }}">
                {{ $defectAnalytics['summary']['gudang_tertinggi_nama'] ?? '—' }}
            </div>
            <span class="text-[10px] text-muted-foreground">{{ number_format($defectAnalytics['summary']['gudang_tertinggi_qty'] ?? 0, 2) }} unit rusak</span>
        </div>
    </div>

    {{-- Rekapitulasi Bulanan per Gudang --}}
    <div class="mt-4 border border-border rounded-lg overflow-hidden">
        <div class="px-4 py-2.5 bg-muted/40 border-b border-border flex items-center justify-between">
            <span class="text-xs font-semibold text-foreground">Rekapitulasi Kerusakan Barang per Bulan (Tahun {{ $year ?? date('Y') }})</span>
            <span class="text-[11px] text-muted-foreground">Akumulasi per gudang penerima</span>
        </div>
        <div class="overflow-x-auto max-h-72 overflow-y-auto">
            <table class="w-full text-xs">
                <thead class="sticky top-0 bg-muted/80 border-b border-border text-muted-foreground font-medium">
                    <tr>
                        <th class="px-4 py-2.5 text-left w-32">Bulan</th>
                        <th class="px-4 py-2.5 text-right w-24">Transfer</th>
                        <th class="px-4 py-2.5 text-right w-32">Qty Dikirim</th>
                        <th class="px-4 py-2.5 text-right w-32">Qty Baik</th>
                        <th class="px-4 py-2.5 text-right w-32 text-destructive font-bold">Qty Rusak</th>
                        <th class="px-4 py-2.5 text-right w-24">Defect %</th>
                        <th class="px-4 py-2.5 text-left">Rincian per Gudang Penerima</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border bg-card">
                    @php
                        $matrix = $defectAnalytics['monthly_matrix'] ?? [];
                    @endphp
                    @forelse($matrix as $mRow)
                        @if(empty($month) || $mRow['month'] == $month)
                        <tr class="hover:bg-muted/30 {{ $mRow['qty_rusak'] > 0 ? 'bg-destructive/5' : '' }}">
                            <td class="px-4 py-2.5 font-medium text-foreground">{{ $mRow['month_name'] }}</td>
                            <td class="px-4 py-2.5 text-right font-medium text-muted-foreground tabular-nums">{{ $mRow['transfers_count'] }}</td>
                            <td class="px-4 py-2.5 text-right font-medium tabular-nums">{{ number_format($mRow['qty_dikirim'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right font-medium text-foreground tabular-nums">{{ number_format($mRow['qty_baik'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right font-bold text-destructive tabular-nums">
                                {{ $mRow['qty_rusak'] > 0 ? number_format($mRow['qty_rusak'], 2) : '-' }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-semibold tabular-nums {{ $mRow['defect_rate_percent'] > 0 ? 'text-destructive' : 'text-muted-foreground' }}">
                                {{ number_format($mRow['defect_rate_percent'], 2) }}%
                            </td>
                            <td class="px-4 py-2.5">
                                @if(!empty($mRow['warehouses']))
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($mRow['warehouses'] as $whBreakdown)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] {{ $whBreakdown['qty_rusak'] > 0 ? 'bg-destructive/10 text-destructive font-semibold border border-destructive/20' : 'bg-muted text-muted-foreground' }}">
                                                <span>{{ $whBreakdown['gudang_nama'] }}:</span>
                                                <span class="tabular-nums">{{ number_format($whBreakdown['qty_rusak'], 2) }} rusak</span>
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
                            <td colspan="7" class="px-4 py-4 text-center text-muted-foreground">Tidak ada data transfer pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Rekapitulasi Kerusakan SKU per Gudang (monthly_by_warehouse) --}}
    <div class="mt-4 border border-border rounded-lg overflow-hidden">
        <div class="px-4 py-2.5 bg-muted/40 border-b border-border flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-foreground">Rincian SKU Rusak per Gudang</span>
                <span class="text-[11px] text-muted-foreground ml-2">Akumulasi produk rusak yang diterima di masing-masing gudang (Tahun {{ $year ?? date('Y') }})</span>
            </div>
            <span class="text-[11px] font-mono text-muted-foreground">{{ count($defectAnalytics['monthly_by_warehouse'] ?? []) }} Gudang Terdata</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-muted/80 border-b border-border text-muted-foreground font-medium">
                    <tr>
                        <th class="px-4 py-2.5 text-left w-56">Gudang Penerima</th>
                        <th class="px-4 py-2.5 text-right w-32">Total Rusak</th>
                        <th class="px-4 py-2.5 text-left">Daftar SKU Rusak & Kuantitas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border bg-card">
                    @forelse($defectAnalytics['monthly_by_warehouse'] ?? [] as $whDefect)
                        <tr class="hover:bg-muted/30 {{ ($whDefect['total_tahunan'] ?? 0) > 0 ? 'bg-destructive/5' : '' }}">
                            <td class="px-4 py-3 align-top">
                                <div class="font-bold text-foreground">{{ $whDefect['nama'] }}</div>
                                <div class="font-mono text-[10px] text-muted-foreground">{{ $whDefect['kode'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-right align-top tabular-nums">
                                <span class="font-bold {{ ($whDefect['total_tahunan'] ?? 0) > 0 ? 'text-destructive' : 'text-muted-foreground' }}">
                                    {{ number_format($whDefect['total_tahunan'] ?? 0, 2) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 align-top">
                                @if(!empty($whDefect['sku_rusak_list']))
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($whDefect['sku_rusak_list'] as $skuItem)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] bg-destructive/10 border border-destructive/20 text-destructive">
                                                <span class="font-mono font-bold">{{ $skuItem['sku'] }}</span>
                                                <span class="text-foreground">{{ $skuItem['nama'] }}:</span>
                                                <span class="font-bold bg-card px-1.5 py-0.5 rounded border border-border tabular-nums">{{ number_format($skuItem['qty_rusak'], 2) }}</span>
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
                            <td colspan="3" class="px-4 py-4 text-center text-muted-foreground">Tidak ada data gudang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-card>


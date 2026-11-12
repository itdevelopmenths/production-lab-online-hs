<x-app-layout title="Laporan Defect & Kerusakan">
    <x-page-header
        title="Laporan Defect & Kerusakan Mutu"
        subtitle="Monitoring batch produksi dan pergerakan logistik transfer yang mengalami unit rusak/cacat"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Defect' => null]"
        :backUrl="route('reports.index')"
    />

    {{-- Filter Toolbar --}}
    <form method="GET" action="{{ route('reports.defect') }}" class="mb-6 bg-card p-4 rounded-xl border border-border shadow-xs flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="filter-tahun" class="block text-xs font-medium text-muted-foreground mb-1">Tahun Evaluasi</label>
                <select name="tahun" id="filter-tahun" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground w-full sm:w-auto min-w-28 max-w-full outline-none cursor-pointer">
                    @for($y = now()->year + 1; $y >= now()->year - 2; $y--)
                        <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label for="filter-bulan" class="block text-xs font-medium text-muted-foreground mb-1">Bulan</label>
                <select name="bulan" id="filter-bulan" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground w-full sm:w-auto min-w-36 max-w-full outline-none cursor-pointer">
                    <option value="">— Semua Bulan —</option>
                    @php
                        $namaBulan = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                        ];
                    @endphp
                    @foreach($namaBulan as $mNum => $mLabel)
                        <option value="{{ $mNum }}" @selected($month == $mNum)>{{ $mLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-gudang" class="block text-xs font-medium text-muted-foreground mb-1">Gudang / Lokasi</label>
                <select name="gudang_id" id="filter-gudang" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground w-full sm:w-auto min-w-40 max-w-full outline-none cursor-pointer">
                    <option value="">— Semua Gudang —</option>
                    @foreach($gudangs as $g)
                        <option value="{{ $g->id }}" @selected($gudangId == $g->id)>{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pt-5">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold bg-primary text-primary-foreground shadow-xs hover:bg-primary/90 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filter
                </button>
            </div>
        </div>

        <div>
            <a href="{{ route('reports.defect') }}" class="text-xs text-muted-foreground hover:text-foreground font-medium transition-colors">
                Reset Filter
            </a>
        </div>
    </form>

    @php
        $totalBatches = $batches->count();
        $totalRusak = $batches->sum('qty_rusak');
        $validDefects = $batches->map(fn($b) => $b->defectRate())->filter(fn($d) => $d !== null);
        $avgDefect = $validDefects->isNotEmpty() ? $validDefects->avg() : null;

        $trfSummary = $transferAnalytics['summary'] ?? [];
        $trfTotalRusak = $trfSummary['total_qty_rusak'] ?? 0;
        $trfRate = $trfSummary['defect_rate_percent'] ?? 0;
        $trfTopGudang = $trfSummary['gudang_tertinggi_nama'] ?? '—';
        $trfTopQty = $trfSummary['gudang_tertinggi_qty'] ?? 0;
        $trfTerdampakCount = $trfSummary['transfer_terdampak_count'] ?? 0;
    @endphp

    {{-- KPI Summary Cards: Produksi vs Transfer --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 mb-6">
        <div class="rounded-xl border border-border bg-card p-3.5 shadow-xs min-w-0">
            <p class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Batch Produksi Defect</p>
            <p class="text-lg font-bold font-mono text-foreground mt-1">{{ number_format($totalBatches) }} <span class="text-xs font-normal text-muted-foreground">Batch</span></p>
        </div>
        <div class="rounded-xl border border-border bg-card p-3.5 shadow-xs min-w-0">
            <p class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Rusak Produksi</p>
            <p class="text-lg font-bold font-mono text-destructive mt-1">{{ number_format($totalRusak, 0, ',', '.') }} <span class="text-xs font-normal text-muted-foreground">Pcs</span></p>
        </div>
        <div class="rounded-xl border border-border bg-card p-3.5 shadow-xs min-w-0">
            <p class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Defect Rate Produksi</p>
            <p class="text-lg font-bold font-mono text-destructive mt-1">
                {{ $avgDefect !== null ? number_format($avgDefect, 1) . '%' : '0.0%' }}
            </p>
        </div>
        <div class="rounded-xl border border-border bg-card p-3.5 shadow-xs min-w-0">
            <p class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Rusak Pengiriman (Transfer)</p>
            <p class="text-lg font-bold font-mono text-destructive mt-1">{{ number_format($trfTotalRusak, 0, ',', '.') }} <span class="text-xs font-normal text-muted-foreground">Pcs</span></p>
        </div>
        <div class="rounded-xl border border-border bg-card p-3.5 shadow-xs min-w-0">
            <p class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Gudang Paling Terdampak</p>
            <p class="text-sm font-bold text-foreground truncate mt-1" title="{{ $trfTopGudang }}">{{ $trfTopGudang }}</p>
            <p class="text-[10px] text-muted-foreground font-mono mt-0.5">{{ number_format($trfTopQty, 0, ',', '.') }} Pcs rusak</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-3.5 shadow-xs min-w-0">
            <p class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Defect Rate Transfer</p>
            <p class="text-lg font-bold font-mono text-amber-600 mt-1">{{ number_format($trfRate, 2) }}%</p>
            <p class="text-[10px] text-muted-foreground font-mono mt-0.5">{{ $trfTerdampakCount }} transfer terdampak</p>
        </div>
    </div>

    <div class="space-y-6">
        {{-- Table Card 1: Batch Produksi --}}
        <x-card title="Rincian Batch Produksi Terdampak Defect" subtitle="Daftar batch selesai yang mencatatkan kuantitas unit rusak lebih dari nol">
            <div tabindex="0" role="region" aria-label="Tabel Rincian Batch Produksi Terdampak Defect" class="-mx-4 -my-4 overflow-x-auto focus:outline-none focus:ring-1 focus:ring-primary/30">
                <table class="w-full text-xs text-left border-collapse min-w-[700px]">
                    <thead class="bg-muted/70 text-muted-foreground uppercase text-[11px] font-semibold tracking-wider border-b border-border">
                        <tr>
                            <th class="px-4 py-3 w-40">No. Batch</th>
                            <th class="px-4 py-3">Produk Jadi</th>
                            <th class="px-4 py-3 text-right">Output Baik</th>
                            <th class="px-4 py-3 text-right">Output Rusak</th>
                            <th class="px-4 py-3 text-center">Tingkat Defect</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($batches as $b)
                        @php
                            $defect = $b->defectRate();
                            $produkLabel = $b->outputs->isNotEmpty()
                                ? $b->outputs->map(fn($o) => $o->produk?->nama)->filter()->join(', ')
                                : ($b->produk?->nama ?? '—');
                        @endphp
                        <tr class="hover:bg-muted/40 transition-colors">
                            <td class="px-4 py-2.5 font-mono font-medium text-foreground">
                                @can('batch.view')
                                    <a href="{{ route('batches.show', $b) }}" class="hover:underline font-semibold text-primary">{{ $b->no_batch }}</a>
                                @else
                                    {{ $b->no_batch }}
                                @endcan
                            </td>
                            <td class="px-4 py-2.5 font-medium text-foreground">{{ $produkLabel }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-foreground">{{ number_format($b->qty_baik, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-destructive font-semibold">{{ number_format($b->qty_rusak, 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-center">
                                @if($defect !== null)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-destructive/10 text-destructive border border-destructive/20">
                                        {{ number_format($defect, 1) }}%
                                    </span>
                                @else
                                    <span class="text-muted-foreground">-</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-xs text-muted-foreground">
                                <svg class="mx-auto h-8 w-8 text-emerald-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Kualitas sempurna! Tidak ditemukan batch produksi dengan unit rusak pada filter terpilih.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Section 2: Rekapitulasi Kerusakan Pengiriman (Transfer) Bulanan per Gudang --}}
        <x-card title="Rekapitulasi Kerusakan Pengiriman (Transfer) Bulanan per Gudang" subtitle="Matriks persebaran kuantitas unit rusak pengiriman logistik antar-gudang (Tahun {{ $year }})">
            <div tabindex="0" role="region" aria-label="Matriks Kerusakan Pengiriman Transfer Bulanan per Gudang" class="-mx-4 -my-4 overflow-x-auto focus:outline-none focus:ring-1 focus:ring-primary/30">
                <table class="w-full text-xs text-left border-collapse min-w-[980px]">
                    <thead class="bg-muted/70 text-muted-foreground uppercase text-[10px] font-semibold tracking-wider border-b border-border">
                        <tr>
                            <th class="px-3 py-3 w-44">Gudang Tujuan</th>
                            @for($m = 1; $m <= 12; $m++)
                                <th class="px-2 py-3 text-right">{{ substr($namaBulan[$m], 0, 3) }}</th>
                            @endfor
                            <th class="px-3 py-3 text-right bg-muted/90 font-bold">Total Tahunan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($transferAnalytics['monthly_by_warehouse'] ?? [] as $mWh)
                        <tr class="hover:bg-muted/40 transition-colors">
                            <td class="px-3 py-2.5 font-medium text-foreground">
                                <div class="font-semibold">{{ $mWh['nama'] }}</div>
                                <div class="text-[10px] font-mono text-muted-foreground">{{ $mWh['kode'] }}</div>
                            </td>
                            @for($m = 1; $m <= 12; $m++)
                                @php
                                    $val = $mWh['months'][$m] ?? 0;
                                @endphp
                                <td class="px-2 py-2.5 text-right font-mono {{ $val > 0 ? 'text-destructive font-bold' : 'text-muted-foreground/60' }}">
                                    {{ $val > 0 ? number_format($val, 0, ',', '.') : '—' }}
                                </td>
                            @endfor
                            <td class="px-3 py-2.5 text-right font-mono font-bold bg-muted/40 {{ ($mWh['total_tahunan'] ?? 0) > 0 ? 'text-destructive' : 'text-muted-foreground' }}">
                                {{ number_format($mWh['total_tahunan'] ?? 0, 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="14" class="px-4 py-8 text-center text-muted-foreground text-xs">
                                Tidak ada data matriks gudang untuk tahun {{ $year }}.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Sub-tabel Rincian SKU Rusak per Gudang --}}
            @php
                $anySkuRusak = false;
                foreach ($transferAnalytics['monthly_by_warehouse'] ?? [] as $w) {
                    if (!empty($w['sku_rusak_list'])) {
                        $anySkuRusak = true;
                        break;
                    }
                }
            @endphp
            @if($anySkuRusak)
                <div class="mt-6 pt-4 border-t border-border">
                    <h5 class="text-xs font-semibold text-foreground mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Rincian Komoditas SKU Rusak Akumulasi Pengiriman ({{ $year }})
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($transferAnalytics['monthly_by_warehouse'] ?? [] as $w)
                            @if(!empty($w['sku_rusak_list']))
                                <div class="p-3 rounded-lg border border-border bg-muted/20 space-y-1.5">
                                    <div class="font-bold text-xs text-foreground flex items-center justify-between border-b border-border/60 pb-1">
                                        <span>{{ $w['nama'] }}</span>
                                        <span class="font-mono text-destructive text-[11px]">{{ number_format($w['total_tahunan'], 0, ',', '.') }} Pcs</span>
                                    </div>
                                    <div class="divide-y divide-border/40 text-[11px]">
                                        @foreach($w['sku_rusak_list'] as $sku)
                                            <div class="py-1 flex items-center justify-between">
                                                <div class="truncate pr-2">
                                                    <span class="font-medium text-foreground">{{ $sku['nama'] }}</span>
                                                    <span class="font-mono text-[10px] text-muted-foreground block">{{ $sku['sku'] }}</span>
                                                </div>
                                                <span class="font-mono font-semibold text-destructive shrink-0">{{ number_format($sku['qty_rusak'], 0, ',', '.') }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>

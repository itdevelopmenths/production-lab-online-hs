@can('defect.view')
@if(!empty($defectAnalytics))
{{-- DASHBOARD WIDGET: REKAPITULASI BARANG RUSAK / DEFECT PER BULAN PER GUDANG --}}
<div id="defect-dashboard"
     class="space-y-4 scroll-mt-20"
     x-data="{
         viewMode: (function() {
             try { return localStorage.getItem('hs_defect_view_mode') || 'matrix'; } catch(e) { return 'matrix'; }
         })(),
         setViewMode(mode) {
             this.viewMode = mode;
             try { localStorage.setItem('hs_defect_view_mode', mode); } catch(e) {}
         }
     }">
    <x-card :noPadding="true">
        <x-slot:header>
            <div class="flex flex-col lg:flex-row lg:items-center justify-between w-full gap-3 py-0.5">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="size-[42px] rounded-[8px] bg-[#f9fafb] dark:bg-gray-800 border border-[#e8e8e8] dark:border-border text-[#101828] dark:text-white flex items-center justify-center shrink-0">
                        <svg class="size-[20px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-[16px] leading-[1.4] font-semibold tracking-[-0.176px] text-[#101828] dark:text-white">Dashboard Barang Rusak & Defect Mutasi</h2>
                            <x-badge variant="neutral" size="sm" :pill="true">{{ !empty($isCombinedDefectSources) ? 'PO, Batch & Mutasi' : 'Audit Logistik' }}</x-badge>
                        </div>
                        <p class="text-[12px] leading-[18px] tracking-[-0.132px] text-[#6a7282] dark:text-gray-400 mt-0.5">
                            @if(!empty($isCombinedDefectSources))
                                Akumulasi barang rusak dari Transfer, Purchasing (PO), dan Batch Produksi per bulan per fasilitas gudang
                            @else
                                Akumulasi barang rusak per bulan per fasilitas gudang dari penerimaan transfer fisik
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Filter Toolbar --}}
                <form method="GET" action="{{ $formAction ?? request()->url() }}#defect-dashboard"
                      onsubmit="try { sessionStorage.setItem('hs_defect_scroll', window.scrollY); sessionStorage.setItem('hs_defect_focused', document.activeElement?.name || ''); } catch(e) {}"
                      class="flex flex-wrap items-center lg:justify-end gap-2 shrink-0 max-w-full">
                    <div class="inline-flex items-center rounded-[8px] border border-[#e8e8e8] dark:border-border bg-white dark:bg-gray-900 px-2.5 h-8 text-xs shadow-theme-xs">
                        <span class="text-text-muted mr-1.5 font-medium">Tahun:</span>
                        <select name="year" class="bg-transparent text-xs font-semibold text-text-active outline-none cursor-pointer">
                            @foreach(range(date('Y') - 2, date('Y') + 1) as $yr)
                                <option value="{{ $yr }}" class="bg-white dark:bg-gray-900 text-[#101828] dark:text-white" {{ ($year ?? date('Y')) == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="inline-flex items-center rounded-[8px] border border-[#e8e8e8] dark:border-border bg-white dark:bg-gray-900 px-2.5 h-8 text-xs shadow-theme-xs">
                        <span class="text-text-muted mr-1.5 font-medium">Bulan:</span>
                        <select name="month" class="bg-transparent text-xs font-semibold text-text-active outline-none cursor-pointer">
                            <option value="" class="bg-white dark:bg-gray-900 text-[#101828] dark:text-white">Semua Bulan (12 Bulan)</option>
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" class="bg-white dark:bg-gray-900 text-[#101828] dark:text-white" {{ ($month ?? null) == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="inline-flex items-center rounded-[8px] border border-[#e8e8e8] dark:border-border bg-white dark:bg-gray-900 px-2.5 h-8 text-xs shadow-theme-xs">
                        <span class="text-text-muted mr-1.5 font-medium">Gudang:</span>
                        <select name="gudang_id" class="bg-transparent text-xs font-semibold text-text-active outline-none cursor-pointer max-w-40 truncate">
                            <option value="" class="bg-white dark:bg-gray-900 text-[#101828] dark:text-white">Semua Fasilitas</option>
                            @foreach($warehouses ?? [] as $wh)
                                <option value="{{ $wh->id }}" class="bg-white dark:bg-gray-900 text-[#101828] dark:text-white" {{ ($gudangId ?? null) == $wh->id ? 'selected' : '' }}>{{ $wh->nama }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-button type="submit" variant="primary" size="sm" class="h-8">
                        <x-slot:icon>
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        </x-slot:icon>
                        Filter
                    </x-button>

                    @if(!empty($month) || !empty($gudangId))
                        <x-button :href="($formAction ?? request()->url()) . '?year=' . ($year ?? date('Y')) . '#defect-dashboard'"
                                  variant="secondary"
                                  size="sm"
                                  class="h-8"
                                  onclick="try { sessionStorage.setItem('hs_defect_scroll', window.scrollY); } catch(e) {}">
                            Reset
                        </x-button>
                    @endif
                </form>
            </div>
        </x-slot:header>

        <div class="p-4 sm:p-5 space-y-4">
            {{-- 5 High-Density Stat Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="p-3.5 rounded-[12px] bg-[#FBFBFB] dark:bg-gray-950/60 border border-[#e8e8e8] dark:border-gray-800 shadow-theme-xs flex flex-col justify-between">
                    <span class="text-[11px] font-medium text-text-muted">Total Selesai</span>
                    <div class="text-xl font-semibold font-mono tabular-nums text-[#101828] dark:text-white mt-1">{{ number_format($defectAnalytics['summary']['total_transfers'] ?? 0) }}</div>
                    <span class="text-[11px] text-text-muted mt-0.5">{{ !empty($isCombinedDefectSources) ? 'Transaksi selesai' : 'Transaksi mutasi' }}</span>
                </div>

                <div class="p-3.5 rounded-[12px] bg-[#FBFBFB] dark:bg-gray-950/60 border border-[#e8e8e8] dark:border-gray-800 shadow-theme-xs flex flex-col justify-between">
                    <span class="text-[11px] font-medium text-text-muted">{{ !empty($isCombinedDefectSources) ? 'Total Volume' : 'Total Dikirim' }}</span>
                    <div class="text-xl font-semibold font-mono tabular-nums text-[#101828] dark:text-white mt-1">{{ number_format($defectAnalytics['summary']['total_qty_dikirim'] ?? 0, 2) }}</div>
                    <span class="text-[11px] text-text-muted mt-0.5">{{ !empty($isCombinedDefectSources) ? 'Volume Transfer, PO & Batch' : 'Volume mutasi fisik' }}</span>
                </div>

                <div class="p-3.5 rounded-[12px] bg-[#FBFBFB] dark:bg-gray-950/60 border border-[#e8e8e8] dark:border-gray-800 shadow-theme-xs flex flex-col justify-between">
                    <span class="text-[11px] font-medium text-text-muted">Total Barang Rusak</span>
                    <div class="text-xl font-semibold font-mono tabular-nums mt-1 {{ ($defectAnalytics['summary']['total_qty_rusak'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-[#101828] dark:text-white' }}">
                        {{ number_format($defectAnalytics['summary']['total_qty_rusak'] ?? 0, 2) }}
                    </div>
                    <span class="text-[11px] text-text-muted mt-0.5">
                        @php
                            $bySrc = $defectAnalytics['summary']['by_source'] ?? [];
                        @endphp
                        @if(!empty($isCombinedDefectSources) && ($defectAnalytics['summary']['total_qty_rusak'] ?? 0) > 0)
                            Trf: {{ \App\Helpers\NumberHelper::formatQty($bySrc['transfer'] ?? 0) }} &middot; PO: {{ \App\Helpers\NumberHelper::formatQty($bySrc['purchasing'] ?? 0) }} &middot; Batch: {{ \App\Helpers\NumberHelper::formatQty($bySrc['batch'] ?? 0) }}
                        @else
                            Penerimaan rusak
                        @endif
                    </span>
                </div>

                <div class="p-3.5 rounded-[12px] bg-[#FBFBFB] dark:bg-gray-950/60 border border-[#e8e8e8] dark:border-gray-800 shadow-theme-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-medium text-text-muted">Defect Rate</span>
                        @if(($defectAnalytics['summary']['defect_rate_percent'] ?? 0) > 0)
                            <span class="size-1.5 rounded-full bg-red-500 animate-pulse"></span>
                        @endif
                    </div>
                    <div class="text-xl font-semibold font-mono tabular-nums mt-1 {{ ($defectAnalytics['summary']['defect_rate_percent'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-[#101828] dark:text-white' }}">
                        {{ number_format($defectAnalytics['summary']['defect_rate_percent'] ?? 0, 2) }}%
                    </div>
                    <span class="text-[11px] text-text-muted mt-0.5">{{ !empty($isCombinedDefectSources) ? 'Rasio rusak vs volume' : 'Rasio rusak vs kirim' }}</span>
                </div>

                <div class="p-3.5 rounded-[12px] bg-[#FBFBFB] dark:bg-gray-950/60 border border-[#e8e8e8] dark:border-gray-800 shadow-theme-xs col-span-2 md:col-span-1 flex flex-col justify-between">
                    <span class="text-[11px] font-medium text-text-muted">Gudang Terdampak</span>
                    <div class="text-sm font-bold text-[#101828] dark:text-white mt-1 truncate" title="{{ $defectAnalytics['summary']['gudang_tertinggi_nama'] ?? '—' }}">
                        {{ $defectAnalytics['summary']['gudang_tertinggi_nama'] ?? '—' }}
                    </div>
                    <span class="text-[11px] text-text-muted mt-0.5">
                        @if(($defectAnalytics['summary']['gudang_tertinggi_qty'] ?? 0) > 0)
                            <strong class="text-red-600 dark:text-red-400 font-mono">{{ number_format($defectAnalytics['summary']['gudang_tertinggi_qty'], 2) }}</strong> unit rusak
                        @else
                            Nihil laporan kerusakan
                        @endif
                    </span>
                </div>
            </div>

            {{-- Blueprint Segmented StatusTabs Switcher --}}
            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-[#e8e8e8] dark:border-gray-800">
                <div class="bg-[#f3f4f6] dark:bg-gray-900 dark:border dark:border-gray-800 p-[2px] gap-[2px] rounded-[8px] h-9 inline-flex items-center w-fit max-w-full overflow-x-auto no-scrollbar">
                    <button type="button"
                            @click="setViewMode('matrix')"
                            :class="viewMode === 'matrix' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white border border-[#e8e8e8] dark:border-gray-700 shadow-[0px_1px_2px_0px_rgba(16,24,40,0.05)] font-semibold' : 'bg-transparent text-[#6a7282] dark:text-gray-400 border border-transparent hover:text-[#101828] dark:hover:text-white font-medium'"
                            class="px-3 py-1 rounded-[6px] h-full text-xs sm:text-[13px] transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Matriks Tren Bulanan</span>
                    </button>
                    <button type="button"
                            @click="setViewMode('sku')"
                            :class="viewMode === 'sku' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white border border-[#e8e8e8] dark:border-gray-700 shadow-[0px_1px_2px_0px_rgba(16,24,40,0.05)] font-semibold' : 'bg-transparent text-[#6a7282] dark:text-gray-400 border border-transparent hover:text-[#101828] dark:hover:text-white font-medium'"
                            class="px-3 py-1 rounded-[6px] h-full text-xs sm:text-[13px] transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <span>Rincian SKU per Fasilitas</span>
                        <span class="bg-[#f3f4f6] dark:bg-gray-700 text-[#364153] dark:text-gray-200 px-[7px] py-px rounded-full text-[11px] font-semibold tabular-nums">
                            {{ count($defectAnalytics['monthly_by_warehouse'] ?? []) }}
                        </span>
                    </button>
                </div>

                <div class="text-xs text-text-muted hidden sm:block">
                    Periode Audit: <strong class="text-text-active">Tahun {{ $year ?? date('Y') }}</strong>
                    @if(!empty($month))
                        &middot; <strong class="text-text-active">{{ \Carbon\Carbon::create(2026, $month, 1)->translatedFormat('F') }}</strong>
                    @endif
                </div>
            </div>

            {{-- Tab 1: Matriks Kerusakan per Bulan --}}
            <div x-show="viewMode === 'matrix'" class="border border-[#e8e8e8] dark:border-border rounded-[10px] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-[#f8fafb] dark:bg-gray-800/95 border-b border-[#e8e8e8] dark:border-border text-text-muted font-normal">
                            <tr>
                                <th class="px-3.5 py-2.5 text-left w-32 font-normal">Bulan</th>
                                <th class="px-3.5 py-2.5 text-right w-24 font-normal">{{ !empty($isCombinedDefectSources) ? 'Transaksi' : 'Transfer' }}</th>
                                <th class="px-3.5 py-2.5 text-right w-32 font-normal">{{ !empty($isCombinedDefectSources) ? 'Total Volume' : 'Qty Dikirim' }}</th>
                                <th class="px-3.5 py-2.5 text-right w-32 font-normal">Qty Baik</th>
                                <th class="px-3.5 py-2.5 text-right w-32 text-red-600 dark:text-red-400 font-semibold">Qty Rusak</th>
                                <th class="px-3.5 py-2.5 text-right w-24 font-normal">Defect %</th>
                                <th class="px-3.5 py-2.5 text-left font-normal">{{ !empty($isCombinedDefectSources) ? 'Rincian per Gudang / Lokasi' : 'Rincian per Gudang Penerima' }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e8e8e8] dark:divide-gray-800/60 bg-white dark:bg-gray-900">
                            @php
                                $matrix = $defectAnalytics['monthly_matrix'] ?? [];
                            @endphp
                            @forelse($matrix as $mRow)
                                @if(empty($month) || $mRow['month'] == $month)
                                <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/30 {{ $mRow['qty_rusak'] > 0 ? 'bg-red-50/40 dark:bg-red-500/5' : '' }}">
                                    <td class="px-3.5 py-2.5 font-medium text-[#101828] dark:text-white">{{ $mRow['month_name'] }}</td>
                                    <td class="px-3.5 py-2.5 text-right font-medium text-text-muted tabular-nums">{{ $mRow['transfers_count'] }}</td>
                                    <td class="px-3.5 py-2.5 text-right font-medium text-[#101828] dark:text-gray-200 tabular-nums">{{ number_format($mRow['qty_dikirim'], 2) }}</td>
                                    <td class="px-3.5 py-2.5 text-right font-medium text-[#101828] dark:text-gray-200 tabular-nums">{{ number_format($mRow['qty_baik'], 2) }}</td>
                                    <td class="px-3.5 py-2.5 text-right font-bold text-red-600 dark:text-red-400 tabular-nums">
                                        {{ $mRow['qty_rusak'] > 0 ? number_format($mRow['qty_rusak'], 2) : '-' }}
                                    </td>
                                    <td class="px-3.5 py-2.5 text-right font-semibold tabular-nums {{ $mRow['defect_rate_percent'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-text-muted' }}">
                                        {{ number_format($mRow['defect_rate_percent'], 2) }}%
                                    </td>
                                    <td class="px-3.5 py-2.5">
                                        @if(!empty($mRow['warehouses']))
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach($mRow['warehouses'] as $whBreakdown)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] {{ $whBreakdown['qty_rusak'] > 0 ? 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 font-semibold border border-red-200 dark:border-red-500/20' : 'bg-gray-100 dark:bg-gray-800 text-text-muted border border-transparent' }}">
                                                        <span>{{ $whBreakdown['gudang_nama'] }}:</span>
                                                        <span class="tabular-nums font-mono">{{ number_format($whBreakdown['qty_rusak'], 2) }} rusak</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-text-muted">Tidak ada data transfer pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tab 2: Rincian SKU per Fasilitas Gudang --}}
            <div x-show="viewMode === 'sku'" class="border border-[#e8e8e8] dark:border-border rounded-[10px] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-[#f8fafb] dark:bg-gray-800/95 border-b border-[#e8e8e8] dark:border-border text-text-muted font-normal">
                            <tr>
                                <th class="px-3.5 py-2.5 text-left w-56 font-normal">Gudang Penerima</th>
                                <th class="px-3.5 py-2.5 text-right w-32 font-normal">Total Rusak</th>
                                <th class="px-3.5 py-2.5 text-left font-normal">Daftar SKU Rusak & Kuantitas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e8e8e8] dark:divide-gray-800/60 bg-white dark:bg-gray-900">
                            @forelse($defectAnalytics['monthly_by_warehouse'] ?? [] as $whDefect)
                                <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/30 {{ ($whDefect['total_tahunan'] ?? 0) > 0 ? 'bg-red-50/40 dark:bg-red-500/5' : '' }}">
                                    <td class="px-3.5 py-3 align-top">
                                        <div class="font-bold text-[#101828] dark:text-white text-xs">{{ $whDefect['nama'] }}</div>
                                        <div class="font-mono text-[10px] text-text-muted">{{ $whDefect['kode'] }}</div>
                                    </td>
                                    <td class="px-3.5 py-3 text-right align-top tabular-nums">
                                        <span class="font-bold font-mono {{ ($whDefect['total_tahunan'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-text-muted' }}">
                                            {{ number_format($whDefect['total_tahunan'] ?? 0, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-3.5 py-3 align-top">
                                        @if(!empty($whDefect['sku_rusak_list']))
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach($whDefect['sku_rusak_list'] as $skuItem)
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] text-[11px] bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400">
                                                        <span class="font-mono font-bold">{{ $skuItem['sku'] }}</span>
                                                        <span class="text-[#101828] dark:text-gray-200">{{ $skuItem['nama'] }}:</span>
                                                        <span class="font-bold font-mono bg-white dark:bg-gray-900 px-1.5 py-0.5 rounded-[4px] border border-[#e8e8e8] dark:border-border tabular-nums text-[#101828] dark:text-white">{{ number_format($skuItem['qty_rusak'], 2) }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-text-muted text-[11px] italic">Tidak ada catatan barang rusak</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-text-muted">Tidak ada data gudang tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-card>

    {{-- Script: Preservasi Scroll Position & Focus Saat Filter Action --}}
    <script>
        (function() {
            function restoreDefectScrollAndFocus() {
                try {
                    var savedScroll = sessionStorage.getItem('hs_defect_scroll');
                    if (savedScroll !== null) {
                        sessionStorage.removeItem('hs_defect_scroll');
                        var targetY = parseInt(savedScroll, 10);
                        if (!isNaN(targetY)) {
                            window.scrollTo({ top: targetY, behavior: 'instant' });
                        }
                    } else if (window.location.hash === '#defect-dashboard') {
                        var el = document.getElementById('defect-dashboard');
                        if (el) {
                            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }

                    var savedFocused = sessionStorage.getItem('hs_defect_focused');
                    if (savedFocused) {
                        sessionStorage.removeItem('hs_defect_focused');
                        var inputEl = document.querySelector('#defect-dashboard [name="' + savedFocused + '"]');
                        if (inputEl) {
                            inputEl.focus({ preventScroll: true });
                        }
                    }
                } catch(e) {}
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', restoreDefectScrollAndFocus);
            } else {
                restoreDefectScrollAndFocus();
            }
            requestAnimationFrame(restoreDefectScrollAndFocus);
        })();
    </script>
</div>
@endif
@endcan

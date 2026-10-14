{{-- Section: Bahan Baku Lokal 4-Tabel Engine --}}
<template x-if="tab === 'lokal'">
    <div class="space-y-4">
        {{-- Sub-navigasi 4 Tabel Lokal --}}
        <div class="flex items-center justify-between gap-3 border-b border-border/60 pb-3">
            <div class="inline-flex gap-1.5 bg-muted/60 p-1 rounded-xl border border-border/40 text-xs">
                <button type="button" @click="lokalSubTab = 'rekomendasi'; page = 1" :class="lokalSubTab === 'rekomendasi' ? 'bg-primary text-primary-foreground font-semibold shadow-xs' : 'text-muted-foreground hover:text-foreground font-medium'" class="px-3.5 py-1.5 rounded-lg transition-colors cursor-pointer">
                    1. Rekomendasi Order Lokal
                </button>
                <button type="button" @click="lokalSubTab = 'analisa'; page = 1" :class="lokalSubTab === 'analisa' ? 'bg-primary text-primary-foreground font-semibold shadow-xs' : 'text-muted-foreground hover:text-foreground font-medium'" class="px-3.5 py-1.5 rounded-lg transition-colors cursor-pointer">
                    2. Working Data Analisa (ADU)
                </button>
                <button type="button" @click="lokalSubTab = 'lead_time'; page = 1" :class="lokalSubTab === 'lead_time' ? 'bg-primary text-primary-foreground font-semibold shadow-xs' : 'text-muted-foreground hover:text-foreground font-medium'" class="px-3.5 py-1.5 rounded-lg transition-colors cursor-pointer">
                    3. Ringkasan Lead Time & Buffer
                </button>
                <button type="button" @click="lokalSubTab = 'stages'; page = 1" :class="lokalSubTab === 'stages' ? 'bg-primary text-primary-foreground font-semibold shadow-xs' : 'text-muted-foreground hover:text-foreground font-medium'" class="px-3.5 py-1.5 rounded-lg transition-colors cursor-pointer">
                    4. Rincian 9 Tahap Lead Time
                </button>
            </div>
        </div>

        {{-- TABEL 1: Rekomendasi Order Lokal (Working Data) --}}
        <div x-show="lokalSubTab === 'rekomendasi'">
            <x-card 
                title="Tabel Rekomendasi Pemesanan Bahan Lokal (rekomendasi_order_lokal)" 
                subtitle="Kalkulasi selisih stok, batas minimum, pembulatan kelipatan MOQ, dan rekomendasi order dalam satuan beli"
                :noPadding="true"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-muted/70 text-muted-foreground text-[11px] uppercase font-semibold border-b border-border tracking-wider">
                            <tr>
                                <th class="py-3 px-3 text-center w-10">
                                    <input type="checkbox" @change="toggleSelectCurrentPage($event)" :checked="isCurrentPageAllSelected()" class="rounded-md border-input text-foreground focus:ring-ring cursor-pointer">
                                </th>
                                <th class="py-3 px-4">Bahan Baku</th>
                                <th class="py-3 px-4">Supplier</th>
                                <th class="py-3 px-4 text-right">Batas Min</th>
                                <th class="py-3 px-4 text-right">Target</th>
                                <th class="py-3 px-4 text-right">Stok Fisik</th>
                                <th class="py-3 px-4 text-right">Akan Datang</th>
                                <th class="py-3 px-4 text-right">Selisih</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Rumus MOQ</th>
                                <th class="py-3 px-4 text-right bg-muted/40 text-foreground font-bold">Rekomendasi Order</th>
                                <th class="py-3 px-4 text-right">Harga Satuan</th>
                                <th class="py-3 px-4 text-right">Estimasi Nominal</th>
                                <th class="py-3 px-3 text-center w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-card">
                            <template x-for="r in paginatedRows()" :key="r.id">
                                <tr class="hover:bg-muted/40 transition" :class="r.status === 'order' ? 'bg-destructive/5' : ''">
                                    <td class="py-3 px-3 text-center">
                                        <input type="checkbox" :value="r.id" x-model="selectedItemIds" class="rounded-md border-input text-foreground focus:ring-ring cursor-pointer">
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-foreground" x-text="r.nama"></div>
                                        <div class="font-mono text-[10px] text-muted-foreground" x-text="r.sku + ' · Satuan: ' + r.satuan"></div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-foreground" x-text="r.supplier_nama || '-'"></div>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono text-foreground" x-text="formatNumber(r.batas_minimum)"></td>
                                    <td class="py-3 px-4 text-right font-mono text-foreground" x-text="formatNumber(r.target_stock)"></td>
                                    <td class="py-3 px-4 text-right font-mono font-medium text-foreground" x-text="formatNumber(r.stok_saat_ini)"></td>
                                    <td class="py-3 px-4 text-right font-mono text-muted-foreground" x-text="formatNumber(r.akan_datang)"></td>
                                    <td class="py-3 px-4 text-right font-mono font-bold" :class="r.selisih < 0 ? 'text-destructive' : 'text-emerald-700 dark:text-emerald-400'" x-text="formatNumber(r.selisih)"></td>
                                    <td class="py-3 px-4 text-center" x-html="badge(r.status)"></td>
                                    <td class="py-3 px-4 text-right font-mono font-medium text-foreground" x-text="formatNumber(r.rumus_moq) + ' ' + r.satuan"></td>
                                    <td class="py-3 px-4 text-right font-mono font-bold bg-muted/40 text-foreground">
                                        <span x-text="formatNumber(r.rekomendasi_order)"></span>
                                        <template x-if="r.faktor_konversi > 1">
                                            <span class="text-[10px] font-normal text-muted-foreground block" x-text="'(x' + r.faktor_konversi + ' ' + r.satuan + ')'"></span>
                                        </template>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono text-muted-foreground" x-text="'Rp ' + formatNumber(r.harga_per_satuan)"></td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-foreground" x-text="'Rp ' + formatNumber(r.total_nominal_order)"></td>
                                    <td class="py-3 px-3 text-center">
                                        <button type="button" @click="openManualLokalModal(r)" class="px-2.5 py-1 bg-muted hover:bg-muted/80 text-foreground rounded-lg border border-border text-[10px] font-medium transition cursor-pointer" title="Edit Data Input Manual">
                                            Edit Input
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="paginatedRows().length === 0">
                                <tr>
                                    <td colspan="14" class="py-8 text-center text-muted-foreground text-xs">
                                        Tidak ada item bahan lokal yang cocok dengan pencarian atau filter.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Bar --}}
                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground">
                        <div class="flex items-center gap-2 font-mono text-[11px]">
                            <span>Menampilkan <strong class="text-foreground" x-text="startItem()"></strong> - <strong class="text-foreground" x-text="endItem()"></strong> dari <strong class="text-foreground" x-text="currentDataLength()"></strong> item</span>
                            <template x-if="searchQuery || statusFilter !== 'all'">
                                <span class="text-foreground bg-muted px-2 py-0.5 rounded-md border border-border text-[10px] font-sans">
                                    (Difilter dari <span x-text="currentRawLength()"></span> total)
                                </span>
                            </template>
                        </div>

                        <div class="flex items-center gap-1.5" x-show="totalPages() > 1">
                            <button 
                                type="button" 
                                @click="gotoPage(page - 1)" 
                                :disabled="page <= 1"
                                class="px-2.5 py-1 text-xs rounded-lg border border-border bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                &laquo; Prev
                            </button>

                            <template x-for="p in pagesList()" :key="p">
                                <div>
                                    <template x-if="p === '...'">
                                        <span class="px-2 py-1 text-muted-foreground">...</span>
                                    </template>
                                    <template x-if="p !== '...'">
                                        <button 
                                            type="button" 
                                            @click="gotoPage(p)" 
                                            :class="page === p ? 'bg-primary text-primary-foreground font-semibold border-primary' : 'bg-card text-foreground hover:bg-muted border-border'"
                                            class="min-w-[28px] px-2 py-1 text-xs rounded-lg border transition cursor-pointer"
                                            x-text="p"
                                        ></button>
                                    </template>
                                </div>
                            </template>

                            <button 
                                type="button" 
                                @click="gotoPage(page + 1)" 
                                :disabled="page >= totalPages()"
                                class="px-2.5 py-1 text-xs rounded-lg border border-border bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                Next &raquo;
                            </button>
                        </div>
                    </div>
                </x-slot:footer>
            </x-card>
        </div>

        {{-- TABEL 2: Working Data Analisa (analisa_lokal) --}}
        <div x-show="lokalSubTab === 'analisa'">
            <x-card 
                title="Tabel Working Data Analisa Stok (analisa_lokal)" 
                subtitle="Perhitungan ADU (Average Daily Usage), Batas Minimum [ADU x (Lead Time + Safety Stock)], dan Target Stock"
                :noPadding="true"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-muted/70 text-muted-foreground text-[11px] uppercase font-semibold border-b border-border tracking-wider">
                            <tr>
                                <th class="py-3 px-4">Bahan Baku</th>
                                <th class="py-3 px-4">Supplier</th>
                                <th class="py-3 px-4 text-right">Avg Lead Time (Hari)</th>
                                <th class="py-3 px-4 text-right">Safety Stock (Hari)</th>
                                <th class="py-3 px-4 text-right">Terjual 4 Bulan</th>
                                <th class="py-3 px-4 text-right bg-muted/40 text-foreground">ADU</th>
                                <th class="py-3 px-4 text-right">Review Period</th>
                                <th class="py-3 px-4 text-right bg-muted/40 text-foreground font-bold">Batas Minimum</th>
                                <th class="py-3 px-4 text-right font-bold text-foreground">Target Stock</th>
                                <th class="py-3 px-4 text-right">Generated At</th>
                                <th class="py-3 px-3 text-center w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-card">
                            <template x-for="r in paginatedRows()" :key="r.id">
                                <tr class="hover:bg-muted/40 transition">
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-foreground" x-text="r.nama"></div>
                                        <div class="font-mono text-[10px] text-muted-foreground" x-text="r.sku + ' · Satuan: ' + r.satuan"></div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-foreground" x-text="r.supplier_nama || '-'"></div>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono text-muted-foreground" x-text="r.total_avg_lead_time"></td>
                                    <td class="py-3 px-4 text-right font-mono text-muted-foreground" x-text="r.safety_stock"></td>
                                    <td class="py-3 px-4 text-right font-mono text-foreground" x-text="formatNumber(r.terjual_rata_rata_4bulan)"></td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-foreground bg-muted/40" x-text="formatNumber(r.adu)"></td>
                                    <td class="py-3 px-4 text-right font-mono text-muted-foreground" x-text="r.review_period + ' Hari'"></td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-foreground bg-muted/40" x-text="formatNumber(r.batas_minimum) + ' ' + r.satuan"></td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-foreground" x-text="formatNumber(r.target_stock) + ' ' + r.satuan"></td>
                                    <td class="py-3 px-4 text-right font-mono text-[11px] text-muted-foreground" x-text="r.generated_at"></td>
                                    <td class="py-3 px-3 text-center">
                                        <button type="button" @click="openManualLokalModal(r)" class="px-2.5 py-1 bg-muted hover:bg-muted/80 text-foreground rounded-lg border border-border text-[10px] font-medium transition cursor-pointer" title="Edit Terjual 4 Bulan & Review Period">
                                            Edit Input
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="paginatedRows().length === 0">
                                <tr>
                                    <td colspan="11" class="py-8 text-center text-muted-foreground text-xs">
                                        Tidak ada data working analisa yang cocok.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Bar --}}
                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground">
                        <div class="flex items-center gap-2 font-mono text-[11px]">
                            <span>Menampilkan <strong class="text-foreground" x-text="startItem()"></strong> - <strong class="text-foreground" x-text="endItem()"></strong> dari <strong class="text-foreground" x-text="currentDataLength()"></strong> item</span>
                        </div>

                        <div class="flex items-center gap-1.5" x-show="totalPages() > 1">
                            <button 
                                type="button" 
                                @click="gotoPage(page - 1)" 
                                :disabled="page <= 1"
                                class="px-2.5 py-1 text-xs rounded-lg border border-border bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                &laquo; Prev
                            </button>

                            <template x-for="p in pagesList()" :key="p">
                                <div>
                                    <template x-if="p === '...'">
                                        <span class="px-2 py-1 text-muted-foreground">...</span>
                                    </template>
                                    <template x-if="p !== '...'">
                                        <button 
                                            type="button" 
                                            @click="gotoPage(p)" 
                                            :class="page === p ? 'bg-primary text-primary-foreground font-semibold border-primary' : 'bg-card text-foreground hover:bg-muted border-border'"
                                            class="min-w-[28px] px-2 py-1 text-xs rounded-lg border transition cursor-pointer"
                                            x-text="p"
                                        ></button>
                                    </template>
                                </div>
                            </template>

                            <button 
                                type="button" 
                                @click="gotoPage(page + 1)" 
                                :disabled="page >= totalPages()"
                                class="px-2.5 py-1 text-xs rounded-lg border border-border bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                Next &raquo;
                            </button>
                        </div>
                    </div>
                </x-slot:footer>
            </x-card>
        </div>

        {{-- TABEL 3: Ringkasan Lead Time (lead_time_lokal) --}}
        <div x-show="lokalSubTab === 'lead_time'">
            <x-card 
                title="Tabel Ringkasan Lead Time Lokal (lead_time_lokal)" 
                subtitle="Ringkasan lead time average, lead time max, tambahan buffer, dan Safety Stock = (Max - Avg) + Buffer"
                :noPadding="true"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-muted/70 text-muted-foreground text-[11px] uppercase font-semibold border-b border-border tracking-wider">
                            <tr>
                                <th class="py-3 px-4">Bahan Baku</th>
                                <th class="py-3 px-4">Supplier</th>
                                <th class="py-3 px-4 text-right">Total Avg Lead Time</th>
                                <th class="py-3 px-4 text-right">Total Max Lead Time</th>
                                <th class="py-3 px-4 text-right">Tambahan Buffer</th>
                                <th class="py-3 px-4 text-right bg-muted/40 text-foreground font-bold">Safety Stock (Hari)</th>
                                <th class="py-3 px-3 text-center w-28">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-card">
                            <template x-for="r in paginatedRows()" :key="r.id">
                                <tr class="hover:bg-muted/40 transition">
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-foreground" x-text="r.nama"></div>
                                        <div class="font-mono text-[10px] text-muted-foreground" x-text="r.sku + ' · Satuan: ' + r.satuan"></div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-foreground" x-text="r.supplier_nama || '-'"></div>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono text-muted-foreground" x-text="r.total_avg_lead_time + ' Hari'"></td>
                                    <td class="py-3 px-4 text-right font-mono text-muted-foreground" x-text="r.total_max_lead_time + ' Hari'"></td>
                                    <td class="py-3 px-4 text-right font-mono text-foreground" x-text="r.tambahan_buffer_hari + ' Hari'"></td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-foreground bg-muted/40" x-text="r.safety_stock + ' Hari'"></td>
                                    <td class="py-3 px-3 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" @click="openManualLokalModal(r)" class="px-2.5 py-1 bg-muted hover:bg-muted/80 text-foreground rounded-lg border border-border text-[10px] font-medium transition cursor-pointer" title="Edit Tambahan Buffer">
                                                Analisa
                                            </button>
                                            <button type="button" @click="openStageModal(r)" class="px-2.5 py-1 bg-muted hover:bg-muted/80 text-foreground rounded-lg border border-border text-[10px] font-medium transition cursor-pointer" title="Edit 9 Tahap Lead Time">
                                                LT
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="paginatedRows().length === 0">
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-muted-foreground text-xs">
                                        Tidak ada data ringkasan lead time yang cocok.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Bar --}}
                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground">
                        <div class="flex items-center gap-2 font-mono text-[11px]">
                            <span>Menampilkan <strong class="text-foreground" x-text="startItem()"></strong> - <strong class="text-foreground" x-text="endItem()"></strong> dari <strong class="text-foreground" x-text="currentDataLength()"></strong> item</span>
                        </div>

                        <div class="flex items-center gap-1.5" x-show="totalPages() > 1">
                            <button 
                                type="button" 
                                @click="gotoPage(page - 1)" 
                                :disabled="page <= 1"
                                class="px-2.5 py-1 text-xs rounded-lg border border-border bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                &laquo; Prev
                            </button>

                            <template x-for="p in pagesList()" :key="p">
                                <div>
                                    <template x-if="p === '...'">
                                        <span class="px-2 py-1 text-muted-foreground">...</span>
                                    </template>
                                    <template x-if="p !== '...'">
                                        <button 
                                            type="button" 
                                            @click="gotoPage(p)" 
                                            :class="page === p ? 'bg-primary text-primary-foreground font-semibold border-primary' : 'bg-card text-foreground hover:bg-muted border-border'"
                                            class="min-w-[28px] px-2 py-1 text-xs rounded-lg border transition cursor-pointer"
                                            x-text="p"
                                        ></button>
                                    </template>
                                </div>
                            </template>

                            <button 
                                type="button" 
                                @click="gotoPage(page + 1)" 
                                :disabled="page >= totalPages()"
                                class="px-2.5 py-1 text-xs rounded-lg border border-border bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                Next &raquo;
                            </button>
                        </div>
                    </div>
                </x-slot:footer>
            </x-card>
        </div>

        {{-- TABEL 4: Rincian 9 Tahap Lead Time (lead_time_lokal_stage) --}}
        <div x-show="lokalSubTab === 'stages'" class="space-y-4">
            {{-- Highlight Summary: Lead Time Terlama (Average & Max) --}}
            <template x-if="longestAvgStage() || longestMaxStage()">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-card :noPadding="true">
                        <div class="px-4 py-3 bg-muted/40 border-b border-border flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <svg class="size-4 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h4 class="text-xs font-semibold text-foreground uppercase tracking-wider">Lead Time Terlama (Average)</h4>
                            </div>
                            <span class="px-2.5 py-1 text-xs font-bold font-mono bg-primary text-primary-foreground rounded-lg shadow-xs" x-text="(longestAvgStage()?.days || 0) + ' Hari'"></span>
                        </div>
                        <div class="p-4 bg-card text-xs">
                            <table class="w-full text-left">
                                <tbody class="divide-y divide-border">
                                    <tr>
                                        <td class="py-2 text-muted-foreground font-medium w-28">Bahan Baku</td>
                                        <td class="py-2 text-foreground text-right">
                                            <span class="font-bold text-foreground" x-text="longestAvgStage()?.nama || '-'"></span>
                                            <template x-if="longestAvgStage()?.sku">
                                                <span class="font-mono text-muted-foreground text-[11px] ml-1" x-text="'(' + longestAvgStage().sku + ')'"></span>
                                            </template>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-muted-foreground font-medium w-28">Supplier</td>
                                        <td class="py-2 text-foreground font-semibold text-right" x-text="longestAvgStage()?.supplier_nama || '-'"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </x-card>

                    <x-card :noPadding="true">
                        <div class="px-4 py-3 bg-muted/40 border-b border-border flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <svg class="size-4 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <h4 class="text-xs font-semibold text-foreground uppercase tracking-wider">Lead Time Terlama (Max Skenario)</h4>
                            </div>
                            <span class="px-2.5 py-1 text-xs font-bold font-mono bg-foreground text-background rounded-lg shadow-xs" x-text="(longestMaxStage()?.days || 0) + ' Hari'"></span>
                        </div>
                        <div class="p-4 bg-card text-xs">
                            <table class="w-full text-left">
                                <tbody class="divide-y divide-border">
                                    <tr>
                                        <td class="py-2 text-muted-foreground font-medium w-28">Bahan Baku</td>
                                        <td class="py-2 text-foreground text-right">
                                            <span class="font-bold text-foreground" x-text="longestMaxStage()?.nama || '-'"></span>
                                            <template x-if="longestMaxStage()?.sku">
                                                <span class="font-mono text-muted-foreground text-[11px] ml-1" x-text="'(' + longestMaxStage().sku + ')'"></span>
                                            </template>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-muted-foreground font-medium w-28">Supplier</td>
                                        <td class="py-2 text-foreground font-semibold text-right" x-text="longestMaxStage()?.supplier_nama || '-'"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </div>
            </template>

            <x-card 
                title="Tabel Rincian 9 Tahap Lead Time (lead_time_lokal_stage)" 
                subtitle="Perencanaan, Approval, Supplier Confirm, Payment, PO, Pengemasan, Pengiriman, Unloading, dan Input"
                :noPadding="true"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-muted/70 text-muted-foreground text-[10px] uppercase font-semibold border-b border-border tracking-wider">
                            <tr>
                                <th class="py-3 px-3">Bahan</th>
                                <th class="py-3 px-3">Supplier</th>
                                <th class="py-3 px-3">Skenario</th>
                                <th class="py-3 px-2 text-right">Rencana</th>
                                <th class="py-3 px-2 text-right">Apprv</th>
                                <th class="py-3 px-2 text-right">Confirm</th>
                                <th class="py-3 px-2 text-right">Bayar</th>
                                <th class="py-3 px-2 text-right">PO</th>
                                <th class="py-3 px-2 text-right">Kemas</th>
                                <th class="py-3 px-2 text-right">Kirim</th>
                                <th class="py-3 px-2 text-right">Unload</th>
                                <th class="py-3 px-2 text-right">Input</th>
                                <th class="py-3 px-3 text-right bg-muted/40 font-bold text-foreground">Total</th>
                                <th class="py-3 px-3 text-center w-20">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-card">
                            <template x-for="stg in paginatedStages()" :key="stg.produk_id">
                                <template x-for="type in ['average', 'max']" :key="type">
                                    <tr class="hover:bg-muted/40 transition" :class="type === 'max' ? 'border-b-2 border-border' : ''">
                                        <td class="py-2.5 px-3" x-show="type === 'average'" rowspan="2">
                                            <div class="font-bold text-foreground text-xs" x-text="stg.nama"></div>
                                            <div class="font-mono text-[10px] text-muted-foreground" x-text="stg.sku"></div>
                                        </td>
                                        <td class="py-2.5 px-3" x-show="type === 'average'" rowspan="2">
                                            <div class="font-medium text-foreground text-xs" x-text="stg.supplier_nama || '-'"></div>
                                        </td>
                                        <td class="py-2.5 px-3 font-mono uppercase text-[10px] font-bold text-muted-foreground" x-text="type"></td>
                                        <td class="py-2.5 px-2 text-right font-mono text-muted-foreground" x-text="stg[type] ? stg[type].perencanaan : 0"></td>
                                        <td class="py-2.5 px-2 text-right font-mono text-muted-foreground" x-text="stg[type] ? stg[type].approval : 0"></td>
                                        <td class="py-2.5 px-2 text-right font-mono text-muted-foreground" x-text="stg[type] ? stg[type].supplier_confirm : 0"></td>
                                        <td class="py-2.5 px-2 text-right font-mono text-muted-foreground" x-text="stg[type] ? stg[type].payment : 0"></td>
                                        <td class="py-2.5 px-2 text-right font-mono text-muted-foreground" x-text="stg[type] ? stg[type].po : 0"></td>
                                        <td class="py-2.5 px-2 text-right font-mono text-muted-foreground" x-text="stg[type] ? stg[type].pengemasan : 0"></td>
                                        <td class="py-2.5 px-2 text-right font-mono text-muted-foreground" x-text="stg[type] ? stg[type].pengiriman : 0"></td>
                                        <td class="py-2.5 px-2 text-right font-mono text-muted-foreground" x-text="stg[type] ? stg[type].unloading : 0"></td>
                                        <td class="py-2.5 px-2 text-right font-mono text-muted-foreground" x-text="stg[type] ? stg[type].input : 0"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-foreground bg-muted/40" x-text="(stg[type] ? stg[type].total : 0) + ' Hari'"></td>
                                        <td class="py-2.5 px-3 text-center" x-show="type === 'average'" rowspan="2">
                                            <button type="button" @click="openStageModal(stg)" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium rounded-lg border border-border bg-muted hover:bg-muted/80 text-foreground transition cursor-pointer" title="Edit Rincian 9 Tahap Lead Time">
                                                <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </template>
                            <template x-if="paginatedStages().length === 0">
                                <tr>
                                    <td colspan="14" class="py-8 text-center text-muted-foreground text-xs">
                                        Tidak ada rincian tahap lead time yang cocok.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Bar --}}
                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground">
                        <div class="flex items-center gap-2 font-mono text-[11px]">
                            <span>Menampilkan <strong class="text-foreground" x-text="startItem()"></strong> - <strong class="text-foreground" x-text="endItem()"></strong> dari <strong class="text-foreground" x-text="currentDataLength()"></strong> item</span>
                        </div>

                        <div class="flex items-center gap-1.5" x-show="totalPages() > 1">
                            <button 
                                type="button" 
                                @click="gotoPage(page - 1)" 
                                :disabled="page <= 1"
                                class="px-2.5 py-1 text-xs rounded-lg border border-border bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                &laquo; Prev
                            </button>

                            <template x-for="p in pagesList()" :key="p">
                                <div>
                                    <template x-if="p === '...'">
                                        <span class="px-2 py-1 text-muted-foreground">...</span>
                                    </template>
                                    <template x-if="p !== '...'">
                                        <button 
                                            type="button" 
                                            @click="gotoPage(p)" 
                                            :class="page === p ? 'bg-primary text-primary-foreground font-semibold border-primary' : 'bg-card text-foreground hover:bg-muted border-border'"
                                            class="min-w-[28px] px-2 py-1 text-xs rounded-lg border transition cursor-pointer"
                                            x-text="p"
                                        ></button>
                                    </template>
                                </div>
                            </template>

                            <button 
                                type="button" 
                                @click="gotoPage(page + 1)" 
                                :disabled="page >= totalPages()"
                                class="px-2.5 py-1 text-xs rounded-lg border border-border bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                Next &raquo;
                            </button>
                        </div>
                    </div>
                </x-slot:footer>
            </x-card>
        </div>
    </div>
</template>

{{-- Section: Bahan Baku Impor (analisa_impor & lead_time_impor) --}}
<template x-if="tab === 'impor'">
    <div class="space-y-4">
        <x-card 
            title="Tabel Analisa & Rekomendasi Pemesanan Bahan Impor (analisa_impor & lead_time_impor)"
            subtitle="Lead Time (Avg/Max), Buffer Hari ABC (+4d/+2d/+0d), Safety Stock, Target Stock, Inbound ETA, Proyeksi, dan Rekomendasi Order MOQ"
            :noPadding="true"
        >
            {{-- Loading Spinner --}}
            <div x-show="loading" class="py-12 flex flex-col items-center justify-center text-muted-foreground">
                <svg class="animate-spin h-6 w-6 text-foreground mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-xs">Memuat kalkulasi data analisa impor...</span>
            </div>

            <div x-show="!loading" class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-muted/70 text-muted-foreground text-[11px] uppercase font-semibold border-b border-border tracking-wider">
                        <tr>
                            <th class="py-3 px-3 text-center w-10">
                                <input type="checkbox" @change="toggleSelectCurrentPage($event)" :checked="isCurrentPageAllSelected()" class="rounded-md border-input text-foreground focus:ring-ring cursor-pointer">
                            </th>
                            <th class="py-3 px-4">Bahan Baku Impor</th>
                            <th class="py-3 px-4">Supplier</th>
                            <th class="py-3 px-3 text-center">ABC</th>
                            <th class="py-3 px-4 text-center">Lead Time (Avg/Max)</th>
                            <th class="py-3 px-4 text-right">Buffer</th>
                            <th class="py-3 px-4 text-right">Safety Stock</th>
                            <th class="py-3 px-4 text-right">Min Stock</th>
                            <th class="py-3 px-4 text-right">Target</th>
                            <th class="py-3 px-4 text-right">Stok Fisik</th>
                            <th class="py-3 px-4 text-right">Inbound ETA</th>
                            <th class="py-3 px-4 text-right">Proyeksi</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right bg-muted/40 text-foreground font-bold">Rekomendasi Order</th>
                            <th class="py-3 px-4 text-right">Estimasi Nominal</th>
                            <th class="py-3 px-3 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card">
                        <template x-for="r in paginatedRows()" :key="r.id">
                            <tr class="hover:bg-muted/40 transition" :class="r.status === 'po' ? 'bg-destructive/5' : ''">
                                <td class="py-3 px-3 text-center">
                                    <input type="checkbox" :value="r.id" x-model="selectedItemIds" class="rounded-md border-input text-foreground focus:ring-ring cursor-pointer">
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-foreground" x-text="r.nama"></div>
                                    <div class="flex items-center gap-1.5 font-mono text-[10px] text-muted-foreground mt-0.5">
                                        <span x-text="r.sku"></span>
                                        <span>·</span>
                                        <span x-text="'Satuan: ' + r.satuan"></span>
                                    </div>
                                    <template x-if="r.periode_mulai && r.periode_akhir">
                                        <div class="text-[9px] text-muted-foreground font-mono mt-0.5" x-text="'Periode: ' + r.periode_mulai + ' ~ ' + r.periode_akhir + ' (' + r.jumlah_hari_periode + ' hr)'"></div>
                                    </template>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="font-medium text-foreground" x-text="r.supplier_nama || '-'"></div>
                                    <template x-if="r.supplier_kategori && r.supplier_kategori !== '-'">
                                        <div class="text-[10px] text-muted-foreground font-mono mt-0.5" x-text="r.supplier_kategori"></div>
                                    </template>
                                </td>
                                <td class="py-3 px-3 text-center font-mono">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase" :class="abcBadgeClass(r.klasifikasi_abc)" :title="r.klasifikasi_abc_nama || r.klasifikasi_abc" x-text="r.klasifikasi_abc"></span>
                                </td>
                                <td class="py-3 px-4 text-center font-mono text-muted-foreground" x-text="formatNumber(r.lead_time_average) + ' / ' + formatNumber(r.lead_time_max) + ' hr'"></td>
                                <td class="py-3 px-4 text-right font-mono text-muted-foreground" x-text="formatNumber(r.buffer_days) + ' hr'"></td>
                                <td class="py-3 px-4 text-right font-mono text-foreground" x-text="formatNumber(r.safety_stock)"></td>
                                <td class="py-3 px-4 text-right font-mono text-foreground" x-text="formatNumber(r.minimum_stock)"></td>
                                <td class="py-3 px-4 text-right font-mono font-medium text-foreground" x-text="formatNumber(r.target_stock)"></td>
                                <td class="py-3 px-4 text-right font-mono text-foreground" x-text="formatNumber(r.stok_saat_ini)"></td>
                                <td class="py-3 px-4 text-right font-mono text-muted-foreground" x-text="'+' + formatNumber(r.inbound_before_eta)"></td>
                                <td class="py-3 px-4 text-right font-mono" :class="r.proyeksi < r.target_stock ? 'text-destructive font-bold' : 'text-foreground'" x-text="formatNumber(r.proyeksi)"></td>
                                <td class="py-3 px-4 text-center" x-html="badge(r.status)"></td>
                                <td class="py-3 px-4 text-right font-mono font-bold bg-muted/40 text-foreground">
                                    <span x-text="formatNumber(Math.round(r.total_qty_order))"></span>
                                    <template x-if="r.satuan_order_moq && r.satuan_order_moq > 1">
                                        <span class="text-[10px] font-normal text-muted-foreground block" x-text="'MOQ: ' + formatNumber(r.satuan_order_moq)"></span>
                                    </template>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-foreground" x-text="'Rp ' + formatNumber(r.total_nominal_order)"></td>
                                <td class="py-3 px-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button" @click="openManualImporModal(r)" class="px-2.5 py-1 bg-muted hover:bg-muted/80 text-foreground rounded-lg border border-border text-[10px] font-medium transition cursor-pointer" title="Edit Data Input Manual Impor">
                                            Edit Input
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="paginatedRows().length === 0">
                            <tr>
                                <td colspan="17" class="py-8 text-center text-muted-foreground text-xs">
                                    Tidak ada item bahan impor yang cocok dengan pencarian atau filter.
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Pagination Bar --}}
            <x-slot:footer>
                <div x-show="!loading && rows.length > 0" class="flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground">
                    <div class="flex items-center gap-2 font-mono text-[11px]">
                        <span>Menampilkan <strong class="text-foreground" x-text="startItem()"></strong> - <strong class="text-foreground" x-text="endItem()"></strong> dari <strong class="text-foreground" x-text="currentDataLength()"></strong> item</span>
                        <template x-if="searchQuery || statusFilter !== 'all'">
                            <span class="text-muted-foreground font-normal">(difilter dari <span x-text="currentRawLength()"></span> total)</span>
                        </template>
                    </div>

                    <div class="flex items-center gap-1.5" x-show="totalPages() > 1">
                        <button type="button" @click="gotoPage(page - 1)" :disabled="page <= 1" class="px-2.5 py-1 text-xs rounded-lg border border-border bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                            &laquo; Prev
                        </button>

                        <template x-for="p in pagesList()" :key="p">
                            <div>
                                <template x-if="p === '...'">
                                    <span class="px-2 py-1 text-muted-foreground">...</span>
                                </template>
                                <template x-if="p !== '...'">
                                    <button type="button" @click="gotoPage(p)" :class="page === p ? 'bg-primary text-primary-foreground font-semibold border-primary' : 'bg-card text-foreground hover:bg-muted border-border'" class="min-w-[28px] px-2 py-1 text-xs rounded-lg border transition cursor-pointer" x-text="p"></button>
                                </template>
                            </div>
                        </template>

                        <button type="button" @click="gotoPage(page + 1)" :disabled="page >= totalPages()" class="px-2.5 py-1 text-xs rounded-lg border border-border bg-card text-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                            Next &raquo;
                        </button>
                    </div>
                </div>
            </x-slot:footer>
        </x-card>
    </div>
</template>

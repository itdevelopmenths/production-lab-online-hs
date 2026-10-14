{{-- Section: Log Riwayat Snapshot (riwayat_analisa) --}}
<template x-if="tab === 'riwayat'">
    <x-card 
        title="Log Riwayat Snapshot Analisa (riwayat_analisa)" 
        subtitle="Audit trail arsip historis snapshot terkunci (locked) lengkap dengan kode sesi batch finalisasi" 
        :noPadding="true"
    >
        {{-- Loading Spinner --}}
        <div x-show="loading" class="py-12 flex flex-col items-center justify-center text-muted-foreground">
            <svg class="animate-spin h-6 w-6 text-foreground mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-xs">Memuat data riwayat snapshot...</span>
        </div>

        <div x-show="!loading" class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-muted/70 text-muted-foreground text-[11px] uppercase font-semibold border-b border-border tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Kode Sesi (Session ID)</th>
                        <th class="py-3 px-4">Tipe Analisa</th>
                        <th class="py-3 px-4">Item / SKU</th>
                        <th class="py-3 px-4 text-right">Batas Min</th>
                        <th class="py-3 px-4 text-right">Target</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right font-bold text-foreground">Qty Order</th>
                        <th class="py-3 px-4">Pencatat</th>
                        <th class="py-3 px-3 text-center">Status Kunci</th>
                        <th class="py-3 px-3 text-center w-16">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border bg-card">
                    <template x-for="r in paginatedRows()" :key="r.id">
                        <tr class="hover:bg-muted/40 transition">
                            <td class="py-3 px-4 font-mono text-muted-foreground" x-text="r.tanggal"></td>
                            <td class="py-3 px-4 font-mono text-[11px]">
                                <span class="px-2 py-0.5 rounded-md bg-muted text-foreground border border-border" x-text="r.session_id"></span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-muted text-foreground border border-border capitalize" x-text="r.tipe"></span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-foreground" x-text="r.item_label"></div>
                                <template x-if="r.detail && r.detail.nama">
                                    <div class="text-[10px] text-muted-foreground mt-0.5" x-text="r.detail.nama"></div>
                                </template>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-foreground" x-text="formatNumber(r.batas_minimum)"></td>
                            <td class="py-3 px-4 text-right font-mono text-foreground" x-text="formatNumber(r.target_stock)"></td>
                            <td class="py-3 px-4 text-center" x-html="badge(r.status)"></td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-foreground" x-text="formatNumber(r.qty_order)"></td>
                            <td class="py-3 px-4 text-muted-foreground" x-text="r.pencatat"></td>
                            <td class="py-3 px-3 text-center">
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                                    <svg class="size-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    Locked
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <template x-if="r.detail && Object.keys(r.detail).length > 0">
                                    <button type="button" @click="openSnapshotModal(r)" class="px-2.5 py-1 bg-muted hover:bg-muted/80 text-foreground rounded-lg border border-border text-[10px] font-medium transition cursor-pointer" title="Lihat Snapshot Detail">
                                        Lihat
                                    </button>
                                </template>
                                <template x-if="!r.detail || Object.keys(r.detail).length === 0">
                                    <span class="text-muted-foreground">-</span>
                                </template>
                            </td>
                        </tr>
                    </template>
                    <template x-if="paginatedRows().length === 0">
                        <tr>
                            <td colspan="11" class="py-8 text-center text-muted-foreground text-xs">
                                Tidak ada data log snapshot riwayat yang cocok.
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
</template>

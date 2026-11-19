{{-- Section: Log Riwayat Snapshot (riwayat_analisa) --}}
<template x-if="tab === 'riwayat'">
    <x-card 
        title="Log Riwayat Snapshot Analisa (riwayat_analisa)" 
        subtitle="Audit trail arsip historis snapshot terkunci (locked) lengkap dengan kode sesi batch finalisasi" 
        :noPadding="true"
    >
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </x-slot:icon>

        {{-- Loading Spinner --}}
        <div x-show="loading" class="py-12 flex flex-col items-center justify-center text-[#667085] dark:text-gray-400">
            <svg class="animate-spin h-6 w-6 text-[#111111] dark:text-white mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-xs">Memuat data riwayat snapshot...</span>
        </div>

        <div x-show="!loading" tabindex="0" role="region" aria-label="Log Riwayat Snapshot Analisa" class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[13px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                    <tr>
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Kode Sesi (Session ID)</th>
                        <th class="py-3 px-4">Tipe Analisa</th>
                        <th class="py-3 px-4">Item / SKU</th>
                        <th class="py-3 px-4">Supplier</th>
                        <th class="py-3 px-4 text-center">Avg / Max LT Dipakai</th>
                        <th class="py-3 px-3 text-center">Sumber LT</th>
                        <th class="py-3 px-4 text-right">Batas Min</th>
                        <th class="py-3 px-4 text-right">Target</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right font-semibold text-[#101828] dark:text-white">Qty Order</th>
                        <th class="py-3 px-4">Pencatat</th>
                        <th class="py-3 px-3 text-center">Status Kunci</th>
                        <th class="py-3 px-3 text-center w-16">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 bg-white dark:bg-gray-900">
                    <template x-for="r in paginatedRows()" :key="r.id">
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-3 px-4 font-mono text-xs text-[#667085] dark:text-gray-400" x-text="r.tanggal"></td>
                            <td class="py-3 px-4 font-mono text-[11px]">
                                <span class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-[#101828] dark:text-gray-200 border border-[#E8E8E8] dark:border-gray-700" x-text="r.session_id"></span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 dark:bg-gray-800 text-[#101828] dark:text-gray-200 border border-[#E8E8E8] dark:border-gray-700 capitalize" x-text="r.tipe"></span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-[#101828] dark:text-white" x-text="r.item_label"></div>
                                <template x-if="r.detail && r.detail.nama">
                                    <div class="text-[11px] text-[#667085] dark:text-gray-400 mt-0.5" x-text="r.detail.nama"></div>
                                </template>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-[#101828] dark:text-white text-xs" x-text="r.supplier_nama || '-'"></div>
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-xs text-[#667085] dark:text-gray-400">
                                <template x-if="r.average_lt_dipakai !== null && r.max_lt_dipakai !== null">
                                    <span x-text="formatLt(r.average_lt_dipakai) + ' / ' + formatLt(r.max_lt_dipakai) + ' hr'"></span>
                                </template>
                                <template x-if="r.average_lt_dipakai === null || r.max_lt_dipakai === null">
                                    <span>-</span>
                                </template>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <template x-if="r.sumber_lt === 'otomatis'">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50" title="Sumber: Histori PO Otomatis">
                                        <span class="size-1 rounded-full bg-emerald-500 shrink-0"></span>
                                        <span>Otomatis</span>
                                    </span>
                                </template>
                                <template x-if="r.sumber_lt === 'manual'">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50" title="Sumber: Estimasi Input Manual">
                                        <span class="size-1 rounded-full bg-amber-500 shrink-0"></span>
                                        <span>Manual</span>
                                    </span>
                                </template>
                                <template x-if="r.sumber_lt !== 'otomatis' && r.sumber_lt !== 'manual'">
                                    <span class="text-[#667085] dark:text-gray-400 font-mono text-[10px]" x-text="r.sumber_lt"></span>
                                </template>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-[#101828] dark:text-white" x-text="formatNumber(r.batas_minimum)"></td>
                            <td class="py-3 px-4 text-right font-mono text-[#101828] dark:text-white" x-text="formatNumber(r.target_stock)"></td>
                            <td class="py-3 px-4 text-center" x-html="badge(r.status)"></td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-[#101828] dark:text-white" x-text="formatNumber(r.qty_order)"></td>
                            <td class="py-3 px-4 text-xs text-[#667085] dark:text-gray-400" x-text="r.pencatat"></td>
                            <td class="py-3 px-3 text-center">
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 px-2 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800/50">
                                    <svg class="size-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    Locked
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <template x-if="r.detail && Object.keys(r.detail).length > 0">
                                    <button type="button" @click="openSnapshotModal(r)" class="px-2.5 py-1 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-[#101828] dark:text-gray-200 rounded-lg border border-[#E8E8E8] dark:border-gray-700 text-[11px] font-medium transition cursor-pointer" title="Lihat Snapshot Detail">
                                        Lihat
                                    </button>
                                </template>
                                <template x-if="!r.detail || Object.keys(r.detail).length === 0">
                                    <span class="text-[#667085] dark:text-gray-400">-</span>
                                </template>
                            </td>
                        </tr>
                    </template>
                    <template x-if="paginatedRows().length === 0">
                        <tr>
                            <td colspan="14" class="py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                Tidak ada data log snapshot riwayat yang cocok.
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination Bar --}}
        <x-slot:footer>
            <div x-show="!loading && rows.length > 0" class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#667085] dark:text-gray-400">
                <div class="flex items-center gap-2 font-mono text-[11px]">
                    <span>Menampilkan <strong class="text-[#101828] dark:text-white" x-text="startItem()"></strong> - <strong class="text-[#101828] dark:text-white" x-text="endItem()"></strong> dari <strong class="text-[#101828] dark:text-white" x-text="currentDataLength()"></strong> item</span>
                </div>

                <div class="flex items-center gap-1.5" x-show="totalPages() > 1">
                    <button type="button" @click="gotoPage(page - 1)" :disabled="page <= 1" class="px-2.5 py-1 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-[#101828] dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                        &laquo; Back
                    </button>

                    <template x-for="p in pagesList()" :key="p">
                        <div>
                            <template x-if="p === '...'">
                                <span class="px-2 py-1 text-[#667085] dark:text-gray-500">...</span>
                            </template>
                            <template x-if="p !== '...'">
                                <button type="button" @click="gotoPage(p)" :class="page === p ? 'bg-[#111111] dark:bg-white text-white dark:text-[#111111] font-semibold border-[#111111] dark:border-white' : 'bg-white dark:bg-gray-900 text-[#101828] dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 border-[#E8E8E8] dark:border-gray-700'" class="min-w-[28px] px-2 py-1 text-xs rounded-lg border transition cursor-pointer" x-text="p"></button>
                            </template>
                        </div>
                    </template>

                    <button type="button" @click="gotoPage(page + 1)" :disabled="page >= totalPages()" class="px-2.5 py-1 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-[#101828] dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                        Next &raquo;
                    </button>
                </div>
            </div>
        </x-slot:footer>
    </x-card>
</template>

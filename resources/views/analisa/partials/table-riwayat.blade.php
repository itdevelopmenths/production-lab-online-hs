{{-- Section: Log Riwayat Snapshot (riwayat_analisa) --}}
<template x-if="tab === 'riwayat'">
    <x-card class="tbl-modern"
        title="Log Riwayat Snapshot Analisa"
        subtitle="Arsip snapshot terkunci, dikelompokkan per bulan lalu per sesi finalisasi"
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

        {{-- Pengelompokan: Bulan → Sesi finalisasi --}}
        <div x-show="!loading" class="px-3 sm:px-4 py-3 border-b border-[#E8E8E8] dark:border-gray-800 space-y-3">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <label for="riwayat-bulan" class="text-[11px] text-[#667085] dark:text-gray-400">Bulan</label>
                <select id="riwayat-bulan" :value="riwayatBulan" @change="pilihRiwayatBulan($event.target.value)"
                        class="h-8 min-w-48 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs text-[#101828] dark:text-gray-200 px-2.5 rounded-[6px] focus:outline-none focus:ring-1 focus:ring-gray-400">
                    <template x-if="(riwayatInfo.bulan_list || []).length === 0">
                        <option value="">Belum ada snapshot</option>
                    </template>
                    <template x-for="b in (riwayatInfo.bulan_list || [])" :key="b.key">
                        <option :value="b.key" :selected="b.key === riwayatBulan" x-text="`Analisa ${b.label} (${b.jumlah_sesi} sesi, ${b.jumlah_item} item)`"></option>
                    </template>
                </select>
                <span class="text-[11px] text-[#667085] dark:text-gray-400 tabular-nums" x-show="(riwayatInfo.sessions || []).length > 0">
                    <span x-text="(riwayatInfo.sessions || []).length"></span> sesi finalisasi di <span class="font-medium text-[#101828] dark:text-gray-200" x-text="riwayatInfo.bulan_label"></span>
                </span>
            </div>

            <div class="flex flex-wrap gap-2" x-show="(riwayatInfo.sessions || []).length > 0" role="group" aria-label="Pilih sesi finalisasi">
                <button type="button" @click="riwayatSession = 'all'; page = 1"
                        :aria-pressed="riwayatSession === 'all'"
                        :class="riwayatSession === 'all' ? 'border-[#111111] dark:border-white bg-[#111111] dark:bg-white text-white dark:text-[#111111]' : 'border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-[#101828] dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800'"
                        class="px-3 py-2 text-left border transition cursor-pointer rounded-[8px]">
                    <span class="block text-xs font-medium">Semua sesi</span>
                    <span class="block text-[11px] opacity-70 tabular-nums" x-text="`${rows.length} item`"></span>
                </button>
                <template x-for="s in (riwayatInfo.sessions || [])" :key="s.key">
                    <button type="button" @click="pilihRiwayatSesi(s.key)"
                            :aria-pressed="riwayatSession === s.key"
                            :title="s.session_id"
                            :class="riwayatSession === s.key ? 'border-[#111111] dark:border-white bg-[#111111] dark:bg-white text-white dark:text-[#111111]' : 'border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-[#101828] dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800'"
                            class="px-3 py-2 text-left border transition cursor-pointer rounded-[8px] max-w-full">
                        <span class="block text-xs font-medium tabular-nums">
                            <span x-text="s.tanggal"></span><span x-show="s.waktu" x-text="' · ' + s.waktu"></span><span x-text="' · ' + s.pencatat"></span>
                        </span>
                        <span class="block text-[11px] opacity-70 truncate" x-text="(s.tipe_labels || []).join(', ')"></span>
                        <span class="block text-[11px] opacity-70 tabular-nums" x-text="`${s.jumlah_item} item · ${s.jumlah_order} perlu order`"></span>
                    </button>
                </template>
            </div>
        </div>

        <div x-show="!loading" tabindex="0" role="region" aria-label="Log Riwayat Snapshot Analisa" class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-xs font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                    <tr>
                        <th class="py-3 px-2.5">Tanggal</th>
                        <th class="py-3 px-2.5">Kode Sesi (Session ID)</th>
                        <th class="py-3 px-2.5">Tipe Analisa</th>
                        <th class="py-3 px-2.5">Item / SKU</th>
                        <th class="py-3 px-2.5">Supplier</th>
                        <th class="py-3 px-2.5 text-center">Avg / Max LT Dipakai</th>
                        <th class="py-3 px-2 text-center">Sumber LT</th>
                        <th class="py-3 px-2.5 text-right">Batas Min</th>
                        <th class="py-3 px-2.5 text-right">Target</th>
                        <th class="py-3 px-2.5 text-center">Status</th>
                        <th class="py-3 px-2.5 text-right font-medium text-[#101828] dark:text-white">Qty Order</th>
                        <th class="py-3 px-2.5">Pencatat</th>
                        <th class="py-3 px-2 text-center">Status Kunci</th>
                        <th class="py-3 px-2 text-center w-16">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 bg-white dark:bg-gray-900">
                    <template x-for="r in paginatedRows()" :key="r.id">
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-3 px-2.5 tabular-nums text-xs text-[#667085] dark:text-gray-400" x-text="r.tanggal"></td>
                            <td class="py-3 px-2.5 tabular-nums text-[11px]">
                                <span class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-[#101828] dark:text-gray-200 border border-[#E8E8E8] dark:border-gray-700" x-text="r.session_id"></span>
                            </td>
                            <td class="py-3 px-2.5">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 dark:bg-gray-800 text-[#101828] dark:text-gray-200 border border-[#E8E8E8] dark:border-gray-700 capitalize" x-text="r.tipe"></span>
                            </td>
                            <td class="py-3 px-2.5">
                                <div class="font-medium text-[#101828] dark:text-white" x-text="r.item_label"></div>
                                <template x-if="r.detail && r.detail.nama">
                                    <div class="text-[11px] text-[#667085] dark:text-gray-400 mt-0.5" x-text="r.detail.nama"></div>
                                </template>
                            </td>
                            <td class="py-3 px-2.5">
                                <div class="font-medium text-[#101828] dark:text-white text-xs" x-text="r.supplier_nama || '-'"></div>
                            </td>
                            <td class="py-3 px-2.5 text-center tabular-nums text-xs text-[#667085] dark:text-gray-400">
                                <template x-if="r.average_lt_dipakai !== null && r.max_lt_dipakai !== null">
                                    <span x-text="formatLt(r.average_lt_dipakai) + ' / ' + formatLt(r.max_lt_dipakai) + ' hr'"></span>
                                </template>
                                <template x-if="r.average_lt_dipakai === null || r.max_lt_dipakai === null">
                                    <span>-</span>
                                </template>
                            </td>
                            <td class="py-3 px-2 text-center">
                                <template x-if="r.sumber_lt === 'otomatis'">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card border border-border dark:border-border" title="Sumber: Histori PO Otomatis">
                                        <span class="size-1 rounded-full bg-gray-400 shrink-0"></span>
                                        <span>Otomatis</span>
                                    </span>
                                </template>
                                <template x-if="r.sumber_lt === 'manual'">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card border border-border dark:border-border" title="Sumber: Estimasi Input Manual">
                                        <span class="size-1 rounded-full bg-gray-400 shrink-0"></span>
                                        <span>Manual</span>
                                    </span>
                                </template>
                                <template x-if="r.sumber_lt !== 'otomatis' && r.sumber_lt !== 'manual'">
                                    <span class="text-[#667085] dark:text-gray-400 tabular-nums text-[10px]" x-text="r.sumber_lt"></span>
                                </template>
                            </td>
                            <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white" x-text="formatNumber(r.batas_minimum)"></td>
                            <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white" x-text="formatNumber(r.target_stock)"></td>
                            <td class="py-3 px-2.5 text-center" x-html="badge(r.status)"></td>
                            <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white" x-text="formatNumber(r.qty_order)"></td>
                            <td class="py-3 px-2.5 text-xs text-[#667085] dark:text-gray-400" x-text="r.pencatat"></td>
                            <td class="py-3 px-2 text-center">
                                <span class="inline-flex items-center gap-1 text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card px-2 py-0.5 rounded-full border border-border dark:border-border">
                                    <svg class="size-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    Locked
                                </span>
                            </td>
                            <td class="py-3 px-2 text-center">
                                <template x-if="r.detail && Object.keys(r.detail).length > 0">
                                    <button type="button" @click="openSnapshotModal(r)" class="px-2.5 py-1 text-[11px] font-medium transition cursor-pointer btn-soft rounded-[6px]" title="Lihat Snapshot Detail">
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
                <div class="flex items-center gap-2 tabular-nums text-[11px]">
                    <span>Menampilkan <strong class="text-[#101828] dark:text-white" x-text="startItem()"></strong> - <strong class="text-[#101828] dark:text-white" x-text="endItem()"></strong> dari <strong class="text-[#101828] dark:text-white" x-text="currentDataLength()"></strong> item</span>
                </div>

                <div class="flex items-center gap-1.5" x-show="totalPages() > 1">
                    <button type="button" @click="gotoPage(page - 1)" :disabled="page <= 1" class="px-2.5 py-1 text-xs disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer btn-soft rounded-[6px]">
                        &laquo; Back
                    </button>

                    <template x-for="p in pagesList()" :key="p">
                        <div>
                            <template x-if="p === '...'">
                                <span class="px-2 py-1 text-[#667085] dark:text-gray-500">...</span>
                            </template>
                            <template x-if="p !== '...'">
                                <button type="button" @click="gotoPage(p)" :class="page === p ? 'bg-[#111111] dark:bg-white text-white dark:text-[#111111] font-medium border-[#111111] dark:border-white' : 'bg-white dark:bg-gray-900 text-[#101828] dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 border-[#E8E8E8] dark:border-gray-700'" class="min-w-[28px] px-2 py-1 text-xs border transition cursor-pointer rounded-[6px]" x-text="p"></button>
                            </template>
                        </div>
                    </template>

                    <button type="button" @click="gotoPage(page + 1)" :disabled="page >= totalPages()" class="px-2.5 py-1 text-xs disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer btn-soft rounded-[6px]">
                        Next &raquo;
                    </button>
                </div>
            </div>
        </x-slot:footer>
    </x-card>
</template>

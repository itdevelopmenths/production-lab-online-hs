{{-- Section: Log Riwayat Snapshot (riwayat_analisa) --}}
<template x-if="tab === 'riwayat'">
    <x-card 
        title="Log Riwayat Snapshot Analisa (riwayat_analisa)" 
        subtitle="Audit trail arsip historis snapshot terkunci (locked) lengkap dengan kode sesi batch finalisasi"
        :noPadding="true"
    >
        {{-- Loading Spinner --}}
        <div x-show="loading" class="py-12 flex flex-col items-center justify-center text-gray-500">
            <svg class="animate-spin h-6 w-6 text-primary-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-xs">Memuat data riwayat snapshot...</span>
        </div>

        <div x-show="!loading" class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-100 text-gray-700 text-[11px] uppercase font-semibold border-b border-gray-200">
                    <tr>
                        <th class="py-2.5 px-3">Tanggal</th>
                        <th class="py-2.5 px-3">Kode Sesi (Session ID)</th>
                        <th class="py-2.5 px-3">Tipe Analisa</th>
                        <th class="py-2.5 px-3">Item / SKU</th>
                        <th class="py-2.5 px-3 text-right">Batas Min</th>
                        <th class="py-2.5 px-3 text-right">Target</th>
                        <th class="py-2.5 px-3 text-center">Status</th>
                        <th class="py-2.5 px-3 text-right font-bold">Qty Order</th>
                        <th class="py-2.5 px-3">Pencatat</th>
                        <th class="py-2.5 px-2 text-center">Status Kunci</th>
                        <th class="py-2.5 px-2 text-center w-16">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="r in paginatedRows()" :key="r.id">
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="py-2.5 px-3 font-mono text-gray-700" x-text="r.tanggal"></td>
                            <td class="py-2.5 px-3 font-mono text-[11px]">
                                <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-800 border border-gray-200" x-text="r.session_id"></span>
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-200 capitalize" x-text="r.tipe"></span>
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="font-bold text-gray-900" x-text="r.item_label"></div>
                                <template x-if="r.detail && r.detail.nama">
                                    <div class="text-[10px] text-gray-500" x-text="r.detail.nama"></div>
                                </template>
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono text-gray-700" x-text="formatNumber(r.batas_minimum)"></td>
                            <td class="py-2.5 px-3 text-right font-mono text-gray-700" x-text="formatNumber(r.target_stock)"></td>
                            <td class="py-2.5 px-3 text-center" x-html="badge(r.status)"></td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-primary-900" x-text="formatNumber(r.qty_order)"></td>
                            <td class="py-2.5 px-3 text-gray-600" x-text="r.pencatat"></td>
                            <td class="py-2.5 px-2 text-center">
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    Locked
                                </span>
                            </td>
                            <td class="py-2.5 px-2 text-center">
                                <template x-if="r.detail && Object.keys(r.detail).length > 0">
                                    <button type="button" @click="openSnapshotModal(r)" class="px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded border border-gray-300 text-[10px] font-medium transition cursor-pointer" title="Lihat Snapshot Detail">
                                        Lihat
                                    </button>
                                </template>
                                <template x-if="!r.detail || Object.keys(r.detail).length === 0">
                                    <span class="text-gray-300">-</span>
                                </template>
                            </td>
                        </tr>
                    </template>
                    <template x-if="paginatedRows().length === 0">
                        <tr>
                            <td colspan="11" class="py-8 text-center text-gray-400 text-xs">
                                Tidak ada data log snapshot riwayat yang cocok.
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination Bar --}}
        <x-slot:footer>
            <div x-show="!loading && rows.length > 0" class="flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
                <div class="flex items-center gap-2 font-mono text-[11px]">
                    <span>Menampilkan <strong x-text="startItem()"></strong> - <strong x-text="endItem()"></strong> dari <strong x-text="currentDataLength()"></strong> item</span>
                </div>

                <div class="flex items-center gap-1" x-show="totalPages() > 1">
                    <button type="button" @click="gotoPage(page - 1)" :disabled="page <= 1" class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                        &laquo; Prev
                    </button>

                    <template x-for="p in pagesList()" :key="p">
                        <div>
                            <template x-if="p === '...'">
                                <span class="px-2 py-1 text-gray-400">...</span>
                            </template>
                            <template x-if="p !== '...'">
                                <button type="button" @click="gotoPage(p)" :class="page === p ? 'bg-primary-700 text-white font-bold border-primary-700' : 'bg-white text-gray-700 hover:bg-gray-100 border-gray-300'" class="min-w-[28px] px-2 py-1 text-xs rounded border transition cursor-pointer" x-text="p"></button>
                            </template>
                        </div>
                    </template>

                    <button type="button" @click="gotoPage(page + 1)" :disabled="page >= totalPages()" class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                        Next &raquo;
                    </button>
                </div>
            </div>
        </x-slot:footer>
    </x-card>
</template>

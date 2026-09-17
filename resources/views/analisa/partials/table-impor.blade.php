{{-- Section: Bahan Baku Impor (analisa_impor & lead_time_impor) --}}
<template x-if="tab === 'impor'">
    <div class="space-y-4">
        <x-card 
            title="Tabel Analisa & Rekomendasi Pemesanan Bahan Impor (analisa_impor & lead_time_impor)"
            subtitle="Lead Time (Avg/Max), Buffer Hari ABC (+4d/+2d/+0d), Safety Stock, Target Stock, Inbound ETA, Proyeksi, Rekomendasi Order MOQ, dan Distribusi Varian"
            :noPadding="true"
        >
            {{-- Loading Spinner --}}
            <div x-show="loading" class="py-12 flex flex-col items-center justify-center text-gray-500">
                <svg class="animate-spin h-6 w-6 text-primary-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-xs">Memuat kalkulasi data analisa impor...</span>
            </div>

            <div x-show="!loading" class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-100 text-gray-700 text-[11px] uppercase font-semibold border-b border-gray-200">
                        <tr>
                            <th class="py-2.5 px-3 text-center w-10">
                                <input type="checkbox" @change="toggleSelectCurrentPage($event)" :checked="isCurrentPageAllSelected()" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                            </th>
                            <th class="py-2.5 px-3">Bahan Baku Impor</th>
                            <th class="py-2.5 px-3">Supplier</th>
                            <th class="py-2.5 px-2 text-center">ABC</th>
                            <th class="py-2.5 px-3 text-center">Lead Time (Avg/Max)</th>
                            <th class="py-2.5 px-3 text-right">Buffer</th>
                            <th class="py-2.5 px-3 text-right">Safety Stock</th>
                            <th class="py-2.5 px-3 text-right">Min Stock</th>
                            <th class="py-2.5 px-3 text-right">Target</th>
                            <th class="py-2.5 px-3 text-right">Stok Fisik</th>
                            <th class="py-2.5 px-3 text-right">Inbound ETA</th>
                            <th class="py-2.5 px-3 text-right">Proyeksi</th>
                            <th class="py-2.5 px-3 text-center">Status</th>
                            <th class="py-2.5 px-3 text-right bg-primary-50/50 text-primary-900 font-bold">Rekomendasi Order</th>
                            <th class="py-2.5 px-3 text-right">Estimasi Nominal</th>
                            <th class="py-2.5 px-2 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="r in paginatedRows()" :key="r.id">
                            <tr class="hover:bg-gray-50/80 transition" :class="r.status === 'po' ? 'bg-rose-50/20' : ''">
                                <td class="py-2.5 px-3 text-center">
                                    <input type="checkbox" :value="r.produk_id" x-model="selectedItemIds" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-gray-900" x-text="r.nama"></div>
                                    <div class="flex items-center gap-1.5 font-mono text-[10px] text-gray-500 mt-0.5">
                                        <span x-text="r.sku"></span>
                                        <span>·</span>
                                        <span x-text="'Satuan: ' + r.satuan"></span>
                                        <template x-if="r.punya_varian">
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">Multi-Varian</span>
                                        </template>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-medium text-gray-800" x-text="r.supplier_nama || '-'"></div>
                                    <template x-if="r.supplier_kategori && r.supplier_kategori !== '-'">
                                        <div class="text-[10px] text-gray-400 font-mono" x-text="r.supplier_kategori"></div>
                                    </template>
                                </td>
                                <td class="py-2.5 px-2 text-center font-mono">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase" :class="abcBadgeClass(r.klasifikasi_abc)" x-text="r.klasifikasi_abc"></span>
                                </td>
                                <td class="py-2.5 px-3 text-center font-mono text-gray-700" x-text="formatNumber(r.lead_time_average) + ' / ' + formatNumber(r.lead_time_max) + ' hr'"></td>
                                <td class="py-2.5 px-3 text-right font-mono text-gray-600" x-text="formatNumber(r.buffer_days) + ' hr'"></td>
                                <td class="py-2.5 px-3 text-right font-mono text-gray-700" x-text="formatNumber(r.safety_stock)"></td>
                                <td class="py-2.5 px-3 text-right font-mono text-gray-700" x-text="formatNumber(r.minimum_stock)"></td>
                                <td class="py-2.5 px-3 text-right font-mono font-medium text-gray-900" x-text="formatNumber(r.target_stock)"></td>
                                <td class="py-2.5 px-3 text-right font-mono text-gray-900" x-text="formatNumber(r.stok_saat_ini)"></td>
                                <td class="py-2.5 px-3 text-right font-mono text-gray-600" x-text="'+' + formatNumber(r.inbound_before_eta)"></td>
                                <td class="py-2.5 px-3 text-right font-mono" :class="r.proyeksi < r.target_stock ? 'text-rose-600 font-bold' : 'text-emerald-700'" x-text="formatNumber(r.proyeksi)"></td>
                                <td class="py-2.5 px-3 text-center" x-html="badge(r.status)"></td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold bg-primary-50/30 text-primary-900">
                                    <span x-text="formatNumber(r.total_qty_order)"></span>
                                    <template x-if="r.satuan_order_moq && r.satuan_order_moq !== r.satuan">
                                        <span class="text-[10px] font-normal text-gray-500 block" x-text="r.satuan_order_moq + ' (x' + r.faktor_konversi + ')'"></span>
                                    </template>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900" x-text="'Rp ' + formatNumber(r.total_nominal_order)"></td>
                                <td class="py-2.5 px-2 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button" @click="openManualImporModal(r)" class="px-2 py-1 bg-primary-50 hover:bg-primary-100 text-primary-700 rounded border border-primary-200 text-[10px] font-medium transition cursor-pointer" title="Edit Data Input Manual Impor">
                                            Edit Input
                                        </button>
                                        <template x-if="r.punya_varian && r.varian && r.varian.length > 0">
                                            <button type="button" @click="openVarianModal(r)" class="px-2 py-1 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded border border-purple-200 text-[10px] font-medium transition cursor-pointer" title="Lihat Rincian Varian">
                                                Varian (<span x-text="r.varian.length"></span>)
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="paginatedRows().length === 0">
                            <tr>
                                <td colspan="16" class="py-8 text-center text-gray-400 text-xs">
                                    Tidak ada item bahan impor yang cocok dengan pencarian atau filter.
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
                        <template x-if="searchQuery || statusFilter !== 'all'">
                            <span class="text-gray-400 font-normal">(difilter dari <span x-text="currentRawLength()"></span> total)</span>
                        </template>
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
    </div>
</template>

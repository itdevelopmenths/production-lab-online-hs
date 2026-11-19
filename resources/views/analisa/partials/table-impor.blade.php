{{-- Section: Bahan Baku Impor (analisa_impor & lead_time_impor) --}}
<template x-if="tab === 'impor'">
    <div class="space-y-4">
        {{-- Sub-navigasi 2 Tabel Impor --}}
        <div class="flex items-center justify-between gap-3 border-b border-[#E8E8E8] dark:border-gray-800 pb-3">
            <div class="inline-flex flex-wrap gap-1 bg-[#f3f4f6] dark:bg-gray-900 p-1 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 text-xs">
                <button type="button" @click="imporSubTab = 'rekomendasi'; page = 1" :class="imporSubTab === 'rekomendasi' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white font-semibold shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white font-medium'" class="px-3.5 py-1.5 rounded-[7px] transition-all cursor-pointer">
                    1. Rekomendasi Order Impor
                </button>
                <button type="button" @click="imporSubTab = 'analisa'; page = 1" :class="imporSubTab === 'analisa' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white font-semibold shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white font-medium'" class="px-3.5 py-1.5 rounded-[7px] transition-all cursor-pointer">
                    2. Working Data Analisa (ADU)
                </button>
            </div>
        </div>

        {{-- TABEL 1: Rekomendasi Order Impor (1 baris per produk — supplier aktif) --}}
        <div x-show="imporSubTab === 'rekomendasi'">
        <x-card 
            title="Tabel Analisa & Rekomendasi Pemesanan Bahan Impor (analisa_impor & lead_time_impor)"
            subtitle="Lead Time (Avg/Max), Buffer Hari ABC (+4d/+2d/+0d), Safety Stock, Target Stock, Inbound ETA, Proyeksi, dan Rekomendasi Order MOQ"
            :noPadding="true"
        >
            <x-slot:icon>
                <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </x-slot:icon>

            {{-- Loading Spinner --}}
            <div x-show="loading" class="py-12 flex flex-col items-center justify-center text-[#667085] dark:text-gray-400">
                <svg class="animate-spin h-6 w-6 text-[#111111] dark:text-white mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-xs">Memuat kalkulasi data analisa impor...</span>
            </div>

            <div x-show="!loading" tabindex="0" role="region" aria-label="Tabel Rekomendasi Order Impor" class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[13px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                        <tr>
                            <th class="py-3 px-3 text-center w-10">
                                <input type="checkbox" @change="toggleSelectCurrentPage($event)" :checked="isCurrentPageAllSelected()" class="rounded border-[#E8E8E8] dark:border-gray-700 text-[#111111] dark:text-white focus:ring-0 cursor-pointer">
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
                            <th class="py-3 px-4 text-right bg-gray-100/70 dark:bg-gray-800 text-[#101828] dark:text-white font-semibold">Rekomendasi Order</th>
                            <th class="py-3 px-4 text-right">Estimasi Nominal</th>
                            <th class="py-3 px-3 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 bg-white dark:bg-gray-900">
                        <template x-for="r in paginatedRows()" :key="r.id">
                            <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition" :class="r.status === 'po' ? 'bg-rose-50/30 dark:bg-rose-950/15' : ''">
                                <td class="py-3 px-3 text-center">
                                    <input type="checkbox" :value="r.id" x-model="selectedItemIds" class="rounded border-[#E8E8E8] dark:border-gray-700 text-[#111111] dark:text-white focus:ring-0 cursor-pointer">
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-[#101828] dark:text-white" x-text="r.nama"></div>
                                    <div class="flex items-center gap-1.5 font-mono text-[11px] text-[#667085] dark:text-gray-400 mt-0.5">
                                        <span x-text="r.sku"></span>
                                        <span>·</span>
                                        <span x-text="'Satuan: ' + r.satuan"></span>
                                    </div>
                                    <template x-if="r.periode_mulai && r.periode_akhir">
                                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-mono mt-0.5" x-text="'Periode: ' + r.periode_mulai + ' ~ ' + r.periode_akhir + ' (' + r.jumlah_hari_periode + ' hr)'"></div>
                                    </template>
                                    <template x-if="r.varian && r.varian.length > 0">
                                        <div>
                                            <button type="button" @click="openVarianModal(r)" class="mt-1 inline-flex items-center gap-1 text-[11px] text-[#111111] dark:text-gray-200 hover:underline font-mono cursor-pointer" title="Lihat Distribusi Varian">
                                                <span x-text="r.varian.length + ' Varian'"></span>
                                                <svg class="size-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                            </button>
                                        </div>
                                    </template>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <button type="button" @click="openCompareSupplier(r)" class="font-medium text-[#111111] dark:text-white hover:underline text-left cursor-pointer flex items-center gap-1" title="Bandingkan & Ganti Supplier">
                                            <span x-text="r.supplier_nama || 'Pilih Supplier'"></span>
                                            <svg class="size-3 text-[#667085] dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                        </button>
                                        <template x-if="r.sumber_lt === 'otomatis'">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50" title="Sumber: Histori PO Otomatis">
                                                <span class="size-1 rounded-full bg-emerald-500 shrink-0"></span>
                                                <span>Otomatis</span>
                                            </span>
                                        </template>
                                        <template x-if="r.sumber_lt !== 'otomatis'">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50" title="Sumber: Estimasi Input Manual">
                                                <span class="size-1 rounded-full bg-amber-500 shrink-0"></span>
                                                <span>Manual</span>
                                            </span>
                                        </template>
                                        <a :href="'{{ route('purchasing.index', ['tab' => 'po']) }}' + (r.supplier_nama && r.supplier_nama !== '-' ? '&search=' + encodeURIComponent(r.supplier_nama) : '')" href="{{ route('purchasing.index', ['tab' => 'po']) }}" class="text-[10px] text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white underline ml-1" title="Lihat Daftar Purchase Order Supplier">Histori LT</a>
                                    </div>
                                    <template x-if="r.supplier_kategori && r.supplier_kategori !== '-'">
                                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-mono mt-0.5" x-text="r.supplier_kategori"></div>
                                    </template>
                                </td>
                                <td class="py-3 px-3 text-center font-mono">
                                    @can('analisa.manage')
                                    <select
                                        :value="r.klasifikasi_abc_id ? String(r.klasifikasi_abc_id) : ''"
                                        @change="quickChangeImporAbc(r, $event.target.value)"
                                        :class="r.klasifikasi_abc_id ? abcBadgeClass(r.klasifikasi_abc) : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700'"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase cursor-pointer focus:outline-none"
                                        :title="r.klasifikasi_abc_nama || 'Default (Belum Diset)'"
                                    >
                                        <option value="">Default (C)</option>
                                        <template x-for="abc in klasifikasiAbcList" :key="abc.id">
                                            <option :value="abc.id" :selected="r.klasifikasi_abc_id == abc.id" x-text="abc.kode.toUpperCase()"></option>
                                        </template>
                                    </select>
                                    @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase" :class="r.klasifikasi_abc_id ? abcBadgeClass(r.klasifikasi_abc) : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700'" :title="r.klasifikasi_abc_nama || r.klasifikasi_abc" x-text="r.klasifikasi_abc_id ? r.klasifikasi_abc : 'Default (C)'"></span>
                                    @endcan
                                </td>
                                <td class="py-3 px-4 text-center font-mono text-xs text-[#667085] dark:text-gray-400" x-text="formatLt(r.lead_time_average) + ' / ' + formatLt(r.lead_time_max) + ' hr'"></td>
                                <td class="py-3 px-4 text-right font-mono text-xs text-[#667085] dark:text-gray-400" x-text="formatLt(r.buffer_days) + ' hr'"></td>
                                <td class="py-3 px-4 text-right font-mono text-[#101828] dark:text-white" x-text="formatNumber(r.safety_stock)"></td>
                                <td class="py-3 px-4 text-right font-mono text-[#101828] dark:text-white" x-text="formatNumber(r.minimum_stock)"></td>
                                <td class="py-3 px-4 text-right font-mono font-medium text-[#101828] dark:text-white" x-text="formatNumber(r.target_stock)"></td>
                                <td class="py-3 px-4 text-right font-mono text-[#101828] dark:text-white" x-text="formatNumber(r.stok_saat_ini)"></td>
                                <td class="py-3 px-4 text-right font-mono text-[#667085] dark:text-gray-400" x-text="'+' + formatNumber(r.inbound_before_eta)"></td>
                                <td class="py-3 px-4 text-right font-mono" :class="r.proyeksi < r.target_stock ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-[#101828] dark:text-white'" x-text="formatNumber(r.proyeksi)"></td>
                                <td class="py-3 px-4 text-center" x-html="badge(r.status)"></td>
                                <td class="py-3 px-4 text-right font-mono font-bold bg-[#fafafa] dark:bg-gray-800/40 text-[#101828] dark:text-white">
                                    <span x-text="formatNumber(Math.round(r.total_qty_order))"></span>
                                    <template x-if="r.satuan_order_moq && r.satuan_order_moq > 1">
                                        <span class="text-[10px] font-normal text-[#667085] dark:text-gray-400 block" x-text="'MOQ: ' + formatNumber(r.satuan_order_moq)"></span>
                                    </template>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-[#101828] dark:text-white" x-text="'Rp ' + formatNumber(r.total_nominal_order)"></td>
                                <td class="py-3 px-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        @can('analisa.manage')
                                        <button type="button" @click="openManualImporModal(r)" class="px-2.5 py-1 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-[#101828] dark:text-gray-200 rounded-lg border border-[#E8E8E8] dark:border-gray-700 text-[11px] font-medium transition cursor-pointer" title="Edit Data Input Manual Impor">
                                            Edit Input
                                        </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="paginatedRows().length === 0">
                            <tr>
                                <td colspan="16" class="py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                    Tidak ada item bahan impor yang cocok dengan pencarian atau filter.
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
                        <template x-if="searchQuery || statusFilter !== 'all'">
                            <span class="text-[#667085] dark:text-gray-400 font-normal">(difilter dari <span x-text="currentRawLength()"></span> total)</span>
                        </template>
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
        </div>

        {{-- TABEL 2: Working Data Analisa Impor (ADU Seluruh Supplier-Produk) --}}
        <div x-show="imporSubTab === 'analisa'">
            <x-card
                title="Tabel Working Data Analisa Impor — ADU Seluruh Supplier–Produk"
                subtitle="ADU dihitung murni per produk (OUT / Jumlah Hari, tidak bergantung supplier); Lead Time, Buffer Days, Safety Stock, Min Stock, & Target Stock mengikuti masing-masing supplier"
                :noPadding="true"
            >
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </x-slot:icon>

                <div x-show="loading" class="py-12 flex flex-col items-center justify-center text-[#667085] dark:text-gray-400">
                    <svg class="animate-spin h-6 w-6 text-[#111111] dark:text-white mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-xs">Memuat data ADU seluruh supplier-produk impor...</span>
                </div>

                <div x-show="!loading" tabindex="0" role="region" aria-label="Tabel Working Data Analisa Impor" class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[13px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                            <tr>
                                <th class="py-3 px-4">Bahan Baku Impor</th>
                                <th class="py-3 px-4">Supplier</th>
                                <th class="py-3 px-3 text-center">ABC</th>
                                <th class="py-3 px-4 text-center">Lead Time (Avg/Max)</th>
                                <th class="py-3 px-4 text-right">Buffer Days</th>
                                <th class="py-3 px-4 text-right">Total OUT</th>
                                <th class="py-3 px-4 text-right bg-gray-100/70 dark:bg-gray-800 text-[#101828] dark:text-white">ADU</th>
                                <th class="py-3 px-4 text-right">Review Period</th>
                                <th class="py-3 px-4 text-right">Safety Stock</th>
                                <th class="py-3 px-4 text-right bg-gray-100/70 dark:bg-gray-800 text-[#101828] dark:text-white font-semibold">Min Stock</th>
                                <th class="py-3 px-4 text-right font-semibold text-[#101828] dark:text-white">Target Stock</th>
                                <th class="py-3 px-3 text-center w-36">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 bg-white dark:bg-gray-900">
                            <template x-for="r in paginatedAnalisaRows()" :key="r.row_key">
                                <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition" :class="r.is_active_supplier ? 'bg-gray-50/70 dark:bg-gray-800/30' : ''">
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-[#101828] dark:text-white" x-text="r.nama"></div>
                                        <div class="font-mono text-[11px] text-[#667085] dark:text-gray-400" x-text="r.sku + ' · Satuan: ' + r.satuan"></div>
                                        <template x-if="r.periode_mulai && r.periode_akhir">
                                            <div class="text-[10px] text-[#667085] dark:text-gray-400 font-mono mt-0.5" x-text="'Periode: ' + r.periode_mulai + ' ~ ' + r.periode_akhir + ' (' + r.jumlah_hari_periode + ' hr)'"></div>
                                        </template>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <button type="button" @click="openCompareSupplier(r)" class="font-medium text-[#111111] dark:text-white hover:underline text-left cursor-pointer flex items-center gap-1" title="Bandingkan & Ganti Supplier">
                                                <span x-text="r.supplier_nama || 'Pilih Supplier'"></span>
                                                <svg class="size-3 text-[#667085] dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                            </button>
                                            <template x-if="r.is_active_supplier && r.supplier_id">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold uppercase bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50">Aktif</span>
                                            </template>
                                            <template x-if="r.sumber_lt === 'otomatis'">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50" :title="'Sumber: Histori PO Otomatis (' + (r.total_po_selesai || 0) + ' PO)'">
                                                    <span class="size-1 rounded-full bg-emerald-500 shrink-0"></span>
                                                    <span x-text="'Otomatis (' + (r.total_po_selesai || 0) + ' PO)'"></span>
                                                </span>
                                            </template>
                                            <template x-if="r.sumber_lt !== 'otomatis'">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50" title="Sumber: Estimasi Input Manual">
                                                    <span class="size-1 rounded-full bg-amber-500 shrink-0"></span>
                                                    <span>Manual</span>
                                                </span>
                                            </template>
                                            <template x-if="r.relasi_status === 'riwayat_po'">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-medium bg-gray-100 dark:bg-gray-800 text-[#667085] dark:text-gray-300 border border-[#E8E8E8] dark:border-gray-700" title="Pernah menyuplai berdasarkan riwayat PO">Riwayat PO</span>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-center font-mono">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase" :class="r.klasifikasi_abc_id ? abcBadgeClass(r.klasifikasi_abc) : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700'" :title="r.klasifikasi_abc_nama || 'Default (C)'" x-text="r.klasifikasi_abc_id ? r.klasifikasi_abc : 'C'"></span>
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono text-xs text-[#667085] dark:text-gray-400" x-text="formatLt(r.lead_time_average) + ' / ' + formatLt(r.lead_time_max) + ' hr'"></td>
                                    <td class="py-3 px-4 text-right font-mono text-xs text-[#667085] dark:text-gray-400" x-text="formatLt(r.buffer_days) + ' hr'"></td>
                                    <td class="py-3 px-4 text-right font-mono text-[#101828] dark:text-white" x-text="formatNumber(r.out)"></td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-[#101828] dark:text-white bg-[#fafafa] dark:bg-gray-800/40" x-text="formatNumber(r.adu)"></td>
                                    <td class="py-3 px-4 text-right font-mono text-xs text-[#667085] dark:text-gray-400" x-text="r.review_period + ' Hari'"></td>
                                    <td class="py-3 px-4 text-right font-mono text-[#101828] dark:text-white" x-text="formatNumber(r.safety_stock)"></td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-[#101828] dark:text-white bg-[#fafafa] dark:bg-gray-800/40" x-text="formatNumber(r.minimum_stock) + ' ' + r.satuan"></td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-[#101828] dark:text-white" x-text="formatNumber(r.target_stock) + ' ' + r.satuan"></td>
                                    <td class="py-3 px-3 text-center">
                                        @can('analisa.manage')
                                        <div class="flex items-center justify-center gap-1 flex-wrap">
                                            <template x-if="!r.is_active_supplier && r.supplier_id">
                                                <button type="button" @click="selectSupplierDirect(r.produk_id, r.supplier_id)" :disabled="savingSelectSupplier" class="px-2.5 py-1 bg-[#111111] hover:bg-[#292929] dark:bg-white dark:hover:bg-gray-100 text-white dark:text-[#111111] disabled:opacity-50 rounded-lg text-[10px] font-semibold transition cursor-pointer" title="Jadikan Supplier Aktif untuk Rekomendasi Order">
                                                    Aktifkan
                                                </button>
                                            </template>
                                            <button type="button" @click="openManualImporModal(r)" class="px-2.5 py-1 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-[#101828] dark:text-gray-200 rounded-lg border border-[#E8E8E8] dark:border-gray-700 text-[10px] font-medium transition cursor-pointer" title="Edit OUT, Periode, & Parameter Produk">
                                                Edit Input
                                            </button>
                                        </div>
                                        @else
                                        <span class="text-[#667085] dark:text-gray-400 text-[10px]">-</span>
                                        @endcan
                                    </td>
                                </tr>
                            </template>
                            <template x-if="paginatedAnalisaRows().length === 0">
                                <tr>
                                    <td colspan="12" class="py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                        Tidak ada data working analisa impor yang cocok.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Bar --}}
                <x-slot:footer>
                    <div x-show="!loading && currentRawLength() > 0" class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#667085] dark:text-gray-400">
                        <div class="flex items-center gap-2 font-mono text-[11px]">
                            <span>Menampilkan <strong class="text-[#101828] dark:text-white" x-text="startItem()"></strong> - <strong class="text-[#101828] dark:text-white" x-text="endItem()"></strong> dari <strong class="text-[#101828] dark:text-white" x-text="currentDataLength()"></strong> baris supplier–produk</span>
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
        </div>
    </div>
</template>

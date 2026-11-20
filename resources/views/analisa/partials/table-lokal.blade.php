{{-- Section: Bahan Baku Lokal 4-Tabel Engine --}}
<template x-if="tab === 'lokal'">
    <div class="space-y-4">
        {{-- Sub-navigasi 4 Tabel Lokal --}}
        <div class="flex items-center justify-between gap-3 border-b border-[#E8E8E8] dark:border-gray-800 pb-3">
            <div class="inline-flex flex-wrap gap-1 bg-[#f3f4f6] dark:bg-gray-900 p-1 rounded-[8px] border border-[#E8E8E8] dark:border-gray-800 text-xs">
                <button type="button" @click="lokalSubTab = 'rekomendasi'; page = 1" :class="lokalSubTab === 'rekomendasi' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white font-medium shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white font-medium'" class="px-3.5 py-1.5 transition-all cursor-pointer rounded-[6px]">
                    1. Rekomendasi Order Lokal
                </button>
                <button type="button" @click="lokalSubTab = 'analisa'; page = 1" :class="lokalSubTab === 'analisa' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white font-medium shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white font-medium'" class="px-3.5 py-1.5 transition-all cursor-pointer rounded-[6px]">
                    2. Working Data Analisa (ADU)
                </button>
                <button type="button" @click="lokalSubTab = 'lead_time'; page = 1" :class="lokalSubTab === 'lead_time' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white font-medium shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white font-medium'" class="px-3.5 py-1.5 transition-all cursor-pointer rounded-[6px]">
                    3. Ringkasan Lead Time & Buffer
                </button>
                <button type="button" @click="lokalSubTab = 'stages'; page = 1" :class="lokalSubTab === 'stages' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white font-medium shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white font-medium'" class="px-3.5 py-1.5 transition-all cursor-pointer rounded-[6px]">
                    4. Rincian 8 Tahap Lead Time
                </button>
            </div>
        </div>

        {{-- TABEL 1: Rekomendasi Order Lokal (Working Data) --}}
        <div x-show="lokalSubTab === 'rekomendasi'">
            <x-card class="tbl-modern" 
                title="Tabel Rekomendasi Pemesanan Bahan Lokal (rekomendasi_order_lokal)" 
                subtitle="ORDER bila Stok + Akan Datang ≤ Target Stock. Selisih terhadap Target dibulatkan ke kelipatan MOQ, lalu dikonversi ke satuan beli"
                :noPadding="true"
            >
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </x-slot:icon>

                <div tabindex="0" role="region" aria-label="Tabel Rekomendasi Pemesanan Bahan Lokal" class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-xs font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                            <tr>
                                <th class="py-3 px-2 text-center w-10">
                                    <input type="checkbox" @change="toggleSelectAllFiltered($event)" :checked="isAllFilteredSelected()" :indeterminate="isSomeFilteredSelected()" title="Pilih semua hasil (semua halaman)" class="rounded border-[#E8E8E8] dark:border-gray-700 text-[#111111] dark:text-white focus:ring-0 cursor-pointer">
                                </th>
                                <th class="py-3 px-2.5">Bahan Baku</th>
                                <th class="py-3 px-2.5">Supplier</th>
                                <th class="py-3 px-2.5 text-right">Batas Min</th>
                                <th class="py-3 px-2.5 text-right">Target</th>
                                <th class="py-3 px-2.5 text-right">Stok Fisik</th>
                                <th class="py-3 px-2.5 text-right">Akan Datang</th>
                                <th class="py-3 px-2.5 text-right">Selisih</th>
                                <th class="py-3 px-2.5 text-center">Status</th>
                                <th class="py-3 px-2.5 text-right">Rumus MOQ</th>
                                <th class="py-3 px-2.5 text-right bg-gray-100/70 dark:bg-gray-800 text-[#101828] dark:text-white font-medium">Rekomendasi Order</th>
                                <th class="py-3 px-2.5 text-right">Harga Satuan</th>
                                <th class="py-3 px-2.5 text-right">Estimasi Nominal</th>
                                <th class="py-3 px-2 text-center w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 bg-white dark:bg-gray-900">
                            <template x-for="r in paginatedRows()" :key="r.id">
                                <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                                    <td class="py-3 px-2 text-center">
                                        <input type="checkbox" :value="r.id" x-model="selectedItemIds" class="rounded border-[#E8E8E8] dark:border-gray-700 text-[#111111] dark:text-white focus:ring-0 cursor-pointer">
                                    </td>
                                    <td class="py-3 px-2.5">
                                        <div class="font-medium text-[#101828] dark:text-white" x-text="r.nama"></div>
                                        <div class="tabular-nums text-[11px] text-[#667085] dark:text-gray-400" x-text="r.sku + ' · Satuan: ' + r.satuan"></div>
                                    </td>
                                    <td class="py-3 px-2.5">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <button type="button" @click="openCompareSupplier(r)" class="font-medium text-[#111111] dark:text-white hover:underline text-left cursor-pointer flex items-center gap-1" title="Bandingkan & Ganti Supplier">
                                                <span x-text="r.supplier_nama || 'Pilih Supplier'"></span>
                                                <svg class="size-3 text-[#667085] dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                            </button>
                                            <template x-if="r.sumber_lt === 'otomatis'">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card border border-border dark:border-border" title="Sumber: Histori PO Otomatis">
                                                    <span class="size-1 rounded-full bg-gray-400 shrink-0"></span>
                                                    <span>Otomatis</span>
                                                </span>
                                            </template>
                                            <template x-if="r.sumber_lt !== 'otomatis'">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card border border-border dark:border-border" title="Sumber: Estimasi Input Manual">
                                                    <span class="size-1 rounded-full bg-gray-400 shrink-0"></span>
                                                    <span>Manual</span>
                                                </span>
                                            </template>
                                            <a :href="'{{ route('purchasing.index', ['tab' => 'po']) }}' + (r.supplier_nama && r.supplier_nama !== '-' ? '&search=' + encodeURIComponent(r.supplier_nama) : '')" href="{{ route('purchasing.index', ['tab' => 'po']) }}" class="text-[10px] text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white underline ml-1" title="Lihat Daftar Purchase Order Supplier">Histori LT</a>
                                        </div>
                                    </td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white" x-text="formatNumber(r.batas_minimum)"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white" x-text="formatNumber(r.target_stock)"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white" x-text="formatNumber(r.stok_saat_ini)"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatNumber(r.akan_datang)"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums" :class="r.selisih < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-foreground dark:text-foreground'" x-text="formatNumber(r.selisih)"></td>
                                    <td class="py-3 px-2.5 text-center" x-html="badge(r.status)"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white" x-text="formatNumber(r.rumus_moq) + ' ' + r.satuan"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums bg-[#fafafa] dark:bg-gray-800/40 text-[#101828] dark:text-white">
                                        <span x-text="formatNumber(r.rekomendasi_order)"></span>
                                        <template x-if="r.faktor_konversi > 1">
                                            <span class="text-[10px] font-normal text-[#667085] dark:text-gray-400 block" x-text="'(x' + r.faktor_konversi + ' ' + r.satuan + ')'"></span>
                                        </template>
                                    </td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="'Rp ' + formatNumber(r.harga_per_satuan)"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white" x-text="'Rp ' + formatNumber(r.total_nominal_order)"></td>
                                    <td class="py-3 px-2 text-center">
                                        @can('analisa.manage')
                                        <button type="button" @click="openManualLokalModal(r)" class="px-2.5 py-1 text-[11px] font-medium transition cursor-pointer btn-soft rounded-[6px]" title="Edit Data Input Manual">
                                            Edit Input
                                        </button>
                                        @else
                                        <span class="text-[#667085] dark:text-gray-400 text-[10px]">-</span>
                                        @endcan
                                    </td>
                                </tr>
                            </template>
                            <template x-if="paginatedRows().length === 0">
                                <tr>
                                    <td colspan="13" class="py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                        Tidak ada item bahan lokal yang cocok dengan pencarian atau filter.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Bar --}}
                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#667085] dark:text-gray-400">
                        <div class="flex items-center gap-2 tabular-nums text-[11px]">
                            <span>Menampilkan <strong class="text-[#101828] dark:text-white" x-text="startItem()"></strong> - <strong class="text-[#101828] dark:text-white" x-text="endItem()"></strong> dari <strong class="text-[#101828] dark:text-white" x-text="currentDataLength()"></strong> item</span>
                            <template x-if="searchQuery || statusFilter !== 'all'">
                                <span class="text-[#101828] dark:text-gray-200 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-md border border-[#E8E8E8] dark:border-gray-700 text-[10px] font-sans">
                                    (Difilter dari <span x-text="currentRawLength()"></span> total)
                                </span>
                            </template>
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
        </div>

        {{-- TABEL 2: Working Data Analisa (analisa_lokal — seluruh kombinasi supplier-produk) --}}
        <div x-show="lokalSubTab === 'analisa'">
            <x-card class="tbl-modern" 
                title="Tabel Working Data Analisa Stok — ADU Seluruh Supplier–Produk" 
                subtitle="ADU dihitung murni per produk (tidak bergantung supplier); Lead Time, Safety Stock, Batas Minimum, & Target Stock mengikuti masing-masing supplier"
                :noPadding="true"
            >
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </x-slot:icon>

                <div tabindex="0" role="region" aria-label="Tabel Working Data Analisa Stok Lokal" class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-xs font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                            <tr>
                                <th class="py-3 px-2.5">Bahan Baku</th>
                                <th class="py-3 px-2.5">Supplier</th>
                                <th class="py-3 px-2.5 text-right">Avg LT (Hari)</th>
                                <th class="py-3 px-2.5 text-right">Safety Stock (Hari)</th>
                                <th class="py-3 px-2.5 text-right">Terjual 4 Bulan</th>
                                <th class="py-3 px-2.5 text-right bg-gray-100/70 dark:bg-gray-800 text-[#101828] dark:text-white">ADU</th>
                                <th class="py-3 px-2.5 text-right">Review Period</th>
                                <th class="py-3 px-2.5 text-right bg-gray-100/70 dark:bg-gray-800 text-[#101828] dark:text-white font-medium">Batas Minimum</th>
                                <th class="py-3 px-2.5 text-right font-medium text-[#101828] dark:text-white">Target Stock</th>
                                <th class="py-3 px-2.5 text-right">Generated At</th>
                                <th class="py-3 px-2 text-center w-36">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 bg-white dark:bg-gray-900">
                            <template x-for="r in paginatedAnalisaRows()" :key="r.row_key">
                                <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition" :class="r.is_active_supplier ? 'bg-gray-50/70 dark:bg-gray-800/30' : ''">
                                    <td class="py-3 px-2.5">
                                        <div class="font-medium text-[#101828] dark:text-white" x-text="r.nama"></div>
                                        <div class="tabular-nums text-[11px] text-[#667085] dark:text-gray-400" x-text="r.sku + ' · Satuan: ' + r.satuan"></div>
                                    </td>
                                    <td class="py-3 px-2.5">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <button type="button" @click="openCompareSupplier(r)" class="font-medium text-[#111111] dark:text-white hover:underline text-left cursor-pointer flex items-center gap-1" title="Bandingkan & Ganti Supplier">
                                                <span x-text="r.supplier_nama || 'Pilih Supplier'"></span>
                                                <svg class="size-3 text-[#667085] dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                            </button>
                                            <template x-if="r.is_active_supplier && r.supplier_id">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-medium uppercase bg-card text-foreground dark:bg-card dark:text-foreground border border-border dark:border-border">Aktif</span>
                                            </template>
                                            <template x-if="r.sumber_lt === 'otomatis'">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card border border-border dark:border-border" :title="'Sumber: Histori PO Otomatis (' + (r.total_po_selesai || 0) + ' PO)'">
                                                    <span class="size-1 rounded-full bg-gray-400 shrink-0"></span>
                                                    <span x-text="'Otomatis (' + (r.total_po_selesai || 0) + ' PO)'"></span>
                                                </span>
                                            </template>
                                            <template x-if="r.sumber_lt !== 'otomatis'">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card border border-border dark:border-border" title="Sumber: Estimasi Input Manual">
                                                    <span class="size-1 rounded-full bg-gray-400 shrink-0"></span>
                                                    <span>Manual</span>
                                                </span>
                                            </template>
                                            <template x-if="r.relasi_status === 'riwayat_po'">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-medium bg-gray-100 dark:bg-gray-800 text-[#667085] dark:text-gray-300 border border-[#E8E8E8] dark:border-gray-700" title="Pernah menyuplai berdasarkan riwayat PO">Riwayat PO</span>
                                            </template>
                                            <a :href="'{{ route('purchasing.index', ['tab' => 'po']) }}' + (r.supplier_nama && r.supplier_nama !== '-' ? '&search=' + encodeURIComponent(r.supplier_nama) : '')" href="{{ route('purchasing.index', ['tab' => 'po']) }}" class="text-[10px] text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white underline ml-1" title="Lihat Daftar Purchase Order Supplier">Histori LT</a>
                                        </div>
                                    </td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-xs text-[#667085] dark:text-gray-400" x-text="formatLt(r.total_avg_lead_time)"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-xs text-[#667085] dark:text-gray-400" x-text="formatLt(r.safety_stock)"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white" x-text="formatNumber(r.terjual_rata_rata_4bulan)"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white bg-[#fafafa] dark:bg-gray-800/40" x-text="formatNumber(r.adu)"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-xs text-[#667085] dark:text-gray-400" x-text="r.review_period + ' Hari'"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white bg-[#fafafa] dark:bg-gray-800/40" x-text="formatNumber(r.batas_minimum) + ' ' + r.satuan"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white" x-text="formatNumber(r.target_stock) + ' ' + r.satuan"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-xs text-[#667085] dark:text-gray-400" x-text="r.generated_at"></td>
                                    <td class="py-3 px-2 text-center">
                                        @can('analisa.manage')
                                        <div class="flex items-center justify-center gap-1 flex-wrap">
                                            <template x-if="!r.is_active_supplier && r.supplier_id">
                                                <button type="button" @click="selectSupplierDirect(r.produk_id, r.supplier_id)" :disabled="savingSelectSupplier" class="px-2.5 py-1 disabled:opacity-50 text-[10px] font-medium transition cursor-pointer btn-glossy rounded-[6px]" title="Jadikan Supplier Aktif untuk Rekomendasi Order">
                                                    Aktifkan
                                                </button>
                                            </template>
                                            <button type="button" @click="openManualLokalModal(r)" class="px-2.5 py-1 text-[10px] font-medium transition cursor-pointer btn-soft rounded-[6px]" title="Edit Terjual 4 Bulan & Review Period Produk">
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
                                    <td colspan="11" class="py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                        Tidak ada data working analisa yang cocok.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Bar --}}
                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#667085] dark:text-gray-400">
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
        </div>

        {{-- TABEL 3: Ringkasan Lead Time (lead_time_lokal) --}}
        <div x-show="lokalSubTab === 'lead_time'">
            <x-card class="tbl-modern" 
                title="Tabel Ringkasan Lead Time Lokal (lead_time_lokal)" 
                subtitle="Ringkasan lead time average, lead time max, tambahan buffer, dan Safety Stock = (Max - Avg) + Buffer"
                :noPadding="true"
            >
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </x-slot:icon>

                <div tabindex="0" role="region" aria-label="Tabel Ringkasan Lead Time Lokal" class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-xs font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                            <tr>
                                <th class="py-3 px-2.5">Bahan Baku</th>
                                <th class="py-3 px-2.5">Supplier</th>
                                <th class="py-3 px-2.5 text-right">Total Avg Lead Time</th>
                                <th class="py-3 px-2.5 text-right">Total Max Lead Time</th>
                                <th class="py-3 px-2.5 text-right">Tambahan Buffer</th>
                                <th class="py-3 px-2.5 text-right bg-gray-100/70 dark:bg-gray-800 text-[#101828] dark:text-white font-medium">Safety Stock (Hari)</th>
                                <th class="py-3 px-2 text-center w-28">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 bg-white dark:bg-gray-900">
                            <template x-for="r in paginatedRows()" :key="r.id">
                                <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                                    <td class="py-3 px-2.5">
                                        <div class="font-medium text-[#101828] dark:text-white" x-text="r.nama"></div>
                                        <div class="tabular-nums text-[11px] text-[#667085] dark:text-gray-400" x-text="r.sku + ' · Satuan: ' + r.satuan"></div>
                                    </td>
                                    <td class="py-3 px-2.5">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <button type="button" @click="openCompareSupplier(r)" class="font-medium text-[#111111] dark:text-white hover:underline text-left cursor-pointer flex items-center gap-1" title="Bandingkan & Ganti Supplier">
                                                <span x-text="r.supplier_nama || 'Pilih Supplier'"></span>
                                                <svg class="size-3 text-[#667085] dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                            </button>
                                            <template x-if="r.sumber_lt === 'otomatis'">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card border border-border dark:border-border" title="Sumber: Histori PO Otomatis">
                                                    <span class="size-1 rounded-full bg-gray-400 shrink-0"></span>
                                                    <span>Otomatis</span>
                                                </span>
                                            </template>
                                            <template x-if="r.sumber_lt !== 'otomatis'">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card border border-border dark:border-border" title="Sumber: Estimasi Input Manual">
                                                    <span class="size-1 rounded-full bg-gray-400 shrink-0"></span>
                                                    <span>Manual</span>
                                                </span>
                                            </template>
                                            <a :href="'{{ route('purchasing.index', ['tab' => 'po']) }}' + (r.supplier_nama && r.supplier_nama !== '-' ? '&search=' + encodeURIComponent(r.supplier_nama) : '')" href="{{ route('purchasing.index', ['tab' => 'po']) }}" class="text-[10px] text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white underline ml-1" title="Lihat Daftar Purchase Order Supplier">Histori LT</a>
                                        </div>
                                    </td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-xs text-[#667085] dark:text-gray-400" x-text="formatLt(r.total_avg_lead_time) + ' Hari'"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-xs text-[#667085] dark:text-gray-400" x-text="formatLt(r.total_max_lead_time) + ' Hari'"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white" x-text="formatLt(r.tambahan_buffer_hari) + ' Hari'"></td>
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#101828] dark:text-white bg-[#fafafa] dark:bg-gray-800/40" x-text="formatLt(r.safety_stock) + ' Hari'"></td>
                                    <td class="py-3 px-2 text-center">
                                        @can('analisa.manage')
                                        <div class="flex items-center justify-center gap-1.5">
                                            <button type="button" @click="openManualLokalModal(r)" class="px-2.5 py-1 text-[10px] font-medium transition cursor-pointer btn-soft rounded-[6px]" title="Edit Tambahan Buffer">
                                                Analisa
                                            </button>
                                            <button type="button" @click="openStageModal(r)" class="px-2.5 py-1 text-[10px] font-medium transition cursor-pointer btn-soft rounded-[6px]" title="Edit 8 Tahap Lead Time">
                                                LT
                                            </button>
                                        </div>
                                        @else
                                        <span class="text-[#667085] dark:text-gray-400 text-[10px]">-</span>
                                        @endcan
                                    </td>
                                </tr>
                            </template>
                            <template x-if="paginatedRows().length === 0">
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                        Tidak ada data ringkasan lead time yang cocok.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Bar --}}
                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#667085] dark:text-gray-400">
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
        </div>

        {{-- TABEL 4: Rincian 8 Tahap Lead Time (lead_time_lokal_stage) --}}
        <div x-show="lokalSubTab === 'stages'" class="space-y-4">
            {{-- Highlight Summary: Lead Time Terlama (Average & Max) --}}
            <template x-if="longestAvgStage() || longestMaxStage()">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-card class="tbl-modern" :noPadding="true">
                        <div class="px-4 py-3 bg-[#f8fafb] dark:bg-gray-800/80 border-b border-[#E8E8E8] dark:border-gray-800 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <svg class="size-4 text-[#101828] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h4 class="text-xs font-medium text-[#101828] dark:text-white uppercase tracking-wider">Lead Time Terlama (Average)</h4>
                            </div>
                            <span class="px-2.5 py-1 text-xs font-medium tabular-nums bg-[#111111] dark:bg-white text-white dark:text-[#111111] rounded-lg" x-text="formatLt(longestAvgStage()?.days || 0) + ' Hari'"></span>
                        </div>
                        <div class="p-4 bg-white dark:bg-gray-900 text-xs">
                            <table class="w-full text-left">
                                <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                                    <tr>
                                        <td class="py-2 text-[#667085] dark:text-gray-400 font-medium w-28">Bahan Baku</td>
                                        <td class="py-2 text-[#101828] dark:text-white text-right">
                                            <span class="font-medium text-[#101828] dark:text-white" x-text="longestAvgStage()?.nama || '-'"></span>
                                            <template x-if="longestAvgStage()?.sku">
                                                <span class="tabular-nums text-[#667085] dark:text-gray-400 text-[11px] ml-1" x-text="'(' + longestAvgStage().sku + ')'"></span>
                                            </template>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-[#667085] dark:text-gray-400 font-medium w-28">Supplier</td>
                                        <td class="py-2 text-[#101828] dark:text-white font-medium text-right" x-text="longestAvgStage()?.supplier_nama || '-'"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </x-card>

                    <x-card class="tbl-modern" :noPadding="true">
                        <div class="px-4 py-3 bg-[#f8fafb] dark:bg-gray-800/80 border-b border-[#E8E8E8] dark:border-gray-800 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <svg class="size-4 text-[#101828] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <h4 class="text-xs font-medium text-[#101828] dark:text-white uppercase tracking-wider">Lead Time Terlama (Max Skenario)</h4>
                            </div>
                            <span class="px-2.5 py-1 text-xs font-medium tabular-nums bg-[#111111] dark:bg-white text-white dark:text-[#111111] rounded-lg" x-text="formatLt(longestMaxStage()?.days || 0) + ' Hari'"></span>
                        </div>
                        <div class="p-4 bg-white dark:bg-gray-900 text-xs">
                            <table class="w-full text-left">
                                <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                                    <tr>
                                        <td class="py-2 text-[#667085] dark:text-gray-400 font-medium w-28">Bahan Baku</td>
                                        <td class="py-2 text-[#101828] dark:text-white text-right">
                                            <span class="font-medium text-[#101828] dark:text-white" x-text="longestMaxStage()?.nama || '-'"></span>
                                            <template x-if="longestMaxStage()?.sku">
                                                <span class="tabular-nums text-[#667085] dark:text-gray-400 text-[11px] ml-1" x-text="'(' + longestMaxStage().sku + ')'"></span>
                                            </template>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 text-[#667085] dark:text-gray-400 font-medium w-28">Supplier</td>
                                        <td class="py-2 text-[#101828] dark:text-white font-medium text-right" x-text="longestMaxStage()?.supplier_nama || '-'"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </div>
            </template>

            <x-card class="tbl-modern" 
                title="Tabel Rincian 8 Tahap Lead Time (lead_time_lokal_stage)" 
                subtitle="Perencanaan, Konfirmasi Supplier, Persetujuan, Pembayaran, PO Terbit, Pengiriman, Unloading, dan Input Sistem"
                :noPadding="true"
            >
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </x-slot:icon>

                <div tabindex="0" role="region" aria-label="Tabel Rincian 8 Tahap Lead Time" class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[11px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                            <tr>
                                <th class="py-3 px-2">Bahan</th>
                                <th class="py-3 px-2">Supplier</th>
                                <th class="py-3 px-2">Skenario</th>
                                <th class="py-3 px-2 text-right">Rencana</th>
                                <th class="py-3 px-2 text-right">Konfirmasi</th>
                                <th class="py-3 px-2 text-right">Setuju</th>
                                <th class="py-3 px-2 text-right">Bayar</th>
                                <th class="py-3 px-2 text-right">PO</th>
                                <th class="py-3 px-2 text-right">Kirim</th>
                                <th class="py-3 px-2 text-right">Unload</th>
                                <th class="py-3 px-2 text-right">Input</th>
                                <th class="py-3 px-2 text-right bg-gray-100/70 dark:bg-gray-800 font-medium text-[#101828] dark:text-white">Total</th>
                                <th class="py-3 px-2 text-center w-20">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 bg-white dark:bg-gray-900">
                            <template x-for="stg in paginatedStages()" :key="stg.produk_id">
                                <template x-for="type in ['average', 'max']" :key="type">
                                    <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition" :class="type === 'max' ? 'border-b-2 border-[#E8E8E8] dark:border-gray-700' : ''">
                                        <td class="py-2.5 px-2" x-show="type === 'average'" rowspan="2">
                                            <div class="font-medium text-[#101828] dark:text-white text-xs" x-text="stg.nama"></div>
                                            <div class="tabular-nums text-[10px] text-[#667085] dark:text-gray-400" x-text="stg.sku"></div>
                                        </td>
                                        <td class="py-2.5 px-2" x-show="type === 'average'" rowspan="2">
                                            <div class="flex flex-col gap-1">
                                                <button type="button" @click="openCompareSupplier(stg)" class="font-medium text-[#111111] dark:text-white hover:underline text-left cursor-pointer inline-flex items-center gap-1 text-xs" title="Bandingkan & Ganti Supplier">
                                                    <span x-text="stg.supplier_nama || 'Pilih Supplier'"></span>
                                                    <svg class="size-3 text-[#667085] dark:text-gray-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                                </button>
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <template x-if="stg.sumber_lt === 'otomatis'">
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card border border-border dark:border-border" title="Sumber: Rincian 8 Stage Histori PO Otomatis">
                                                            <span class="size-1 rounded-full bg-gray-400 shrink-0"></span>
                                                            <span x-text="'Otomatis' + (stg.jumlah_transaksi ? ' (' + stg.jumlah_transaksi + ' PO)' : '')"></span>
                                                        </span>
                                                    </template>
                                                    <template x-if="stg.sumber_lt !== 'otomatis'">
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card border border-border dark:border-border" title="Sumber: Estimasi Input Manual">
                                                            <span class="size-1 rounded-full bg-gray-400 shrink-0"></span>
                                                            <span>Manual</span>
                                                        </span>
                                                    </template>
                                                    <a :href="'{{ route('purchasing.index', ['tab' => 'po']) }}' + (stg.supplier_nama && stg.supplier_nama !== '-' ? '&search=' + encodeURIComponent(stg.supplier_nama) : '')" href="{{ route('purchasing.index', ['tab' => 'po']) }}" class="text-[10px] text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white underline" title="Lihat Daftar Purchase Order Supplier">Histori LT</a>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-2 tabular-nums uppercase text-[10px] font-medium text-[#667085] dark:text-gray-400" x-text="type"></td>
                                        <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(stg[type] ? stg[type].perencanaan : 0)"></td>
                                        <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(stg[type] ? stg[type].supplier_confirm : 0)"></td>
                                        <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(stg[type] ? stg[type].approval : 0)"></td>
                                        <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(stg[type] ? stg[type].payment : 0)"></td>
                                        <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(stg[type] ? stg[type].po : 0)"></td>
                                        <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(stg[type] ? stg[type].pengiriman : 0)"></td>
                                        <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(stg[type] ? stg[type].unloading : 0)"></td>
                                        <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(stg[type] ? stg[type].input : 0)"></td>
                                        <td class="py-2.5 px-2 text-right tabular-nums text-[#101828] dark:text-white bg-[#fafafa] dark:bg-gray-800/40" x-text="formatLt(stg[type] ? stg[type].total : 0) + ' Hari'"></td>
                                        <td class="py-2.5 px-2 text-center" x-show="type === 'average'" rowspan="2">
                                            @can('analisa.manage')
                                            <button type="button" @click="openStageModal(stg)" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium transition cursor-pointer btn-soft rounded-[6px]" title="Edit Rincian 8 Tahap Lead Time">
                                                <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                Edit
                                            </button>
                                            @endcan
                                        </td>
                                    </tr>
                                </template>
                            </template>
                            <template x-if="paginatedStages().length === 0">
                                <tr>
                                    <td colspan="13" class="py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                        Tidak ada rincian tahap lead time yang cocok.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Bar --}}
                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#667085] dark:text-gray-400">
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
        </div>
    </div>
</template>

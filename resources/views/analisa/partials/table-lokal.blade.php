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
                    2. Analisa
                </button>
                <button type="button" @click="lokalSubTab = 'stages'; page = 1" :class="lokalSubTab === 'stages' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white font-medium shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white font-medium'" class="px-3.5 py-1.5 transition-all cursor-pointer rounded-[6px]">
                    3. Rincian 8 Tahap Lead Time
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
                                    <td class="py-3 px-2.5 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatRupiah(r.harga_per_satuan)"></td>
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

        {{-- TABEL 2: Analisa (analisa_lokal + ringkasan lead_time_lokal — seluruh kombinasi supplier-produk) --}}
        <div x-show="lokalSubTab === 'analisa'">
            <x-card class="tbl-modern"
                title="Tabel Analisa Stok — Seluruh Supplier–Produk"
                subtitle="ADU dihitung murni per produk (tidak bergantung supplier); Avg Lead Time & Safety Stock mengikuti masing-masing supplier. Batas Minimum & Target Stock ada di Rekomendasi Order Lokal"
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
                                <th class="py-3 px-2.5 text-right">Generated At</th>
                                <th class="py-3 px-2 text-center w-40">Aksi</th>
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
                                    <td class="py-3 px-2.5 text-right tabular-nums text-xs text-[#667085] dark:text-gray-400" x-text="r.generated_at"></td>
                                    <td class="py-3 px-2 text-center">
                                        @can('analisa.manage')
                                        <div class="flex items-center justify-center gap-1 flex-wrap">
                                            <template x-if="!r.is_active_supplier && r.supplier_id">
                                                <button type="button" @click="selectSupplierDirect(r.produk_id, r.supplier_id)" :disabled="savingSelectSupplier" class="px-2.5 py-1 disabled:opacity-50 text-[10px] font-medium transition cursor-pointer btn-glossy rounded-[6px]" title="Jadikan Supplier Aktif untuk Rekomendasi Order">
                                                    Aktifkan
                                                </button>
                                            </template>
                                            <button type="button" @click="openManualLokalModal(r)" class="px-2.5 py-1 text-[10px] font-medium transition cursor-pointer btn-soft rounded-[6px]" title="Edit Review Period, Buffer, Harga & Koreksi Stok">
                                                Edit Input
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
                            <template x-if="paginatedAnalisaRows().length === 0">
                                <tr>
                                    <td colspan="9" class="py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                        Tidak ada data analisa yang cocok.
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

        @include('analisa.partials.table-stages', ['tipe' => 'lokal'])
    </div>
</template>

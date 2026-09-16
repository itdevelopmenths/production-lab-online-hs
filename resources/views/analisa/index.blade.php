<x-app-layout title="Analisa Stok">
    <div x-data="analisa()" x-init="load()" class="space-y-5">
        <x-page-header
            title="Analisa & Rekomendasi Stok"
            subtitle="Engine kalkulasi otomatis Lead Time, Buffer Stock, Batas Minimum, Target Stock, dan Rekomendasi Pemesanan"
            :breadcrumbs="['Inventori' => null, 'Analisa Stok' => null]"
        >
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <template x-if="tab === 'lokal'">
                        <div class="flex items-center gap-2">
                            @can('analisa.manage')
                            <button type="button" @click="generateLokal()" :disabled="generating" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-700 hover:bg-primary-800 disabled:opacity-50 text-white text-xs font-semibold rounded-sm shadow-xs transition cursor-pointer">
                                <svg x-show="!generating" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                <svg x-show="generating" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span x-text="generating ? 'Menghitung Engine...' : 'Generate Analisa Lokal'"></span>
                            </button>
                            @endcan

                            @can('analisa.create_po')
                            <button type="button" @click="openPoModal()" x-show="selectedOrderCount() > 0" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold rounded-sm shadow-xs transition cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>Buat PO (<span x-text="selectedOrderCount()"></span>)</span>
                            </button>
                            @endcan
                        </div>
                    </template>

                    @can('analisa.snapshot')
                    <button type="button" @click="snapshot()" x-show="tab !== 'riwayat'" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-800 hover:bg-gray-900 text-white text-xs font-medium rounded-sm shadow-xs transition cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                        <span>Simpan Snapshot Riwayat</span>
                    </button>
                    @endcan
                </div>
            </x-slot:actions>
        </x-page-header>

        <!-- Segmented Tab Navigation & Status -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-3">
            <div class="inline-flex rounded-sm bg-gray-100 p-0.5 border border-gray-200">
                <template x-for="t in tabs" :key="t.key">
                    <button type="button"
                        @click="tab = t.key; load()"
                        :class="tab === t.key ? 'bg-white text-primary-700 shadow-xs font-semibold' : 'text-gray-600 hover:text-gray-900 font-medium'"
                        class="px-3.5 py-1.5 text-xs rounded-sm transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <span x-text="t.label"></span>
                    </button>
                </template>
            </div>
            
            <div class="text-xs text-gray-500 flex items-center gap-2">
                <template x-if="tab === 'lokal' && meta.last_generated_at">
                    <div class="flex items-center gap-1.5 text-[11px] bg-primary-50 text-primary-800 px-2 py-0.5 rounded border border-primary-100 font-mono">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Di-generate: <strong x-text="meta.last_generated_at"></strong> (<span x-text="meta.last_generated_by"></span>)</span>
                    </div>
                </template>
                <template x-if="tab !== 'lokal' || !meta.last_generated_at">
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Data Real-time Sistem</span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Quick KPI Summary Strip -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-xs">
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Item</p>
                <p class="text-lg font-bold font-mono text-gray-900 mt-0.5" x-text="rows.length"></p>
            </div>
            <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-xs">
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Perlu Tindakan / Order</p>
                <p class="text-lg font-bold font-mono text-rose-600 mt-0.5" x-text="kpiOrderCount()"></p>
            </div>
            <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-xs">
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Status Aman</p>
                <p class="text-lg font-bold font-mono text-emerald-600 mt-0.5" x-text="kpiAmanCount()"></p>
            </div>
            <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-xs">
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Qty Rekomendasi</p>
                <p class="text-lg font-bold font-mono text-primary-700 mt-0.5" x-text="kpiTotalOrderQty()"></p>
            </div>
        </div>

        <!-- Toolbar Pencarian, Filter & Kontrol Pagination -->
        <div class="bg-white p-3 rounded-sm border border-gray-200 shadow-xs flex flex-wrap items-center justify-between gap-3">
            <!-- Kiri: Input Cari & Filter Status -->
            <div class="flex flex-wrap items-center gap-2.5 flex-1 min-w-[280px]">
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                    <input 
                        type="text" 
                        x-model.debounce.250ms="searchQuery" 
                        @input="page = 1; if(tab !== 'lokal') renderTable();"
                        placeholder="Cari SKU, nama bahan / item..."
                        class="w-full pl-8 pr-7 py-1.5 text-xs rounded-sm border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    >
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <button type="button" x-show="searchQuery" @click="searchQuery = ''; page = 1; if(tab !== 'lokal') renderTable();" class="absolute inset-y-0 right-0 pr-2 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <template x-if="tab === 'lokal' && lokalSubTab === 'rekomendasi'">
                    <div class="flex items-center gap-1.5">
                        <select 
                            x-model="statusFilter" 
                            @change="page = 1"
                            class="rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                        >
                            <option value="all">Semua Status</option>
                            <option value="order">Hanya Perlu Order</option>
                            <option value="aman">Hanya Status Aman</option>
                        </select>
                    </div>
                </template>
            </div>

            <!-- Kanan: Tombol Aksi Massal & Jumlah per Halaman -->
            <div class="flex flex-wrap items-center gap-2.5 text-xs">
                <template x-if="tab === 'lokal' && lokalSubTab === 'rekomendasi'">
                    <div class="flex items-center gap-1.5 border-r border-gray-200 pr-2.5">
                        <button type="button" @click="selectAllPerluOrder()" class="px-2 py-1 text-[11px] font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 rounded border border-rose-200 transition cursor-pointer">
                            Pilih Perlu Order (<span x-text="kpiOrderCount()"></span>)
                        </button>
                        <button type="button" @click="selectAllFiltered()" class="px-2 py-1 text-[11px] font-medium text-gray-700 bg-gray-50 hover:bg-gray-100 rounded border border-gray-200 transition cursor-pointer">
                            Pilih Semua Hasil (<span x-text="filteredRows().length"></span>)
                        </button>
                        <button type="button" x-show="selectedItemIds.length > 0" @click="selectedItemIds = []" class="px-2 py-1 text-[11px] font-medium text-gray-500 hover:text-gray-800 transition cursor-pointer">
                            Batal Pilih
                        </button>
                    </div>
                </template>

                <div class="flex items-center gap-1.5 text-gray-600 text-xs">
                    <span>Tampilkan:</span>
                    <select 
                        x-model="perPage" 
                        @change="page = 1; if(tab !== 'lokal') renderTable();"
                        class="rounded-sm border-gray-300 text-xs py-1 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                        <option value="all">Semua</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Section: Bahan Baku Lokal 4-Tabel Engine -->
        <template x-if="tab === 'lokal'">
            <div class="space-y-4">
                <!-- Sub-navigasi 4 Tabel Lokal -->
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-2">
                    <div class="inline-flex gap-1 bg-gray-50 p-1 rounded border border-gray-200 text-xs">
                        <button type="button" @click="lokalSubTab = 'rekomendasi'; page = 1" :class="lokalSubTab === 'rekomendasi' ? 'bg-primary-700 text-white font-bold shadow-xs' : 'text-gray-700 hover:bg-gray-200/60 font-medium'" class="px-3 py-1 rounded-xs transition cursor-pointer">
                            1. Rekomendasi Order Lokal
                        </button>
                        <button type="button" @click="lokalSubTab = 'analisa'; page = 1" :class="lokalSubTab === 'analisa' ? 'bg-primary-700 text-white font-bold shadow-xs' : 'text-gray-700 hover:bg-gray-200/60 font-medium'" class="px-3 py-1 rounded-xs transition cursor-pointer">
                            2. Working Data Analisa (ADU)
                        </button>
                        <button type="button" @click="lokalSubTab = 'lead_time'; page = 1" :class="lokalSubTab === 'lead_time' ? 'bg-primary-700 text-white font-bold shadow-xs' : 'text-gray-700 hover:bg-gray-200/60 font-medium'" class="px-3 py-1 rounded-xs transition cursor-pointer">
                            3. Ringkasan Lead Time & Buffer
                        </button>
                        <button type="button" @click="lokalSubTab = 'stages'; page = 1" :class="lokalSubTab === 'stages' ? 'bg-primary-700 text-white font-bold shadow-xs' : 'text-gray-700 hover:bg-gray-200/60 font-medium'" class="px-3 py-1 rounded-xs transition cursor-pointer">
                            4. Rincian 9 Tahap Lead Time
                        </button>
                    </div>

                    <div class="text-[11px] text-gray-500 font-mono">
                        Data Tersimpan di Database Engine
                    </div>
                </div>

                <!-- TABEL 1: Rekomendasi Order Lokal (Working Data) -->
                <div x-show="lokalSubTab === 'rekomendasi'" class="bg-white rounded-sm border border-gray-200 shadow-xs overflow-hidden">
                    <div class="p-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-bold text-gray-900">Tabel Rekomendasi Pemesanan Bahan Lokal (rekomendasi_order_lokal)</h3>
                            <p class="text-[11px] text-gray-500">Kalkulasi selisih stok, batas minimum, pembulatan kelipatan MOQ, dan rekomendasi order dalam satuan beli</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-700 text-[11px] uppercase font-semibold border-b border-gray-200">
                                <tr>
                                    <th class="py-2.5 px-3 text-center w-10">
                                        <input type="checkbox" @change="toggleSelectCurrentPage($event)" :checked="isCurrentPageAllSelected()" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                                    </th>
                                    <th class="py-2.5 px-3">Bahan Baku</th>
                                    <th class="py-2.5 px-3 text-right">Batas Min</th>
                                    <th class="py-2.5 px-3 text-right">Target</th>
                                    <th class="py-2.5 px-3 text-right">Stok Fisik</th>
                                    <th class="py-2.5 px-3 text-right">Akan Datang</th>
                                    <th class="py-2.5 px-3 text-right">Selisih</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-right">Rumus MOQ</th>
                                    <th class="py-2.5 px-3 text-right bg-primary-50/50 text-primary-900 font-bold">Rekomendasi Order</th>
                                    <th class="py-2.5 px-3 text-right">Harga Satuan</th>
                                    <th class="py-2.5 px-3 text-right">Estimasi Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="r in paginatedRows()" :key="r.id">
                                    <tr class="hover:bg-gray-50/80 transition" :class="r.status === 'order' ? 'bg-rose-50/20' : ''">
                                        <td class="py-2.5 px-3 text-center">
                                            <input type="checkbox" :value="r.id" x-model="selectedItemIds" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 cursor-pointer">
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <div class="font-bold text-gray-900" x-text="r.nama"></div>
                                            <div class="font-mono text-[10px] text-gray-400" x-text="r.sku + ' · Satuan: ' + r.satuan"></div>
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="formatNumber(r.batas_minimum)"></td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="formatNumber(r.target_stock)"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-medium text-gray-900" x-text="formatNumber(r.stok_saat_ini)"></td>
                                        <td class="py-2.5 px-3 text-right font-mono text-gray-600" x-text="formatNumber(r.akan_datang)"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold" :class="r.selisih < 0 ? 'text-rose-600' : 'text-emerald-700'" x-text="formatNumber(r.selisih)"></td>
                                        <td class="py-2.5 px-3 text-center" x-html="badge(r.status)"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-medium" x-text="formatNumber(r.rumus_moq) + ' ' + r.satuan"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold bg-primary-50/30 text-primary-900">
                                            <span x-text="formatNumber(r.rekomendasi_order)"></span>
                                            <template x-if="r.faktor_konversi > 1">
                                                <span class="text-[10px] font-normal text-gray-500 block" x-text="'(x' + r.faktor_konversi + ' ' + r.satuan + ')'"></span>
                                            </template>
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-gray-600" x-text="'Rp ' + formatNumber(r.harga_per_satuan)"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900" x-text="'Rp ' + formatNumber(r.total_nominal_order)"></td>
                                    </tr>
                                </template>
                                <template x-if="paginatedRows().length === 0">
                                    <tr>
                                        <td colspan="12" class="py-8 text-center text-gray-400 text-xs">
                                            Tidak ada item bahan lokal yang cocok dengan pencarian atau filter.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Bar -->
                    <div class="p-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
                        <div class="flex items-center gap-2 font-mono text-[11px]">
                            <span>Menampilkan <strong x-text="startItem()"></strong> - <strong x-text="endItem()"></strong> dari <strong x-text="currentDataLength()"></strong> item</span>
                            <template x-if="searchQuery || statusFilter !== 'all'">
                                <span class="text-primary-700 bg-primary-50 px-1.5 py-0.5 rounded border border-primary-200 text-[10px] font-sans">
                                    (Difilter dari <span x-text="currentRawLength()"></span> total)
                                </span>
                            </template>
                        </div>

                        <div class="flex items-center gap-1" x-show="totalPages() > 1">
                            <button 
                                type="button" 
                                @click="gotoPage(page - 1)" 
                                :disabled="page <= 1"
                                class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                &laquo; Prev
                            </button>

                            <template x-for="p in pagesList()" :key="p">
                                <div>
                                    <template x-if="p === '...'">
                                        <span class="px-2 py-1 text-gray-400">...</span>
                                    </template>
                                    <template x-if="p !== '...'">
                                        <button 
                                            type="button" 
                                            @click="gotoPage(p)"
                                            :class="page === p ? 'bg-primary-700 text-white font-bold border-primary-700' : 'bg-white text-gray-700 hover:bg-gray-100 border-gray-300'"
                                            class="min-w-[28px] px-2 py-1 text-xs rounded border transition cursor-pointer"
                                            x-text="p"
                                        ></button>
                                    </template>
                                </div>
                            </template>

                            <button 
                                type="button" 
                                @click="gotoPage(page + 1)" 
                                :disabled="page >= totalPages()"
                                class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                Next &raquo;
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TABEL 2: Working Data Analisa (analisa_lokal) -->
                <div x-show="lokalSubTab === 'analisa'" class="bg-white rounded-sm border border-gray-200 shadow-xs overflow-hidden">
                    <div class="p-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-xs font-bold text-gray-900">Tabel Working Data Analisa Stok (analisa_lokal)</h3>
                        <p class="text-[11px] text-gray-500">Perhitungan ADU (Average Daily Usage), Batas Minimum [ADU x (Lead Time + Safety Stock)], dan Target Stock</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-700 text-[11px] uppercase font-semibold border-b border-gray-200">
                                <tr>
                                    <th class="py-2.5 px-3">Bahan Baku</th>
                                    <th class="py-2.5 px-3 text-right">Avg Lead Time (Hari)</th>
                                    <th class="py-2.5 px-3 text-right">Safety Stock (Hari)</th>
                                    <th class="py-2.5 px-3 text-right">Terjual 4 Bulan</th>
                                    <th class="py-2.5 px-3 text-right bg-amber-50/50 text-amber-900">ADU (Harian)</th>
                                    <th class="py-2.5 px-3 text-right">Review Period</th>
                                    <th class="py-2.5 px-3 text-right bg-primary-50/50 text-primary-900 font-bold">Batas Minimum</th>
                                    <th class="py-2.5 px-3 text-right font-bold">Target Stock</th>
                                    <th class="py-2.5 px-3 text-right">Generated At</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="r in paginatedRows()" :key="r.id">
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="py-2.5 px-3 font-medium text-gray-900" x-text="r.nama + ' (' + r.sku + ')'"></td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="r.total_avg_lead_time"></td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="r.safety_stock"></td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="formatNumber(r.terjual_rata_rata_4bulan)"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-amber-800 bg-amber-50/30" x-text="Number(r.adu).toFixed(4)"></td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="r.review_period + ' Hari'"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-primary-800 bg-primary-50/30" x-text="formatNumber(r.batas_minimum) + ' ' + r.satuan"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900" x-text="formatNumber(r.target_stock) + ' ' + r.satuan"></td>
                                        <td class="py-2.5 px-3 text-right font-mono text-[11px] text-gray-400" x-text="r.generated_at"></td>
                                    </tr>
                                </template>
                                <template x-if="paginatedRows().length === 0">
                                    <tr>
                                        <td colspan="9" class="py-8 text-center text-gray-400 text-xs">
                                            Tidak ada data working analisa yang cocok.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Bar -->
                    <div class="p-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
                        <div class="flex items-center gap-2 font-mono text-[11px]">
                            <span>Menampilkan <strong x-text="startItem()"></strong> - <strong x-text="endItem()"></strong> dari <strong x-text="currentDataLength()"></strong> item</span>
                        </div>

                        <div class="flex items-center gap-1" x-show="totalPages() > 1">
                            <button 
                                type="button" 
                                @click="gotoPage(page - 1)" 
                                :disabled="page <= 1"
                                class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                &laquo; Prev
                            </button>

                            <template x-for="p in pagesList()" :key="p">
                                <div>
                                    <template x-if="p === '...'">
                                        <span class="px-2 py-1 text-gray-400">...</span>
                                    </template>
                                    <template x-if="p !== '...'">
                                        <button 
                                            type="button" 
                                            @click="gotoPage(p)"
                                            :class="page === p ? 'bg-primary-700 text-white font-bold border-primary-700' : 'bg-white text-gray-700 hover:bg-gray-100 border-gray-300'"
                                            class="min-w-[28px] px-2 py-1 text-xs rounded border transition cursor-pointer"
                                            x-text="p"
                                        ></button>
                                    </template>
                                </div>
                            </template>

                            <button 
                                type="button" 
                                @click="gotoPage(page + 1)" 
                                :disabled="page >= totalPages()"
                                class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                Next &raquo;
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TABEL 3: Ringkasan Lead Time (lead_time_lokal) -->
                <div x-show="lokalSubTab === 'lead_time'" class="bg-white rounded-sm border border-gray-200 shadow-xs overflow-hidden">
                    <div class="p-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-xs font-bold text-gray-900">Tabel Ringkasan Lead Time Lokal (lead_time_lokal)</h3>
                        <p class="text-[11px] text-gray-500">Ringkasan lead time average, lead time max, tambahan buffer, dan Safety Stock = (Max - Avg) + Buffer</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-700 text-[11px] uppercase font-semibold border-b border-gray-200">
                                <tr>
                                    <th class="py-2.5 px-3">Bahan Baku</th>
                                    <th class="py-2.5 px-3 text-right">Total Avg Lead Time</th>
                                    <th class="py-2.5 px-3 text-right">Total Max Lead Time</th>
                                    <th class="py-2.5 px-3 text-right">Tambahan Buffer</th>
                                    <th class="py-2.5 px-3 text-right bg-emerald-50/50 text-emerald-900 font-bold">Safety Stock (Hari)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="r in paginatedRows()" :key="r.id">
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="py-2.5 px-3 font-medium text-gray-900" x-text="r.nama + ' (' + r.sku + ')'"></td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="r.total_avg_lead_time + ' Hari'"></td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="r.total_max_lead_time + ' Hari'"></td>
                                        <td class="py-2.5 px-3 text-right font-mono text-gray-500" x-text="(r.safety_stock - (r.total_max_lead_time - r.total_avg_lead_time)) + ' Hari'"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-800 bg-emerald-50/30" x-text="r.safety_stock + ' Hari'"></td>
                                    </tr>
                                </template>
                                <template x-if="paginatedRows().length === 0">
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-gray-400 text-xs">
                                            Tidak ada data ringkasan lead time yang cocok.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Bar -->
                    <div class="p-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
                        <div class="flex items-center gap-2 font-mono text-[11px]">
                            <span>Menampilkan <strong x-text="startItem()"></strong> - <strong x-text="endItem()"></strong> dari <strong x-text="currentDataLength()"></strong> item</span>
                        </div>

                        <div class="flex items-center gap-1" x-show="totalPages() > 1">
                            <button 
                                type="button" 
                                @click="gotoPage(page - 1)" 
                                :disabled="page <= 1"
                                class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                &laquo; Prev
                            </button>

                            <template x-for="p in pagesList()" :key="p">
                                <div>
                                    <template x-if="p === '...'">
                                        <span class="px-2 py-1 text-gray-400">...</span>
                                    </template>
                                    <template x-if="p !== '...'">
                                        <button 
                                            type="button" 
                                            @click="gotoPage(p)"
                                            :class="page === p ? 'bg-primary-700 text-white font-bold border-primary-700' : 'bg-white text-gray-700 hover:bg-gray-100 border-gray-300'"
                                            class="min-w-[28px] px-2 py-1 text-xs rounded border transition cursor-pointer"
                                            x-text="p"
                                        ></button>
                                    </template>
                                </div>
                            </template>

                            <button 
                                type="button" 
                                @click="gotoPage(page + 1)" 
                                :disabled="page >= totalPages()"
                                class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                Next &raquo;
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TABEL 4: Rincian 9 Tahap Lead Time (lead_time_lokal_stage) -->
                <div x-show="lokalSubTab === 'stages'" class="bg-white rounded-sm border border-gray-200 shadow-xs overflow-hidden">
                    <div class="p-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-bold text-gray-900">Tabel Rincian 9 Tahap Lead Time (lead_time_lokal_stage)</h3>
                            <p class="text-[11px] text-gray-500">Perencanaan, Approval, Supplier Confirm, Payment, PO, Pengemasan, Pengiriman, Unloading, dan Input</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-gray-700 text-[10px] uppercase font-semibold border-b border-gray-200">
                                <tr>
                                    <th class="py-2.5 px-2">Bahan</th>
                                    <th class="py-2.5 px-2">Skenario</th>
                                    <th class="py-2.5 px-1.5 text-right">Rencana</th>
                                    <th class="py-2.5 px-1.5 text-right">Apprv</th>
                                    <th class="py-2.5 px-1.5 text-right">Confirm</th>
                                    <th class="py-2.5 px-1.5 text-right">Bayar</th>
                                    <th class="py-2.5 px-1.5 text-right">PO</th>
                                    <th class="py-2.5 px-1.5 text-right">Kemas</th>
                                    <th class="py-2.5 px-1.5 text-right">Kirim</th>
                                    <th class="py-2.5 px-1.5 text-right">Unload</th>
                                    <th class="py-2.5 px-1.5 text-right">Input</th>
                                    <th class="py-2.5 px-2 text-right bg-primary-50/50 font-bold">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="stg in paginatedStages()" :key="stg.produk_id">
                                    <template x-for="type in ['average', 'max']" :key="type">
                                        <tr class="hover:bg-gray-50 transition" :class="type === 'max' ? 'border-b-2 border-gray-200' : ''">
                                            <td class="py-2 px-2 font-medium text-gray-900" x-show="type === 'average'" rowspan="2" x-text="stg.nama"></td>
                                            <td class="py-2 px-2 font-mono uppercase text-[10px] font-bold" :class="type === 'average' ? 'text-blue-700' : 'text-amber-700'" x-text="type"></td>
                                            <td class="py-2 px-1.5 text-right font-mono" x-text="stg[type] ? stg[type].perencanaan : 0"></td>
                                            <td class="py-2 px-1.5 text-right font-mono" x-text="stg[type] ? stg[type].approval : 0"></td>
                                            <td class="py-2 px-1.5 text-right font-mono" x-text="stg[type] ? stg[type].supplier_confirm : 0"></td>
                                            <td class="py-2 px-1.5 text-right font-mono" x-text="stg[type] ? stg[type].payment : 0"></td>
                                            <td class="py-2 px-1.5 text-right font-mono" x-text="stg[type] ? stg[type].po : 0"></td>
                                            <td class="py-2 px-1.5 text-right font-mono" x-text="stg[type] ? stg[type].pengemasan : 0"></td>
                                            <td class="py-2 px-1.5 text-right font-mono" x-text="stg[type] ? stg[type].pengiriman : 0"></td>
                                            <td class="py-2 px-1.5 text-right font-mono" x-text="stg[type] ? stg[type].unloading : 0"></td>
                                            <td class="py-2 px-1.5 text-right font-mono" x-text="stg[type] ? stg[type].input : 0"></td>
                                            <td class="py-2 px-2 text-right font-mono font-bold text-primary-900 bg-primary-50/30" x-text="(stg[type] ? stg[type].total : 0) + ' Hari'"></td>
                                        </tr>
                                    </template>
                                </template>
                                <template x-if="paginatedStages().length === 0">
                                    <tr>
                                        <td colspan="12" class="py-8 text-center text-gray-400 text-xs">
                                            Tidak ada rincian tahap lead time yang cocok.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Bar -->
                    <div class="p-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
                        <div class="flex items-center gap-2 font-mono text-[11px]">
                            <span>Menampilkan <strong x-text="startItem()"></strong> - <strong x-text="endItem()"></strong> dari <strong x-text="currentDataLength()"></strong> item</span>
                        </div>

                        <div class="flex items-center gap-1" x-show="totalPages() > 1">
                            <button 
                                type="button" 
                                @click="gotoPage(page - 1)" 
                                :disabled="page <= 1"
                                class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                &laquo; Prev
                            </button>

                            <template x-for="p in pagesList()" :key="p">
                                <div>
                                    <template x-if="p === '...'">
                                        <span class="px-2 py-1 text-gray-400">...</span>
                                    </template>
                                    <template x-if="p !== '...'">
                                        <button 
                                            type="button" 
                                            @click="gotoPage(p)"
                                            :class="page === p ? 'bg-primary-700 text-white font-bold border-primary-700' : 'bg-white text-gray-700 hover:bg-gray-100 border-gray-300'"
                                            class="min-w-[28px] px-2 py-1 text-xs rounded border transition cursor-pointer"
                                            x-text="p"
                                        ></button>
                                    </template>
                                </div>
                            </template>

                            <button 
                                type="button" 
                                @click="gotoPage(page + 1)" 
                                :disabled="page >= totalPages()"
                                class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                            >
                                Next &raquo;
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Section: Other Tabs (Impor, Fulfillment, Riwayat) -->
        <div x-show="tab !== 'lokal'" class="bg-white rounded-sm border border-gray-200 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr id="ahead"></tr>
                    </thead>
                    <tbody id="abody" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>

            <!-- Loading Spinner -->
            <div x-show="loading" class="py-12 flex flex-col items-center justify-center text-gray-500">
                <svg class="animate-spin h-6 w-6 text-primary-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-xs">Memuat kalkulasi data analisa...</span>
            </div>

            <!-- Empty State -->
            <div x-show="!loading && rows.length === 0" class="py-12 text-center">
                <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="mt-2 text-xs font-medium text-gray-500">Tidak ada data analisa untuk kategori ini.</p>
            </div>

            <!-- Pagination Bar for Other Tabs -->
            <div x-show="!loading && rows.length > 0" class="p-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
                <div class="flex items-center gap-2 font-mono text-[11px]">
                    <span>Menampilkan <strong x-text="startItem()"></strong> - <strong x-text="endItem()"></strong> dari <strong x-text="currentDataLength()"></strong> item</span>
                </div>

                <div class="flex items-center gap-1" x-show="totalPages() > 1">
                    <button 
                        type="button" 
                        @click="gotoPage(page - 1)" 
                        :disabled="page <= 1"
                        class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                    >
                        &laquo; Prev
                    </button>

                    <template x-for="p in pagesList()" :key="p">
                        <div>
                            <template x-if="p === '...'">
                                <span class="px-2 py-1 text-gray-400">...</span>
                            </template>
                            <template x-if="p !== '...'">
                                <button 
                                    type="button" 
                                    @click="gotoPage(p)"
                                    :class="page === p ? 'bg-primary-700 text-white font-bold border-primary-700' : 'bg-white text-gray-700 hover:bg-gray-100 border-gray-300'"
                                    class="min-w-[28px] px-2 py-1 text-xs rounded border transition cursor-pointer"
                                    x-text="p"
                                ></button>
                            </template>
                        </div>
                    </template>

                    <button 
                        type="button" 
                        @click="gotoPage(page + 1)" 
                        :disabled="page >= totalPages()"
                        class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
                    >
                        Next &raquo;
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Buat Draft Purchase Order dari Rekomendasi (Wide ERP Layout) -->
        <div x-show="poModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <div @click="poModalOpen = false" class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-md text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-200">
                    <form method="POST" action="{{ route('analisa.create-po') }}">
                        @csrf

                        <!-- Hidden Inputs for all selected items -->
                        <div class="hidden">
                            <template x-for="(item, idx) in getSelectedItems()" :key="'hidden-' + item.id">
                                <div>
                                    <input type="hidden" :name="`items[${idx}][produk_id]`" :value="item.produk_id">
                                    <input type="hidden" :name="`items[${idx}][qty]`" :value="item.rumus_moq">
                                    <input type="hidden" :name="`items[${idx}][qty_satuan_beli]`" :value="item.rekomendasi_order">
                                    <input type="hidden" :name="`items[${idx}][satuan_beli]`" :value="item.satuan">
                                    <input type="hidden" :name="`items[${idx}][faktor_konversi]`" :value="item.faktor_konversi">
                                    <input type="hidden" :name="`items[${idx}][harga_satuan]`" :value="item.harga_per_satuan">
                                    <input type="hidden" :name="`items[${idx}][harga_total]`" :value="item.total_nominal_order">
                                </div>
                            </template>
                        </div>

                        <!-- Header Modal -->
                        <div class="bg-gray-50/80 px-5 py-3.5 border-b border-gray-200 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-sm bg-primary-700 text-white flex items-center justify-center shadow-xs">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                        <span>Buat Draft Purchase Order dari Rekomendasi</span>
                                        <span class="bg-primary-100 text-primary-800 text-[11px] font-semibold px-2 py-0.5 rounded-full font-mono" x-text="getSelectedItems().length + ' Item Terpilih'"></span>
                                    </h3>
                                    <p class="text-[11px] text-gray-500">Draft PO otomatis dibuat dan ditandai "Dari Analisa". Item dapat ditinjau sebelum disimpan.</p>
                                </div>
                            </div>
                            <button type="button" @click="poModalOpen = false" class="text-gray-400 hover:text-gray-600 rounded-sm p-1 hover:bg-gray-100 transition cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="p-5 space-y-4">
                            <!-- Top Form Grid: Supplier, Gudang, & Summary Stats -->
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3.5 bg-gray-50 p-3.5 rounded-sm border border-gray-200">
                                <div class="sm:col-span-4">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">
                                        Pilih Supplier Rekanan <span class="text-rose-500">*</span>
                                    </label>
                                    <select name="supplier_id" required class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                        <option value="">— Pilih Supplier —</option>
                                        @foreach($suppliers as $s)
                                            <option value="{{ $s->id }}">{{ $s->nama }} ({{ ucfirst($s->kategori) }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="sm:col-span-4">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">
                                        Lokasi PO (Gudang Tujuan)
                                    </label>
                                    <select name="gudang_id" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                        <option value="">— Pilih Gudang Penerima —</option>
                                        @foreach($gudang as $g)
                                            <option value="{{ $g->id }}">{{ $g->nama }} ({{ ucwords(str_replace('_', ' ', $g->tipe)) }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="sm:col-span-4 bg-white p-2.5 rounded-sm border border-gray-200 flex flex-col justify-between">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Total Estimasi PO</span>
                                    <div class="text-base font-bold font-mono text-primary-700" x-text="'Rp ' + formatNumber(getSelectedTotalNominal())"></div>
                                </div>
                            </div>

                            <!-- Items Section -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Daftar Item yang Akan Dipesan</h4>
                                        <span class="text-[11px] text-gray-500 font-mono">(<span x-text="getModalFilteredItems().length"></span> dari <span x-text="getSelectedItems().length"></span> item)</span>
                                    </div>
                                    <div class="w-64">
                                        <input 
                                            type="text" 
                                            x-model="modalSearchQuery" 
                                            placeholder="Cari item di modal..." 
                                            class="w-full text-xs px-2.5 py-1 rounded-sm border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                        >
                                    </div>
                                </div>

                                <!-- Structured Scrollable Table -->
                                <div class="border border-gray-200 rounded-sm overflow-hidden shadow-2xs">
                                    <div class="max-h-72 sm:max-h-80 overflow-y-auto">
                                        <table class="w-full text-xs text-left">
                                            <thead class="bg-gray-100 text-gray-700 text-[11px] uppercase font-semibold sticky top-0 border-b border-gray-200 z-10">
                                                <tr>
                                                    <th class="py-2 px-3 w-8 text-center">#</th>
                                                    <th class="py-2 px-3">Bahan Baku</th>
                                                    <th class="py-2 px-3 text-right">Rekomendasi Order</th>
                                                    <th class="py-2 px-3 text-right">Kuantitas Dasar</th>
                                                    <th class="py-2 px-3 text-right">Harga Satuan</th>
                                                    <th class="py-2 px-3 text-right">Subtotal</th>
                                                    <th class="py-2 px-3 text-center w-12">Batal</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                <template x-for="(item, idx) in getModalFilteredItems()" :key="item.id">
                                                    <tr class="hover:bg-gray-50/80 transition">
                                                        <td class="py-2 px-3 text-center font-mono text-[11px] text-gray-400" x-text="idx + 1"></td>
                                                        <td class="py-2 px-3">
                                                            <div class="font-bold text-gray-900" x-text="item.nama"></div>
                                                            <div class="font-mono text-[10px] text-gray-400" x-text="item.sku"></div>
                                                        </td>
                                                        <td class="py-2 px-3 text-right font-mono font-bold text-primary-800" x-text="formatNumber(item.rekomendasi_order) + ' ' + (item.satuan_beli || item.satuan)"></td>
                                                        <td class="py-2 px-3 text-right font-mono text-gray-600" x-text="formatNumber(item.rumus_moq) + ' ' + item.satuan"></td>
                                                        <td class="py-2 px-3 text-right font-mono text-gray-600" x-text="'Rp ' + formatNumber(item.harga_per_satuan)"></td>
                                                        <td class="py-2 px-3 text-right font-mono font-bold text-gray-900" x-text="'Rp ' + formatNumber(item.total_nominal_order)"></td>
                                                        <td class="py-2 px-3 text-center">
                                                            <button 
                                                                type="button" 
                                                                @click="removeItemFromPo(item.id)" 
                                                                title="Hapus item ini dari Draft PO" 
                                                                class="text-gray-400 hover:text-rose-600 p-1 rounded hover:bg-rose-50 transition cursor-pointer"
                                                            >
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                                <template x-if="getSelectedItems().length === 0">
                                                    <tr>
                                                        <td colspan="7" class="py-8 text-center text-gray-400 text-xs">
                                                            Tidak ada item yang dipilih. Silakan centang item pada tabel rekomendasi.
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Modal -->
                        <div class="bg-gray-50 px-5 py-3 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
                            <div class="text-[11px] text-gray-500">
                                * Kuantitas dan harga dapat diedit lebih lanjut di halaman Draft PO setelah dibuat.
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="poModalOpen = false" class="px-3.5 py-1.5 border border-gray-300 rounded-sm text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 transition cursor-pointer">
                                    Batal
                                </button>
                                <button type="submit" :disabled="getSelectedItems().length === 0" class="px-4 py-1.5 bg-primary-700 hover:bg-primary-800 disabled:opacity-50 text-white rounded-sm text-xs font-semibold shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Konfirmasi & Buat Draft PO (<span x-text="getSelectedItems().length"></span>)</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function analisa(){
      return {
        tab: 'lokal',
        lokalSubTab: 'rekomendasi',
        rows: [],
        tables: {
          rekomendasi: [],
          analisa: [],
          lead_time: [],
          stages: []
        },
        meta: {
          last_generated_at: null,
          last_generated_by: null
        },
        selectedItemIds: [],
        poModalOpen: false,
        loading: false,
        generating: false,
        page: 1,
        perPage: 15,
        searchQuery: '',
        statusFilter: 'all',
        modalSearchQuery: '',
        tabs: [
          { key: 'lokal', label: 'Bahan Baku Lokal' },
          { key: 'impor', label: 'Bahan Baku Impor' },
          { key: 'fulfillment', label: 'Produk Jadi (Fulfillment)' },
          { key: 'riwayat', label: 'Log Riwayat Snapshot' }
        ],
        cols: {
          lokal: [
            ['sku', 'SKU', 'text-left'],
            ['nama', 'Nama Bahan', 'text-left'],
            ['adu', 'ADU', 'text-right'],
            ['batas_minimum', 'Batas Min', 'text-right'],
            ['target_stock', 'Target', 'text-right'],
            ['tersedia', 'Tersedia', 'text-right'],
            ['selisih', 'Selisih', 'text-right'],
            ['status', 'Status', 'text-center'],
            ['qty_order', 'Qty Order', 'text-right']
          ],
          impor: [
            ['sku', 'SKU', 'text-left'],
            ['nama', 'Nama Bahan', 'text-left'],
            ['klasifikasi_abc', 'ABC', 'text-center'],
            ['buffer_days', 'Buffer Hari', 'text-right'],
            ['total_selisih', 'Selisih', 'text-right'],
            ['status', 'Status', 'text-center'],
            ['total_qty_order', 'Qty Order', 'text-right'],
            ['total_nominal_order', 'Nominal Order', 'text-right']
          ],
          fulfillment: [
            ['sku', 'SKU', 'text-left'],
            ['nama', 'Nama Produk', 'text-left'],
            ['batas_minimum_total', 'Batas Min', 'text-right'],
            ['target_stock_total', 'Target', 'text-right'],
            ['stok_total', 'Stok Saat Ini', 'text-right'],
            ['akan_datang', 'Akan Datang', 'text-right'],
            ['stok_all', 'Stok Total', 'text-right'],
            ['selisih', 'Selisih', 'text-right'],
            ['status', 'Status', 'text-center'],
            ['qty_order', 'Qty Order', 'text-right']
          ],
          riwayat: [
            ['tanggal', 'Tanggal', 'text-left'],
            ['tipe', 'Tipe Analisa', 'text-left'],
            ['item_label', 'Item / SKU', 'text-left'],
            ['batas_minimum', 'Batas Min', 'text-right'],
            ['target_stock', 'Target', 'text-right'],
            ['status', 'Status', 'text-center'],
            ['qty_order', 'Qty Order', 'text-right'],
            ['pencatat', 'Oleh', 'text-left']
          ]
        },
        urls: {
          lokal: '{{ route('analisa.lokal.data') }}',
          impor: '{{ route('analisa.impor.data') }}',
          fulfillment: '{{ route('analisa.fulfillment.data') }}',
          riwayat: '{{ route('analisa.riwayat.data') }}'
        },
        formatNumber(v){
          if (v === null || v === undefined || isNaN(v)) return '0';
          const num = Number(v);
          if (Math.abs(num - Math.round(num)) < 0.00001) {
            return num.toLocaleString('id-ID', { maximumFractionDigits: 0 });
          }
          return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        fmt(v, colName){
          if (typeof v === 'number') {
            if (colName === 'total_nominal_order') {
              return 'Rp ' + v.toLocaleString('id-ID', { maximumFractionDigits: 0 });
            }
            return v.toLocaleString('id-ID', { maximumFractionDigits: 2 });
          }
          return v ?? '-';
        },
        badge(s){
          const st = String(s || '').toLowerCase();
          if (st === 'order' || st === 'po') {
            return '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200 uppercase tracking-wide">ORDER</span>';
          }
          if (st === 'aman' || st === 'tidak') {
            return '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase tracking-wide">AMAN</span>';
          }
          return `<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-gray-100 text-gray-700 border border-gray-200 uppercase tracking-wide">${st}</span>`;
        },
        kpiOrderCount(){
          return this.rows.filter(r => {
            const st = String(r.status || '').toLowerCase();
            return st === 'order' || st === 'po';
          }).length;
        },
        kpiAmanCount(){
          return this.rows.filter(r => {
            const st = String(r.status || '').toLowerCase();
            return st === 'aman' || st === 'tidak';
          }).length;
        },
        kpiTotalOrderQty(){
          const sum = this.rows.reduce((acc, r) => {
            const q = Number(r.rekomendasi_order || r.qty_order || r.total_qty_order || 0);
            return acc + (isNaN(q) ? 0 : q);
          }, 0);
          return sum.toLocaleString('id-ID', { maximumFractionDigits: 2 });
        },
        filteredRows(){
          let list = this.rows || [];
          if (this.searchQuery && this.searchQuery.trim()) {
            const q = this.searchQuery.toLowerCase().trim();
            list = list.filter(r => 
              (r.nama && r.nama.toLowerCase().includes(q)) || 
              (r.sku && r.sku.toLowerCase().includes(q)) ||
              (r.item_label && r.item_label.toLowerCase().includes(q))
            );
          }
          if (this.tab === 'lokal' && this.lokalSubTab === 'rekomendasi' && this.statusFilter !== 'all') {
            list = list.filter(r => {
              const s = String(r.status || '').toLowerCase();
              if (this.statusFilter === 'order') return s === 'order' || s === 'po';
              if (this.statusFilter === 'aman') return s === 'aman' || s === 'tidak';
              return true;
            });
          }
          return list;
        },
        paginatedRows(){
          const list = this.filteredRows();
          if (this.perPage === 'all') return list;
          const pp = Number(this.perPage) || 15;
          const start = (this.page - 1) * pp;
          return list.slice(start, start + pp);
        },
        filteredStages(){
          let list = (this.tables && this.tables.stages) ? this.tables.stages : [];
          if (this.searchQuery && this.searchQuery.trim()) {
            const q = this.searchQuery.toLowerCase().trim();
            list = list.filter(s => 
              (s.nama && s.nama.toLowerCase().includes(q)) || 
              (s.sku && s.sku.toLowerCase().includes(q))
            );
          }
          return list;
        },
        paginatedStages(){
          const list = this.filteredStages();
          if (this.perPage === 'all') return list;
          const pp = Number(this.perPage) || 15;
          const start = (this.page - 1) * pp;
          return list.slice(start, start + pp);
        },
        currentDataLength(){
          if (this.tab === 'lokal' && this.lokalSubTab === 'stages') {
            return this.filteredStages().length;
          }
          return this.filteredRows().length;
        },
        currentRawLength(){
          if (this.tab === 'lokal' && this.lokalSubTab === 'stages') {
            return (this.tables && this.tables.stages) ? this.tables.stages.length : 0;
          }
          return this.rows.length;
        },
        totalPages(){
          const len = this.currentDataLength();
          if (this.perPage === 'all' || len === 0) return 1;
          const pp = Number(this.perPage) || 15;
          return Math.ceil(len / pp) || 1;
        },
        startItem(){
          const len = this.currentDataLength();
          if (len === 0) return 0;
          if (this.perPage === 'all') return 1;
          const pp = Number(this.perPage) || 15;
          return ((this.page - 1) * pp) + 1;
        },
        endItem(){
          const len = this.currentDataLength();
          if (this.perPage === 'all') return len;
          const pp = Number(this.perPage) || 15;
          return Math.min(this.page * pp, len);
        },
        gotoPage(p){
          const max = this.totalPages();
          if (p < 1) p = 1;
          if (p > max) p = max;
          this.page = p;
          if (this.tab !== 'lokal') {
            this.renderTable();
          }
        },
        pagesList(){
          const total = this.totalPages();
          const current = this.page;
          if (total <= 7) {
            return Array.from({ length: total }, (_, i) => i + 1);
          }
          if (current <= 4) {
            return [1, 2, 3, 4, 5, '...', total];
          }
          if (current >= total - 3) {
            return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
          }
          return [1, '...', current - 1, current, current + 1, '...', total];
        },
        isCurrentPageAllSelected(){
          const pageItems = this.paginatedRows();
          if (pageItems.length === 0) return false;
          return pageItems.every(r => this.selectedItemIds.includes(r.id));
        },
        toggleSelectCurrentPage(e){
          const pageItemIds = this.paginatedRows().map(r => r.id);
          if (e.target.checked) {
            this.selectedItemIds = Array.from(new Set([...this.selectedItemIds, ...pageItemIds]));
          } else {
            this.selectedItemIds = this.selectedItemIds.filter(id => !pageItemIds.includes(id));
          }
        },
        selectAllPerluOrder(){
          this.selectedItemIds = this.rows.filter(r => {
            const s = String(r.status || '').toLowerCase();
            return s === 'order' || s === 'po';
          }).map(r => r.id);
        },
        selectAllFiltered(){
          const filteredIds = this.filteredRows().map(r => r.id);
          this.selectedItemIds = Array.from(new Set([...this.selectedItemIds, ...filteredIds]));
        },
        selectedOrderCount(){
          return this.selectedItemIds.length;
        },
        getSelectedItems(){
          return this.rows.filter(r => this.selectedItemIds.includes(r.id));
        },
        getSelectedTotalNominal(){
          return this.getSelectedItems().reduce((acc, r) => acc + (Number(r.total_nominal_order) || 0), 0);
        },
        getModalFilteredItems(){
          const items = this.getSelectedItems();
          if (!this.modalSearchQuery || !this.modalSearchQuery.trim()) return items;
          const q = this.modalSearchQuery.toLowerCase().trim();
          return items.filter(i => (i.nama && i.nama.toLowerCase().includes(q)) || (i.sku && i.sku.toLowerCase().includes(q)));
        },
        removeItemFromPo(id){
          this.selectedItemIds = this.selectedItemIds.filter(itemId => itemId !== id);
        },
        openPoModal(){
          if (this.selectedItemIds.length === 0) {
            // Auto select items that need order
            this.selectedItemIds = this.rows.filter(r => r.status === 'order').map(r => r.id);
          }
          if (this.selectedItemIds.length === 0) {
            Swal.fire({
              icon: 'info',
              title: 'Pilih Item',
              text: 'Silakan centang setidaknya satu item bahan yang ingin dibuatkan Purchase Order.',
              confirmButtonColor: '#0284c7'
            });
            return;
          }
          this.modalSearchQuery = '';
          this.poModalOpen = true;
        },
        async generateLokal(){
          this.generating = true;
          try {
            const fd = new FormData();
            fd.append('_token', '{{ csrf_token() }}');
            const res = await fetch('{{ route('analisa.generate-lokal') }}', {
              method: 'POST',
              body: fd,
              headers: { 'Accept': 'application/json' }
            });
            const j = await res.json();
            if (res.ok && j.success) {
              Swal.fire({
                icon: 'success',
                title: 'Generate Berhasil',
                text: j.message,
                confirmButtonColor: '#0284c7'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Generate Gagal',
                text: j.message || 'Terjadi kesalahan saat memproses data.',
                confirmButtonColor: '#0284c7'
              });
            }
          } catch(e) {
            Swal.fire({
              icon: 'error',
              title: 'Kesalahan Sistem',
              text: 'Tidak dapat terhubung ke server.',
              confirmButtonColor: '#0284c7'
            });
          } finally {
            this.generating = false;
          }
        },
        async load(){
          this.loading = true;
          try {
            const res = await fetch(this.urls[this.tab]);
            const j = await res.json();
            this.rows = j.data || [];
            if (j.tables) {
              this.tables = j.tables;
            }
            if (j.meta) {
              this.meta = j.meta;
            }
            // Auto-check items with status 'order'
            if (this.tab === 'lokal') {
              this.selectedItemIds = this.rows.filter(r => r.status === 'order').map(r => r.id);
            }
            this.page = 1;
            if (this.tab !== 'lokal') {
              this.renderTable();
            }
          } catch(e) {
            console.error(e);
            this.rows = [];
          } finally {
            this.loading = false;
          }
        },
        renderTable(){
          const cols = this.cols[this.tab];
          const ahead = document.getElementById('ahead');
          const abody = document.getElementById('abody');

          if (ahead) {
            ahead.innerHTML = cols.map(c => `
              <th class="px-3 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 border-b border-gray-200 ${c[2]}">
                ${c[1]}
              </th>
            `).join('');
          }

          if (abody) {
            const list = this.paginatedRows();
            if (list.length === 0) {
              abody.innerHTML = '<tr><td colspan="' + cols.length + '" class="py-8 text-center text-gray-400 text-xs">Tidak ada data analisa yang cocok dengan filter atau pencarian.</td></tr>';
              return;
            }

            abody.innerHTML = list.map((r, idx) => {
              const bg = idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/40';
              return `<tr class="${bg} hover:bg-primary-50/20 transition-colors">` + cols.map(c => {
                const val = r[c[0]];
                const colKey = c[0];
                const align = c[2];

                if (colKey === 'status') {
                  return `<td class="px-3 py-2 text-xs ${align}">${this.badge(val)}</td>`;
                }

                if (colKey === 'sku') {
                  return `<td class="px-3 py-2 text-xs font-mono font-medium text-primary-800 ${align}">${val ?? '-'}</td>`;
                }

                if (colKey === 'nama' || colKey === 'item_label') {
                  return `<td class="px-3 py-2 text-xs font-medium text-gray-900 ${align}">${val ?? '-'}</td>`;
                }

                if (align === 'text-right') {
                  return `<td class="px-3 py-2 text-xs font-mono text-gray-800 ${align}">${this.fmt(val, colKey)}</td>`;
                }

                return `<td class="px-3 py-2 text-xs text-gray-700 ${align}">${this.fmt(val, colKey)}</td>`;
              }).join('') + '</tr>';
            }).join('');
          }
        },
        async snapshot(){
          const map = {
            lokal: 'bahan_lokal',
            impor: 'bahan_impor',
            fulfillment: 'produk_jadi_fulfillment'
          };
          const fd = new FormData();
          fd.append('tipe', map[this.tab]);
          fd.append('_token', '{{ csrf_token() }}');

          try {
            const res = await fetch('{{ route('analisa.snapshot') }}', {
              method: 'POST',
              body: fd,
              headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
              Swal.fire({
                icon: 'success',
                title: 'Snapshot Tersimpan',
                text: 'Hasil kalkulasi analisa telah diarsipkan ke tab Riwayat.',
                confirmButtonColor: '#0284c7'
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan',
                text: 'Tidak dapat menyimpan snapshot analisa.',
                confirmButtonColor: '#0284c7'
              });
            }
          } catch(e) {
            Swal.fire({
              icon: 'error',
              title: 'Terjadi Kesalahan',
              text: 'Koneksi ke server terganggu.',
              confirmButtonColor: '#0284c7'
            });
          }
        }
      };
    }
    </script>
    @endpush
</x-app-layout>

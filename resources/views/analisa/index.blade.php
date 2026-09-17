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
                            <!-- Fase 1: Generate -->
                            <button type="button" @click="generateLokal()" :disabled="generating" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-700 hover:bg-primary-800 disabled:opacity-50 text-white text-xs font-semibold rounded-sm shadow-xs transition cursor-pointer" title="Generate working data analisa dari master">
                                <svg x-show="!generating" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                <svg x-show="generating" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span x-text="generating ? 'Menghitung Engine...' : 'Generate Analisa'"></span>
                            </button>

                            <!-- Fase 2: Checking (Generate Ulang) -->
                            <button type="button" @click="checkingLokal()" :disabled="generating" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-50 hover:bg-sky-100 disabled:opacity-50 text-sky-800 border border-sky-300 text-xs font-semibold rounded-sm shadow-xs transition cursor-pointer" title="Periksa dan hitung ulang stok fisik & PO berjalan saat ini">
                                <svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>Checking (Generate Ulang)</span>
                            </button>
                            @endcan

                            @can('analisa.create_po')
                            <button type="button" @click="goToCreatePo()" x-show="selectedOrderCount() > 0" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold rounded-sm shadow-xs transition cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>Buat PO (<span x-text="selectedOrderCount()"></span>)</span>
                            </button>
                            @endcan
                        </div>
                    </template>

                    <template x-if="tab === 'impor'">
                        <div class="flex items-center gap-2">
                            @can('analisa.manage')
                            <!-- Fase 1: Generate -->
                            <button type="button" @click="generateImpor()" :disabled="generating" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-700 hover:bg-primary-800 disabled:opacity-50 text-white text-xs font-semibold rounded-sm shadow-xs transition cursor-pointer" title="Generate working data analisa impor">
                                <svg x-show="!generating" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                <svg x-show="generating" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span x-text="generating ? 'Menghitung Engine Impor...' : 'Generate Analisa Impor'"></span>
                            </button>

                            <!-- Fase 2: Checking (Generate Ulang) -->
                            <button type="button" @click="checkingImpor()" :disabled="generating" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-50 hover:bg-sky-100 disabled:opacity-50 text-sky-800 border border-sky-300 text-xs font-semibold rounded-sm shadow-xs transition cursor-pointer" title="Periksa dan hitung ulang stok fisik & PO impor saat ini">
                                <svg class="w-3.5 h-3.5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>Checking (Generate Ulang)</span>
                            </button>
                            @endcan

                            @can('analisa.create_po')
                            <button type="button" @click="goToCreatePo()" x-show="selectedOrderCount() > 0" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold rounded-sm shadow-xs transition cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                <span>Buat PO Impor (<span x-text="selectedOrderCount()"></span>)</span>
                            </button>
                            @endcan
                        </div>
                    </template>

                    @can('analisa.snapshot')
                    <!-- Fase 3: Finalisasi -->
                    <button type="button" @click="confirmFinalisasi()" x-show="tab !== 'riwayat'" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-800 hover:bg-gray-900 text-white text-xs font-medium rounded-sm shadow-xs transition cursor-pointer" title="Kunci data working analisa aktif ke riwayat snapshot">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span>Finalisasi ke Riwayat</span>
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
                <template x-if="(tab === 'lokal' || tab === 'impor') && meta.last_generated_at">
                    <div class="flex items-center gap-1.5 text-[11px] bg-primary-50 text-primary-800 px-2 py-0.5 rounded border border-primary-100 font-mono">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Di-generate: <strong x-text="meta.last_generated_at"></strong> (<span x-text="meta.last_generated_by"></span>)</span>
                    </div>
                </template>
                <template x-if="!( (tab === 'lokal' || tab === 'impor') && meta.last_generated_at )">
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
                        placeholder="Cari SKU, nama bahan, supplier..."
                        class="w-full pl-8 pr-7 py-1.5 text-xs rounded-sm border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                    >
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <button type="button" x-show="searchQuery" @click="searchQuery = ''; page = 1; if(tab !== 'lokal') renderTable();" class="absolute inset-y-0 right-0 pr-2 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <template x-if="tab === 'lokal' || tab === 'impor'">
                    <div class="flex items-center gap-1.5">
                        <template x-if="(tab === 'lokal' && lokalSubTab === 'rekomendasi') || tab === 'impor'">
                            <select 
                                x-model="statusFilter" 
                                @change="page = 1"
                                class="rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            >
                                <option value="all">Semua Status</option>
                                <option value="order">Hanya Perlu Order</option>
                                <option value="aman">Hanya Status Aman</option>
                            </select>
                        </template>
                        <select 
                            x-model="supplierFilter" 
                            @change="page = 1"
                            class="rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                        >
                            <option value="all">Semua Supplier</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->nama }} ({{ ucfirst($s->kategori) }})</option>
                            @endforeach
                        </select>
                    </div>
                </template>
            </div>

            <!-- Kanan: Tombol Aksi Massal & Jumlah per Halaman -->
            <div class="flex flex-wrap items-center gap-2.5 text-xs">
                <template x-if="(tab === 'lokal' && lokalSubTab === 'rekomendasi') || tab === 'impor'">
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
                                    <th class="py-2.5 px-3">Supplier</th>
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
                                    <th class="py-2.5 px-2 text-center w-24">Aksi</th>
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
                                        <td class="py-2.5 px-3">
                                            <div class="font-medium text-gray-800" x-text="r.supplier_nama || '-'"></div>
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
                                        <td class="py-2.5 px-2 text-center">
                                            <button type="button" @click="openManualLokalModal(r)" class="px-2 py-1 bg-primary-50 hover:bg-primary-100 text-primary-700 rounded border border-primary-200 text-[10px] font-medium transition cursor-pointer" title="Edit Data Input Manual">
                                                Edit Input
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="paginatedRows().length === 0">
                                    <tr>
                                        <td colspan="14" class="py-8 text-center text-gray-400 text-xs">
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
                                    <th class="py-2.5 px-3">Supplier</th>
                                    <th class="py-2.5 px-3 text-right">Avg Lead Time (Hari)</th>
                                    <th class="py-2.5 px-3 text-right">Safety Stock (Hari)</th>
                                    <th class="py-2.5 px-3 text-right">Terjual 4 Bulan</th>
                                    <th class="py-2.5 px-3 text-right bg-amber-50/50 text-amber-900">ADU (Harian)</th>
                                    <th class="py-2.5 px-3 text-right">Review Period</th>
                                    <th class="py-2.5 px-3 text-right bg-primary-50/50 text-primary-900 font-bold">Batas Minimum</th>
                                    <th class="py-2.5 px-3 text-right font-bold">Target Stock</th>
                                    <th class="py-2.5 px-3 text-right">Generated At</th>
                                    <th class="py-2.5 px-2 text-center w-24">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="r in paginatedRows()" :key="r.id">
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="py-2.5 px-3">
                                            <div class="font-bold text-gray-900" x-text="r.nama"></div>
                                            <div class="font-mono text-[10px] text-gray-400" x-text="r.sku + ' · Satuan: ' + r.satuan"></div>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <div class="font-medium text-gray-800" x-text="r.supplier_nama || '-'"></div>
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="r.total_avg_lead_time"></td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="r.safety_stock"></td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="formatNumber(r.terjual_rata_rata_4bulan)"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-amber-800 bg-amber-50/30" x-text="Number(r.adu).toFixed(4)"></td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="r.review_period + ' Hari'"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-primary-800 bg-primary-50/30" x-text="formatNumber(r.batas_minimum) + ' ' + r.satuan"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900" x-text="formatNumber(r.target_stock) + ' ' + r.satuan"></td>
                                        <td class="py-2.5 px-3 text-right font-mono text-[11px] text-gray-400" x-text="r.generated_at"></td>
                                        <td class="py-2.5 px-2 text-center">
                                            <button type="button" @click="openManualLokalModal(r)" class="px-2 py-1 bg-primary-50 hover:bg-primary-100 text-primary-700 rounded border border-primary-200 text-[10px] font-medium transition cursor-pointer" title="Edit Terjual 4 Bulan & Review Period">
                                                Edit Input
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="paginatedRows().length === 0">
                                    <tr>
                                        <td colspan="11" class="py-8 text-center text-gray-400 text-xs">
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
                                    <th class="py-2.5 px-3">Supplier</th>
                                    <th class="py-2.5 px-3 text-right">Total Avg Lead Time</th>
                                    <th class="py-2.5 px-3 text-right">Total Max Lead Time</th>
                                    <th class="py-2.5 px-3 text-right">Tambahan Buffer</th>
                                    <th class="py-2.5 px-3 text-right bg-emerald-50/50 text-emerald-900 font-bold">Safety Stock (Hari)</th>
                                    <th class="py-2.5 px-2 text-center w-28">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="r in paginatedRows()" :key="r.id">
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="py-2.5 px-3">
                                            <div class="font-bold text-gray-900" x-text="r.nama"></div>
                                            <div class="font-mono text-[10px] text-gray-400" x-text="r.sku + ' · Satuan: ' + r.satuan"></div>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <div class="font-medium text-gray-800" x-text="r.supplier_nama || '-'"></div>
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="r.total_avg_lead_time + ' Hari'"></td>
                                        <td class="py-2.5 px-3 text-right font-mono" x-text="r.total_max_lead_time + ' Hari'"></td>
                                        <td class="py-2.5 px-3 text-right font-mono text-gray-700" x-text="r.tambahan_buffer_hari + ' Hari'"></td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-800 bg-emerald-50/30" x-text="r.safety_stock + ' Hari'"></td>
                                        <td class="py-2.5 px-2 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button type="button" @click="openManualLokalModal(r)" class="px-2 py-1 bg-primary-50 hover:bg-primary-100 text-primary-700 rounded border border-primary-200 text-[10px] font-medium transition cursor-pointer" title="Edit Tambahan Buffer">
                                                    Buffer
                                                </button>
                                                <button type="button" @click="openStageModal(r)" class="px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded border border-amber-200 text-[10px] font-medium transition cursor-pointer" title="Edit 9 Tahap Lead Time">
                                                    9 Tahap
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="paginatedRows().length === 0">
                                    <tr>
                                        <td colspan="7" class="py-8 text-center text-gray-400 text-xs">
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
                <div x-show="lokalSubTab === 'stages'" class="space-y-3">
                    <!-- Highlight Summary: Lead Time Terlama (Average & Max) - AdminLTE Card Outline Style -->
                    <template x-if="longestAvgStage() || longestMaxStage()">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- AdminLTE Card Outline: Average Lead Time -->
                            <div class="bg-white rounded-sm border border-gray-200 shadow-xs">
                                <div class="px-3.5 py-2 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Lead Time Terlama (Average)</h4>
                                    </div>
                                    <span class="px-2 py-0.5 text-xs font-bold font-mono bg-primary-700 text-white rounded-xs shadow-2xs" x-text="(longestAvgStage()?.days || 0) + ' Hari'"></span>
                                </div>
                                <div class="p-3 bg-white text-xs">
                                    <table class="w-full text-left">
                                        <tbody class="divide-y divide-gray-100">
                                            <tr>
                                                <td class="py-1.5 text-gray-500 font-medium w-24">Bahan Baku</td>
                                                <td class="py-1.5 text-gray-900 text-right">
                                                    <span class="font-bold text-gray-900" x-text="longestAvgStage()?.nama || '-'"></span>
                                                    <template x-if="longestAvgStage()?.sku">
                                                        <span class="font-mono text-gray-400 text-[11px] ml-1" x-text="'(' + longestAvgStage().sku + ')'"></span>
                                                    </template>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="py-1.5 text-gray-500 font-medium w-24">Supplier</td>
                                                <td class="py-1.5 text-gray-800 font-semibold text-right" x-text="longestAvgStage()?.supplier_nama || '-'"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- AdminLTE Card Outline: Max Lead Time -->
                            <div class="bg-white rounded-sm border border-gray-200 shadow-xs">
                                <div class="px-3.5 py-2 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Lead Time Terlama (Max Skenario)</h4>
                                    </div>
                                    <span class="px-2 py-0.5 text-xs font-bold font-mono bg-amber-600 text-white rounded-xs shadow-2xs" x-text="(longestMaxStage()?.days || 0) + ' Hari'"></span>
                                </div>
                                <div class="p-3 bg-white text-xs">
                                    <table class="w-full text-left">
                                        <tbody class="divide-y divide-gray-100">
                                            <tr>
                                                <td class="py-1.5 text-gray-500 font-medium w-24">Bahan Baku</td>
                                                <td class="py-1.5 text-gray-900 text-right">
                                                    <span class="font-bold text-gray-900" x-text="longestMaxStage()?.nama || '-'"></span>
                                                    <template x-if="longestMaxStage()?.sku">
                                                        <span class="font-mono text-gray-400 text-[11px] ml-1" x-text="'(' + longestMaxStage().sku + ')'"></span>
                                                    </template>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="py-1.5 text-gray-500 font-medium w-24">Supplier</td>
                                                <td class="py-1.5 text-gray-800 font-semibold text-right" x-text="longestMaxStage()?.supplier_nama || '-'"></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="bg-white rounded-sm border border-gray-200 shadow-xs overflow-hidden">
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
                                        <th class="py-2.5 px-2">Supplier</th>
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
                                        <th class="py-2.5 px-2 text-center w-20">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="stg in paginatedStages()" :key="stg.produk_id">
                                        <template x-for="type in ['average', 'max']" :key="type">
                                            <tr class="hover:bg-gray-50 transition" :class="type === 'max' ? 'border-b-2 border-gray-200' : ''">
                                                <td class="py-2 px-2" x-show="type === 'average'" rowspan="2">
                                                    <div class="font-bold text-gray-900 text-xs" x-text="stg.nama"></div>
                                                    <div class="font-mono text-[10px] text-gray-400" x-text="stg.sku"></div>
                                                </td>
                                                <td class="py-2 px-2" x-show="type === 'average'" rowspan="2">
                                                    <div class="font-medium text-gray-800 text-xs" x-text="stg.supplier_nama || '-'"></div>
                                                </td>
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
                                                <td class="py-2 px-2 text-center" x-show="type === 'average'" rowspan="2">
                                                    <button type="button" @click="openStageModal(stg)" class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-medium rounded border border-primary-600 bg-primary-50 text-primary-700 hover:bg-primary-100 transition cursor-pointer" title="Edit Rincian 9 Tahap Lead Time">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                        Edit
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                    <template x-if="paginatedStages().length === 0">
                                        <tr>
                                            <td colspan="14" class="py-8 text-center text-gray-400 text-xs">
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
            </div>
        </template>

        <!-- Section: Bahan Baku Impor (analisa_impor & lead_time_impor) -->
        <template x-if="tab === 'impor'">
            <div class="space-y-4">
                <div class="bg-white rounded-sm border border-gray-200 shadow-xs overflow-hidden">
                    <div class="p-3 bg-gray-50 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-xs font-bold text-gray-900">Tabel Analisa & Rekomendasi Pemesanan Bahan Impor (analisa_impor & lead_time_impor)</h3>
                            <p class="text-[11px] text-gray-500">Lead Time (Avg/Max), Buffer Hari ABC (+4d/+2d/+0d), Safety Stock, Target Stock, Inbound ETA, Proyeksi, Rekomendasi Order MOQ, dan Distribusi Varian</p>
                        </div>
                    </div>

                    <!-- Loading Spinner -->
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

                    <!-- Pagination Bar -->
                    <div x-show="!loading && rows.length > 0" class="p-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
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
                </div>
            </div>
        </template>

        <!-- Section: Log Riwayat Snapshot (riwayat_analisa) -->
        <template x-if="tab === 'riwayat'">
            <div class="bg-white rounded-sm border border-gray-200 shadow-xs overflow-hidden">
                <div class="p-3 bg-gray-50 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-xs font-bold text-gray-900">Log Riwayat Snapshot Analisa (riwayat_analisa)</h3>
                        <p class="text-[11px] text-gray-500">Audit trail arsip historis snapshot terkunci (locked) lengkap dengan kode sesi batch finalisasi</p>
                    </div>
                </div>

                <!-- Loading Spinner -->
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

                <!-- Pagination Bar -->
                <div x-show="!loading && rows.length > 0" class="p-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
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
            </div>
        </template>

        <!-- Section: Produk Jadi Fulfillment -->
        <div x-show="tab === 'fulfillment'" class="bg-white rounded-sm border border-gray-200 shadow-xs overflow-hidden">
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
                <span class="text-xs">Memuat kalkulasi data fulfillment...</span>
            </div>

            <!-- Empty State -->
            <div x-show="!loading && rows.length === 0" class="py-12 text-center">
                <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="mt-2 text-xs font-medium text-gray-500">Tidak ada data analisa untuk kategori ini.</p>
            </div>

            <!-- Pagination Bar for Fulfillment -->
            <div x-show="!loading && rows.length > 0" class="p-3 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
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
        </div>
        <!-- Modal Partials -->
        @include('analisa.partials.modal-varian')
        @include('analisa.partials.modal-snapshot')
        @include('analisa.partials.modal-stage-lead-time')
        @include('analisa.partials.modal-manual-lokal')
        @include('analisa.partials.modal-manual-impor')
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
        showVarianModal: false,
        activeVarianProduct: null,
        showSnapshotModal: false,
        activeSnapshot: null,
        showStageModal: false,
        savingStage: false,
        stageForm: {
          produk_id: null,
          nama: '',
          sku: '',
          supplier_nama: '',
          tambahan_buffer_hari: 0,
          average: {
            id: null,
            perencanaan: 0,
            approval: 0,
            supplier_confirm: 0,
            payment: 0,
            po: 0,
            pengemasan: 0,
            pengiriman: 0,
            unloading: 0,
            input: 0
          },
          max: {
            id: null,
            perencanaan: 0,
            approval: 0,
            supplier_confirm: 0,
            payment: 0,
            po: 0,
            pengemasan: 0,
            pengiriman: 0,
            unloading: 0,
            input: 0
          }
        },
        showManualLokalModal: false,
        savingManualLokal: false,
        manualLokalForm: {
          produk_id: null,
          nama: '',
          sku: '',
          satuan: '',
          supplier_nama: '',
          terjual_rata_rata_4bulan: 0,
          review_period: 15,
          tambahan_buffer_hari: 0,
          stok_saat_ini: 0,
          harga_ml_pcs: 0,
          total_avg_lead_time: 0,
          total_max_lead_time: 0,
          safety_stock: 0,
          akan_datang: 0
        },
        showManualImporModal: false,
        savingManualImpor: false,
        manualImporForm: {
          produk_id: null,
          nama: '',
          sku: '',
          satuan: '',
          supplier_nama: '',
          lead_time_average: 0,
          lead_time_max: 0,
          out: 0,
          review_period: 30,
          klasifikasi_abc: 'b',
          stok_saat_ini: 0,
          inbound_before_eta: 0,
          harga_per_satuan: 0
        },
        loading: false,
        generating: false,
        page: 1,
        perPage: 15,
        searchQuery: '',
        statusFilter: 'all',
        supplierFilter: 'all',
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
            ['supplier_nama', 'Supplier', 'text-left'],
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
            ['supplier_nama', 'Supplier', 'text-left'],
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
              (r.supplier_nama && r.supplier_nama.toLowerCase().includes(q)) ||
              (r.item_label && r.item_label.toLowerCase().includes(q)) ||
              (r.session_id && r.session_id.toLowerCase().includes(q)) ||
              (r.tipe && r.tipe.toLowerCase().includes(q))
            );
          }
          if (this.tab === 'lokal' || this.tab === 'impor') {
            if (this.statusFilter !== 'all' && ((this.tab === 'lokal' && this.lokalSubTab === 'rekomendasi') || this.tab === 'impor')) {
              list = list.filter(r => {
                const s = String(r.status || '').toLowerCase();
                if (this.statusFilter === 'order') return s === 'order' || s === 'po';
                if (this.statusFilter === 'aman') return s === 'aman' || s === 'tidak';
                return true;
              });
            }
            if (this.supplierFilter !== 'all') {
              const sf = String(this.supplierFilter);
              list = list.filter(r => String(r.supplier_id) === sf);
            }
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
              (s.sku && s.sku.toLowerCase().includes(q)) ||
              (s.supplier_nama && s.supplier_nama.toLowerCase().includes(q))
            );
          }
          if (this.supplierFilter !== 'all') {
            const sf = String(this.supplierFilter);
            list = list.filter(s => String(s.supplier_id) === sf);
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
        longestAvgStage(){
          const list = this.filteredStages();
          if (!list || !list.length) return null;
          let longest = null;
          let maxDays = -1;
          for (const s of list) {
            const days = Number(s.average?.total ?? s.total_average_lead_time ?? 0);
            if (days > maxDays) {
              maxDays = days;
              longest = {
                nama: s.nama,
                sku: s.sku,
                supplier_nama: s.supplier_nama || '-',
                days: days
              };
            }
          }
          return longest;
        },
        longestMaxStage(){
          const list = this.filteredStages();
          if (!list || !list.length) return null;
          let longest = null;
          let maxDays = -1;
          for (const s of list) {
            const days = Number(s.max?.total ?? s.total_max_lead_time ?? 0);
            if (days > maxDays) {
              maxDays = days;
              longest = {
                nama: s.nama,
                sku: s.sku,
                supplier_nama: s.supplier_nama || '-',
                days: days
              };
            }
          }
          return longest;
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
          if (this.tab === 'fulfillment') {
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
          const key = this.tab === 'impor' ? 'produk_id' : 'id';
          return pageItems.every(r => this.selectedItemIds.includes(r[key]));
        },
        toggleSelectCurrentPage(e){
          const key = this.tab === 'impor' ? 'produk_id' : 'id';
          const pageItemIds = this.paginatedRows().map(r => r[key]);
          if (e.target.checked) {
            this.selectedItemIds = Array.from(new Set([...this.selectedItemIds, ...pageItemIds]));
          } else {
            this.selectedItemIds = this.selectedItemIds.filter(id => !pageItemIds.includes(id));
          }
        },
        selectAllPerluOrder(){
          if (this.tab === 'impor') {
            this.selectedItemIds = this.rows.filter(r => {
              const s = String(r.status || '').toLowerCase();
              return s === 'order' || s === 'po';
            }).map(r => r.produk_id);
          } else {
            this.selectedItemIds = this.rows.filter(r => {
              const s = String(r.status || '').toLowerCase();
              return s === 'order' || s === 'po';
            }).map(r => r.id);
          }
        },
        selectAllFiltered(){
          const key = this.tab === 'impor' ? 'produk_id' : 'id';
          const filteredIds = this.filteredRows().map(r => r[key]);
          this.selectedItemIds = Array.from(new Set([...this.selectedItemIds, ...filteredIds]));
        },
        selectedOrderCount(){
          return this.selectedItemIds.length;
        },
        getSelectedItems(){
          const key = this.tab === 'impor' ? 'produk_id' : 'id';
          return this.rows.filter(r => this.selectedItemIds.includes(r[key]));
        },
        getSelectedTotalNominal(){
          return this.getSelectedItems().reduce((acc, r) => acc + (Number(r.total_nominal_order) || 0), 0);
        },
        goToCreatePo(){
          if (this.selectedItemIds.length === 0) {
            // Auto select items that need order
            if (this.tab === 'impor') {
              this.selectedItemIds = this.rows.filter(r => {
                const s = String(r.status || '').toLowerCase();
                return s === 'order' || s === 'po';
              }).map(r => r.produk_id);
            } else {
              this.selectedItemIds = this.rows.filter(r => {
                const s = String(r.status || '').toLowerCase();
                return s === 'order' || s === 'po';
              }).map(r => r.id);
            }
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
          let url = '{{ route('analisa.create-po') }}?ids=' + encodeURIComponent(this.selectedItemIds.join(','));
          const selectedItems = this.getSelectedItems();
          const supIds = selectedItems.map(r => r.supplier_id).filter(Boolean);
          const uniqueSupIds = Array.from(new Set(supIds));
          if (uniqueSupIds.length === 1) {
            url += '&supplier_id=' + encodeURIComponent(uniqueSupIds[0]);
          }
          window.location.href = url;
        },
        abcBadgeClass(abc){
          const v = String(abc || '').toLowerCase();
          if (v === 'wajib_a' || v === 'a') return 'bg-amber-100 text-amber-800 border border-amber-300';
          if (v === 'b') return 'bg-blue-100 text-blue-800 border border-blue-300';
          return 'bg-gray-100 text-gray-700 border border-gray-300';
        },
        openVarianModal(item){
          this.activeVarianProduct = item;
          this.showVarianModal = true;
        },
        openSnapshotModal(item){
          this.activeSnapshot = item;
          this.showSnapshotModal = true;
        },
        openStageModal(item){
          let stg = null;
          if (item.average || item.max) {
            stg = item;
          } else if (this.tables && this.tables.stages) {
            stg = this.tables.stages.find(s => s.produk_id === item.produk_id);
          }
          const avg = (stg && stg.average) ? stg.average : {};
          const max = (stg && stg.max) ? stg.max : {};

          this.stageForm = {
            produk_id: item.produk_id,
            nama: item.nama,
            sku: item.sku,
            supplier_nama: item.supplier_nama,
            tambahan_buffer_hari: item.tambahan_buffer_hari ?? (stg ? stg.tambahan_buffer_hari : 0),
            average: {
              id: avg.id || null,
              perencanaan: avg.perencanaan ?? 0,
              approval: avg.approval ?? 0,
              supplier_confirm: avg.supplier_confirm ?? 0,
              payment: avg.payment ?? 0,
              po: avg.po ?? 0,
              pengemasan: avg.pengemasan ?? 0,
              pengiriman: avg.pengiriman ?? 0,
              unloading: avg.unloading ?? 0,
              input: avg.input ?? 0,
            },
            max: {
              id: max.id || null,
              perencanaan: max.perencanaan ?? 0,
              approval: max.approval ?? 0,
              supplier_confirm: max.supplier_confirm ?? 0,
              payment: max.payment ?? 0,
              po: max.po ?? 0,
              pengemasan: max.pengemasan ?? 0,
              pengiriman: max.pengiriman ?? 0,
              unloading: max.unloading ?? 0,
              input: max.input ?? 0,
            }
          };
          this.showStageModal = true;
        },
        stageSum(type){
          const s = this.stageForm[type] || {};
          return (Number(s.perencanaan) || 0) +
                 (Number(s.approval) || 0) +
                 (Number(s.supplier_confirm) || 0) +
                 (Number(s.payment) || 0) +
                 (Number(s.po) || 0) +
                 (Number(s.pengemasan) || 0) +
                 (Number(s.pengiriman) || 0) +
                 (Number(s.unloading) || 0) +
                 (Number(s.input) || 0);
        },
        stageSafetyStock(){
          const max = this.stageSum('max');
          const avg = this.stageSum('average');
          const diff = Math.max(0, max - avg);
          return diff + (Number(this.stageForm.tambahan_buffer_hari) || 0);
        },
        async saveStages(){
          this.savingStage = true;
          try {
            const payload = {
              produk_id: this.stageForm.produk_id,
              tambahan_buffer_hari: Number(this.stageForm.tambahan_buffer_hari) || 0,
              stages: [
                {
                  id: this.stageForm.average.id || null,
                  skenario: 'average',
                  perencanaan: Number(this.stageForm.average.perencanaan) || 0,
                  approval: Number(this.stageForm.average.approval) || 0,
                  supplier_confirm: Number(this.stageForm.average.supplier_confirm) || 0,
                  payment: Number(this.stageForm.average.payment) || 0,
                  po: Number(this.stageForm.average.po) || 0,
                  pengemasan: Number(this.stageForm.average.pengemasan) || 0,
                  pengiriman: Number(this.stageForm.average.pengiriman) || 0,
                  unloading: Number(this.stageForm.average.unloading) || 0,
                  input: Number(this.stageForm.average.input) || 0,
                },
                {
                  id: this.stageForm.max.id || null,
                  skenario: 'max',
                  perencanaan: Number(this.stageForm.max.perencanaan) || 0,
                  approval: Number(this.stageForm.max.approval) || 0,
                  supplier_confirm: Number(this.stageForm.max.supplier_confirm) || 0,
                  payment: Number(this.stageForm.max.payment) || 0,
                  po: Number(this.stageForm.max.po) || 0,
                  pengemasan: Number(this.stageForm.max.pengemasan) || 0,
                  pengiriman: Number(this.stageForm.max.pengiriman) || 0,
                  unloading: Number(this.stageForm.max.unloading) || 0,
                  input: Number(this.stageForm.max.input) || 0,
                }
              ]
            };
            const res = await fetch('{{ route('analisa.stages-lokal.update') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
              },
              body: JSON.stringify(payload)
            });
            const j = await res.json();
            if (res.ok && j.success) {
              this.showStageModal = false;
              Swal.fire({
                icon: 'success',
                title: 'Tahap Lead Time Disimpan',
                text: 'Perubahan 9 tahap lead time berhasil disimpan & analisa dihitung ulang.',
                confirmButtonColor: '#0284c7'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan',
                text: j.message || 'Terjadi kesalahan saat menyimpan data tahap.',
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
            this.savingStage = false;
          }
        },

        openManualLokalModal(item){
          this.manualLokalForm = {
            produk_id: item.produk_id,
            nama: item.nama,
            sku: item.sku,
            satuan: item.satuan,
            supplier_nama: item.supplier_nama,
            terjual_rata_rata_4bulan: item.terjual_rata_rata_4bulan || 0,
            review_period: item.review_period || 15,
            tambahan_buffer_hari: item.tambahan_buffer_hari || 0,
            stok_saat_ini: item.stok_saat_ini || 0,
            harga_ml_pcs: item.harga_per_satuan || 0,
            total_avg_lead_time: item.total_avg_lead_time || 0,
            total_max_lead_time: item.total_max_lead_time || 0,
            safety_stock: item.safety_stock || 0,
            akan_datang: item.akan_datang || 0,
          };
          this.showManualLokalModal = true;
        },
        simulasiLokalAdu(){
          return ((Number(this.manualLokalForm.terjual_rata_rata_4bulan) || 0) / 30).toFixed(4);
        },
        simulasiLokalBatasMin(){
          const adu = (Number(this.manualLokalForm.terjual_rata_rata_4bulan) || 0) / 30;
          const avgLt = Number(this.manualLokalForm.total_avg_lead_time) || 0;
          const ss = (Number(this.manualLokalForm.safety_stock) || 0) + (Number(this.manualLokalForm.tambahan_buffer_hari) || 0);
          return this.formatNumber(adu * (avgLt + ss));
        },
        simulasiLokalTarget(){
          const adu = (Number(this.manualLokalForm.terjual_rata_rata_4bulan) || 0) / 30;
          const avgLt = Number(this.manualLokalForm.total_avg_lead_time) || 0;
          const ss = (Number(this.manualLokalForm.safety_stock) || 0) + (Number(this.manualLokalForm.tambahan_buffer_hari) || 0);
          const rp = Number(this.manualLokalForm.review_period) || 15;
          const batasMin = adu * (avgLt + ss);
          return this.formatNumber(batasMin + (adu * rp));
        },
        simulasiLokalOrder(){
          const adu = (Number(this.manualLokalForm.terjual_rata_rata_4bulan) || 0) / 30;
          const avgLt = Number(this.manualLokalForm.total_avg_lead_time) || 0;
          const ss = (Number(this.manualLokalForm.safety_stock) || 0) + (Number(this.manualLokalForm.tambahan_buffer_hari) || 0);
          const rp = Number(this.manualLokalForm.review_period) || 15;
          const target = (adu * (avgLt + ss)) + (adu * rp);
          const stok = Number(this.manualLokalForm.stok_saat_ini) || 0;
          const akanDatang = Number(this.manualLokalForm.akan_datang) || 0;
          const ord = Math.max(0, target - stok - akanDatang);
          return this.formatNumber(ord) + ' ' + (this.manualLokalForm.satuan || '');
        },
        async saveManualLokal(){
          this.savingManualLokal = true;
          try {
            const payload = {
              produk_id: this.manualLokalForm.produk_id,
              terjual_rata_rata_4bulan: Number(this.manualLokalForm.terjual_rata_rata_4bulan) || 0,
              review_period: Number(this.manualLokalForm.review_period) || 15,
              tambahan_buffer_hari: Number(this.manualLokalForm.tambahan_buffer_hari) || 0,
              stok_saat_ini: Number(this.manualLokalForm.stok_saat_ini) || 0,
              harga_ml_pcs: Number(this.manualLokalForm.harga_ml_pcs) || 0,
            };
            const res = await fetch('{{ route('analisa.update-manual-lokal') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
              },
              body: JSON.stringify(payload)
            });
            const j = await res.json();
            if (res.ok && j.success) {
              this.showManualLokalModal = false;
              Swal.fire({
                icon: 'success',
                title: 'Data Manual Tersimpan',
                text: 'Parameter manual bahan lokal berhasil diperbarui & analisa dihitung ulang.',
                confirmButtonColor: '#0284c7'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan',
                text: j.message || 'Terjadi kesalahan saat menyimpan data manual.',
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
            this.savingManualLokal = false;
          }
        },

        openManualImporModal(item){
          this.manualImporForm = {
            produk_id: item.produk_id,
            nama: item.nama,
            sku: item.sku,
            satuan: item.satuan,
            supplier_nama: item.supplier_nama,
            lead_time_average: item.lead_time_average || 0,
            lead_time_max: item.lead_time_max || 0,
            out: item.out || 0,
            review_period: item.review_period || 30,
            klasifikasi_abc: item.klasifikasi_abc || 'b',
            stok_saat_ini: item.stok_saat_ini || 0,
            inbound_before_eta: item.inbound_before_eta || 0,
            harga_per_satuan: item.harga_per_satuan || 0,
          };
          this.showManualImporModal = true;
        },
        simulasiImporAdu(){
          return ((Number(this.manualImporForm.out) || 0) / 120).toFixed(4);
        },
        simulasiImporSafetyStock(){
          const adu = (Number(this.manualImporForm.out) || 0) / 120;
          const avgLt = Number(this.manualImporForm.lead_time_average) || 0;
          const maxLt = Number(this.manualImporForm.lead_time_max) || 0;
          const bufferDays = Math.max(0, maxLt - avgLt);
          return this.formatNumber(bufferDays * adu);
        },
        simulasiImporTarget(){
          const adu = (Number(this.manualImporForm.out) || 0) / 120;
          const avgLt = Number(this.manualImporForm.lead_time_average) || 0;
          const maxLt = Number(this.manualImporForm.lead_time_max) || 0;
          const bufferDays = Math.max(0, maxLt - avgLt);
          const ss = bufferDays * adu;
          const minStock = ss + (adu * avgLt);
          const rp = Number(this.manualImporForm.review_period) || 30;
          return this.formatNumber(minStock + (adu * rp));
        },
        simulasiImporOrder(){
          const adu = (Number(this.manualImporForm.out) || 0) / 120;
          const avgLt = Number(this.manualImporForm.lead_time_average) || 0;
          const maxLt = Number(this.manualImporForm.lead_time_max) || 0;
          const bufferDays = Math.max(0, maxLt - avgLt);
          const ss = bufferDays * adu;
          const minStock = ss + (adu * avgLt);
          const rp = Number(this.manualImporForm.review_period) || 30;
          const target = minStock + (adu * rp);
          const stok = Number(this.manualImporForm.stok_saat_ini) || 0;
          const inbound = Number(this.manualImporForm.inbound_before_eta) || 0;
          const ord = Math.max(0, target - stok - inbound);
          return this.formatNumber(ord) + ' ' + (this.manualImporForm.satuan || '');
        },
        async saveManualImpor(){
          this.savingManualImpor = true;
          try {
            const payload = {
              produk_id: this.manualImporForm.produk_id,
              lead_time_average: Number(this.manualImporForm.lead_time_average) || 0,
              lead_time_max: Number(this.manualImporForm.lead_time_max) || 0,
              out: Number(this.manualImporForm.out) || 0,
              review_period: Number(this.manualImporForm.review_period) || 30,
              klasifikasi_abc: this.manualImporForm.klasifikasi_abc || 'b',
              stok_saat_ini: Number(this.manualImporForm.stok_saat_ini) || 0,
              inbound_before_eta: Number(this.manualImporForm.inbound_before_eta) || 0,
              harga_per_satuan: Number(this.manualImporForm.harga_per_satuan) || 0,
            };
            const res = await fetch('{{ route('analisa.update-manual-impor') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
              },
              body: JSON.stringify(payload)
            });
            const j = await res.json();
            if (res.ok && j.success) {
              this.showManualImporModal = false;
              Swal.fire({
                icon: 'success',
                title: 'Data Manual Tersimpan',
                text: 'Parameter manual bahan impor berhasil diperbarui & analisa dihitung ulang.',
                confirmButtonColor: '#0284c7'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan',
                text: j.message || 'Terjadi kesalahan saat menyimpan data manual.',
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
            this.savingManualImpor = false;
          }
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
        async generateImpor(){
          this.generating = true;
          try {
            const fd = new FormData();
            fd.append('_token', '{{ csrf_token() }}');
            const res = await fetch('{{ route('analisa.generate-impor') }}', {
              method: 'POST',
              body: fd,
              headers: { 'Accept': 'application/json' }
            });
            const j = await res.json();
            if (res.ok && j.success) {
              Swal.fire({
                icon: 'success',
                title: 'Generate Impor Berhasil',
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
        async checkingLokal(){
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
                title: 'Checking & Sinkronisasi Selesai',
                text: 'Data stok fisik real-time, outstanding PO berjalan, dan batas stok berhasil diperbarui.',
                confirmButtonColor: '#0284c7'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Checking Gagal',
                text: j.message || 'Terjadi kesalahan saat memeriksa data.',
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
        async checkingImpor(){
          this.generating = true;
          try {
            const fd = new FormData();
            fd.append('_token', '{{ csrf_token() }}');
            const res = await fetch('{{ route('analisa.generate-impor') }}', {
              method: 'POST',
              body: fd,
              headers: { 'Accept': 'application/json' }
            });
            const j = await res.json();
            if (res.ok && j.success) {
              Swal.fire({
                icon: 'success',
                title: 'Checking Impor Selesai',
                text: 'Data stok fisik, inbound ETA berjalan, dan rekomendasi order impor berhasil diperbarui.',
                confirmButtonColor: '#0284c7'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Checking Gagal',
                text: j.message || 'Terjadi kesalahan saat memeriksa data.',
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
        confirmFinalisasi(){
          const tipeMap = {
            lokal: 'bahan_lokal',
            impor: 'bahan_impor',
            fulfillment: 'produk_jadi_fulfillment'
          };
          const tipeName = {
            lokal: 'Bahan Baku Lokal',
            impor: 'Bahan Baku Impor',
            fulfillment: 'Produk Jadi Fulfillment'
          };
          const namaTipe = tipeName[this.tab] || 'Aktif';

          Swal.fire({
            title: 'Finalisasi Analisa ke Riwayat?',
            text: `Kunci working data ${namaTipe} ke Log Riwayat Snapshot? Seluruh data aktif saat ini akan diarsipkan sebagai snapshot locked.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0284c7',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Finalisasi Sekarang',
            cancelButtonText: 'Batal'
          }).then((result) => {
            if (result.isConfirmed) {
              this.finalisasi(tipeMap[this.tab] || 'all');
            }
          });
        },
        async finalisasi(tipe){
          try {
            const fd = new FormData();
            fd.append('tipe', tipe);
            fd.append('_token', '{{ csrf_token() }}');

            const res = await fetch('{{ route('analisa.finalisasi') }}', {
              method: 'POST',
              body: fd,
              headers: { 'Accept': 'application/json' }
            });
            const j = await res.json();
            if (res.ok && j.success) {
              Swal.fire({
                icon: 'success',
                title: 'Finalisasi Berhasil',
                text: j.message,
                confirmButtonColor: '#0284c7'
              }).then(() => {
                this.tab = 'riwayat';
                this.load();
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal Finalisasi',
                text: j.message || 'Tidak dapat memfinalisasi analisa.',
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
            // Auto-check items with status 'order' / 'po'
            if (this.tab === 'lokal') {
              this.selectedItemIds = this.rows.filter(r => r.status === 'order').map(r => r.id);
            } else if (this.tab === 'impor') {
              this.selectedItemIds = this.rows.filter(r => r.status === 'po').map(r => r.produk_id);
            } else {
              this.selectedItemIds = [];
            }
            this.page = 1;
            if (this.tab === 'fulfillment') {
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
          return this.finalisasi(this.tab === 'lokal' ? 'bahan_lokal' : (this.tab === 'impor' ? 'bahan_impor' : 'all'));
        }
      };
    }
    </script>
    @endpush
</x-app-layout>

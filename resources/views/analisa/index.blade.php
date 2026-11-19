<x-app-layout title="Analisa Stok">
    <div x-data="analisa()" x-init="load()" x-cloak class="space-y-4">
        {{-- ============ HEADER: judul + 1 aksi utama + menu aksi lain ============ --}}
        <x-page-header
            title="Analisa &amp; Rekomendasi Stok"
            subtitle="Pantau kecukupan stok, lead time supplier, dan rekomendasi order per bahan."
        >
            <x-slot:actions>
                <div class="flex flex-wrap items-center gap-2">
                    @can('analisa.manage')
                    <button type="button" x-show="tab === 'lokal' || tab === 'impor'" @click="openAddSkuModal(tab)" class="inline-flex items-center gap-1.5 h-9 px-3.5 text-xs font-medium transition-colors cursor-pointer btn-soft rounded-[6px]" title="Tambah atau pindahkan SKU ke Analisa Lokal / Impor">
                        <svg class="size-3.5 text-[#667085] dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah SKU</span>
                    </button>

                    <button type="button" x-show="tab === 'lokal' || tab === 'impor'" @click="tab === 'impor' ? generateImpor() : generateLokal()" :disabled="generating" class="inline-flex items-center gap-1.5 h-9 px-4 disabled:opacity-50 text-xs font-medium transition-colors cursor-pointer btn-glossy rounded-[6px]" title="Generate working data analisa dari master">
                        <svg x-show="!generating" class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <svg x-show="generating" class="animate-spin size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="generating ? 'Menghitung…' : (tab === 'impor' ? 'Generate Analisa Impor' : 'Generate Analisa')"></span>
                    </button>
                    @endcan

                    @canany(['analisa.manage', 'analisa.snapshot'])
                    <div class="relative" x-data="{ menuOpen: false }" @keydown.escape.window="menuOpen = false" @click.outside="menuOpen = false" x-show="tab !== 'riwayat'">
                        <button type="button" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen" aria-haspopup="menu" class="inline-flex items-center justify-center gap-1.5 h-9 px-3 text-xs font-medium transition-colors cursor-pointer btn-soft rounded-[6px]" title="Aksi lainnya">
                            <span>Lainnya</span>
                            <svg class="size-3.5 text-[#667085] dark:text-gray-400 transition-transform" :class="menuOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="menuOpen" x-cloak x-transition.opacity.duration.100ms role="menu" class="absolute right-0 top-full mt-1.5 z-30 w-72 p-1.5 bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-gray-700 rounded-xl shadow-[0_8px_24px_rgba(16,24,40,0.10)]">
                            @can('analisa.manage')
                            <button type="button" role="menuitem" x-show="tab === 'lokal' || tab === 'impor'" @click="menuOpen = false; tab === 'impor' ? checkingImpor() : checkingLokal()" :disabled="generating" class="w-full flex items-start gap-2.5 px-3 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-50 transition-colors cursor-pointer rounded-lg">
                                <svg class="size-4 mt-0.5 shrink-0 text-[#667085] dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>
                                    <span class="block text-xs font-medium text-[#101828] dark:text-white">Checking (Generate Ulang)</span>
                                    <span class="block text-[11px] text-[#667085] dark:text-gray-400 mt-0.5">Hitung ulang dengan stok fisik &amp; PO berjalan saat ini.</span>
                                </span>
                            </button>
                            @endcan
                            @can('analisa.snapshot')
                            <button type="button" role="menuitem" @click="menuOpen = false; confirmFinalisasi()" class="w-full flex items-start gap-2.5 px-3 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer rounded-lg">
                                <svg class="size-4 mt-0.5 shrink-0 text-[#667085] dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span>
                                    <span class="block text-xs font-medium text-[#101828] dark:text-white">Finalisasi ke Riwayat</span>
                                    <span class="block text-[11px] text-[#667085] dark:text-gray-400 mt-0.5">Kunci data analisa aktif menjadi snapshot riwayat.</span>
                                </span>
                            </button>
                            @endcan
                        </div>
                    </div>
                    @endcanany
                </div>
            </x-slot:actions>
        </x-page-header>

        {{-- ============ TAB + STATUS DATA ============ --}}
        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
            <div class="inline-flex flex-wrap items-center gap-1 p-1 bg-[#f3f4f6] dark:bg-gray-900 rounded-[8px] border border-[#E8E8E8] dark:border-gray-800" role="tablist">
                <template x-for="t in tabs" :key="t.key">
                    <button type="button" role="tab" :aria-selected="tab === t.key"
                        @click="tab = t.key; load()"
                        :class="tab === t.key ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white shadow-[0_1px_2px_rgba(0,0,0,0.06)]' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white'"
                        class="px-3.5 py-1.5 text-xs transition-all cursor-pointer select-none font-medium rounded-[6px]"
                    >
                        <span x-text="t.label"></span>
                    </button>
                </template>
            </div>

            <p class="text-[11px] text-[#667085] dark:text-gray-400 tabular-nums">
                <template x-if="(tab === 'lokal' || tab === 'impor') && meta.last_generated_at">
                    <span>Terakhir di-generate <strong class="font-medium text-[#101828] dark:text-gray-200" x-text="meta.last_generated_at"></strong> oleh <span x-text="meta.last_generated_by"></span></span>
                </template>
                <template x-if="!((tab === 'lokal' || tab === 'impor') && meta.last_generated_at)">
                    <span>Data real-time sistem</span>
                </template>
            </p>
        </div>

        {{-- ============ RINGKASAN: satu panel, empat angka ============ --}}
        <dl class="grid grid-cols-2 lg:grid-cols-4 gap-px overflow-hidden bg-[#E8E8E8] dark:bg-gray-800 rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
            <div class="px-5 py-4 bg-white dark:bg-gray-900">
                <dt class="text-xs text-[#667085] dark:text-gray-400">Total Item</dt>
                <dd class="mt-1 text-2xl leading-none font-medium tracking-tight tabular-nums text-[#101828] dark:text-white" x-text="rows.length"></dd>
            </div>
            <div class="px-5 py-4 bg-white dark:bg-gray-900">
                <dt class="text-xs text-[#667085] dark:text-gray-400">Perlu Order</dt>
                <dd class="mt-1 flex items-center gap-2 text-2xl leading-none font-medium tracking-tight tabular-nums text-rose-600 dark:text-rose-400">
                    <span x-text="kpiOrderCount()"></span>
                    <span x-show="kpiOrderCount() > 0" class="inline-block size-2 rounded-full bg-rose-500" aria-hidden="true"></span>
                </dd>
            </div>
            <div class="px-5 py-4 bg-white dark:bg-gray-900">
                <dt class="text-xs text-[#667085] dark:text-gray-400">Status Aman</dt>
                <dd class="mt-1 text-2xl leading-none font-medium tracking-tight tabular-nums text-[#101828] dark:text-white" x-text="kpiAmanCount()"></dd>
            </div>
            <div class="px-5 py-4 bg-white dark:bg-gray-900">
                <dt class="text-xs text-[#667085] dark:text-gray-400">Total Qty Rekomendasi</dt>
                <dd class="mt-1 text-2xl leading-none font-medium tracking-tight tabular-nums text-[#101828] dark:text-white" x-text="kpiTotalOrderQty()"></dd>
            </div>
        </dl>

        {{-- ============ TOOLBAR: cari + filter | seleksi ============ --}}
        <div class="bg-white dark:bg-gray-900 rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
            <div class="p-3 sm:p-4 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-52 sm:max-w-md">
                    <label for="analisa-search" class="sr-only">Cari</label>
                    <input
                        id="analisa-search"
                        type="text"
                        x-model.debounce.250ms="searchQuery"
                        @input="page = 1; if(tab !== 'lokal') renderTable();"
                        placeholder="Cari SKU, nama bahan, supplier..."
                        class="w-full h-9 pl-9 pr-8 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 text-[#101828] dark:text-white placeholder:text-[#667085] dark:placeholder:text-gray-500 focus:border-[#111111] dark:focus:border-white outline-none rounded-[6px]"
                    >
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#667085] dark:text-gray-400">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <button type="button" x-show="searchQuery" @click="searchQuery = ''; page = 1; if(tab !== 'lokal') renderTable();" aria-label="Hapus pencarian" class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white cursor-pointer">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <template x-if="tab === 'lokal' || tab === 'impor'">
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-if="(tab === 'lokal' && lokalSubTab === 'rekomendasi') || (tab === 'impor' && imporSubTab === 'rekomendasi')">
                            <select x-model="statusFilter" @change="page = 1" aria-label="Filter status" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 text-xs px-3.5 text-[#101828] dark:text-white focus:border-[#111111] dark:focus:border-white outline-none cursor-pointer rounded-[6px]">
                                <option value="all">Semua Status</option>
                                <option value="order">Hanya Perlu Order</option>
                                <option value="aman">Hanya Status Aman</option>
                            </select>
                        </template>
                        <select x-model="supplierFilter" @change="page = 1" aria-label="Filter supplier" class="h-9 max-w-48 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 text-xs px-3.5 text-[#101828] dark:text-white focus:border-[#111111] dark:focus:border-white outline-none cursor-pointer rounded-[6px]">
                            <option value="all">Semua Supplier</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->nama }} ({{ ucfirst($s->kategori) }})</option>
                            @endforeach
                        </select>
                    </div>
                </template>

                <div class="ml-auto flex items-center gap-2 text-xs text-[#667085] dark:text-gray-400">
                    <label for="analisa-per-page">Baris</label>
                    <select id="analisa-per-page" x-model="perPage" @change="page = 1; if(tab !== 'lokal') renderTable();" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 text-xs px-2.5 text-[#101828] dark:text-white focus:border-[#111111] dark:focus:border-white outline-none cursor-pointer rounded-[6px]">
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                        <option value="all">Semua</option>
                    </select>
                </div>
            </div>

            {{-- Baris seleksi: hanya muncul di sub-tab rekomendasi atau saat ada item terpilih --}}
            <div x-show="(tab === 'lokal' && lokalSubTab === 'rekomendasi') || (tab === 'impor' && imporSubTab === 'rekomendasi') || ((tab === 'lokal' || tab === 'impor') && selectedOrderCount() > 0)" x-cloak class="px-3 sm:px-4 py-2.5 flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-[#E8E8E8] dark:border-gray-800 bg-[#f8fafb] dark:bg-gray-950/40 rounded-b-[12px]">
                <span class="text-[11px] text-[#667085] dark:text-gray-400">Pilih cepat</span>
                <button type="button" @click="selectAllPerluOrder()" class="h-7 px-2.5 text-xs font-medium text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/30 hover:bg-rose-100 dark:hover:bg-rose-950/50 border border-rose-200 dark:border-rose-800/50 transition cursor-pointer rounded-[6px]">
                    Perlu Order (<span x-text="filteredOrderRows().length"></span>)
                </button>
                <button type="button" @click="selectAllFiltered()" class="h-7 px-2.5 text-xs font-medium transition cursor-pointer btn-soft rounded-[6px]">
                    Semua Hasil (<span x-text="filteredRows().length"></span>)
                </button>

                <div class="ml-auto flex items-center gap-3" x-show="selectedOrderCount() > 0">
                    <span class="text-xs text-[#101828] dark:text-gray-200 tabular-nums"><strong class="font-medium" x-text="selectedOrderCount()"></strong> item dipilih</span>
                    <button type="button" @click="selectedItemIds = []" class="text-xs font-medium text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white transition cursor-pointer">Batal pilih</button>
                    @can('analisa.create_po')
                    <button type="button" @click="goToCreatePo()" class="inline-flex items-center gap-1.5 h-8 px-3.5 text-xs font-medium transition-colors cursor-pointer btn-glossy rounded-[6px]">
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span x-text="tab === 'impor' ? 'Buat PO Impor' : 'Buat PO'"></span>
                    </button>
                    @endcan
                </div>
            </div>
        </div>

        {{-- Modularized Tab Partials --}}
        @include('analisa.partials.table-lokal')
        @include('analisa.partials.table-impor')
        @include('analisa.partials.table-riwayat')
        @include('analisa.partials.table-fulfillment')
        <!-- Modal Partials -->
        @include('analisa.partials.modal-snapshot')
        @include('analisa.partials.modal-stage-lead-time')
        @include('analisa.partials.modal-manual-lokal')
        @include('analisa.partials.modal-manual-impor')
        @include('analisa.partials.modal-add-sku')
        @include('analisa.partials.modal-compare-supplier')
        @include('analisa.partials.modal-varian')
    </div>

    @push('scripts')
    <script>
    function analisa(){
      return {
        tab: 'lokal',
        lokalSubTab: 'rekomendasi',
        imporSubTab: 'rekomendasi',
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
        showSnapshotModal: false,
        activeSnapshot: null,
        showVarianModal: false,
        activeVarianProduct: null,
        openVarianModal(item) {
          this.activeVarianProduct = item;
          this.showVarianModal = true;
        },
        showStageModal: false,
        savingStage: false,
        stageForm: {
          produk_id: null,
          nama: '',
          sku: '',
          supplier_nama: '',
          sumber_lt: 'manual',
          jumlah_transaksi: 0,
          tambahan_buffer_hari: 0,
          average: {
            id: null,
            perencanaan: 0,
            approval: 0,
            supplier_confirm: 0,
            payment: 0,
            po: 0,
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
            pengiriman: 0,
            unloading: 0,
            input: 0
          },
          auto_average: null,
          auto_max: null
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
          akan_datang: 0,
          current_hpp: null,
          current_stok_aktual: null,
          current_terjual_sistem: null
        },
        showManualImporModal: false,
        savingManualImpor: false,
        klasifikasiAbcList: [],
        showAddSkuModal: false,
        savingAddSku: false,
        loadingAvailableAnalisaSkus: false,
        addSkuSearch: '',
        availableAnalisaSkus: [],
        addSkuForm: {
          produk_id: null,
          selectedSku: '',
          selectedNama: '',
          selectedSatuan: '',
          currentProfil: '',
          target_profil: 'impor',
          klasifikasi_abc_id: '',
          out: '',
          lead_time_average: '',
          lead_time_max: '',
          terjual_rata_rata_4bulan: '',
          tambahan_buffer_hari: '',
          review_period_impor: 30,
          review_period_lokal: 15,
          harga_per_satuan: '',
          current_hpp: null,
          current_stok_aktual: null,
          current_inbound: null,
          currentIsOpposite() {
            if (!this.produk_id) return false;
            const curImpor = this.currentProfil === 'impor' || this.currentProfil === 'bahan_impor';
            if (this.target_profil === 'impor') return this.currentProfil === 'lokal';
            return curImpor;
          }
        },
        manualImporForm: {
          produk_id: null,
          nama: '',
          sku: '',
          satuan: '',
          supplier_nama: '',
          lead_time_average: 0,
          lead_time_max: 0,
          periode_mulai: '',
          periode_akhir: '',
          jumlah_hari_periode: 122,
          out: 0,
          review_period: 30,
          klasifikasi_abc: 'b',
          klasifikasi_abc_id: null,
          stok_saat_ini: 0,
          inbound_before_eta: 0,
          harga_per_satuan: 0,
          satuan_order_moq: 1,
          current_hpp: null,
          current_stok_aktual: null,
          current_inbound: null,
          empirical_avg_lt: null,
          empirical_max_lt: null
        },
        loading: true,
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
        showCompareSupplierModal: false,
        loadingCompareSupplier: false,
        savingSelectSupplier: false,
        compareProduct: {
          id: null,
          nama: '',
          sku: '',
          suppliers: []
        },
        async openCompareSupplier(row) {
          this.compareProduct = {
            id: row.produk_id,
            nama: row.nama,
            sku: row.sku,
            suppliers: []
          };
          this.showCompareSupplierModal = true;
          this.loadingCompareSupplier = true;
          try {
            const res = await fetch(`/analisa/compare-suppliers/${row.produk_id}`, {
              headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
              this.compareProduct.suppliers = data.data.suppliers || [];
            }
          } catch (e) {
            console.error('Failed to load supplier comparison', e);
          } finally {
            this.loadingCompareSupplier = false;
          }
        },
        async selectSupplier(supplierId) {
          if (!this.compareProduct.id || !supplierId) return;
          await this.selectSupplierDirect(this.compareProduct.id, supplierId, true);
        },
        async selectSupplierDirect(produkId, supplierId, closeModal = false) {
          if (!produkId || !supplierId) return;
          this.savingSelectSupplier = true;
          try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch('{{ route('analisa.select-supplier') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf
              },
              body: JSON.stringify({
                produk_id: produkId,
                supplier_id: supplierId
              })
            });
            const data = await res.json();
            if (data.success) {
              if (closeModal) {
                this.showCompareSupplierModal = false;
              }
              await this.load();
            } else {
              appDialog.alert(data.message || 'Gagal memilih supplier', { tone: 'danger', title: 'Gagal' });
            }
          } catch (e) {
            console.error('Failed to select supplier', e);
            appDialog.alert('Terjadi kesalahan koneksi saat memilih supplier.', { tone: 'danger', title: 'Gagal' });
          } finally {
            this.savingSelectSupplier = false;
          }
        },
        formatLt(v){
          if (v === null || v === undefined || isNaN(v)) return '0.0';
          return Number(v).toFixed(1);
        },
        formatNumber(v){
          if (v === null || v === undefined || isNaN(v)) return '0';
          return Math.round(Number(v)).toLocaleString('id-ID');
        },
        formatRupiah(v){
          if (v === null || v === undefined || isNaN(v)) return 'Rp 0';
          return 'Rp ' + Number(v).toLocaleString('id-ID', { maximumFractionDigits: 2 });
        },
        fmt(v, colName){
          if (typeof v === 'number') {
            if (colName === 'total_nominal_order') {
              return 'Rp ' + Math.round(v).toLocaleString('id-ID', { maximumFractionDigits: 0 });
            }
            return Math.round(v).toLocaleString('id-ID', { maximumFractionDigits: 0 });
          }
          return v ?? '-';
        },
        badge(s){
          const st = String(s || '').toLowerCase();
          if (st === 'order' || st === 'po') {
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50 uppercase tracking-wide">ORDER</span>';
          }
          if (st === 'aman' || st === 'tidak') {
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-card dark:bg-card text-foreground dark:text-foreground border border-border dark:border-border uppercase tracking-wide">AMAN</span>';
          }
          return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 uppercase tracking-wide">${st}</span>`;
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
            if (this.statusFilter !== 'all' && ((this.tab === 'lokal' && this.lokalSubTab === 'rekomendasi') || (this.tab === 'impor' && this.imporSubTab === 'rekomendasi'))) {
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
        filteredAnalisaRows(){
          let list = (this.tables && Array.isArray(this.tables.analisa)) ? this.tables.analisa : [];
          if (this.searchQuery && this.searchQuery.trim()) {
            const q = this.searchQuery.toLowerCase().trim();
            list = list.filter(r =>
              (r.nama && r.nama.toLowerCase().includes(q)) ||
              (r.sku && r.sku.toLowerCase().includes(q)) ||
              (r.supplier_nama && r.supplier_nama.toLowerCase().includes(q))
            );
          }
          if (this.supplierFilter !== 'all') {
            const sf = String(this.supplierFilter);
            list = list.filter(r => String(r.supplier_id) === sf);
          }
          return list;
        },
        paginatedAnalisaRows(){
          const list = this.filteredAnalisaRows();
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
          if ((this.tab === 'lokal' && this.lokalSubTab === 'analisa') || (this.tab === 'impor' && this.imporSubTab === 'analisa')) {
            return this.filteredAnalisaRows().length;
          }
          return this.filteredRows().length;
        },
        currentRawLength(){
          if (this.tab === 'lokal' && this.lokalSubTab === 'stages') {
            return (this.tables && this.tables.stages) ? this.tables.stages.length : 0;
          }
          if ((this.tab === 'lokal' && this.lokalSubTab === 'analisa') || (this.tab === 'impor' && this.imporSubTab === 'analisa')) {
            return (this.tables && Array.isArray(this.tables.analisa)) ? this.tables.analisa.length : 0;
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
          const key = 'id';
          return pageItems.every(r => this.selectedItemIds.includes(r[key]));
        },
        toggleSelectCurrentPage(e){
          const key = 'id';
          const pageItemIds = this.paginatedRows().map(r => r[key]);
          if (e.target.checked) {
            this.selectedItemIds = Array.from(new Set([...this.selectedItemIds, ...pageItemIds]));
          } else {
            this.selectedItemIds = this.selectedItemIds.filter(id => !pageItemIds.includes(id));
          }
        },
        filteredOrderRows(){
          return this.filteredRows().filter(r => {
            const s = String(r.status || '').toLowerCase();
            return s === 'order' || s === 'po';
          });
        },
        selectAllPerluOrder(){
          const ids = this.filteredOrderRows().map(r => r.id);
          this.selectedItemIds = Array.from(new Set([...this.selectedItemIds, ...ids]));
        },
        selectAllFiltered(){
          const key = 'id';
          const filteredIds = this.filteredRows().map(r => r[key]);
          this.selectedItemIds = Array.from(new Set([...this.selectedItemIds, ...filteredIds]));
        },
        selectedOrderCount(){
          return this.selectedItemIds.length;
        },
        getSelectedItems(){
          const key = 'id';
          return this.rows.filter(r => this.selectedItemIds.includes(r[key]));
        },
        getSelectedTotalNominal(){
          return this.getSelectedItems().reduce((acc, r) => acc + (Number(r.total_nominal_order) || 0), 0);
        },
        goToCreatePo(){
          if (this.selectedItemIds.length === 0) {
            this.selectedItemIds = this.rows.filter(r => {
              const s = String(r.status || '').toLowerCase();
              return s === 'order' || s === 'po';
            }).map(r => r.id);
          }
          if (this.selectedItemIds.length === 0) {
            Swal.fire({
              icon: 'info',
              title: 'Pilih Item',
              text: 'Silakan centang setidaknya satu item bahan yang ingin dibuatkan Purchase Order.',
              confirmButtonColor: '#111111'
            });
            return;
          }
          const selectedItems = this.getSelectedItems();
          if (selectedItems.some(r => !r.supplier_id)) {
            Swal.fire({
              icon: 'warning',
              title: 'Supplier Belum Ditentukan',
              text: 'Sebagian item yang dipilih belum memiliki supplier yang ditentukan. Harap tentukan supplier untuk semua item terlebih dahulu.',
              confirmButtonColor: '#111111'
            });
            return;
          }
          const supIds = selectedItems.map(r => r.supplier_id).filter(Boolean);
          const uniqueSupIds = Array.from(new Set(supIds));
          if (uniqueSupIds.length > 1) {
            Swal.fire({
              icon: 'warning',
              title: 'Supplier Tidak Konsisten',
              text: 'Item yang dipilih memiliki supplier yang berbeda-beda. Harap pilih item dari satu supplier yang sama untuk membuat Purchase Order.',
              confirmButtonColor: '#111111'
            });
            return;
          }

          let url = '{{ route('analisa.create-po') }}?ids=' + encodeURIComponent(this.selectedItemIds.join(',')) + '&tipe=' + encodeURIComponent(this.tab);
          if (uniqueSupIds.length === 1) {
            url += '&supplier_id=' + encodeURIComponent(uniqueSupIds[0]);
          }
          window.location.href = url;
        },
        abcBadgeClass(abc){
          const v = String(abc || '').toLowerCase();
          if (v === 'wajib_a' || v === 'a') return 'bg-card dark:bg-card text-foreground dark:text-foreground border border-border dark:border-border';
          if (v === 'b') return 'bg-card dark:bg-card text-foreground dark:text-foreground border border-border dark:border-border';
          return 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700';
        },
        openSnapshotModal(item){
          this.activeSnapshot = item;
          this.showSnapshotModal = true;
        },
        openStageModal(item){
          let stg = null;
          if (item.average || item.max || item.manual_average || item.manual_max) {
            stg = item;
          } else if (this.tables && this.tables.stages) {
            stg = this.tables.stages.find(s => s.produk_id === item.produk_id);
          }
          const isAuto = (stg?.sumber_lt || item.sumber_lt || 'manual') === 'otomatis';
          const avg = isAuto && stg && stg.manual_average ? stg.manual_average : ((stg && stg.average) ? stg.average : {});
          const max = isAuto && stg && stg.manual_max ? stg.manual_max : ((stg && stg.max) ? stg.max : {});

          this.stageForm = {
            produk_id: item.produk_id,
            nama: item.nama,
            sku: item.sku,
            supplier_nama: item.supplier_nama,
            sumber_lt: stg?.sumber_lt || item.sumber_lt || 'manual',
            jumlah_transaksi: stg?.jumlah_transaksi ?? item.jumlah_transaksi ?? 0,
            tambahan_buffer_hari: item.tambahan_buffer_hari ?? (stg ? stg.tambahan_buffer_hari : 0),
            auto_average: isAuto && stg && stg.average ? stg.average : null,
            auto_max: isAuto && stg && stg.max ? stg.max : null,
            average: {
              id: avg.id || null,
              perencanaan: avg.perencanaan ?? 0,
              approval: avg.approval ?? 0,
              supplier_confirm: avg.supplier_confirm ?? 0,
              payment: avg.payment ?? 0,
              po: avg.po ?? 0,
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
        copyAutoStages(){
          if (!this.stageForm.auto_average) return;
          const stages = ['perencanaan', 'approval', 'supplier_confirm', 'payment', 'po', 'pengiriman', 'unloading', 'input'];
          stages.forEach(st => {
            if (this.stageForm.auto_average[st] !== undefined) {
              this.stageForm.average[st] = Number(this.stageForm.auto_average[st]) || 0;
            }
            if (this.stageForm.auto_max && this.stageForm.auto_max[st] !== undefined) {
              this.stageForm.max[st] = Number(this.stageForm.auto_max[st]) || 0;
            }
          });
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
                text: 'Perubahan 8 tahap lead time berhasil disimpan & analisa dihitung ulang.',
                confirmButtonColor: '#111111'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan',
                text: j.message || 'Terjadi kesalahan saat menyimpan data tahap.',
                confirmButtonColor: '#111111'
              });
            }
          } catch(e) {
            Swal.fire({
              icon: 'error',
              title: 'Kesalahan Sistem',
              text: 'Tidak dapat terhubung ke server.',
              confirmButtonColor: '#111111'
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
            current_hpp: item.current_hpp !== undefined ? Number(item.current_hpp) : (item.harga_per_satuan || 0),
            current_stok_aktual: item.current_stok_aktual !== undefined ? Number(item.current_stok_aktual) : (item.stok_saat_ini || 0),
            current_terjual_sistem: item.current_terjual_sistem !== undefined ? Number(item.current_terjual_sistem) : (item.terjual_rata_rata_4bulan || 0),
          };
          this.showManualLokalModal = true;
        },
        simulasiLokalAdu(){
          const terjual = Number(this.manualLokalForm.terjual_rata_rata_4bulan) || 0;
          return this.formatNumber(terjual / 30);
        },
        simulasiLokalBatasMin(){
          const adu = (Number(this.manualLokalForm.terjual_rata_rata_4bulan) || 0) / 30;
          const avgLt = Number(this.manualLokalForm.total_avg_lead_time) || 0;
          const maxLt = Number(this.manualLokalForm.total_max_lead_time) || 0;
          const buffer = Number(this.manualLokalForm.tambahan_buffer_hari) || 0;
          const ss = maxLt > 0 ? (Math.max(0, maxLt - avgLt) + buffer) : (Number(this.manualLokalForm.safety_stock) || 0);
          return this.formatNumber(Math.round(adu * (avgLt + ss)));
        },
        simulasiLokalTarget(){
          const adu = (Number(this.manualLokalForm.terjual_rata_rata_4bulan) || 0) / 30;
          const avgLt = Number(this.manualLokalForm.total_avg_lead_time) || 0;
          const maxLt = Number(this.manualLokalForm.total_max_lead_time) || 0;
          const buffer = Number(this.manualLokalForm.tambahan_buffer_hari) || 0;
          const ss = maxLt > 0 ? (Math.max(0, maxLt - avgLt) + buffer) : (Number(this.manualLokalForm.safety_stock) || 0);
          const rp = Number(this.manualLokalForm.review_period) || 15;
          return this.formatNumber(Math.round(adu * (avgLt + ss + rp)));
        },
        simulasiLokalOrder(){
          const adu = (Number(this.manualLokalForm.terjual_rata_rata_4bulan) || 0) / 30;
          const avgLt = Number(this.manualLokalForm.total_avg_lead_time) || 0;
          const maxLt = Number(this.manualLokalForm.total_max_lead_time) || 0;
          const buffer = Number(this.manualLokalForm.tambahan_buffer_hari) || 0;
          const ss = maxLt > 0 ? (Math.max(0, maxLt - avgLt) + buffer) : (Number(this.manualLokalForm.safety_stock) || 0);
          const batasMin = Math.round(adu * (avgLt + ss));
          const stok = Number(this.manualLokalForm.stok_saat_ini) || 0;
          const akanDatang = Number(this.manualLokalForm.akan_datang) || 0;
          const tersedia = stok + akanDatang;
          if (tersedia > batasMin) {
            return '0 ' + (this.manualLokalForm.satuan || '');
          }
          const selisih = Math.abs(tersedia - batasMin);
          const moq = Number(this.manualLokalForm.satuan_order_moq) || 1;
          const roundedOrder = moq > 1 ? Math.ceil(selisih / moq) * moq : Math.ceil(selisih);
          return this.formatNumber(Math.round(roundedOrder)) + ' ' + (this.manualLokalForm.satuan || '');
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
                confirmButtonColor: '#111111'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan',
                text: j.message || 'Terjadi kesalahan saat menyimpan data manual.',
                confirmButtonColor: '#111111'
              });
            }
          } catch(e) {
            Swal.fire({
              icon: 'error',
              title: 'Kesalahan Sistem',
              text: 'Tidak dapat terhubung ke server.',
              confirmButtonColor: '#111111'
            });
          } finally {
            this.savingManualLokal = false;
          }
        },

        openManualImporModal(item){
          const defaultEnd = item.periode_akhir || new Date().toISOString().split('T')[0];
          let defaultStart = item.periode_mulai;
          if (!defaultStart) {
            const d = new Date();
            d.setMonth(d.getMonth() - 4);
            defaultStart = d.toISOString().split('T')[0];
          }

          this.manualImporForm = {
            produk_id: item.produk_id,
            nama: item.nama,
            sku: item.sku,
            satuan: item.satuan,
            supplier_nama: item.supplier_nama,
            lead_time_average: item.lead_time_average || 0,
            lead_time_max: item.lead_time_max || 0,
            periode_mulai: defaultStart,
            periode_akhir: defaultEnd,
            jumlah_hari_periode: item.jumlah_hari_periode || 122,
            out: item.parent_out !== undefined ? item.parent_out : (item.out || 0),
            review_period: item.review_period || 30,
            klasifikasi_abc: item.klasifikasi_abc_id ? (item.klasifikasi_abc || null) : null,
            klasifikasi_abc_id: item.klasifikasi_abc_id ? String(item.klasifikasi_abc_id) : '',
            stok_saat_ini: item.parent_stok !== undefined ? item.parent_stok : (item.stok_saat_ini || 0),
            inbound_before_eta: item.parent_inbound !== undefined ? item.parent_inbound : (item.inbound_before_eta || 0),
            harga_per_satuan: item.harga_per_satuan || 0,
            satuan_order_moq: Number(item.satuan_order_moq) || 1,
            current_hpp: item.current_hpp !== undefined ? Number(item.current_hpp) : (item.harga_per_satuan || 0),
            current_stok_aktual: item.current_stok_aktual !== undefined ? Number(item.current_stok_aktual) : (item.parent_stok !== undefined ? item.parent_stok : (item.stok_saat_ini || 0)),
            current_inbound: item.current_inbound !== undefined ? Number(item.current_inbound) : (item.parent_inbound !== undefined ? item.parent_inbound : (item.inbound_before_eta || 0)),
            empirical_avg_lt: item.empirical_avg_lt !== undefined && item.empirical_avg_lt !== null ? Number(item.empirical_avg_lt) : null,
            empirical_max_lt: item.empirical_max_lt !== undefined && item.empirical_max_lt !== null ? Number(item.empirical_max_lt) : null,
          };

          if (!this.manualImporForm.klasifikasi_abc_id && item.klasifikasi_abc_id === null) {
            this.manualImporForm.klasifikasi_abc_id = '';
            this.manualImporForm.klasifikasi_abc = null;
          }

          this.showManualImporModal = true;
        },
        onAbcChange(){
          if (this.manualImporForm.klasifikasi_abc_id) {
            const found = this.klasifikasiAbcList.find(x => x.id == this.manualImporForm.klasifikasi_abc_id);
            if (found) {
              this.manualImporForm.klasifikasi_abc = found.kode;
            }
          } else {
            this.manualImporForm.klasifikasi_abc = null;
          }
        },
        async openAddSkuModal(defaultProfil = 'impor'){
          this.addSkuSearch = '';
          this.addSkuForm.produk_id = null;
          this.addSkuForm.selectedSku = '';
          this.addSkuForm.selectedNama = '';
          this.addSkuForm.selectedSatuan = '';
          this.addSkuForm.currentProfil = '';
          this.addSkuForm.target_profil = defaultProfil === 'lokal' ? 'lokal' : 'impor';
          this.addSkuForm.klasifikasi_abc_id = '';
          this.addSkuForm.out = '';
          this.addSkuForm.lead_time_average = '';
          this.addSkuForm.lead_time_max = '';
          this.addSkuForm.terjual_rata_rata_4bulan = '';
          this.addSkuForm.tambahan_buffer_hari = '';
          this.addSkuForm.review_period_impor = 30;
          this.addSkuForm.review_period_lokal = 15;
          this.addSkuForm.harga_per_satuan = '';
          this.addSkuForm.current_hpp = null;
          this.addSkuForm.current_stok_aktual = null;
          this.addSkuForm.current_inbound = null;
          this.showAddSkuModal = true;
          await this.fetchAvailableAnalisaSkus();
        },
        async fetchAvailableAnalisaSkus(){
          this.loadingAvailableAnalisaSkus = true;
          try {
            const params = new URLSearchParams();
            if (this.addSkuSearch.trim()) params.set('q', this.addSkuSearch.trim());
            const res = await fetch('{{ route('analisa.available-skus') }}?' + params.toString(), {
              headers: { 'Accept': 'application/json' }
            });
            const d = await res.json();
            this.availableAnalisaSkus = d.data || [];
          } catch (e) {
            this.availableAnalisaSkus = [];
          } finally {
            this.loadingAvailableAnalisaSkus = false;
          }
        },
        selectSkuToAdd(item){
          this.addSkuForm.produk_id = item.id;
          this.addSkuForm.selectedSku = item.sku;
          this.addSkuForm.selectedNama = item.nama;
          this.addSkuForm.selectedSatuan = item.satuan || '';
          this.addSkuForm.currentProfil = item.profil_analisa || '';
          this.addSkuForm.current_hpp = item.current_hpp !== undefined ? Number(item.current_hpp) : (item.harga_hpp || 0);
          this.addSkuForm.current_stok_aktual = item.current_stok_aktual !== undefined ? Number(item.current_stok_aktual) : 0;
          this.addSkuForm.current_inbound = item.current_inbound !== undefined ? Number(item.current_inbound) : 0;
          if (item.klasifikasi_abc_id) {
            this.addSkuForm.klasifikasi_abc_id = String(item.klasifikasi_abc_id);
          }
          if (this.addSkuForm.current_hpp > 0 && !this.addSkuForm.harga_per_satuan) {
            this.addSkuForm.harga_per_satuan = this.addSkuForm.current_hpp;
          }
        },
        async submitAddSku(){
          if (!this.addSkuForm.produk_id) return;
          this.savingAddSku = true;
          try {
            const target = this.addSkuForm.target_profil;
            const payload = {
              produk_id: Number(this.addSkuForm.produk_id),
              target_profil: target,
            };
            if (target === 'impor') {
              payload.klasifikasi_abc_id = this.addSkuForm.klasifikasi_abc_id ? Number(this.addSkuForm.klasifikasi_abc_id) : null;
              if (this.addSkuForm.out !== '' && this.addSkuForm.out !== null) payload.out = Number(this.addSkuForm.out);
              if (this.addSkuForm.lead_time_average !== '' && this.addSkuForm.lead_time_average !== null) payload.lead_time_average = Number(this.addSkuForm.lead_time_average);
              if (this.addSkuForm.lead_time_max !== '' && this.addSkuForm.lead_time_max !== null) payload.lead_time_max = Number(this.addSkuForm.lead_time_max);
              payload.review_period = Number(this.addSkuForm.review_period_impor) || 30;
            } else {
              if (this.addSkuForm.terjual_rata_rata_4bulan !== '' && this.addSkuForm.terjual_rata_rata_4bulan !== null) payload.terjual_rata_rata_4bulan = Number(this.addSkuForm.terjual_rata_rata_4bulan);
              if (this.addSkuForm.tambahan_buffer_hari !== '' && this.addSkuForm.tambahan_buffer_hari !== null) payload.tambahan_buffer_hari = Number(this.addSkuForm.tambahan_buffer_hari);
              payload.review_period = Number(this.addSkuForm.review_period_lokal) || 15;
            }
            if (this.addSkuForm.harga_per_satuan !== '' && this.addSkuForm.harga_per_satuan !== null) {
              payload.harga_per_satuan = Number(this.addSkuForm.harga_per_satuan);
            }

            const res = await fetch('{{ route('analisa.add-sku') }}', {
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
              this.showAddSkuModal = false;
              this.tab = target;
              await this.load();
              Swal.fire({ icon: 'success', title: 'Berhasil', text: j.message, timer: 1600, showConfirmButton: false });
            } else {
              Swal.fire({ icon: 'error', title: 'Gagal', text: j.message || 'Gagal menambahkan SKU ke Analisa.', confirmButtonColor: '#111111' });
            }
          } catch (e) {
            Swal.fire({ icon: 'error', title: 'Kesalahan Sistem', text: 'Tidak dapat terhubung ke server.', confirmButtonColor: '#111111' });
          } finally {
            this.savingAddSku = false;
          }
        },
        async quickChangeImporAbc(row, newAbcId){
          try {
            const res = await fetch('{{ route('analisa.update-manual-impor') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
              },
              body: JSON.stringify({
                produk_id: row.produk_id,
                klasifikasi_abc_id: newAbcId ? Number(newAbcId) : null
              })
            });
            const j = await res.json();
            if (res.ok && j.success) {
              await this.load();
            } else {
              Swal.fire({ icon: 'error', title: 'Gagal', text: j.message || 'Gagal memperbarui Klasifikasi ABC.', confirmButtonColor: '#111111' });
              await this.load();
            }
          } catch (e) {
            Swal.fire({ icon: 'error', title: 'Kesalahan Sistem', text: 'Tidak dapat terhubung ke server.', confirmButtonColor: '#111111' });
          }
        },
        simulasiImporJumlahHari(){
          const s = this.manualImporForm.periode_mulai;
          const e = this.manualImporForm.periode_akhir;
          if (s && e) {
            const d1 = new Date(s);
            const d2 = new Date(e);
            const diff = Math.round((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
            if (diff > 0) return diff;
          }
          return Number(this.manualImporForm.jumlah_hari_periode) || 122;
        },
        simulasiImporTambahanBuffer(){
          if (this.manualImporForm.klasifikasi_abc_id) {
            const found = this.klasifikasiAbcList.find(x => x.id == this.manualImporForm.klasifikasi_abc_id);
            if (found) return Number(found.tambahan_buffer_hari) || 0;
          }
          const code = String(this.manualImporForm.klasifikasi_abc || 'c').toLowerCase();
          if (code === 'wajib_a' || code === 'a') return 4;
          if (code === 'b') return 2;
          return 0;
        },
        simulasiImporAdu(){
          const out = Number(this.manualImporForm.out) || 0;
          const days = this.simulasiImporJumlahHari();
          let base = days > 0 ? (out / days) : 0;

          return this.formatNumber(base);
        },
        simulasiImporAduEtaRaw(){
          const out = Number(this.manualImporForm.out) || 0;
          const days = this.simulasiImporJumlahHari();
          // ADU murni per produk (tidak bergantung pada supplier / lead time)
          return days > 0 ? (out / days) : 0;
        },
        simulasiImporAduEta(){
          return this.formatNumber(this.simulasiImporAduEtaRaw());
        },
        simulasiImporSafetyStock(){
          const aduEta = this.simulasiImporAduEtaRaw();
          const avgLt = Number(this.manualImporForm.lead_time_average) || 0;
          const maxLt = Number(this.manualImporForm.lead_time_max) || 0;
          const bufferDays = Math.max(0, maxLt - avgLt) + this.simulasiImporTambahanBuffer();
          return this.formatNumber(Math.round(bufferDays * aduEta));
        },
        simulasiImporTarget(){
          const aduEta = this.simulasiImporAduEtaRaw();
          const avgLt = Number(this.manualImporForm.lead_time_average) || 0;
          const maxLt = Number(this.manualImporForm.lead_time_max) || 0;
          const rp = Number(this.manualImporForm.review_period) || 30;
          const bufferDays = Math.max(0, maxLt - avgLt) + this.simulasiImporTambahanBuffer();
          const target = Math.floor((aduEta * (avgLt + bufferDays + rp)) + 0.0001);
          return this.formatNumber(target);
        },
        simulasiImporProyeksiRaw(){
          const aduEta = this.simulasiImporAduEtaRaw();
          const avgLt = Number(this.manualImporForm.lead_time_average) || 0;
          const minStock = Math.round(aduEta * avgLt);
          let stok = Number(this.manualImporForm.stok_saat_ini) || 0;
          let inbound = Number(this.manualImporForm.inbound_before_eta) || 0;
          
          return Math.round(stok + inbound - minStock);
        },
        simulasiImporProyeksi(){
          return this.formatNumber(this.simulasiImporProyeksiRaw());
        },
        simulasiImporOrder(){
          const proyeksi = this.simulasiImporProyeksiRaw();
          const aduEta = this.simulasiImporAduEtaRaw();
          const avgLt = Number(this.manualImporForm.lead_time_average) || 0;
          const maxLt = Number(this.manualImporForm.lead_time_max) || 0;
          const rp = Number(this.manualImporForm.review_period) || 30;
          const bufferDays = Math.max(0, maxLt - avgLt) + this.simulasiImporTambahanBuffer();
          const target = Math.floor((aduEta * (avgLt + bufferDays + rp)) + 0.0001);

          const selisih = Math.round(proyeksi - target);
          if (selisih >= 0) {
            return '0 ' + (this.manualImporForm.satuan || '');
          }
          const rawOrder = Math.abs(selisih);
          const moq = Math.round(Number(this.manualImporForm.satuan_order_moq) || 1);
          const roundedOrder = moq > 1 ? Math.ceil(rawOrder / moq) * moq : Math.ceil(rawOrder);
          return this.formatNumber(Math.round(roundedOrder)) + ' ' + (this.manualImporForm.satuan || '');
        },
        async saveManualImpor(){
          this.savingManualImpor = true;
          try {
            const payload = {
              produk_id: this.manualImporForm.produk_id,
              lead_time_average: Number(this.manualImporForm.lead_time_average) || 0,
              lead_time_max: Number(this.manualImporForm.lead_time_max) || 0,
              periode_mulai: this.manualImporForm.periode_mulai,
              periode_akhir: this.manualImporForm.periode_akhir,
              out: Number(this.manualImporForm.out) || 0,
              review_period: Number(this.manualImporForm.review_period) || 30,
              klasifikasi_abc: this.manualImporForm.klasifikasi_abc_id ? (this.manualImporForm.klasifikasi_abc || null) : null,
              klasifikasi_abc_id: this.manualImporForm.klasifikasi_abc_id ? Number(this.manualImporForm.klasifikasi_abc_id) : null,
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
                text: 'Parameter manual bahan impor berhasil diperbarui & analisa dihitung ulang (PRD v2.2).',
                confirmButtonColor: '#111111'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan',
                text: j.message || 'Terjadi kesalahan saat menyimpan data manual.',
                confirmButtonColor: '#111111'
              });
            }
          } catch(e) {
            Swal.fire({
              icon: 'error',
              title: 'Kesalahan Sistem',
              text: 'Tidak dapat terhubung ke server.',
              confirmButtonColor: '#111111'
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
                confirmButtonColor: '#111111'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Generate Gagal',
                text: j.message || 'Terjadi kesalahan saat memproses data.',
                confirmButtonColor: '#111111'
              });
            }
          } catch(e) {
            Swal.fire({
              icon: 'error',
              title: 'Kesalahan Sistem',
              text: 'Tidak dapat terhubung ke server.',
              confirmButtonColor: '#111111'
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
                confirmButtonColor: '#111111'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Generate Gagal',
                text: j.message || 'Terjadi kesalahan saat memproses data.',
                confirmButtonColor: '#111111'
              });
            }
          } catch(e) {
            Swal.fire({
              icon: 'error',
              title: 'Kesalahan Sistem',
              text: 'Tidak dapat terhubung ke server.',
              confirmButtonColor: '#111111'
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
                confirmButtonColor: '#111111'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Checking Gagal',
                text: j.message || 'Terjadi kesalahan saat memeriksa data.',
                confirmButtonColor: '#111111'
              });
            }
          } catch(e) {
            Swal.fire({
              icon: 'error',
              title: 'Kesalahan Sistem',
              text: 'Tidak dapat terhubung ke server.',
              confirmButtonColor: '#111111'
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
                confirmButtonColor: '#111111'
              });
              await this.load();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Checking Gagal',
                text: j.message || 'Terjadi kesalahan saat memeriksa data.',
                confirmButtonColor: '#111111'
              });
            }
          } catch(e) {
            Swal.fire({
              icon: 'error',
              title: 'Kesalahan Sistem',
              text: 'Tidak dapat terhubung ke server.',
              confirmButtonColor: '#111111'
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
            confirmButtonColor: '#111111',
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
                confirmButtonColor: '#111111'
              }).then(() => {
                this.tab = 'riwayat';
                this.load();
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal Finalisasi',
                text: j.message || 'Tidak dapat memfinalisasi analisa.',
                confirmButtonColor: '#111111'
              });
            }
          } catch(e) {
            Swal.fire({
              icon: 'error',
              title: 'Terjadi Kesalahan',
              text: 'Koneksi ke server terganggu.',
              confirmButtonColor: '#111111'
            });
          }
        },
        async load(){
          this.loading = true;
          try {
            if (this.klasifikasiAbcList.length === 0) {
              fetch('{{ route('klasifikasi-abc.options') }}')
                .then(r => r.json())
                .then(d => {
                  if (d.success) this.klasifikasiAbcList = d.data || [];
                }).catch(() => {});
            }

            const res = await fetch(this.urls[this.tab]);
            const j = await res.json();
            this.rows = j.data || [];
            this.tables = j.tables || { rekomendasi: [], analisa: [], lead_time: [], stages: [] };
            if (j.meta) {
              this.meta = j.meta;
            }
            // Auto-check items with status 'order' / 'po'
            if (this.tab === 'lokal') {
              this.selectedItemIds = this.rows.filter(r => r.status === 'order').map(r => r.id);
            } else if (this.tab === 'impor') {
              this.selectedItemIds = this.rows.filter(r => r.status === 'po').map(r => r.id);
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
              <th class="py-3 px-4 text-[13px] font-medium text-[#667085] dark:text-gray-400 bg-[#f8fafb] dark:bg-gray-800/95 border-b border-[#E8E8E8] dark:border-gray-800 ${c[2]}">
                ${c[1]}
              </th>
            `).join('');
          }

          if (abody) {
            const list = this.paginatedRows();
            if (list.length === 0) {
              abody.innerHTML = '<tr><td colspan="' + cols.length + '" class="py-8 text-center text-[#667085] dark:text-gray-400 text-xs">Tidak ada data analisa yang cocok dengan filter atau pencarian.</td></tr>';
              return;
            }

            abody.innerHTML = list.map((r, idx) => {
              const bg = idx % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-[#fafafa] dark:bg-gray-900/60';
              return `<tr class="${bg} hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors">` + cols.map(c => {
                const val = r[c[0]];
                const colKey = c[0];
                const align = c[2];

                if (colKey === 'status') {
                  return `<td class="px-4 py-3 text-sm ${align}">${this.badge(val)}</td>`;
                }

                if (colKey === 'sku') {
                  return `<td class="px-4 py-3 text-xs tabular-nums text-[#101828] dark:text-white ${align}">${val ?? '-'}</td>`;
                }

                if (colKey === 'nama' || colKey === 'item_label') {
                  return `<td class="px-4 py-3 text-sm font-medium text-[#101828] dark:text-white ${align}">${val ?? '-'}</td>`;
                }

                if (align === 'text-right') {
                  return `<td class="px-4 py-3 text-sm tabular-nums text-[#101828] dark:text-gray-200 ${align}">${this.fmt(val, colKey)}</td>`;
                }

                return `<td class="px-4 py-3 text-sm text-[#667085] dark:text-gray-300 ${align}">${this.fmt(val, colKey)}</td>`;
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

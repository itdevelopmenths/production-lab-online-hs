<x-app-layout title="Stock Opname Fisik">
    <div class="w-full space-y-4">
        <x-page-header
            title="Stock Opname Fisik"
            :breadcrumbs="['Inventori' => null, 'Stok & Mutasi' => route('stok.index'), 'Stock Opname' => null]"
            :backUrl="route('stok.index')"
            backSize="xs"
        />

            <!-- Info Guidance Box -->
            <x-callout variant="info" title="Mekanisme Penyesuaian Saldo Absolut">
                Nilai kuantitas fisik yang Anda masukkan akan langsung ditetapkan sebagai saldo akhir saat ini di gudang terpilih. Sistem akan secara otomatis menghitung selisih deviasi dan membukukannya ke dalam log kartu stok.
            </x-callout>

            <form method="POST" action="{{ route('stok.opname.store') }}"
                  x-data="opnameForm({
                      initialProdukId: '{{ $selectedProdukId ?? old('produk_id', '') }}',
                      initialGudangId: '{{ $selectedGudangId ?? old('gudang_id', '') }}',
                      initialQty: '{{ old('qty_fisik', '') }}',
                      initialUom: '{{ $selectedProdukId ? ($produk->firstWhere('id', $selectedProdukId)?->satuan ?? '') : '' }}'
                  })"
                  @product-selected="onProductSelected($event.detail)"
            >
                @csrf
                <x-card title="Formulir Hasil Hitung Fisik" subtitle="Lengkapi spesifikasi komoditas, lokasi penyimpanan, dan kuantitas hasil opname" :noPadding="true">
                    <x-slot:icon>
                        <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </x-slot:icon>

                    <div class="bg-[#F5F5F5] dark:bg-gray-950 p-4 sm:p-5 space-y-4">
                        <x-form-section title="Identifikasi Produk & Lokasi Gudang" subtitle="Pilih komoditas serta gudang target untuk memuat saldo buku sistem">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-form-field name="produk_id" label="Produk / Komoditas" :required="true" help="Ketik SKU atau nama produk untuk mencari" class="relative z-30">
                                    <x-product-search-select
                                        name="produk_id"
                                        variant="seamless"
                                        :selectedId="old('produk_id', $selectedProdukId ?? null)"
                                        placeholder="— Cari & Pilih Produk / Bahan —"
                                        :required="true"
                                    />
                                </x-form-field>

                                <x-form-field name="gudang_id" label="Lokasi Gudang" :required="true" help="Fasilitas gudang tempat stok berada">
                                    <x-select name="gudang_id" x-model="gudangId" @change="onGudangChanged()" variant="seamless" required>
                                        <option value="">— Pilih Lokasi Gudang —</option>
                                        @foreach($gudang as $g)
                                            <option value="{{ $g->id }}" @selected(($selectedGudangId ?? old('gudang_id')) == $g->id)>
                                                {{ $g->nama }} {{ isset($g->kode) ? '('.$g->kode.')' : '' }}
                                            </option>
                                        @endforeach
                                    </x-select>
                                </x-form-field>
                            </div>
                        </x-form-section>

                        <!-- Status Saldo Sistem & Estimasi Deviasi KPI Cards -->
                        <template x-if="produkId && gudangId">
                            <div class="p-4 bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-gray-800 rounded-[12px] shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-medium text-[#101828] dark:text-white flex items-center gap-1.5">
                                        <svg class="size-4 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                        Monitoring Saldo Sistem & Estimasi Selisih
                                    </span>
                                    <span x-show="loading" class="text-[11px] text-[#667085] dark:text-gray-400 font-medium flex items-center gap-1.5">
                                        <svg class="animate-spin size-3.5 text-[#111111] dark:text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                        Memeriksa stok sistem...
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 pt-1">
                                    <!-- Card 1: Stok di Sistem -->
                                    <div class="p-3.5 bg-[#F5F5F5] dark:bg-gray-950 rounded-xl border border-[#E8E8E8] dark:border-gray-800">
                                        <div class="text-[11px] font-medium text-[#667085] dark:text-gray-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <span class="inline-block size-2 rounded-full bg-[#111111] dark:bg-white"></span>
                                            Stok di Sistem
                                        </div>
                                        <div class="mt-1.5 flex items-baseline gap-1.5">
                                            <span class="text-xl font-medium tabular-nums text-[#101828] dark:text-white" x-text="hasFetched ? formatQty(systemQty) : '—'"></span>
                                            <span class="text-xs tabular-nums text-[#667085] dark:text-gray-400 font-medium uppercase" x-text="uom || 'Unit'"></span>
                                        </div>
                                        <div class="text-[10px] text-[#667085] dark:text-gray-400 mt-0.5">Saldo aktif tercatat sebelum opname</div>
                                    </div>

                                    <!-- Card 2: Fisik Diinput -->
                                    <div class="p-3.5 bg-[#F5F5F5] dark:bg-gray-950 rounded-xl border border-[#E8E8E8] dark:border-gray-800">
                                        <div class="text-[11px] font-medium text-[#667085] dark:text-gray-400 uppercase tracking-wider">Fisik Diinput</div>
                                        <div class="mt-1.5 flex items-baseline gap-1.5">
                                            <span class="text-xl font-medium tabular-nums text-[#101828] dark:text-white" x-text="(physicalQty !== '' && physicalQty !== null) ? formatQty(physicalQty) : '—'"></span>
                                            <span class="text-xs tabular-nums text-[#667085] dark:text-gray-400 font-medium uppercase" x-text="uom || 'Unit'"></span>
                                        </div>
                                        <div class="text-[10px] text-[#667085] dark:text-gray-400 mt-0.5">Hasil hitung aktual yang akan diset</div>
                                    </div>

                                    <!-- Card 3: Estimasi Deviasi / Selisih -->
                                    <div class="p-3.5 bg-[#F5F5F5] dark:bg-gray-950 rounded-xl border border-[#E8E8E8] dark:border-gray-800 transition-colors">
                                        <div class="text-[11px] font-medium text-[#667085] dark:text-gray-400 uppercase tracking-wider">Estimasi Deviasi</div>
                                        <div class="mt-1.5 flex items-baseline gap-1.5">
                                            <span class="text-xl font-medium tabular-nums"
                                                  :class="varianceClass()"
                                                  x-text="varianceText()"
                                            ></span>
                                            <span class="text-xs tabular-nums text-[#667085] dark:text-gray-400 font-medium uppercase" x-text="uom || 'Unit'"></span>
                                        </div>
                                        <div class="text-[10px] mt-0.5 font-medium">
                                            <template x-if="hasFetched && variance() !== null">
                                                <span>
                                                    <span x-show="varianceStatus() === 'zero'" class="text-foreground dark:text-foreground font-medium">Saldo Fisik Sesuai Buku</span>
                                                    <span x-show="varianceStatus() === 'surplus'" class="text-[#101828] dark:text-white font-medium">Surplus / Fisik Lebih Banyak</span>
                                                    <span x-show="varianceStatus() === 'deficit'" class="text-rose-600 dark:text-rose-400 font-medium">Defisit / Fisik Kurang</span>
                                                </span>
                                            </template>
                                            <template x-if="!hasFetched || variance() === null">
                                                <span class="text-[#667085] dark:text-gray-400">Menunggu input kuantitas fisik</span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template x-if="!produkId || !gudangId">
                            <div class="p-4 bg-white dark:bg-gray-900 border border-dashed border-[#E8E8E8] dark:border-gray-700 rounded-[12px] text-center text-xs text-[#667085] dark:text-gray-400">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-[#667085] dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Pilih komoditas produk dan lokasi gudang di atas untuk memuat dan menampilkan saldo sistem saat ini.
                                </span>
                            </div>
                        </template>

                        <x-form-section title="Hasil Hitung Aktual & Justifikasi" subtitle="Masukkan volume fisik di rak dan alasan bila terdapat selisih">
                            <div class="space-y-4">
                                <x-form-field name="qty_fisik" label="Kuantitas Fisik Aktual" :required="true" help="Jumlah total hasil stock counting fisik di rak/lokasi penyimpanan">
                                    <x-input type="number" step="0.01" min="0" name="qty_fisik" x-model="physicalQty" variant="seamless" value="{{ old('qty_fisik') }}" placeholder="0.00" :required="true" class="pr-20 tabular-nums">
                                        <span class="absolute right-2.5 inset-y-0 flex items-center pointer-events-none">
                                            <span class="px-2 py-0.5 text-[11px] tabular-nums font-medium text-[#101828] dark:text-white bg-gray-100 dark:bg-gray-800 border border-[#E8E8E8] dark:border-gray-700 rounded-md uppercase tracking-wider" x-text="uom || 'Unit'"></span>
                                        </span>
                                    </x-input>
                                    <div x-show="hasFetched" class="mt-1.5 text-[11px] text-[#667085] dark:text-gray-400 flex items-center gap-1 font-medium">
                                        <span>Saldo sistem saat ini:</span>
                                        <span class="tabular-nums font-medium text-[#101828] dark:text-white" x-text="formatQty(systemQty) + ' ' + (uom || 'Unit')"></span>
                                        <span>— masukkan hasil hitung fisik di atas untuk mencatat selisih.</span>
                                    </div>
                                </x-form-field>

                                <x-form-field name="catatan" label="Catatan / Justifikasi Selisih" help="Alasan penyesuaian (misal: kebocoran kemasan, sampling QC lab, deviasi timbangan)">
                                    <x-input name="catatan" variant="seamless" value="{{ old('catatan') }}" placeholder="misal: Hasil audit fisik berkala Q1 atau susut penguapan..." />
                                </x-form-field>
                            </div>
                        </x-form-section>
                    </div>

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2.5">
                            <x-button href="{{ route('stok.index') }}" variant="secondary" size="sm">
                                Batal
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm">
                                <x-slot:icon>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </x-slot:icon>
                                Simpan Stock Opname
                            </x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>

    @push('scripts')
    <script>
    function opnameForm(config) {
        return {
            produkId: config.initialProdukId || '',
            gudangId: config.initialGudangId || '',
            physicalQty: config.initialQty || '',
            uom: config.initialUom || '',
            systemQty: null,
            hasFetched: false,
            loading: false,

            init() {
                if (this.produkId && this.gudangId) {
                    this.fetchCurrentStock();
                }
            },

            onProductSelected(item) {
                if (item) {
                    this.produkId = item.id;
                    this.uom = item.satuan || 'Unit';
                    this.fetchCurrentStock();
                } else {
                    this.produkId = '';
                    this.uom = '';
                    this.systemQty = null;
                    this.hasFetched = false;
                }
            },

            onGudangChanged() {
                this.fetchCurrentStock();
            },

            fetchCurrentStock() {
                if (!this.produkId || !this.gudangId) {
                    this.systemQty = null;
                    this.hasFetched = false;
                    return;
                }

                this.loading = true;
                fetch(`{{ route('stok.current-stock') }}?produk_id=${this.produkId}&gudang_id=${this.gudangId}`)
                    .then(res => res.json())
                    .then(data => {
                        this.loading = false;
                        this.systemQty = Number(data.qty);
                        this.hasFetched = true;
                    })
                    .catch(() => {
                        this.loading = false;
                    });
            },

            variance() {
                if (this.physicalQty === '' || this.physicalQty === null || isNaN(Number(this.physicalQty))) {
                    return null;
                }
                const sys = this.systemQty !== null ? this.systemQty : 0;
                return Number(this.physicalQty) - sys;
            },

            varianceStatus() {
                const v = this.variance();
                if (!this.hasFetched || v === null) return 'none';
                if (v === 0) return 'zero';
                return v > 0 ? 'surplus' : 'deficit';
            },

            varianceClass() {
                const status = this.varianceStatus();
                if (status === 'zero') return 'text-foreground dark:text-foreground';
                if (status === 'surplus') return 'text-foreground';
                if (status === 'deficit') return 'text-destructive';
                return 'text-muted-foreground';
            },

            varianceText() {
                const v = this.variance();
                if (!this.hasFetched || v === null) return '—';
                return (v > 0 ? '+' : '') + this.formatQty(v);
            },

            formatQty(val) {
                if (val === null || val === undefined || isNaN(Number(val))) return '0';
                const num = Number(val);
                if (Math.floor(num) === num) {
                    return num.toLocaleString('id-ID');
                }
                return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        };
    }
    </script>
    @endpush
</x-app-layout>

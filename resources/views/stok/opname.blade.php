<x-app-layout title="Stock Opname Fisik">
    <x-page-header
        title="Stock Opname Fisik"
        subtitle="Penyesuaian kuantitas fisik aktual di gudang; selisih penyesuaian akan otomatis dicatatkan ke kartu stok"
        :breadcrumbs="['Inventori' => null, 'Stok & Mutasi' => route('stok.index'), 'Stock Opname' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('stok.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Buku Stok
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="max-w-4xl space-y-4">
        <!-- Info Guidance Box -->
        <div class="bg-blue-50/75 border-l-4 border-primary-600 p-3.5 rounded-r-sm text-xs text-gray-700">
            <div class="flex items-start gap-2.5">
                <svg class="w-4 h-4 text-primary-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="space-y-1">
                    <p class="font-semibold text-gray-900">Mekanisme Penyesuaian Saldo Absolut:</p>
                    <p class="text-gray-600 leading-relaxed">
                        Nilai kuantitas fisik yang Anda masukkan akan langsung ditetapkan sebagai saldo akhir saat ini di gudang terpilih. Sistem akan secara otomatis menghitung selisih deviasi (positif atau negatif) dan membukukannya ke dalam log kartu stok.
                    </p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('stok.opname.store') }}"
              x-data="opnameForm({
                  initialProdukId: '{{ $selectedProdukId ?? old('produk_id', '') }}',
                  initialGudangId: '{{ $selectedGudangId ?? old('gudang_id', '') }}',
                  initialQty: '{{ old('qty_fisik', '') }}'
              })"
              @product-selected="onProductSelected($event.detail)"
        >
            @csrf
            <x-card title="Formulir Hasil Hitung Fisik" subtitle="Lengkapi spesifikasi komoditas, lokasi penyimpanan, dan kuantitas hasil opname" variant="primary">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-form-group name="produk_id" label="Produk / Komoditas" :required="true" help="Ketik SKU atau nama produk untuk mencari (paginasi 10 item)" class="relative z-30">
                        <x-product-search-select
                            name="produk_id"
                            :selectedId="old('produk_id', $selectedProdukId ?? null)"
                            placeholder="— Cari & Pilih Produk / Bahan —"
                            :required="true"
                        />
                    </x-form-group>

                    <x-form-group name="gudang_id" label="Lokasi Gudang" :required="true" help="Fasilitas gudang tempat stok berada">
                        <select name="gudang_id" x-model="gudangId" @change="onGudangChanged()" class="w-full rounded-sm border-gray-300 text-xs py-2 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" required>
                            <option value="">— Pilih Lokasi Gudang —</option>
                            @foreach($gudang as $g)
                                <option value="{{ $g->id }}" @selected(($selectedGudangId ?? old('gudang_id')) == $g->id)>
                                    {{ $g->nama }} {{ isset($g->kode) ? '('.$g->kode.')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </x-form-group>

                    <!-- Status Saldo Sistem & Estimasi Deviasi KPI Cards -->
                    <div class="md:col-span-2">
                        <template x-if="produkId && gudangId">
                            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-sm">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                        Monitoring Saldo Sistem & Estimasi Selisih
                                    </span>
                                    <span x-show="loading" class="text-[11px] text-primary-600 font-medium flex items-center gap-1">
                                        <svg class="animate-spin h-3.5 w-3.5 text-primary-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                        Memeriksa stok sistem...
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                                    <!-- Card 1: Stok di Sistem -->
                                    <div class="p-3 bg-white rounded-sm border border-gray-200 shadow-xs">
                                        <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Stok di Sistem</div>
                                        <div class="mt-1 flex items-baseline gap-1.5">
                                            <span class="text-lg font-bold font-mono text-gray-900" x-text="hasFetched ? formatQty(systemQty) : '—'"></span>
                                            <span class="text-xs font-mono text-gray-500 font-semibold uppercase" x-text="uom || 'Unit'"></span>
                                        </div>
                                        <div class="text-[10px] text-gray-400 mt-0.5">Saldo tercatat sebelum opname</div>
                                    </div>

                                    <!-- Card 2: Fisik Diinput -->
                                    <div class="p-3 bg-white rounded-sm border border-gray-200 shadow-xs">
                                        <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Fisik Diinput</div>
                                        <div class="mt-1 flex items-baseline gap-1.5">
                                            <span class="text-lg font-bold font-mono text-blue-600" x-text="(physicalQty !== '' && physicalQty !== null) ? formatQty(physicalQty) : '—'"></span>
                                            <span class="text-xs font-mono text-gray-500 font-semibold uppercase" x-text="uom || 'Unit'"></span>
                                        </div>
                                        <div class="text-[10px] text-gray-400 mt-0.5">Hasil hitung aktual yang akan diset</div>
                                    </div>

                                    <!-- Card 3: Estimasi Deviasi / Selisih -->
                                    <div class="p-3 bg-white rounded-sm border shadow-xs transition-colors"
                                         :class="{
                                             'border-emerald-200 bg-emerald-50/50': hasFetched && variance() !== null && variance() === 0,
                                             'border-blue-200 bg-blue-50/50': hasFetched && variance() !== null && variance() > 0,
                                             'border-rose-200 bg-rose-50/50': hasFetched && variance() !== null && variance() < 0,
                                             'border-gray-200': !hasFetched || variance() === null
                                         }"
                                    >
                                        <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Estimasi Deviasi</div>
                                        <div class="mt-1 flex items-baseline gap-1.5">
                                            <span class="text-lg font-bold font-mono"
                                                  :class="{
                                                      'text-emerald-700': hasFetched && variance() !== null && variance() === 0,
                                                      'text-blue-700': hasFetched && variance() !== null && variance() > 0,
                                                      'text-rose-700': hasFetched && variance() !== null && variance() < 0,
                                                      'text-gray-400': !hasFetched || variance() === null
                                                  }"
                                                  x-text="hasFetched && variance() !== null ? (variance() > 0 ? '+' : '') + formatQty(variance()) : '—'"
                                            ></span>
                                            <span class="text-xs font-mono text-gray-500 font-semibold uppercase" x-text="uom || 'Unit'"></span>
                                        </div>
                                        <div class="text-[10px] mt-0.5 font-medium">
                                            <template x-if="hasFetched && variance() !== null">
                                                <span>
                                                    <span x-show="variance() === 0" class="text-emerald-700 font-semibold">Saldo Fisik Sesuai Buku</span>
                                                    <span x-show="variance() > 0" class="text-blue-700 font-semibold">Surplus / Fisik Lebih Banyak</span>
                                                    <span x-show="variance() < 0" class="text-rose-700 font-semibold">Defisit / Fisik Kurang</span>
                                                </span>
                                            </template>
                                            <template x-if="!hasFetched || variance() === null">
                                                <span class="text-gray-400">Menunggu input kuantitas fisik</span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template x-if="!produkId || !gudangId">
                            <div class="p-3 bg-gray-50 border border-dashed border-gray-300 rounded-sm text-center text-xs text-gray-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Pilih komoditas produk dan lokasi gudang di atas untuk menampilkan saldo sistem saat ini.
                                </span>
                            </div>
                        </template>
                    </div>

                    <div class="md:col-span-2">
                        <x-form-group name="qty_fisik" label="Kuantitas Fisik Aktual" :required="true" help="Jumlah total hasil stock counting fisik di rak/lokasi penyimpanan">
                            <x-input type="number" step="0.01" min="0" name="qty_fisik" x-model="physicalQty" value="{{ old('qty_fisik') }}" placeholder="0.00" :required="true" class="pr-20 font-mono">
                                <span class="absolute right-2.5 inset-y-0 flex items-center pointer-events-none">
                                    <span class="px-2 py-0.5 text-[11px] font-mono font-bold text-primary-700 bg-primary-50 border border-primary-200 rounded-xs uppercase tracking-wider" x-text="uom || 'Unit'"></span>
                                </span>
                            </x-input>
                        </x-form-group>
                    </div>

                    <div class="md:col-span-2">
                        <x-form-group name="catatan" label="Catatan / Justifikasi Selisih" help="Alasan penyesuaian (misal: kebocoran kemasan, sampling QC lab, deviasi timbangan)">
                            <x-input name="catatan" value="{{ old('catatan') }}" placeholder="misal: Hasil audit fisik berkala Q1 atau susut penguapan..." />
                        </x-form-group>
                    </div>
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
            uom: '',
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

<x-app-layout title="Stok & Mutasi">
    <div x-data="{ openMutasi: false }">
        <x-page-header
            title="Stok & Mutasi"
            :breadcrumbs="['Stok & Mutasi' => null]"
        >
            <x-slot:actions>
                @can('stok.opname')
                <x-button href="{{ route('stok.opname') }}" variant="secondary" size="xs">
                    Opname Fisik
                </x-button>
                @endcan
                @can('stok.mutasi')
                <x-button @click="openMutasi = true" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Mutasi Manual
                </x-button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <div class="mb-3 flex flex-wrap items-center justify-between gap-2.5 bg-card px-3.5 py-2 rounded-lg border border-border shadow-2xs">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <label for="filter-gudang" class="block text-[11px] font-medium text-muted-foreground mb-0.5">Lokasi Gudang</label>
                    <select id="filter-gudang" autocomplete="off" class="h-8 rounded-md border border-input bg-background text-xs py-1 px-2.5 focus:ring-1 focus:ring-primary/20 text-foreground w-full sm:w-auto min-w-44 max-w-full outline-none cursor-pointer">
                        @if($isGlobal)
                            <option value="">All Gudang</option>
                        @elseif(($gudangTypeOptions ?? collect())->count() > 1)
                            <option value="">All Gudang (Sesuai Akses)</option>
                        @endif
                        @if(($gudangTypeOptions ?? collect())->isNotEmpty())
                            <optgroup label="Sub Jenis Gudang">
                                @foreach($gudangTypeOptions as $typeOption)
                                    <option value="{{ $typeOption['value'] }}">{{ $typeOption['label'] }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        <optgroup label="Gudang Spesifik">
                            @foreach($gudang as $g)
                                <option value="{{ $g->id }}" @selected((string) ($defaultFilterGudangId ?? '') === (string) $g->id)>{{ $g->nama }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label for="filter-kategori" class="block text-[11px] font-medium text-muted-foreground mb-0.5">Kategori Barang</label>
                    <select id="filter-kategori" class="h-8 rounded-md border border-input bg-background text-xs py-1 px-2.5 focus:ring-1 focus:ring-primary/20 text-foreground w-full sm:w-auto min-w-36 max-w-full outline-none cursor-pointer">
                        <option value="">— Semua Kategori —</option>
                        <option value="bahan">Bahan Baku & Kemas</option>
                        <option value="produk_jadi">Produk Jadi</option>
                    </select>
                </div>
            </div>
            <div class="text-right">
                <span class="text-xs text-muted-foreground font-medium">Buku Saldo & Evaluasi Stok</span>
            </div>
        </div>

        <x-card title="Buku Saldo & Alert Ketersediaan Stok" subtitle="Status dihitung dari (Stok Aktual + Stok Berjalan) − Batas Min (Analisa)" :noPadding="true">
            <div tabindex="0" role="region" aria-label="Tabel Buku Saldo & Status Ketersediaan Stok" class="overflow-x-auto">
                <table id="tbl" class="w-full text-sm">
                    <thead>
                        <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[13px] font-medium">
                            <th scope="col" class="px-4 py-3 text-left">SKU</th>
                            <th scope="col" class="px-4 py-3 text-left">Produk</th>
                            <th scope="col" class="px-4 py-3 text-left">Gudang</th>
                            <th scope="col" class="px-4 py-3 text-right">Batas Min (Analisa)</th>
                            <th scope="col" class="px-4 py-3 text-right">Stok Aktual</th>
                            <th scope="col" class="px-4 py-3 text-right">Stok Berjalan</th>
                            <th scope="col" class="px-4 py-3 text-center">Status</th>
                            @if($canSeePrice)
                                <th scope="col" class="px-4 py-3 text-right">HPP</th>
                                <th scope="col" class="px-4 py-3 text-right">Nilai Stok</th>
                            @endif
                            <th scope="col" class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </x-card>

        {{-- Modal Mutasi Manual (AdminLTE Sharp Modal) --}}
        @can('stok.mutasi')
        <script>
            function stokMutasiForm(defaultGudangId, currentStockUrl) {
                return {
                    produkId: '',
                    gudangId: defaultGudangId || '',
                    mutasiUom: '',
                    tipe: 'in',
                    qty: '',
                    systemQty: null,
                    hasFetched: false,
                    loading: false,
                    onProductSelected(detail) {
                        if (detail) {
                            this.produkId = detail.id;
                            this.mutasiUom = detail.satuan || 'Unit';
                            this.fetchStock();
                        } else {
                            this.produkId = '';
                            this.mutasiUom = '';
                            this.systemQty = null;
                            this.hasFetched = false;
                        }
                    },
                    fetchStock() {
                        if (!this.produkId || !this.gudangId) {
                            this.systemQty = null;
                            this.hasFetched = false;
                            return;
                        }
                        this.loading = true;
                        var url = currentStockUrl + '?produk_id=' + encodeURIComponent(this.produkId) + '&gudang_id=' + encodeURIComponent(this.gudangId);
                        var self = this;
                        fetch(url)
                            .then(function(res) { return res.json(); })
                            .then(function(data) {
                                self.loading = false;
                                self.systemQty = Number(data.qty);
                                self.hasFetched = true;
                            })
                            .catch(function() {
                                self.loading = false;
                            });
                    },
                    formatNumber(val) {
                        if (val === null || val === undefined || isNaN(val)) return '—';
                        return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 4 }).format(val);
                    },
                    newEstimatedStock() {
                        if (this.systemQty === null) return null;
                        const q = parseFloat(this.qty) || 0;
                        return this.tipe === 'in' ? (this.systemQty + q) : (this.systemQty - q);
                    }
                };
            }
        </script>
        <x-modal name="openMutasi" title="Mutasi Stok Manual" maxWidth="md">
            @php
                $mutasiInitialGudangId = (string) old('gudang_id', $defaultMutasiGudangId ?? $gudang->first()?->id ?? '');
            @endphp
            <form method="POST" action="{{ route('stok.mutasi') }}" id="form-mutasi-manual" class="space-y-3"
                x-data="stokMutasiForm('{{ $mutasiInitialGudangId }}', '{{ route('stok.current-stock') }}')"
                @product-selected="onProductSelected($event.detail)"
            >
                @csrf
                <div class="relative z-20">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Produk / Bahan Baku <span class="text-rose-500">*</span></label>
                    <x-product-search-select
                        name="produk_id"
                        placeholder="— Cari & Pilih Produk / Bahan —"
                        :required="true"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Gudang <span class="text-rose-500">*</span></label>
                    <select name="gudang_id" x-model="gudangId" @change="fetchStock()" autocomplete="off" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" required>
                        @foreach($gudang as $g)
                            <option value="{{ $g->id }}" @selected($mutasiInitialGudangId === (string) $g->id)>{{ $g->nama }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Box Informasi Stok Tercatat di Sistem --}}
                <div x-show="produkId && gudangId" x-cloak class="p-2.5 bg-blue-50/70 border border-blue-200 rounded-sm space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-blue-900 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            Stok Saat Ini di Sistem:
                        </span>
                        <div class="flex items-center gap-1">
                            <span x-show="loading" class="text-[10px] text-blue-600 flex items-center gap-1">
                                <svg class="animate-spin h-3 w-3 text-blue-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                Memeriksa...
                            </span>
                            <span x-show="!loading && hasFetched" class="font-mono font-bold text-xs text-blue-950">
                                <span x-text="formatNumber(systemQty)"></span>
                                <span class="text-[10px] text-blue-700 uppercase" x-text="mutasiUom || 'Unit'"></span>
                            </span>
                        </div>
                    </div>
                    <div x-show="hasFetched && qty && parseFloat(qty) > 0" class="pt-1.5 border-t border-blue-200/60 flex items-center justify-between text-[11px]">
                        <span class="text-gray-600">Estimasi Stok Baru:</span>
                        <span class="font-mono font-bold text-xs" :class="newEstimatedStock() < 0 ? 'text-rose-700' : 'text-emerald-700'">
                            <span x-text="formatNumber(newEstimatedStock())"></span>
                            <span class="text-[10px] uppercase" x-text="mutasiUom || 'Unit'"></span>
                        </span>
                    </div>
                    <div x-show="hasFetched && tipe === 'out' && qty && parseFloat(qty) > systemQty" class="text-[10px] text-rose-600 font-semibold flex items-center gap-1">
                        <svg class="w-3 h-3 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Peringatan: Qty keluar melebihi stok sistem (<span x-text="formatNumber(systemQty)"></span> <span x-text="mutasiUom"></span>).
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1.5">Tipe Mutasi</label>
                        <select name="tipe" x-model="tipe" class="w-full rounded-lg border border-input bg-background text-xs py-2 px-3 text-foreground focus:border-ring focus:ring-1 focus:ring-ring shadow-xs">
                            <option value="in">Masuk (IN)</option>
                            <option value="out">Keluar (OUT)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1.5">Qty <span class="text-destructive">*</span></label>
                        <div class="relative flex items-center">
                            <input type="number" step="0.01" min="0.01" name="qty" x-model="qty" class="w-full rounded-lg border border-input bg-background text-xs py-2 px-3 pr-14 font-mono text-foreground focus:border-ring focus:ring-1 focus:ring-ring shadow-xs" placeholder="0.00" required>
                            <span class="absolute right-2 px-1.5 py-0.5 text-[10px] font-mono font-bold text-foreground bg-muted border border-border rounded-md uppercase tracking-wider pointer-events-none" x-text="mutasiUom || 'Unit'"></span>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-foreground mb-1.5">Catatan / Alasan Mutasi</label>
                    <input type="text" name="catatan" class="w-full rounded-lg border border-input bg-background text-xs py-2 px-3 text-foreground focus:border-ring focus:ring-1 focus:ring-ring shadow-xs" placeholder="Penyesuaian stok / rusak / sampel">
                </div>
            </form>

            <x-slot:footer>
                <x-button @click="openMutasi = false" variant="outline" size="sm">
                    Batal
                </x-button>
                <x-button onclick="document.getElementById('form-mutasi-manual').submit()" variant="primary" size="sm">
                    Simpan Mutasi
                </x-button>
            </x-slot:footer>
        </x-modal>
        @endcan
    </div>

    @push('scripts')
    <script>
    $(function(){
        const canSeePrice = {{ $canSeePrice ? 'true' : 'false' }};
        const cols = [
            { data: 'sku', orderable: false, searchable: true },
            { data: 'nama', orderable: false, searchable: true },
            { data: 'gudang_nama', orderable: false, searchable: true },
            { data: 'batas_minimum', className: 'text-right font-mono', orderable: false, searchable: false },
            { data: 'stok_aktual', className: 'text-right font-mono font-semibold text-foreground', orderable: false, searchable: false },
            { data: 'stok_berjalan', className: 'text-right font-mono', orderable: false, searchable: false },
            { data: 'status_stok', className: 'text-center', orderable: false, searchable: false }
        ];

        if (canSeePrice) {
            cols.push({ data: 'hpp', className: 'text-right font-mono text-gray-700', orderable: false, searchable: false });
            cols.push({ data: 'nilai_stok', className: 'text-right font-mono font-bold text-gray-900', orderable: false, searchable: false });
        }

        cols.push({ data: 'action', className: 'text-center', orderable: false, searchable: false });

        const tbl = $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("stok.data") }}',
                data: function(d) {
                    d.gudang_id = $('#filter-gudang').val();
                    d.kategori = $('#filter-kategori').val();
                }
            },
            columns: cols
        });

        $('#filter-gudang, #filter-kategori').on('change', function(){
            tbl.ajax.reload();
        });
    });
    </script>
    @endpush
</x-app-layout>

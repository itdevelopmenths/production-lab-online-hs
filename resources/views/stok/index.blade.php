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

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-gray-900 px-4 py-3 rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <label for="filter-gudang" class="block text-[11px] font-medium text-[#667085] dark:text-gray-400 mb-1">Lokasi Gudang</label>
                    <select id="filter-gudang" autocomplete="off" class="h-9 rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-44 max-w-full outline-none cursor-pointer">
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
                    <label for="filter-kategori" class="block text-[11px] font-medium text-[#667085] dark:text-gray-400 mb-1">Kategori Barang</label>
                    <select id="filter-kategori" class="h-9 rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-36 max-w-full outline-none cursor-pointer">
                        <option value="">— Semua Kategori —</option>
                        <option value="bahan">Bahan Baku & Kemas</option>
                        <option value="produk_jadi">Produk Jadi</option>
                    </select>
                </div>
            </div>
            <div class="text-right">
                <span class="text-xs text-[#667085] dark:text-gray-400 font-medium">Buku Saldo & Evaluasi Stok</span>
            </div>
        </div>

        <x-card title="Buku Saldo & Alert Ketersediaan Stok" subtitle="Status dihitung dari (Stok Aktual + Stok Berjalan) − Batas Min (Analisa)" :noPadding="true">
            <x-slot:icon>
                <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </x-slot:icon>
            <div tabindex="0" role="region" aria-label="Tabel Buku Saldo & Status Ketersediaan Stok" class="overflow-x-auto">
                <table id="tbl" class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95">
                        <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-[13px] font-medium">
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
            <form method="POST" action="{{ route('stok.mutasi') }}" id="form-mutasi-manual" class="space-y-4"
                x-data="stokMutasiForm('{{ $mutasiInitialGudangId }}', '{{ route('stok.current-stock') }}')"
                @product-selected="onProductSelected($event.detail)"
            >
                @csrf
                <x-form-field name="produk_id" label="Produk / Bahan Baku" :required="true" class="relative z-20">
                    <x-product-search-select
                        name="produk_id"
                        variant="seamless"
                        placeholder="— Cari & Pilih Produk / Bahan —"
                        :required="true"
                    />
                </x-form-field>

                <x-form-field name="gudang_id" label="Gudang" :required="true">
                    <x-select name="gudang_id" x-model="gudangId" @change="fetchStock()" autocomplete="off" variant="seamless" required>
                        @foreach($gudang as $g)
                            <option value="{{ $g->id }}" @selected($mutasiInitialGudangId === (string) $g->id)>{{ $g->nama }}</option>
                        @endforeach
                    </x-select>
                </x-form-field>

                {{-- Box Informasi Stok Tercatat di Sistem --}}
                <div x-show="produkId && gudangId" x-cloak class="p-3 bg-card dark:bg-card border border-border dark:border-border rounded-xl space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-medium text-foreground dark:text-foreground flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-foreground dark:text-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            Stok Saat Ini di Sistem:
                        </span>
                        <div class="flex items-center gap-1">
                            <span x-show="loading" class="text-[10px] text-foreground dark:text-foreground flex items-center gap-1">
                                <svg class="animate-spin h-3 w-3 text-foreground dark:text-foreground" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                Memeriksa...
                            </span>
                            <span x-show="!loading && hasFetched" class="tabular-nums font-medium text-xs text-foreground dark:text-foreground">
                                <span x-text="formatNumber(systemQty)"></span>
                                <span class="text-[10px] text-foreground dark:text-foreground uppercase" x-text="mutasiUom || 'Unit'"></span>
                            </span>
                        </div>
                    </div>
                    <div x-show="hasFetched && qty && parseFloat(qty) > 0" class="pt-1.5 border-t border-border dark:border-border flex items-center justify-between text-[11px]">
                        <span class="text-[#667085] dark:text-gray-400">Estimasi Stok Baru:</span>
                        <span class="tabular-nums font-medium text-xs" :class="newEstimatedStock() < 0 ? 'text-rose-700 dark:text-rose-400' : 'text-foreground dark:text-foreground'">
                            <span x-text="formatNumber(newEstimatedStock())"></span>
                            <span class="text-[10px] uppercase" x-text="mutasiUom || 'Unit'"></span>
                        </span>
                    </div>
                    <div x-show="hasFetched && tipe === 'out' && qty && parseFloat(qty) > systemQty" class="text-[10px] text-rose-600 dark:text-rose-400 font-medium flex items-center gap-1">
                        <svg class="w-3 h-3 text-rose-500 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Peringatan: Qty keluar melebihi stok sistem (<span x-text="formatNumber(systemQty)"></span> <span x-text="mutasiUom"></span>).
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3.5">
                    <x-form-field name="tipe" label="Tipe Mutasi">
                        <x-select name="tipe" x-model="tipe" variant="seamless">
                            <option value="in">Masuk (IN)</option>
                            <option value="out">Keluar (OUT)</option>
                        </x-select>
                    </x-form-field>
                    <x-form-field name="qty" label="Qty" :required="true">
                        <div class="relative flex items-center">
                            <input type="number" step="0.01" min="0.01" name="qty" x-model="qty" class="w-full h-9 rounded-lg border-0 bg-transparent text-xs py-1.5 pr-14 tabular-nums text-[#101828] dark:text-white outline-none focus:ring-0" placeholder="0.00" required>
                            <span class="absolute right-0 px-1.5 py-0.5 text-[10px] tabular-nums font-medium text-[#101828] dark:text-white bg-gray-100 dark:bg-gray-800 border border-[#E8E8E8] dark:border-gray-700 rounded-md uppercase tracking-wider pointer-events-none" x-text="mutasiUom || 'Unit'"></span>
                        </div>
                    </x-form-field>
                </div>

                <x-form-field name="catatan" label="Catatan / Alasan Mutasi">
                    <x-input type="text" name="catatan" variant="seamless" placeholder="Penyesuaian stok / rusak / sampel" />
                </x-form-field>
            </form>

            <x-slot:footer>
                <x-button @click="openMutasi = false" variant="secondary" size="sm">
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
            { data: 'batas_minimum', className: 'text-right tabular-nums', orderable: false, searchable: false },
            { data: 'stok_aktual', className: 'text-right tabular-nums font-medium text-[#101828] dark:text-white', orderable: false, searchable: false },
            { data: 'stok_berjalan', className: 'text-right tabular-nums', orderable: false, searchable: false },
            { data: 'status_stok', className: 'text-center', orderable: false, searchable: false }
        ];

        if (canSeePrice) {
            cols.push({ data: 'hpp', className: 'text-right tabular-nums text-[#667085] dark:text-gray-300', orderable: false, searchable: false });
            cols.push({ data: 'nilai_stok', className: 'text-right tabular-nums font-medium text-[#101828] dark:text-white', orderable: false, searchable: false });
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

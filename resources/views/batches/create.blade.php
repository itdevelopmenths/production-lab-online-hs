<x-app-layout title="Rencana Batch Baru">
    <div class="max-w-4xl mx-auto">
        <x-page-header
            title="Rencana Batch Baru"
            subtitle="Alokasi bahan baku otomatis dihitung dari resep (BOM) &times; kuantitas rencana"
            :breadcrumbs="['Produksi' => route('batches.index'), 'Rencana Baru' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('batches.index') }}" variant="secondary" size="xs">
                    &larr; Kembali ke Daftar
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('batches.store') }}" x-data="{ uom: '' }" @product-selected="uom = $event.detail ? $event.detail.satuan : ''">
            @csrf
            <x-card title="Parameter Rencana Produksi" subtitle="Stok fisik belum dipotong pada tahap ini, ketersediaan dipantau pada Kolom Rencana Stok" variant="primary">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-form-group name="produk_id" label="Produk Jadi" :required="true" help="Pilih produk jadi yang terdaftar pada formula BOM (paginasi 10 item)" class="relative z-30">
                        <x-product-search-select
                            name="produk_id"
                            :selectedId="old('produk_id')"
                            placeholder="— Cari & Pilih Produk Jadi (BOM) —"
                            filterTipe="produk_jadi"
                            :required="true"
                        />
                    </x-form-group>

                    <x-form-group name="qty_rencana" label="Kuantitas Target Rencana" :required="true" help="Jumlah unit yang direncanakan untuk diproduksi">
                        <x-input type="number" step="0.01" min="0.01" name="qty_rencana" :required="true" placeholder="misal: 500" class="pr-20 font-mono">
                            <span class="absolute right-2.5 inset-y-0 flex items-center pointer-events-none">
                                <span class="px-2 py-0.5 text-[11px] font-mono font-bold text-primary-700 bg-primary-50 border border-primary-200 rounded-xs uppercase tracking-wider" x-text="uom || 'Unit'"></span>
                            </span>
                        </x-input>
                    </x-form-group>

                    <x-form-group name="gudang_operasional_id" label="Gudang Operasional (Sumber Bahan)" help="Gudang asal pengambilan stok bahan baku saat batch dirilis">
                        <x-select name="gudang_operasional_id" placeholder="— Pilih Gudang Bahan —">
                            @foreach($gudangOp as $g)
                                <option value="{{ $g->id }}" @selected(old('gudang_operasional_id') == $g->id)>{{ $g->nama }}</option>
                            @endforeach
                        </x-select>
                    </x-form-group>

                    <x-form-group name="gudang_tujuan_rencana_id" label="Gudang Tujuan (Produk Jadi)" help="Gudang penyimpanan setelah produk selesai diproduksi & lolos QC">
                        <x-select name="gudang_tujuan_rencana_id" placeholder="— Pilih Gudang Output —">
                            @foreach($gudangFf as $g)
                                <option value="{{ $g->id }}" @selected(old('gudang_tujuan_rencana_id') == $g->id)>{{ $g->nama }}</option>
                            @endforeach
                        </x-select>
                    </x-form-group>

                    <x-form-group name="tanggal" label="Tanggal Rencana" :required="true">
                        <x-input type="date" name="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" :required="true" />
                    </x-form-group>
                </div>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2.5">
                        <x-button href="{{ route('batches.index') }}" variant="secondary" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </x-slot:icon>
                            Simpan & Hitung Alokasi
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

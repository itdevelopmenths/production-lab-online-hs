<x-app-layout title="Stok & Mutasi">
    <div x-data="{ openMutasi: false }">
        <x-page-header
            title="Stok & Mutasi"
            subtitle="Pemantauan saldo fisik (Kolom Stok) vs komitmen alokasi batch (Kolom Rencana) berbasis batas aman analisa"
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

        <x-card title="Buku Saldo & Alert Ketersediaan Stok" :noPadding="true">
            <table id="tbl" class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">SKU</th>
                        <th class="px-4 py-3 text-left">Produk</th>
                        <th class="px-4 py-3 text-left">Gudang</th>
                        <th class="px-4 py-3 text-right">Batas Min (Analisa)</th>
                        <th class="px-4 py-3 text-right">Kolom Stok</th>
                        <th class="px-4 py-3 text-right">Kolom Rencana</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
            </table>
        </x-card>

        {{-- Modal Mutasi Manual (AdminLTE Sharp Modal) --}}
        @can('stok.mutasi')
        <x-modal name="openMutasi" title="Mutasi Stok Manual" maxWidth="md">
            <form method="POST" action="{{ route('stok.mutasi') }}" id="form-mutasi-manual" class="space-y-3" x-data="{ mutasiUom: '' }" @product-selected="mutasiUom = $event.detail ? $event.detail.satuan : ''">
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
                    <select name="gudang_id" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" required>
                        @foreach($gudang as $g)
                            <option value="{{ $g->id }}">{{ $g->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Tipe Mutasi</label>
                        <select name="tipe" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                            <option value="in">Masuk (IN)</option>
                            <option value="out">Keluar (OUT)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Qty <span class="text-rose-500">*</span></label>
                        <div class="relative flex items-center">
                            <input type="number" step="0.01" name="qty" class="w-full rounded-sm border-gray-300 text-xs py-1.5 pr-14 font-mono focus:border-primary-500 focus:ring-1 focus:ring-primary-500" placeholder="0.00" required>
                            <span class="absolute right-2 px-1 py-0.5 text-[10px] font-mono font-bold text-primary-700 bg-primary-50 border border-primary-200 rounded-xs uppercase tracking-wider pointer-events-none" x-text="mutasiUom || 'Unit'"></span>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan / Alasan Mutasi</label>
                    <input type="text" name="catatan" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" placeholder="Penyesuaian stok / rusak / sampel">
                </div>
            </form>

            <x-slot:footer>
                <x-button @click="openMutasi = false" variant="secondary" size="xs">
                    Batal
                </x-button>
                <x-button onclick="document.getElementById('form-mutasi-manual').submit()" variant="primary" size="xs">
                    Simpan Mutasi
                </x-button>
            </x-slot:footer>
        </x-modal>
        @endcan
    </div>

    @push('scripts')
    <script>
    $(function(){
        $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("stok.data") }}',
            columns: [
                { data: 'sku', orderable: false },
                { data: 'nama', orderable: false },
                { data: 'gudang_nama', orderable: false },
                { data: 'batas_minimum', className: 'text-right font-mono', orderable: false, searchable: false },
                { data: 'kolom_stok', className: 'text-right font-mono', searchable: false },
                { data: 'kolom_rencana', className: 'text-right font-mono', searchable: false },
                { data: 'action', className: 'text-center', orderable: false, searchable: false }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

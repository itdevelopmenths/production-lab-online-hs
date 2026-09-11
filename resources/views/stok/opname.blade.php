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

        <form method="POST" action="{{ route('stok.opname.store') }}" x-data="{ uom: '' }" @product-selected="uom = $event.detail ? $event.detail.satuan : ''">
            @csrf
            <x-card title="Formulir Hasil Hitung Fisik" subtitle="Lengkapi spesifikasi komoditas, lokasi penyimpanan, dan kuantitas hasil opname" variant="primary">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <x-form-group name="produk_id" label="Produk / Komoditas" :required="true" help="Ketik SKU atau nama produk untuk mencari (paginasi 10 item)" class="relative z-30">
                        <x-product-search-select
                            name="produk_id"
                            :selectedId="old('produk_id')"
                            placeholder="— Cari & Pilih Produk / Bahan —"
                            :required="true"
                        />
                    </x-form-group>

                    <x-form-group name="gudang_id" label="Lokasi Gudang" :required="true" help="Fasilitas gudang tempat stok berada">
                        <x-select name="gudang_id" placeholder="— Pilih Lokasi Gudang —" :required="true">
                            @foreach($gudang as $g)
                                <option value="{{ $g->id }}" @selected(old('gudang_id') == $g->id)>
                                    {{ $g->nama }} {{ isset($g->kode) ? '('.$g->kode.')' : '' }}
                                </option>
                            @endforeach
                        </x-select>
                    </x-form-group>

                    <div class="md:col-span-2">
                        <x-form-group name="qty_fisik" label="Kuantitas Fisik Aktual" :required="true" help="Jumlah total hasil stock counting fisik di rak/lokasi penyimpanan">
                            <x-input type="number" step="0.01" min="0" name="qty_fisik" value="{{ old('qty_fisik') }}" placeholder="0.00" :required="true" class="pr-20 font-mono">
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
</x-app-layout>

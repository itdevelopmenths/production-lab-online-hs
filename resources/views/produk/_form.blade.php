@php
    $p = $produk ?? null;
    $categories = $kategoriList ?? \App\Models\Kategori::with(['varians' => fn($q) => $q->orderBy('nama')])->orderBy('nama')->get();

    $rawMoq = old('satuan_order_moq', $p?->satuan_order_moq ?? 1);
    $floatMoq = ($rawMoq !== null && $rawMoq !== '') ? (float) $rawMoq : 1;
    $displayMoq = ($floatMoq == (int) $floatMoq) ? (int) $floatMoq : rtrim(rtrim(number_format($floatMoq, 2, '.', ''), '0'), '.');
@endphp

<div x-data="{
    categories: {{ Js::from($categories) }},
    selectedKategoriId: '{{ old('kategori_id', $p?->kategori_id) }}',
    selectedVarianId: '{{ old('varian_id', $p?->varian_id) }}',
    namaProduk: '{{ addslashes(old('nama_produk', $p?->nama_produk ?? $p?->nama ?? '')) }}',
    
    get currentVarians() {
        const cat = this.categories.find(c => String(c.id) === String(this.selectedKategoriId));
        return cat && cat.varians ? cat.varians : [];
    },

    get currentVarianName() {
        const v = this.currentVarians.find(item => String(item.id) === String(this.selectedVarianId));
        return v ? v.nama : '';
    },

    get fullNamePreview() {
        const base = (this.namaProduk || '').trim();
        const varian = (this.currentVarianName || '').trim();
        if (!base) return '— Belum diisi —';
        return varian ? `${base} ${varian}` : base;
    },

    onKategoriChange() {
        const exists = this.currentVarians.some(v => String(v.id) === String(this.selectedVarianId));
        if (!exists) {
            this.selectedVarianId = '';
        }
    }
}" class="space-y-6">

    <!-- Preview Box -->
    <div class="p-3 bg-primary-50/70 border border-primary-200 rounded-md flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="text-[10px] font-bold text-primary-700 uppercase tracking-wider block">Pratinjau Nama Lengkap Produk (Katalog & Transaksi):</span>
            <span class="text-sm font-semibold text-primary-900 font-mono" x-text="fullNamePreview"></span>
        </div>
        <div class="text-right">
            <span class="text-[10px] text-gray-500 block">Struktur: Kategori + Nama Dasar + Varian</span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        {{-- Kategori Dropdown --}}
        <x-form-group name="kategori_id" label="Kategori Produk" :required="true" help="Pilih kategori induk produk">
            <select
                name="kategori_id"
                id="kategori_id"
                x-model="selectedKategoriId"
                @change="onKategoriChange()"
                required
                class="w-full text-xs rounded-sm border-gray-300 shadow-2xs focus:border-primary-500 focus:ring-primary-500 transition py-2 px-3"
            >
                <option value="">— Pilih Kategori Produk —</option>
                <template x-for="c in categories" :key="c.id">
                    <option :value="c.id" x-text="c.nama" :selected="String(c.id) === String(selectedKategoriId)"></option>
                </template>
            </select>
        </x-form-group>

        {{-- Varian Dropdown --}}
        <x-form-group name="varian_id" label="Varian Produk" help="Varian disaring otomatis sesuai Kategori terpilih">
            <select
                name="varian_id"
                id="varian_id"
                x-model="selectedVarianId"
                class="w-full text-xs rounded-sm border-gray-300 shadow-2xs focus:border-primary-500 focus:ring-primary-500 transition py-2 px-3"
            >
                <option value="">— Tanpa Varian (Non-Varian) —</option>
                <template x-for="v in currentVarians" :key="v.id">
                    <option :value="v.id" x-text="v.nama" :selected="String(v.id) === String(selectedVarianId)"></option>
                </template>
            </select>
        </x-form-group>

        {{-- Base Name Produk --}}
        <x-form-group name="nama_produk" label="Nama Dasar Produk" :required="true" help="Nama produk tanpa imbuhan varian (misal: Travel Perfume, Botol Spray, Alkohol 96%)">
            <input
                type="text"
                name="nama_produk"
                id="nama_produk"
                x-model="namaProduk"
                value="{{ old('nama_produk', $p?->nama_produk ?? $p?->nama) }}"
                placeholder="misal: Travel Perfume"
                required
                maxlength="150"
                class="w-full text-xs rounded-sm border-gray-300 shadow-2xs focus:border-primary-500 focus:ring-primary-500 transition py-2 px-3"
            />
        </x-form-group>

        {{-- SKU Kode Barang --}}
        <x-form-group name="sku" label="SKU / Kode Barang" :required="true" help="Kode unik identifikasi stok barang">
            <x-input name="sku" value="{{ old('sku', $p?->sku) }}" placeholder="misal: BAH-ALK-96" :required="true" />
        </x-form-group>

        {{-- Tipe Produk --}}
        <x-form-group name="tipe" label="Tipe Produk" :required="true">
            <x-select name="tipe" :required="true">
                @foreach(['bahan' => 'Bahan Baku', 'kemas' => 'Kemasan / Botol', 'produk_jadi' => 'Produk Jadi (Output BOM)'] as $v => $l)
                    <option value="{{ $v }}" @selected(old('tipe', $p?->tipe) === $v)>{{ $l }}</option>
                @endforeach
            </x-select>
        </x-form-group>

        {{-- Satuan Dasar UOM --}}
        <x-form-group name="satuan" label="Satuan Dasar UOM" :required="true" help="Satuan standar operasional (ml, gr, pcs) untuk stok & HPP">
            <x-select name="satuan" placeholder="— Pilih Satuan UOM —" :required="true">
                @php($uoms = $uomList ?? \App\Models\Uom::active()->orderBy('nama')->get())
                @foreach($uoms as $uom)
                    <option value="{{ $uom->kode }}" @selected(old('satuan', $p?->satuan) === $uom->kode)>
                        {{ $uom->nama }} ({{ $uom->kode }})
                    </option>
                @endforeach
            </x-select>
        </x-form-group>

        {{-- Faktor Konversi Bawaan Sistem (Default 1) --}}
        <input type="hidden" name="faktor_konversi" value="{{ old('faktor_konversi', $p?->faktor_konversi ?? 1) }}">

        {{-- MOQ --}}
        <x-form-group name="satuan_order_moq" label="Minimum Order Quantity (MOQ)" :required="true" help="Batas minimum kelipatan pemesanan">
            <x-input type="number" step="any" min="0.01" name="satuan_order_moq" value="{{ $displayMoq }}" :required="true" />
        </x-form-group>

        {{-- Profil Analisa --}}
        <x-form-group name="profil_analisa" label="Profil Analisa Stok" help="Kategori perhitungan batas aman stok">
            <x-select name="profil_analisa" placeholder="— Pilih Profil Analisa —">
                <option value="lokal" @selected(old('profil_analisa', $p?->profil_analisa) === 'lokal')>Lokal</option>
                <option value="impor" @selected(old('profil_analisa', $p?->profil_analisa) === 'impor')>Impor</option>
            </x-select>
        </x-form-group>

        {{-- Status Aktif --}}
        <div class="md:col-span-2 pt-1">
            <x-checkbox name="is_active" label="Status Aktif Katalog" :checked="old('is_active', $p?->is_active ?? true)" help="Hanya produk aktif yang dapat dipilih dalam pembuatan PO dan Batch Produksi" />
        </div>
    </div>
</div>

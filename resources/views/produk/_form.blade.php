@php($p = $produk ?? null)
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <x-form-group name="sku" label="SKU / Kode Barang" :required="true" help="Kode unik identifikasi stok barang">
        <x-input name="sku" value="{{ old('sku', $p?->sku) }}" placeholder="misal: BAH-ALK-96" :required="true" />
    </x-form-group>

    <x-form-group name="nama" label="Nama Produk / Bahan" :required="true" help="Nama deskriptif komoditas atau formula">
        <x-input name="nama" value="{{ old('nama', $p?->nama) }}" placeholder="misal: Alkohol 96% Food Grade" :required="true" />
    </x-form-group>

    <x-form-group name="tipe" label="Tipe Produk" :required="true">
        <x-select name="tipe" :required="true">
            @foreach(['bahan' => 'Bahan Baku', 'kemas' => 'Kemasan / Botol', 'produk_jadi' => 'Produk Jadi (Output BOM)'] as $v => $l)
                <option value="{{ $v }}" @selected(old('tipe', $p?->tipe) === $v)>{{ $l }}</option>
            @endforeach
        </x-select>
    </x-form-group>

    <x-form-group name="satuan" label="Satuan Unit (UOM)" :required="true" help="Unit standar pengukuran dari Master UOM">
        <x-select name="satuan" placeholder="— Pilih Satuan UOM —" :required="true">
            @php($uoms = $uomList ?? \App\Models\Uom::active()->orderBy('nama')->get())
            @foreach($uoms as $uom)
                <option value="{{ $uom->kode }}" @selected(old('satuan', $p?->satuan) === $uom->kode)>
                    {{ $uom->nama }} ({{ $uom->kode }})
                </option>
            @endforeach
        </x-select>
    </x-form-group>

    <x-form-group name="satuan_order_moq" label="Minimum Order Quantity (MOQ)" :required="true" help="Batas minimum kelipatan pemesanan">
        <x-input type="number" step="0.01" min="0.01" name="satuan_order_moq" value="{{ old('satuan_order_moq', $p?->satuan_order_moq ?? 1) }}" :required="true" />
    </x-form-group>

    <x-form-group name="profil_analisa" label="Profil Analisa Stok" help="Kategori perhitungan batas aman stok">
        <x-select name="profil_analisa" placeholder="— Pilih Profil Analisa —">
            <option value="lokal" @selected(old('profil_analisa', $p?->profil_analisa) === 'lokal')>Lokal</option>
            <option value="impor" @selected(old('profil_analisa', $p?->profil_analisa) === 'impor')>Impor</option>
        </x-select>
    </x-form-group>

    <div class="md:col-span-2 pt-1">
        <x-checkbox name="is_active" label="Status Aktif Katalog" :checked="old('is_active', $p?->is_active ?? true)" help="Hanya produk aktif yang dapat dipilih dalam pembuatan PO dan Batch Produksi" />
    </div>
</div>

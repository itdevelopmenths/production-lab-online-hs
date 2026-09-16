@php($k = $kategori ?? null)
<div class="grid grid-cols-1 gap-5">
    <x-form-group name="nama" label="Nama Kategori Produk" :required="true" help="Contoh: Bahan Baku, Kemasan, Eau de Parfum, Body Mist, Box Kemasan">
        <x-input name="nama" value="{{ old('nama', $k?->nama) }}" placeholder="misal: Eau de Parfum" :required="true" />
    </x-form-group>
</div>

@php($k = $kategori ?? null)
<x-form-section card :cols="1" title="Identitas Kategori" subtitle="Nama grup klasifikasi produk">
    <x-form-field name="nama" label="Nama Kategori Produk" :required="true" help="Contoh: Bahan Baku, Kemasan, Eau de Parfum">
        <x-input name="nama" variant="seamless" value="{{ old('nama', $k?->nama) }}" placeholder="misal: Eau de Parfum" :required="true" />
    </x-form-field>
</x-form-section>

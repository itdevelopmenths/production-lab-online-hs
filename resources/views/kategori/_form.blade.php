@php($k = $kategori ?? null)
<x-form-section card title="Identitas Kategori" subtitle="Nama grup klasifikasi utama produk dan material">
    <x-form-field name="nama" label="Nama Kategori Produk" :required="true" help="Contoh: Bahan Baku, Kemasan, Eau de Parfum, Body Mist, Box Kemasan">
        <x-input name="nama" variant="seamless" value="{{ old('nama', $k?->nama) }}" placeholder="misal: Eau de Parfum" :required="true" />
    </x-form-field>
</x-form-section>

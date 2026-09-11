@php($s = $supplier ?? null)
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <x-form-group name="nama" label="Nama Supplier" :required="true" help="Nama resmi badan usaha atau penyedia">
        <x-input name="nama" value="{{ old('nama', $s?->nama) }}" placeholder="misal: PT Sumber Kimia Abadi" :required="true" />
    </x-form-group>

    <x-form-group name="kategori" label="Kategori Supplier" :required="true" help="Klasifikasi asal vendor">
        <x-select name="kategori" :required="true">
            <option value="lokal" @selected(old('kategori', $s?->kategori) === 'lokal')>Lokal</option>
            <option value="impor" @selected(old('kategori', $s?->kategori) === 'impor')>Impor</option>
        </x-select>
    </x-form-group>

    <x-form-group name="termin_default" label="Termin Pembayaran Default" help="Skema standar jatuh tempo pemesanan">
        <x-select name="termin_default" placeholder="— Pilih Termin (Opsional) —">
            @foreach(['tempo'=>'Tempo','termin'=>'Termin','pelunasan'=>'Pelunasan'] as $v=>$l)
                <option value="{{ $v }}" @selected(old('termin_default', $s?->termin_default) === $v)>{{ $l }}</option>
            @endforeach
        </x-select>
    </x-form-group>

    <x-form-group name="kontak" label="Kontak / PIC" help="No. telp, surel, atau nama representatif vendor">
        <x-input name="kontak" value="{{ old('kontak', $s?->kontak) }}" placeholder="misal: 0812-3456-7890 (Bpk. Anton)" />
    </x-form-group>

    <div class="md:col-span-2">
        <x-form-group name="alamat" label="Alamat Kantor / Gudang" help="Alamat operasional atau pengiriman barang">
            <x-textarea name="alamat" rows="2" placeholder="Alamat lengkap supplier...">{{ old('alamat', $s?->alamat) }}</x-textarea>
        </x-form-group>
    </div>

    <div class="md:col-span-2 pt-1">
        <x-checkbox name="is_active" label="Status Aktif" :checked="old('is_active', $s?->is_active ?? true)" help="Supplier aktif dapat dipilih pada penerimaan dan purchase order" />
    </div>
</div>

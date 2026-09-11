@php($g = $gudang ?? null)
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <x-form-group name="kode" label="Kode Gudang" :required="true" help="Singkatan kode identitas lokasi">
        <x-input name="kode" value="{{ old('kode', $g?->kode) }}" placeholder="misal: GDG-OP-01" :required="true" />
    </x-form-group>

    <x-form-group name="nama" label="Nama Gudang" :required="true" help="Nama lengkap fasilitas / lokasi penyimpanan">
        <x-input name="nama" value="{{ old('nama', $g?->nama) }}" placeholder="misal: Gudang Produksi Lab Utama" :required="true" />
    </x-form-group>

    <x-form-group name="tipe" label="Tipe Gudang" :required="true">
        <x-select name="tipe" :required="true">
            @foreach(['bahan_baku'=>'Bahan Baku','operasional'=>'Operasional','fulfillment_pusat'=>'Fulfillment Pusat','fulfillment_cabang'=>'Fulfillment Cabang'] as $v=>$l)
                <option value="{{ $v }}" @selected(old('tipe', $g?->tipe) === $v)>{{ $l }}</option>
            @endforeach
        </x-select>
    </x-form-group>

    <x-form-group name="parent_gudang_id" label="Gudang Induk (Opsional)" help="Pilih jika merupakan sub-gudang lokasi lain">
        <x-select name="parent_gudang_id" placeholder="— Tidak Ada Induk (Gudang Utama) —">
            @foreach($parents as $id => $nama)
                <option value="{{ $id }}" @selected(old('parent_gudang_id', $g?->parent_gudang_id) == $id)>{{ $nama }}</option>
            @endforeach
        </x-select>
    </x-form-group>

    <x-form-group name="status" label="Status Operasional" :required="true">
        <x-select name="status" :required="true">
            <option value="aktif" @selected(old('status', $g?->status ?? 'aktif') === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected(old('status', $g?->status) === 'nonaktif')>Nonaktif</option>
        </x-select>
    </x-form-group>

    <div class="md:col-span-2 pt-1">
        <x-checkbox name="allow_negative_stock" label="Izinkan Saldo Stok Negatif" :checked="old('allow_negative_stock', $g?->allow_negative_stock ?? false)" help="Peringatan: hanya aktifkan jika gudang memerlukan pencatatan fleksibel sebelum penyesuaian fisik" />
    </div>
</div>

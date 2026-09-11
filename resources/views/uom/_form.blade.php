@php($u = $uom ?? null)
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <x-form-group name="kode" label="Kode Satuan (Simbol)" :required="true" help="Singkatan resmi satuan (huruf kecil, misal: pcs, ml, gr, kg, l, botol)">
        <x-input name="kode" value="{{ old('kode', $u?->kode) }}" placeholder="misal: ml" :required="true" />
    </x-form-group>

    <x-form-group name="nama" label="Nama Satuan Lengkap" :required="true" help="Nama deskriptif unit pengukuran">
        <x-input name="nama" value="{{ old('nama', $u?->nama) }}" placeholder="misal: Mililiter" :required="true" />
    </x-form-group>

    <div class="md:col-span-2">
        <x-form-group name="kategori" label="Kategori Dimensi" help="Klasifikasi besaran fisik unit">
            <x-select name="kategori" placeholder="— Pilih Kategori Dimensi (Opsional) —">
                @foreach(['Volume' => 'Volume (Cairan / Larutan)', 'Massa' => 'Massa / Berat (Bubuk / Padatan)', 'Satuan Hitung' => 'Satuan Hitung (Pieces / Unit)', 'Kemasan' => 'Wadah Kemasan (Botol / Box / Drum)', 'Lainnya' => 'Lainnya'] as $v => $l)
                    <option value="{{ $v }}" @selected(old('kategori', $u?->kategori) === $v)>{{ $l }}</option>
                @endforeach
            </x-select>
        </x-form-group>
    </div>

    <div class="md:col-span-2">
        <x-form-group name="deskripsi" label="Keterangan / Deskripsi" help="Catatan tambahan mengenai konversi atau standar penggunaan">
            <x-textarea name="deskripsi" rows="2" placeholder="Catatan peruntukan satuan...">{{ old('deskripsi', $u?->deskripsi) }}</x-textarea>
        </x-form-group>
    </div>

    <div class="md:col-span-2 pt-1">
        <x-checkbox name="is_active" label="Status Aktif Satuan" :checked="old('is_active', $u?->is_active ?? true)" help="Satuan aktif dapat dipilih dalam pembuatan dan pengeditan master produk" />
    </div>
</div>

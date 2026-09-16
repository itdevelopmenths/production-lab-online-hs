@php($v = $varian ?? null)
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <x-form-group name="kategori_id" label="Kategori Produk" :required="true" help="Pilih kategori induk yang memayungi varian ini">
        <x-select name="kategori_id" :required="true">
            <option value="">— Pilih Kategori Produk —</option>
            @foreach($kategoriList as $k)
                <option value="{{ $k->id }}" @selected((int) old('kategori_id', $v?->kategori_id) === (int) $k->id)>
                    {{ $k->nama }}
                </option>
            @endforeach
        </x-select>
    </x-form-group>

    <x-form-group name="nama" label="Nama Varian" :required="true" help="Contoh: Her, Scandal, Bening, Burgundy, 50ml, Vanilla">
        <x-input name="nama" value="{{ old('nama', $v?->nama) }}" placeholder="misal: Scandal" :required="true" />
    </x-form-group>
</div>

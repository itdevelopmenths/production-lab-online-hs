@php($v = $varian ?? null)
<x-form-section title="Spesifikasi Varian" subtitle="Klasifikasi kategori induk dan penamaan varian">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-form-field name="kategori_id" label="Kategori Produk" :required="true" help="Pilih kategori induk yang memayungi varian ini">
            <x-select name="kategori_id" variant="seamless" :required="true">
                <option value="">— Pilih Kategori Produk —</option>
                @foreach($kategoriList as $k)
                    <option value="{{ $k->id }}" @selected((int) old('kategori_id', $v?->kategori_id) === (int) $k->id)>
                        {{ $k->nama }}
                    </option>
                @endforeach
            </x-select>
        </x-form-field>

        <x-form-field name="nama" label="Nama Varian" :required="true" help="Contoh: Her, Scandal, Bening, Burgundy, 50ml, Vanilla">
            <x-input name="nama" variant="seamless" value="{{ old('nama', $v?->nama) }}" placeholder="misal: Scandal" :required="true" />
        </x-form-field>
    </div>
</x-form-section>

@php($v = $varian ?? null)
<x-form-section card title="Spesifikasi Varian" subtitle="Kategori induk dan nama varian">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3">
        <x-form-field name="kategori_id" label="Kategori Produk" :required="true" help="Kategori induk varian">
            <x-select name="kategori_id" variant="seamless" :required="true">
                <option value="">— Pilih Kategori Produk —</option>
                @foreach($kategoriList as $k)
                    <option value="{{ $k->id }}" @selected((int) old('kategori_id', $v?->kategori_id) === (int) $k->id)>
                        {{ $k->nama }}
                    </option>
                @endforeach
            </x-select>
        </x-form-field>

        <x-form-field name="nama" label="Nama Varian" :required="true" help="Contoh: Her, Scandal, 50ml">
            <x-input name="nama" variant="seamless" value="{{ old('nama', $v?->nama) }}" placeholder="misal: Scandal" :required="true" />
        </x-form-field>
    </div>
</x-form-section>

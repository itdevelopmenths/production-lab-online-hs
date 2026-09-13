@php
    $g = $gudang ?? null;
    $rawTipe = old('tipe', $g?->tipe);
    $selectedTipe = in_array($rawTipe, ['fulfillment_pusat', 'fulfillment_cabang'], true) ? 'fulfillment' : $rawTipe;
@endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <x-form-group name="kode" label="Kode Gudang" :required="true" help="Singkatan kode identitas lokasi unik">
        <x-input name="kode" value="{{ old('kode', $g?->kode) }}" placeholder="misal: GDG-BB-01" :required="true" />
    </x-form-group>

    <x-form-group name="nama" label="Nama Gudang" :required="true" help="Nama lengkap fasilitas / lokasi penyimpanan">
        <x-input name="nama" value="{{ old('nama', $g?->nama) }}" placeholder="misal: Gudang Bahan Baku Utama" :required="true" />
    </x-form-group>

    <x-form-group name="tipe" label="Fungsi Logistik (Tipe)" :required="true">
        <x-select name="tipe" :required="true">
            @foreach(['bahan_baku'=>'Bahan Baku & Kemasan','operasional'=>'Operasional (Lab Produksi)','fulfillment'=>'Fulfillment (Produk Jadi & Distribusi)'] as $v=>$l)
                <option value="{{ $v }}" @selected($selectedTipe === $v)>{{ $l }}</option>
            @endforeach
        </x-select>
    </x-form-group>

    <x-form-group name="parent_gudang_id" label="Gudang Induk (Opsional)" help="Pilih jika merupakan sub-gudang / cabang dari lokasi lain">
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

    <div class="md:col-span-2 pt-2 space-y-3.5 border-t border-gray-100">
        <x-checkbox 
            name="is_pusat" 
            label="Tandai Sebagai Gudang Pusat (Central Hub)" 
            :checked="old('is_pusat', $g ? $g->isPusat() : false)" 
            help="Gudang ini akan menjadi rujukan utama sistem (Inbound PO Supplier untuk Bahan Baku, atau Hub Distribusi Utama untuk Fulfillment). Otomatis tanpa gudang induk." 
        />
        <x-checkbox 
            name="allow_negative_stock" 
            label="Izinkan Saldo Stok Negatif" 
            :checked="old('allow_negative_stock', $g?->allow_negative_stock ?? false)" 
            help="Peringatan: hanya aktifkan jika gudang memerlukan pencatatan fleksibel sebelum penyesuaian fisik" 
        />
    </div>
</div>

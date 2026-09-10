@php($g = $gudang ?? null)
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Kode</label>
        <input type="text" name="kode" value="{{ old('kode', $g?->kode) }}" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500" required>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
        <input type="text" name="nama" value="{{ old('nama', $g?->nama) }}" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500" required>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
        <select name="tipe" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500" required>
            @foreach(['bahan_baku'=>'Bahan Baku','operasional'=>'Operasional','fulfillment_pusat'=>'Fulfillment Pusat','fulfillment_cabang'=>'Fulfillment Cabang'] as $v=>$l)
            <option value="{{ $v }}" @selected(old('tipe', $g?->tipe) === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Gudang Induk (opsional)</label>
        <select name="parent_gudang_id" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500">
            <option value="">—</option>
            @foreach($parents as $id => $nama)
            <option value="{{ $id }}" @selected(old('parent_gudang_id', $g?->parent_gudang_id) == $id)>{{ $nama }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
        <select name="status" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500" required>
            <option value="aktif" @selected(old('status', $g?->status ?? 'aktif') === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected(old('status', $g?->status) === 'nonaktif')>Nonaktif</option>
        </select>
    </div>
    <div class="flex items-center gap-2 pt-6">
        <input type="hidden" name="allow_negative_stock" value="0">
        <input type="checkbox" name="allow_negative_stock" value="1" @checked(old('allow_negative_stock', $g?->allow_negative_stock ?? false)) class="rounded border-gray-300 text-primary-500 focus:ring-primary-500">
        <label class="text-sm text-gray-700">Izinkan stok minus</label>
    </div>
</div>

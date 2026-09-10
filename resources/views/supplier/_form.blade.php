@php($s = $supplier ?? null)
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
        <input type="text" name="nama" value="{{ old('nama', $s?->nama) }}" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500" required>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
        <select name="kategori" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500" required>
            <option value="lokal" @selected(old('kategori', $s?->kategori) === 'lokal')>Lokal</option>
            <option value="impor" @selected(old('kategori', $s?->kategori) === 'impor')>Impor</option>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Termin Default</label>
        <select name="termin_default" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500">
            <option value="">—</option>
            @foreach(['tempo'=>'Tempo','termin'=>'Termin','pelunasan'=>'Pelunasan'] as $v=>$l)
            <option value="{{ $v }}" @selected(old('termin_default', $s?->termin_default) === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Kontak</label>
        <input type="text" name="kontak" value="{{ old('kontak', $s?->kontak) }}" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
        <input type="text" name="alamat" value="{{ old('alamat', $s?->alamat) }}" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500">
    </div>
    <div class="flex items-center gap-2 pt-1">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $s?->is_active ?? true)) class="rounded border-gray-300 text-primary-500 focus:ring-primary-500">
        <label class="text-sm text-gray-700">Aktif</label>
    </div>
</div>

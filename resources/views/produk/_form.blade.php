@php($p = $produk ?? null)
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
        <input type="text" name="sku" value="{{ old('sku', $p?->sku) }}" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500" required>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
        <input type="text" name="nama" value="{{ old('nama', $p?->nama) }}" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500" required>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
        <select name="tipe" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500" required>
            @foreach(['bahan' => 'Bahan', 'kemas' => 'Kemas', 'produk_jadi' => 'Produk Jadi'] as $v => $l)
            <option value="{{ $v }}" @selected(old('tipe', $p?->tipe) === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Satuan</label>
        <input type="text" name="satuan" value="{{ old('satuan', $p?->satuan) }}" placeholder="pcs / ml / L" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500" required>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Satuan Order (MOQ)</label>
        <input type="number" step="0.01" name="satuan_order_moq" value="{{ old('satuan_order_moq', $p?->satuan_order_moq ?? 1) }}" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500" required>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Profil Analisa</label>
        <select name="profil_analisa" class="w-full rounded-lg border-gray-300 text-sm focus:ring-primary-500 focus:border-primary-500">
            <option value="">—</option>
            <option value="lokal" @selected(old('profil_analisa', $p?->profil_analisa) === 'lokal')>Lokal</option>
            <option value="impor" @selected(old('profil_analisa', $p?->profil_analisa) === 'impor')>Impor</option>
        </select>
    </div>
    <div class="flex items-center gap-2 pt-6">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $p?->is_active ?? true)) class="rounded border-gray-300 text-primary-500 focus:ring-primary-500">
        <label class="text-sm text-gray-700">Aktif</label>
    </div>
</div>

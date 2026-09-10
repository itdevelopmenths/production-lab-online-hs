<x-app-layout title="Buat Request/Transfer">
    <div class="max-w-4xl" x-data="rtForm()">
        <a href="{{ route('rt.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>
        <div class="mt-4 bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Buat Request &amp; Transfer</h3>
            <form method="POST" action="{{ route('rt.store') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jenis</label>
                        <select name="jenis" x-model="jenis" class="w-full rounded-lg border-gray-300 text-sm" required>
                            <option value="req_bahan">Request Bahan (approval)</option>
                            <option value="retur_bahan">Retur Bahan</option>
                            <option value="kirim_produk_jadi">Kirim Produk Jadi</option>
                            <option value="antar_fulfillment">Antar Fulfillment</option>
                            <option value="retur_produk_jadi">Retur Produk Jadi</option>
                        </select>
                        <p class="text-xs text-gray-400 mt-1" x-text="jenis==='req_bahan' ? 'Pipeline 5 tahap dengan approval.' : 'Transfer langsung 3 tahap.'"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gudang Asal</label>
                        <select name="gudang_asal_id" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach($gudang as $g)<option value="{{ $g->id }}">{{ $g->nama }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gudang Tujuan</label>
                        <select name="gudang_tujuan_id" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach($gudang as $g)<option value="{{ $g->id }}">{{ $g->nama }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <h4 class="text-sm font-semibold text-gray-800 mb-3">Item</h4>
                <div class="space-y-3">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="flex items-center gap-3">
                            <select :name="`items[${i}][produk_id]`" x-model="row.produk_id" class="flex-1 rounded-lg border-gray-300 text-sm" required>
                                <option value="">— pilih produk —</option>
                                @foreach($produk as $p)<option value="{{ $p->id }}">{{ $p->sku }} — {{ $p->nama }}</option>@endforeach
                            </select>
                            <input type="number" step="0.01" :name="`items[${i}][qty_diminta]`" x-model="row.qty" placeholder="qty diminta" class="w-36 rounded-lg border-gray-300 text-sm" required>
                            <button type="button" @click="rows.splice(i,1)" class="text-red-500 hover:text-red-700">&times;</button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="rows.push({produk_id:'',qty:''})" class="mt-3 text-sm text-primary-600 hover:text-primary-800">+ Tambah item</button>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <textarea name="catatan" rows="2" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('rt.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Batal</a>
                    <button type="submit" class="px-4 py-2 bg-primary-500 text-white text-sm font-medium rounded-xl hover:bg-primary-600">Simpan Draft</button>
                </div>
            </form>
        </div>
    </div>
    @push('scripts')
    <script>function rtForm(){ return { jenis:'req_bahan', rows:[{produk_id:'',qty:''}] }; }</script>
    @endpush
</x-app-layout>

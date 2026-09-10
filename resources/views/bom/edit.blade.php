<x-app-layout title="Kelola BOM">
    <div class="max-w-4xl" x-data="bomForm()">
        <a href="{{ route('bom.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>
        <div class="mt-4 bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900">BOM — {{ $produk->nama }}</h3>
            <p class="text-xs text-gray-500 mb-6">SKU {{ $produk->sku }} · kebutuhan bahan per 1 unit</p>

            <form method="POST" action="{{ route('bom.update', $produk) }}">
                @csrf @method('PUT')
                <div class="space-y-3">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="flex items-center gap-3">
                            <select :name="`items[${i}][bahan_id]`" x-model="row.bahan_id" class="flex-1 rounded-lg border-gray-300 text-sm" required>
                                <option value="">— pilih bahan —</option>
                                @foreach($bahanList as $b)
                                <option value="{{ $b->id }}">{{ $b->sku }} — {{ $b->nama }} ({{ $b->satuan }})</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.0001" :name="`items[${i}][qty_per_unit]`" x-model="row.qty" placeholder="qty" class="w-32 rounded-lg border-gray-300 text-sm" required>
                            <button type="button" @click="rows.splice(i,1)" class="text-red-500 hover:text-red-700 text-sm">&times;</button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="rows.push({bahan_id:'',qty:''})" class="mt-3 text-sm text-primary-600 hover:text-primary-800">+ Tambah bahan</button>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('bom.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Batal</a>
                    <button type="submit" class="px-4 py-2 bg-primary-500 text-white text-sm font-medium rounded-xl hover:bg-primary-600">Simpan BOM</button>
                </div>
            </form>
        </div>
    </div>
    @push('scripts')
    <script>
    function bomForm(){ const init = @json($items->map(fn($i)=>['bahan_id'=>$i->bahan_id,'qty'=>(float)$i->qty_per_unit])->values()); return { rows: init.length ? init : [{bahan_id:'',qty:''}] };
    }
    </script>
    @endpush
</x-app-layout>

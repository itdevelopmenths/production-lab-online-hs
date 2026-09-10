<x-app-layout title="Stock Opname">
    <div class="max-w-2xl">
        <a href="{{ route('stok.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>
        <div class="mt-4 bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Stock Opname</h3>
            <p class="text-xs text-gray-500 mb-6">Set qty fisik hasil hitung; selisih dicatat ke kartu stok.</p>
            <form method="POST" action="{{ route('stok.opname.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Produk</label>
                    <select name="produk_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                        @foreach($produk as $p)<option value="{{ $p->id }}">{{ $p->sku }} — {{ $p->nama }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gudang</label>
                    <select name="gudang_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                        @foreach($gudang as $g)<option value="{{ $g->id }}">{{ $g->nama }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Qty Fisik</label>
                    <input type="number" step="0.01" name="qty_fisik" class="w-full rounded-lg border-gray-300 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <input type="text" name="catatan" class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-primary-500 text-white text-sm font-medium rounded-xl hover:bg-primary-600">Simpan Opname</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

<x-app-layout title="Rencana Produksi">
    <div class="max-w-3xl">
        <a href="{{ route('batches.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>
        <div class="mt-4 bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Rencana Produksi</h3>
            <p class="text-xs text-gray-500 mb-6">Alokasi bahan dihitung otomatis dari BOM × qty, tanpa mengurangi stok fisik.</p>
            <form method="POST" action="{{ route('batches.store') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Produk Jadi</label>
                        <select name="produk_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                            @foreach($produk as $p)<option value="{{ $p->id }}">{{ $p->sku }} — {{ $p->nama }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Qty Rencana</label>
                        <input type="number" step="0.01" name="qty_rencana" class="w-full rounded-lg border-gray-300 text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gudang Operasional (sumber bahan)</label>
                        <select name="gudang_operasional_id" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach($gudangOp as $g)<option value="{{ $g->id }}">{{ $g->nama }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gudang Tujuan (produk jadi)</label>
                        <select name="gudang_tujuan_rencana_id" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">—</option>
                            @foreach($gudangFf as $g)<option value="{{ $g->id }}">{{ $g->nama }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                        <input type="date" name="tanggal" value="{{ date('Y-m-d') }}" class="w-full rounded-lg border-gray-300 text-sm" required>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('batches.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Batal</a>
                    <button type="submit" class="px-4 py-2 bg-primary-500 text-white text-sm font-medium rounded-xl hover:bg-primary-600">Buat Batch</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

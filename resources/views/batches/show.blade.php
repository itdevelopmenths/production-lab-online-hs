<x-app-layout title="Detail Batch">
    <a href="{{ route('batches.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>

    <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $batch->no_batch }}</h3>
                        <p class="text-sm text-gray-500">{{ $batch->produk->nama }} · rencana {{ rtrim(rtrim(number_format($batch->qty_rencana,2),'0'),'.') }} · {{ $batch->tanggal->format('d/m/Y') }}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">{{ ucfirst($batch->status) }}</span>
                </div>
                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div><p class="text-gray-400">Qty Baik</p><p class="font-semibold">{{ $batch->qty_baik !== null ? rtrim(rtrim(number_format($batch->qty_baik,2),'0'),'.') : '-' }}</p></div>
                    <div><p class="text-gray-400">Qty Rusak</p><p class="font-semibold">{{ $batch->qty_rusak !== null ? rtrim(rtrim(number_format($batch->qty_rusak,2),'0'),'.') : '-' }}</p></div>
                    <div><p class="text-gray-400">Yield</p><p class="font-semibold">{{ $batch->yield() !== null ? number_format($batch->yield(),1).'%' : '-' }}</p></div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Alokasi Bahan (BOM explode)</h4>
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200 text-gray-500"><tr><th class="text-left py-2">Bahan</th><th class="text-right py-2">Dialokasikan</th><th class="text-left py-2 pl-4">Status</th></tr></thead>
                    <tbody>
                        @forelse($batch->alokasi as $a)
                        <tr class="border-b border-gray-100">
                            <td class="py-2">{{ $a->bahan->sku }} — {{ $a->bahan->nama }}</td>
                            <td class="text-right">{{ rtrim(rtrim(number_format($a->qty_dialokasikan,2),'0'),'.') }} {{ $a->bahan->satuan }}</td>
                            <td class="pl-4">{{ ucfirst($a->status) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="py-3 text-gray-400 text-center">BOM produk kosong.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @can('batch.complete')
            @if($batch->status === 'release')
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h4 class="text-sm font-semibold text-gray-800 mb-4">Selesaikan Batch</h4>
                <form method="POST" action="{{ route('batches.complete', $batch) }}" class="flex items-end gap-4">
                    @csrf
                    <div><label class="block text-xs text-gray-600 mb-1">Qty Baik</label><input type="number" step="0.01" name="qty_baik" class="w-32 rounded-lg border-gray-300 text-sm" required></div>
                    <div><label class="block text-xs text-gray-600 mb-1">Qty Rusak</label><input type="number" step="0.01" name="qty_rusak" value="0" class="w-32 rounded-lg border-gray-300 text-sm" required></div>
                    <button class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-xl hover:bg-emerald-700">Selesai</button>
                </form>
            </div>
            @endif
            @endcan
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-2">
            <h4 class="text-sm font-semibold text-gray-800 mb-3">Aksi</h4>
            @can('batch.release')@if($batch->status==='rencana')<form method="POST" action="{{ route('batches.release',$batch) }}">@csrf<button class="w-full px-4 py-2 bg-primary-500 text-white text-sm rounded-lg hover:bg-primary-600">Release & Issue</button></form>@endif @endcan
            @can('batch.cancel')@if($batch->status==='rencana')<form method="POST" action="{{ route('batches.cancel',$batch) }}">@csrf<button class="w-full px-4 py-2 bg-red-50 text-red-600 text-sm rounded-lg hover:bg-red-100">Batalkan</button></form>@endif @endcan
            @can('rt.create')@if($batch->status==='selesai')<form method="POST" action="{{ route('batches.kirim',$batch) }}">@csrf<button class="w-full px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">Ajukan Kirim ke Fulfillment</button></form>@endif @endcan
            @if(in_array($batch->status,['dibatalkan']))<p class="text-sm text-gray-400">Batch dibatalkan.</p>@endif
        </div>
    </div>
</x-app-layout>

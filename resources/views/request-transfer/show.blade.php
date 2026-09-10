<x-app-layout title="Detail Dokumen">
    @php($rt = $requestTransfer)
    @php($approval = in_array($rt->jenis, ['req_bahan']))
    <a href="{{ route('rt.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>

    <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ $rt->no_transaksi }}</h3>
                    <p class="text-sm text-gray-500">{{ ucwords(str_replace('_',' ',$rt->jenis)) }} · {{ $rt->gudangAsal?->nama ?? '—' }} &rarr; {{ $rt->gudangTujuan?->nama ?? '—' }}</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">{{ ucwords(str_replace('_',' ',$rt->status)) }}</span>
            </div>
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 text-gray-500"><tr>
                    <th class="text-left py-2">Produk</th><th class="text-right py-2">Diminta</th><th class="text-right py-2">Dikirim</th><th class="text-right py-2">Diterima</th>
                </tr></thead>
                <tbody>
                    @foreach($rt->items as $it)
                    <tr class="border-b border-gray-100">
                        <td class="py-2">{{ $it->produk->sku }} — {{ $it->produk->nama }}</td>
                        <td class="text-right">{{ rtrim(rtrim(number_format($it->qty_diminta,2),'0'),'.') }}</td>
                        <td class="text-right">{{ $it->qty_dikirim !== null ? rtrim(rtrim(number_format($it->qty_dikirim,2),'0'),'.') : '-' }}</td>
                        <td class="text-right">{{ $it->qty_diterima !== null ? rtrim(rtrim(number_format($it->qty_diterima,2),'0'),'.') : '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($rt->catatan)<p class="mt-4 text-sm text-gray-500">Catatan: {{ $rt->catatan }}</p>@endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-2">
            <h4 class="text-sm font-semibold text-gray-800 mb-3">Aksi</h4>
            @php($btn = 'w-full px-4 py-2 text-white text-sm rounded-lg')
            @if($approval)
                @can('rt.submit')@if($rt->status==='draft')<form method="POST" action="{{ route('rt.transition',$rt) }}">@csrf<input type="hidden" name="aksi" value="submit"><button class="{{ $btn }} bg-primary-500 hover:bg-primary-600">Ajukan</button></form>@endif @endcan
                @can('rt.approve')@if($rt->status==='diajukan')<form method="POST" action="{{ route('rt.transition',$rt) }}">@csrf<input type="hidden" name="aksi" value="approve"><button class="{{ $btn }} bg-emerald-600 hover:bg-emerald-700">Setujui</button></form>@endif @endcan
                @can('rt.process')@if($rt->status==='disetujui')<form method="POST" action="{{ route('rt.transition',$rt) }}">@csrf<input type="hidden" name="aksi" value="process"><button class="{{ $btn }} bg-indigo-600 hover:bg-indigo-700">Proses & Kirim (stok keluar)</button></form>@endif @endcan
            @else
                @can('rt.ship')@if($rt->status==='draft')<form method="POST" action="{{ route('rt.transition',$rt) }}">@csrf<input type="hidden" name="aksi" value="ship"><button class="{{ $btn }} bg-indigo-600 hover:bg-indigo-700">Kirim (stok keluar)</button></form>@endif @endcan
            @endif
            @can('rt.receive')@if(in_array($rt->status,['diproses','dikirim']))<form method="POST" action="{{ route('rt.transition',$rt) }}">@csrf<input type="hidden" name="aksi" value="receive"><button class="{{ $btn }} bg-emerald-600 hover:bg-emerald-700">Terima (stok masuk)</button></form>@endif @endcan
            @can('rt.cancel')@if(!in_array($rt->status,['selesai','dibatalkan']))<form method="POST" action="{{ route('rt.transition',$rt) }}">@csrf<input type="hidden" name="aksi" value="cancel"><button class="w-full px-4 py-2 bg-red-50 text-red-600 text-sm rounded-lg hover:bg-red-100">Batalkan</button></form>@endif @endcan
            @if(in_array($rt->status,['selesai','dibatalkan']))<p class="text-sm text-gray-400">Dokumen final.</p>@endif
        </div>
    </div>
</x-app-layout>

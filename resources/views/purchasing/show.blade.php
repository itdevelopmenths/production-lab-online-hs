<x-app-layout title="Detail PO">
    @php($po = $purchaseOrder)
    <a href="{{ route('purchasing.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>

    <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $po->no_po }}</h3>
                        <p class="text-sm text-gray-500">{{ $po->supplier->nama }} · {{ $po->tanggal->format('d/m/Y') }}
                            @if($po->dari_analisa)<span class="ml-1 px-2 py-0.5 bg-amber-50 text-amber-700 text-xs rounded-full">Dari Analisa</span>@endif
                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">{{ ucwords(str_replace('_',' ',$po->status)) }}</span>
                </div>
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200 text-gray-500"><tr>
                        <th class="text-left py-2">Produk</th><th class="text-right py-2">Qty</th>
                        <th class="text-right py-2">Harga Total</th><th class="text-right py-2">HPP/satuan</th><th class="text-right py-2">Diterima</th>
                    </tr></thead>
                    <tbody>
                        @foreach($po->items as $it)
                        <tr class="border-b border-gray-100">
                            <td class="py-2">{{ $it->produk->sku }} — {{ $it->produk->nama }}</td>
                            <td class="text-right">{{ rtrim(rtrim(number_format($it->qty,2),'0'),'.') }} {{ $it->produk->satuan }}</td>
                            <td class="text-right">{{ number_format($it->harga_total,0,',','.') }}</td>
                            <td class="text-right">{{ number_format($it->hargaPerSatuan(),2,',','.') }}</td>
                            <td class="text-right">{{ rtrim(rtrim(number_format($it->qtyDiterima(),2),'0'),'.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="font-semibold"><td class="py-2">Total</td><td></td><td class="text-right">{{ number_format($po->totalNilai(),0,',','.') }}</td><td colspan="2"></td></tr></tfoot>
                </table>
            </div>

            {{-- Barang Datang --}}
            @can('purchasing.receive')
            @if($po->status === 'dikirim_ke_gudang')
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h4 class="text-sm font-semibold text-gray-800 mb-4">Konfirmasi Barang Datang</h4>
                <form method="POST" action="{{ route('purchasing.receive', $po) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Gudang Tujuan</label>
                            <select name="gudang_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                                @foreach($gudang as $g)<option value="{{ $g->id }}">{{ $g->nama }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Tanggal Terima</label>
                            <input type="date" name="tanggal_terima" value="{{ date('Y-m-d') }}" class="w-full rounded-lg border-gray-300 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Kondisi</label>
                            <select name="kondisi" class="w-full rounded-lg border-gray-300 text-sm"><option value="baik">Baik</option><option value="rusak_sebagian">Rusak Sebagian</option></select>
                        </div>
                    </div>
                    @foreach($po->items as $i => $it)
                    <div class="flex items-center gap-3">
                        <span class="flex-1 text-sm text-gray-700">{{ $it->produk->nama }} (PO: {{ rtrim(rtrim(number_format($it->qty,2),'0'),'.') }})</span>
                        <input type="hidden" name="items[{{ $i }}][po_item_id]" value="{{ $it->id }}">
                        <input type="number" step="0.01" name="items[{{ $i }}][qty_diterima]" value="{{ $it->qty }}" class="w-32 rounded-lg border-gray-300 text-sm" placeholder="qty diterima">
                    </div>
                    @endforeach
                    <div class="flex justify-end"><button type="submit" class="px-4 py-2 bg-primary-500 text-white text-sm font-medium rounded-xl hover:bg-primary-600">Terima & Tambah Stok</button></div>
                </form>
            </div>
            @endif
            @endcan
        </div>

        {{-- Sidebar: actions + payments --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-2">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Aksi</h4>
                @can('purchasing.submit')
                @if($po->status === 'draft')<form method="POST" action="{{ route('purchasing.submit', $po) }}">@csrf<button class="w-full px-4 py-2 bg-primary-500 text-white text-sm rounded-lg hover:bg-primary-600">Ajukan</button></form>@endif
                @endcan
                @can('purchasing.approve')
                @if($po->status === 'diajukan')<form method="POST" action="{{ route('purchasing.approve', $po) }}">@csrf<button class="w-full px-4 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700">Setujui & Kirim ke Gudang</button></form>@endif
                @endcan
                @can('purchasing.cancel')
                @if(!in_array($po->status, ['selesai','dibatalkan']))<form method="POST" action="{{ route('purchasing.cancel', $po) }}">@csrf<button class="w-full px-4 py-2 bg-red-50 text-red-600 text-sm rounded-lg hover:bg-red-100">Batalkan</button></form>@endif
                @endcan
                @if(in_array($po->status, ['selesai','dibatalkan']))<p class="text-sm text-gray-400">Tidak ada aksi.</p>@endif
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex justify-between text-sm mb-2"><span class="text-gray-500">Total</span><span class="font-medium">{{ number_format($po->totalNilai(),0,',','.') }}</span></div>
                <div class="flex justify-between text-sm mb-2"><span class="text-gray-500">Dibayar</span><span class="font-medium">{{ number_format($po->totalDibayar(),0,',','.') }}</span></div>
                <div class="flex justify-between text-sm mb-4"><span class="text-gray-500">Sisa</span><span class="font-semibold {{ $po->isLunas() ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($po->sisaTagihan(),0,',','.') }}</span></div>
                @can('purchasing.pay')
                <form method="POST" action="{{ route('purchasing.pay', $po) }}" class="space-y-3 border-t border-gray-100 pt-4">
                    @csrf
                    <select name="skema" class="w-full rounded-lg border-gray-300 text-sm"><option value="termin">Termin</option><option value="tempo">Tempo</option><option value="pelunasan">Pelunasan</option></select>
                    <input type="date" name="tanggal_bayar" value="{{ date('Y-m-d') }}" class="w-full rounded-lg border-gray-300 text-sm" required>
                    <input type="number" step="0.01" name="nominal" placeholder="Nominal" class="w-full rounded-lg border-gray-300 text-sm" required>
                    <button class="w-full px-4 py-2 bg-gray-700 text-white text-sm rounded-lg hover:bg-gray-800">Catat Pembayaran</button>
                </form>
                @endcan
                @if($po->payments->count())
                <ul class="mt-4 space-y-1 text-xs text-gray-500">
                    @foreach($po->payments as $pay)<li>{{ $pay->tanggal_bayar->format('d/m/Y') }} · {{ ucfirst($pay->skema) }} · {{ number_format($pay->nominal,0,',','.') }}</li>@endforeach
                </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

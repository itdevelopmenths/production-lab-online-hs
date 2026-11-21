@php
    use App\Helpers\NumberHelper;
    $canTransfer = auth()->user()->can('transfer.view');
    $inputClass = 'h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white outline-none rounded-[6px]';
@endphp
<x-app-layout title="Barang Rusak">
    <div class="space-y-4">
        <x-page-header
            title="Barang Rusak"
            subtitle="Akumulasi barang rusak hasil QC penerimaan Transfer, per barang dan lokasi gudang"
            :breadcrumbs="['Transfer Barang' => $canTransfer ? route('transfers.index') : null, 'Barang Rusak' => null]"
        />

        {{-- Filter --}}
        <form method="GET" action="{{ route('barang-rusak.index') }}"
              class="bg-white dark:bg-gray-900 p-4 rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 flex flex-wrap items-end gap-3">
            <div>
                <label for="gudang_id" class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Lokasi Gudang</label>
                <select id="gudang_id" name="gudang_id" class="{{ $inputClass }} min-w-44 cursor-pointer">
                    <option value="">— Semua Gudang —</option>
                    @foreach($gudangList as $g)
                        <option value="{{ $g->id }}" @selected($gudangId === $g->id)>{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="tanggal_mulai" class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Dari Tanggal</label>
                <input type="date" id="tanggal_mulai" name="tanggal_mulai" value="{{ $tanggalMulai }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label for="tanggal_selesai" class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Sampai Tanggal</label>
                <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="{{ $tanggalSelesai }}" class="{{ $inputClass }}">
            </div>
            <div class="flex items-center gap-2">
                <x-button type="submit" variant="primary" size="xs">Tampilkan</x-button>
                <x-button href="{{ route('barang-rusak.index') }}" variant="secondary" size="xs">Reset</x-button>
            </div>
            <div class="ml-auto flex items-center gap-4 text-xs text-[#667085] dark:text-gray-400">
                <span>Jenis barang: <strong class="text-[#101828] dark:text-white tabular-nums">{{ $items->count() }}</strong></span>
                <span>Transfer terdampak: <strong class="text-[#101828] dark:text-white tabular-nums">{{ $totalTransfer }}</strong></span>
            </div>
        </form>

        <x-card class="tbl-modern" title="Daftar Barang Rusak"
                subtitle="Klik baris untuk melihat rincian transfer; klik rincian untuk membuka transfernya"
                :noPadding="true">
            <x-slot:icon>
                <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </x-slot:icon>

            <div tabindex="0" role="region" aria-label="Daftar Barang Rusak" class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-[#f8fafb] dark:bg-gray-800/95">
                        <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-xs font-medium">
                            <th class="px-4 py-2.5 w-8"></th>
                            <th class="px-4 py-2.5 text-left w-28">SKU</th>
                            <th class="px-4 py-2.5 text-left">Barang</th>
                            <th class="px-4 py-2.5 text-left">Lokasi Gudang</th>
                            <th class="px-4 py-2.5 text-right">Total Rusak</th>
                            <th class="px-4 py-2.5 text-center">Jumlah Transfer</th>
                            <th class="px-4 py-2.5 text-left">Terakhir</th>
                        </tr>
                    </thead>

                    @forelse($items as $item)
                    <tbody x-data="{ open: false }" class="border-b border-[#E8E8E8] dark:border-gray-800">
                        <tr @click="open = !open" @keydown.enter="open = !open" tabindex="0" role="button" :aria-expanded="open"
                            class="cursor-pointer hover:bg-[#fafafa] dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3 text-[#667085]">
                                <svg class="size-4 transition-transform" :class="open && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </td>
                            <td class="px-4 py-3"><span class="tbl-id tabular-nums">{{ $item->sku }}</span></td>
                            <td class="px-4 py-3"><span class="tbl-primary">{{ $item->produk_nama }}</span></td>
                            <td class="px-4 py-3">{{ $item->gudang_nama }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap tabular-nums font-medium text-red-600 dark:text-red-400">
                                {{ NumberHelper::formatQty($item->total_rusak) }} {{ $item->satuan }}
                            </td>
                            <td class="px-4 py-3 text-center tabular-nums">{{ $item->jumlah_transfer }}</td>
                            <td class="px-4 py-3 whitespace-nowrap tabular-nums">{{ \Illuminate\Support\Carbon::parse($item->terakhir)->format('d/m/Y') }}</td>
                        </tr>
                        <tr x-show="open" x-cloak>
                            <td colspan="7" class="bg-[#fafafa] dark:bg-gray-950/60 px-4 py-3">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-[#667085] dark:text-gray-400">
                                            <th class="py-1.5 pr-3 text-left w-28">Tanggal</th>
                                            <th class="py-1.5 pr-3 text-left">No. Transfer</th>
                                            <th class="py-1.5 pr-3 text-left">Jenis</th>
                                            <th class="py-1.5 pr-3 text-left">Asal → Tujuan</th>
                                            <th class="py-1.5 pr-3 text-right">Dikirim</th>
                                            <th class="py-1.5 pr-3 text-right">Rusak</th>
                                            <th class="py-1.5 text-left">Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($item->details as $d)
                                        <tr @if($canTransfer) onclick="window.location='{{ route('transfers.show', $d->transfer_id) }}'" @endif
                                            class="{{ $canTransfer ? 'cursor-pointer hover:bg-white dark:hover:bg-gray-900' : '' }} border-t border-[#E8E8E8] dark:border-gray-800">
                                            <td class="py-2 pr-3 whitespace-nowrap tabular-nums">{{ \Illuminate\Support\Carbon::parse($d->tanggal)->format('d/m/Y H:i') }}</td>
                                            <td class="py-2 pr-3">
                                                @if($canTransfer)
                                                <a href="{{ route('transfers.show', $d->transfer_id) }}" class="font-medium underline underline-offset-2 text-[#101828] dark:text-white">{{ $d->no_transaksi }}</a>
                                                @else
                                                <span class="font-medium">{{ $d->no_transaksi }}</span>
                                                @endif
                                            </td>
                                            <td class="py-2 pr-3">{{ $jenisLabels[$d->jenis] ?? $d->jenis }}</td>
                                            <td class="py-2 pr-3">{{ $d->gudang_asal_nama ?? '—' }} → {{ $d->gudang_nama }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ NumberHelper::formatQty($d->qty_dikirim) }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap font-medium text-red-600 dark:text-red-400">{{ NumberHelper::formatQty($d->qty_rusak) }}</td>
                                            <td class="py-2">{{ $d->keterangan_rusak ?: '—' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                    @empty
                    <tbody>
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-[#667085] dark:text-gray-400">
                                Tidak ada barang rusak pada periode dan lokasi ini.
                            </td>
                        </tr>
                    </tbody>
                    @endforelse
                </table>
            </div>
        </x-card>
    </div>
</x-app-layout>

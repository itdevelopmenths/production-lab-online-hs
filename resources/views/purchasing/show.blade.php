<x-app-layout title="Detail Purchase Order {{ $purchaseOrder->no_po }}">
    @php
        $po = $purchaseOrder;
        $poBadgeVariant = match($po->status) {
            'diajukan' => 'warning',
            'disetujui' => 'primary',
            'dikirim_ke_gudang' => 'info',
            'selesai' => 'success',
            'dibatalkan' => 'danger',
            default => 'gray',
        };
    @endphp

    <!-- Page Header & Action Tools -->
    <x-page-header
        :title="$po->no_po"
        :subtitle="$po->supplier->nama . ' · Tanggal ' . $po->tanggal->format('d/m/Y') . ($po->dari_analisa ? ' · Dibuat otomatis dari Analisa Stok' : '')"
        :breadcrumbs="[
            'Purchasing' => route('purchasing.index'),
            $po->no_po => null,
        ]"
    >
        <x-slot:actions>
            <x-badge :variant="$poBadgeVariant" :dot="true" size="sm">
                {{ ucwords(str_replace('_',' ',$po->status)) }}
            </x-badge>

            @if($po->dari_analisa)
                <x-badge variant="gold" size="sm">Dari Analisa</x-badge>
            @endif

            <x-button href="{{ route('purchasing.index') }}" variant="secondary" size="xs">
                &larr; Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Kolom Kiri: Tabel Rincian Item PO & Penerimaan Barang -->
        <div class="lg:col-span-2 space-y-5">
            <!-- Tabel Item PO -->
            <x-card title="Rincian Item Purchase Order" :noPadding="true">
                <x-slot:tools>
                    <span class="text-xs text-gray-500 font-mono font-medium">{{ $po->items->count() }} item</span>
                </x-slot:tools>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-100 border-b border-gray-200 text-gray-700 font-semibold">
                            <tr>
                                <th class="py-2.5 px-3.5">Produk</th>
                                <th class="py-2.5 px-3 text-right">Qty</th>
                                <th class="py-2.5 px-3 text-right">Harga Total</th>
                                <th class="py-2.5 px-3 text-right">HPP / Unit</th>
                                <th class="py-2.5 px-3.5 text-right">Diterima</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($po->items as $it)
                            <tr class="hover:bg-gray-50/70 transition">
                                <td class="py-2.5 px-3.5">
                                    <div class="font-bold text-gray-900">{{ $it->produk->nama }}</div>
                                    <div class="font-mono text-[11px] text-gray-400">{{ $it->produk->sku }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium text-gray-900">
                                    {{ rtrim(rtrim(number_format($it->qty, 2, ',', '.'), '0'), ',') }}
                                    <span class="text-gray-400 font-normal">{{ $it->produk->satuan }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold text-gray-900">
                                    Rp {{ number_format($it->harga_total, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono text-gray-600">
                                    Rp {{ number_format($it->hargaPerSatuan(), 2, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3.5 text-right font-medium {{ (float)$it->qtyDiterima() >= (float)$it->qty ? 'text-emerald-700' : 'text-gray-700' }}">
                                    {{ rtrim(rtrim(number_format($it->qtyDiterima(), 2, ',', '.'), '0'), ',') }}
                                    <span class="text-gray-400 font-normal">{{ $it->produk->satuan }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50/75 border-t border-gray-200 font-bold text-gray-900">
                            <tr>
                                <td class="py-2.5 px-3.5">Total Nilai Pesanan</td>
                                <td></td>
                                <td class="py-2.5 px-3 text-right font-mono text-primary-700 text-sm">
                                    Rp {{ number_format($po->totalNilai(), 0, ',', '.') }}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>

            <!-- Konfirmasi Barang Datang (Status dikirim_ke_gudang) -->
            @can('purchasing.receive')
            @if($po->status === 'dikirim_ke_gudang')
            <x-card title="Konfirmasi Barang Datang di Gudang" subtitle="Input kuantitas fisik aktual yang diterima untuk penambahan saldo stok." variant="primary">
                <form method="POST" action="{{ route('purchasing.receive', $po) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Gudang Penerima</label>
                            <select name="gudang_id" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" required>
                                @foreach($gudang as $g)
                                    <option value="{{ $g->id }}">{{ $g->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Terima</label>
                            <input type="date" name="tanggal_terima" value="{{ date('Y-m-d') }}" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Kondisi Fisik</label>
                            <select name="kondisi" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                <option value="baik">Baik (Sesuai Standar)</option>
                                <option value="rusak_sebagian">Rusak Sebagian</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-3 space-y-2">
                        <p class="text-xs font-bold text-gray-700 mb-2 uppercase tracking-wider">Kuantitas Diterima per Item:</p>
                        @foreach($po->items as $i => $it)
                        <div class="flex items-center justify-between p-2.5 bg-gray-50 border border-gray-200 rounded-sm text-xs">
                            <span class="font-medium text-gray-800">{{ $it->produk->nama }} (PO: {{ rtrim(rtrim(number_format($it->qty, 2, ',', '.'), '0'), ',') }} {{ $it->produk->satuan }})</span>
                            <div class="flex items-center gap-2">
                                <input type="hidden" name="items[{{ $i }}][po_item_id]" value="{{ $it->id }}">
                                <input type="number" step="0.01" name="items[{{ $i }}][qty_diterima]" value="{{ $it->qty }}" class="w-28 text-right rounded-sm border-gray-300 text-xs py-1 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" placeholder="0">
                                <span class="text-gray-500 font-normal">{{ $it->produk->satuan }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end pt-2">
                        <x-button type="submit" variant="primary" size="md">
                            Terima & Tambah Stok Gudang
                        </x-button>
                    </div>
                </form>
            </x-card>
            @endif
            @endcan
        </div>

        <!-- Kolom Kanan: Aksi & Status Pembayaran -->
        <div class="space-y-5">
            <!-- Panel Kontrol Status PO -->
            <x-card title="Kontrol Purchase Order" variant="default">
                <div class="space-y-2">
                    @can('purchasing.submit')
                    @if($po->status === 'draft')
                    <form method="POST" action="{{ route('purchasing.submit', $po) }}">
                        @csrf
                        <x-button type="submit" variant="primary" size="md" class="w-full">
                            Ajukan ke Manager
                        </x-button>
                    </form>
                    <p class="text-[11px] text-gray-400 text-center">Mengirim PO ke antrean persetujuan Manager.</p>
                    @endif
                    @endcan

                    @can('purchasing.approve')
                    @if($po->status === 'diajukan')
                    <form method="POST" action="{{ route('purchasing.approve', $po) }}">
                        @csrf
                        <x-button type="submit" variant="success" size="md" class="w-full">
                            Setujui & Kirim ke Gudang
                        </x-button>
                    </form>
                    <p class="text-[11px] text-gray-400 text-center">Wewenang Manager untuk menyetujui pengadaan.</p>
                    @endif
                    @endcan

                    @can('purchasing.cancel')
                    @if(!in_array($po->status, ['selesai', 'dibatalkan']))
                    <form method="POST" action="{{ route('purchasing.cancel', $po) }}" onsubmit="return confirm('Batalkan Purchase Order ini?');" class="pt-2 border-t border-gray-100">
                        @csrf
                        <x-button type="submit" variant="link" size="xs" class="w-full text-rose-600 hover:text-rose-800">
                            Batalkan PO
                        </x-button>
                    </form>
                    @endif
                    @endcan

                    @if(in_array($po->status, ['selesai', 'dibatalkan']))
                    <div class="py-2.5 px-3 bg-gray-50 rounded-sm text-center text-xs text-gray-500 font-medium">
                        Dokumen ini telah berstatus final ({{ ucfirst($po->status) }}).
                    </div>
                    @endif
                </div>
            </x-card>

            <!-- Panel Pembayaran & AP -->
            <x-card title="Status Pembayaran (AP)" variant="default">
                <div class="space-y-2.5 text-xs pb-3 border-b border-gray-100">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total Tagihan</span>
                        <span class="font-bold font-mono text-gray-900">Rp {{ number_format($po->totalNilai(), 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Telah Dibayar</span>
                        <span class="font-bold font-mono text-emerald-700">Rp {{ number_format($po->totalDibayar(), 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between pt-1 border-t border-gray-100">
                        <span class="font-bold text-gray-700">Sisa Tagihan</span>
                        <span class="font-bold font-mono {{ $po->isLunas() ? 'text-emerald-600' : 'text-rose-600' }}">
                            Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                @can('purchasing.pay')
                @if(!$po->isLunas() && !in_array($po->status, ['dibatalkan']))
                <form method="POST" action="{{ route('purchasing.pay', $po) }}" class="space-y-3 pt-3">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">Skema Pembayaran</label>
                        <select name="skema" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                            <option value="termin">Termin</option>
                            <option value="tempo">Tempo</option>
                            <option value="pelunasan">Pelunasan Penuh</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">Tanggal Bayar</label>
                        <input type="date" name="tanggal_bayar" value="{{ date('Y-m-d') }}" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">Nominal (Rp)</label>
                        <input type="number" step="0.01" name="nominal" placeholder="Nominal bayar" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" required>
                    </div>
                    <x-button type="submit" variant="primary" size="sm" class="w-full">
                        Catat Pembayaran
                    </x-button>
                </form>
                @endif
                @endcan

                @if($po->payments->count())
                <div class="mt-4 pt-3 border-t border-gray-100">
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">Riwayat Pembayaran:</p>
                    <ul class="space-y-1.5 text-xs text-gray-600">
                        @foreach($po->payments as $pay)
                        <li class="flex items-center justify-between py-1 px-2 bg-gray-50 rounded-sm">
                            <span>{{ $pay->tanggal_bayar->format('d/m/Y') }} · <span class="capitalize">{{ $pay->skema }}</span></span>
                            <span class="font-bold font-mono text-gray-900">Rp {{ number_format($pay->nominal, 0, ',', '.') }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>

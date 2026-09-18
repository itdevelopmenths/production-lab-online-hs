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
        $canSeePrice = $canSeePrice ?? auth()->user()->can('purchasing.price.view');
    @endphp

    <!-- Page Header & Action Tools -->
    <x-page-header
        :title="$po->no_po . ($po->no_invoice ? ' · ' . $po->no_invoice : '')"
        :subtitle="$po->supplier->nama . ' · Tanggal PO: ' . $po->tanggal->format('d/m/Y') . ($po->gudang ? ' · Lokasi: ' . $po->gudang->nama : '')"
        :breadcrumbs="[
            'Purchasing' => route('purchasing.index'),
            $po->no_po => null,
        ]"
    >
        <x-slot:actions>
            <x-badge :variant="$poBadgeVariant" :dot="true" size="sm">
                {{ ucwords(str_replace('_',' ',$po->status)) }}
            </x-badge>

            @if($canSeePrice && $po->status_pembayaran)
                @php
                    $payBadge = match($po->status_pembayaran) {
                        'overdue' => 'danger',      // Priority 1: Critical (Merah)
                        'belum_lunas' => 'warning', // Priority 2: High Attention (Kuning)
                        'parsial' => 'info',        // Priority 3: In Progress (Biru)
                        'lunas' => 'success',       // Priority 4: Completed (Hijau)
                        default => 'secondary',
                    };
                @endphp
                <x-badge :variant="$payBadge" :dot="true" size="sm">
                    AP: {{ ucwords(str_replace('_',' ',$po->status_pembayaran)) }}
                </x-badge>
            @endif

            @if($po->dari_analisa)
                <x-badge variant="gold" size="sm">Dari Analisa</x-badge>
            @endif

            @can('purchasing.edit')
                @if(in_array($po->status, ['draft', 'diajukan', 'disetujui'], true) && $po->barangDatang->isEmpty())
                    <x-button href="{{ route('purchasing.edit', $po) }}" variant="warning" size="xs">
                        <x-slot:icon>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </x-slot:icon>
                        Edit PO
                    </x-button>
                @endif
            @endcan

            <x-button href="{{ route('purchasing.index') }}" variant="secondary" size="xs">
                &larr; Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <!-- Progress Status Stepper (Requirement 6) -->
    <div class="mb-5">
        <x-purchasing-progress-stepper :po="$po" />
    </div>

    <!-- Metadata Singkat PO -->
    <div class="grid grid-cols-2 {{ $po->skema_bayar === 'tempo' ? 'sm:grid-cols-5' : 'sm:grid-cols-4' }} gap-3 mb-5">
        <div class="bg-white p-3 rounded-sm border border-gray-200 shadow-xs">
            <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">No. Invoice Vendor</span>
            <p class="font-mono font-semibold text-xs text-gray-900 mt-0.5">{{ $po->no_invoice ?? '—' }}</p>
        </div>
        <div class="bg-white p-3 rounded-sm border border-gray-200 shadow-xs">
            <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Lokasi PO (Gudang)</span>
            <p class="font-semibold text-xs text-gray-900 mt-0.5">{{ $po->gudang?->nama ?? 'Semua Gudang' }}</p>
        </div>
        <div class="bg-white p-3 rounded-sm border border-gray-200 shadow-xs">
            <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Estimasi Tiba (ETA)</span>
            <p class="font-mono text-xs {{ $po->eta && $po->eta->isPast() && $po->status !== 'selesai' ? 'text-rose-600 font-bold' : 'text-gray-900' }} mt-0.5">
                {{ $po->eta ? $po->eta->format('d/m/Y') : '—' }}
            </p>
        </div>
        <div class="bg-white p-3 rounded-sm border border-gray-200 shadow-xs">
            <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Skema Bayar</span>
            <p class="font-semibold text-xs text-gray-900 mt-0.5 capitalize">{{ $po->skema_bayar ?? 'cash' }}</p>
        </div>
        @if($po->skema_bayar === 'tempo')
        <div class="bg-white p-3 rounded-sm border {{ $po->tanggal_tempo && $po->tanggal_tempo->isPast() && !$po->isLunas() ? 'border-rose-300 bg-rose-50/40' : 'border-gray-200' }} shadow-xs">
            <span class="text-[10px] uppercase font-bold {{ $po->tanggal_tempo && $po->tanggal_tempo->isPast() && !$po->isLunas() ? 'text-rose-600' : 'text-gray-400' }} tracking-wider">Deadline Tempo</span>
            <p class="font-mono text-xs {{ $po->tanggal_tempo && $po->tanggal_tempo->isPast() && !$po->isLunas() ? 'text-rose-600 font-bold' : 'text-gray-900 font-semibold' }} mt-0.5">
                {{ $po->tanggal_tempo ? $po->tanggal_tempo->format('d/m/Y') : ($po->eta ? $po->eta->format('d/m/Y') : '—') }}
                @if($po->tanggal_tempo && $po->tanggal_tempo->isPast() && !$po->isLunas())
                    <span class="text-[9px] uppercase font-bold text-rose-700 bg-rose-100 px-1 py-0.2 rounded ml-1">Overdue</span>
                @endif
            </p>
        </div>
        @endif
    </div>

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
                        <thead class="bg-gray-100 border-b border-gray-200 text-gray-700 font-semibold text-[11px] uppercase">
                            <tr>
                                <th class="py-2.5 px-3">Bahan Baku</th>
                                <th class="py-2.5 px-3 text-right">Qty PO</th>
                                @if($canSeePrice)
                                    <th class="py-2.5 px-3 text-right">Total Kotor</th>
                                    <th class="py-2.5 px-2 text-right">Diskon</th>
                                    <th class="py-2.5 px-2 text-right">PPN</th>
                                    <th class="py-2.5 px-2 text-right">Ongkir</th>
                                    <th class="py-2.5 px-3 text-right">Net Total</th>
                                    <th class="py-2.5 px-3 text-right bg-primary-50/50 text-primary-900">HPP / Unit</th>
                                @endif
                                <th class="py-2.5 px-3.5 text-right">Diterima Fisik</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($po->items as $it)
                            <tr class="hover:bg-gray-50/70 transition">
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-gray-900">{{ $it->produk->nama }}</div>
                                    <div class="font-mono text-[11px] text-gray-400">{{ $it->produk->sku }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-medium text-gray-900">
                                    @if($it->qty_satuan_beli && $it->satuan_beli && strcasecmp($it->satuan_beli, $it->produk?->satuan ?? '') !== 0)
                                        <div class="text-gray-900 font-semibold">
                                            {{ rtrim(rtrim(number_format($it->qty_satuan_beli, 2, ',', '.'), '0'), ',') }}
                                            <span class="text-xs font-normal text-gray-600">{{ $it->satuan_beli }}</span>
                                        </div>
                                        <div class="text-[10px] text-primary-700 bg-primary-50 px-1.5 py-0.5 rounded border border-primary-100 inline-block mt-0.5">
                                            = {{ rtrim(rtrim(number_format($it->qty, 2, ',', '.'), '0'), ',') }} {{ $it->produk?->satuan }}
                                        </div>
                                    @else
                                        <div>
                                            {{ rtrim(rtrim(number_format($it->qty, 2, ',', '.'), '0'), ',') }}
                                            <span class="text-gray-400 font-normal text-[10px]">{{ $it->produk?->satuan }}</span>
                                        </div>
                                    @endif
                                </td>
                                @if($canSeePrice)
                                    <td class="py-2.5 px-3 text-right font-mono text-gray-700">
                                        Rp {{ number_format($it->harga_total, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-2 text-right font-mono text-rose-600">
                                        {{ (float)$it->diskon > 0 ? '-Rp ' . number_format($it->diskon, 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="py-2.5 px-2 text-right font-mono text-gray-600">
                                        {{ (float)$it->ppn > 0 ? '+Rp ' . number_format($it->ppn, 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="py-2.5 px-2 text-right font-mono text-gray-600">
                                        {{ (float)$it->ongkir > 0 ? '+Rp ' . number_format($it->ongkir, 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-semibold text-gray-900">
                                        Rp {{ number_format($it->netTotal(), abs($it->netTotal() - round($it->netTotal())) < 0.00001 ? 0 : 2, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-primary-800 bg-primary-50/30" title="HPP / Satuan Dasar ({{ $it->produk?->satuan }}): {{ $it->formattedHpp() }}">
                                        <div>{{ $it->formattedHpp() }}</div>
                                        @if($it->qty_satuan_beli && $it->satuan_beli && strcasecmp($it->satuan_beli, $it->produk?->satuan ?? '') !== 0 && (float)$it->qty_satuan_beli > 0)
                                            @php
                                                $hppPerSatuanBeli = $it->netTotal() / (float)$it->qty_satuan_beli;
                                            @endphp
                                            <div class="text-[9px] font-normal text-primary-600">
                                                (Rp {{ number_format($hppPerSatuanBeli, 0, ',', '.') }} / {{ $it->satuan_beli }})
                                            </div>
                                        @endif
                                    </td>
                                @endif
                                <td class="py-2.5 px-3.5 text-right font-mono font-medium {{ (float)$it->qtyDiterima() >= (float)$it->qty ? 'text-emerald-700 font-bold' : 'text-gray-700' }}">
                                    {{ rtrim(rtrim(number_format($it->qtyDiterima(), 2, ',', '.'), '0'), ',') }}
                                    <span class="text-gray-400 font-normal text-[10px]">{{ $it->produk->satuan }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        @if($canSeePrice)
                        <tfoot class="bg-gray-50/80 border-t border-gray-200 font-bold text-gray-900 text-xs">
                            <tr>
                                <td class="py-2.5 px-3">Grand Total Nilai Pesanan</td>
                                <td></td>
                                <td class="py-2.5 px-3 text-right font-mono text-gray-700">
                                    Rp {{ number_format($po->subtotal_produk ?: $po->items->sum('harga_total'), 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-2 text-right font-mono text-rose-600">
                                    {{ (float)$po->diskon_total > 0 ? '-Rp ' . number_format($po->diskon_total, 0, ',', '.') : '—' }}
                                </td>
                                <td class="py-2.5 px-2 text-right font-mono text-gray-700">
                                    {{ (float)$po->ppn_nominal > 0 ? '+Rp ' . number_format($po->ppn_nominal, 0, ',', '.') : '—' }}
                                </td>
                                <td class="py-2.5 px-2 text-right font-mono text-gray-700">
                                    {{ (float)$po->ongkos_kirim > 0 ? '+Rp ' . number_format($po->ongkos_kirim, 0, ',', '.') : '—' }}
                                </td>
                                <td colspan="2" class="py-2.5 px-3 text-right font-mono text-primary-700 text-sm">
                                    Rp {{ number_format($po->totalNilai(), 0, ',', '.') }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </x-card>

            <!-- Riwayat Penerimaan Barang Datang & Selisih (Requirement 13) -->
            @if($po->barangDatang->count())
            <x-card title="Riwayat Penerimaan Barang di Gudang" :noPadding="true">
                <div class="divide-y divide-gray-200">
                    @foreach($po->barangDatang as $bardat)
                    <div class="p-3.5 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-gray-900">Terima: {{ $bardat->tanggal_terima->format('d/m/Y') }}</span>
                                <x-badge :variant="$bardat->kondisi === 'baik' ? 'success' : 'warning'" size="xs">
                                    Kondisi {{ ucfirst(str_replace('_',' ', $bardat->kondisi)) }}
                                </x-badge>
                            </div>
                            <span class="text-gray-400 text-[11px]">Dicatat oleh: {{ $bardat->creator->name ?? 'Gudang' }}</span>
                        </div>
                        <div class="bg-gray-50 rounded-xs p-2 border border-gray-100">
                            <table class="w-full text-xs text-left">
                                <thead class="text-gray-500 text-[10px] uppercase">
                                    <tr>
                                        <th class="py-1">Bahan</th>
                                        <th class="py-1 text-right">Diterima</th>
                                        <th class="py-1 text-right">Selisih</th>
                                        <th class="py-1 pl-3">Keterangan Selisih</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($bardat->items as $bItem)
                                    @php
                                        $poItem = $bItem->relationLoaded('poItem')
                                            ? $bItem->poItem
                                            : $po->items->firstWhere('id', $bItem->po_item_id);
                                        $namaProduk = $poItem?->produk?->nama ?? '—';
                                    @endphp
                                    <tr>
                                        <td class="py-1 font-medium text-gray-800">{{ $namaProduk }}</td>
                                        <td class="py-1 text-right font-mono font-semibold text-gray-900">
                                            {{ rtrim(rtrim(number_format($bItem->qty_diterima, 2, ',', '.'), '0'), ',') }}
                                        </td>
                                        <td class="py-1 text-right font-mono">
                                            @if((float)$bItem->selisih < 0)
                                                <span class="text-rose-600 font-bold">{{ number_format($bItem->selisih, 2, ',', '.') }} (Kurang)</span>
                                            @elseif((float)$bItem->selisih > 0)
                                                <span class="text-blue-600 font-bold">+{{ number_format($bItem->selisih, 2, ',', '.') }} (Lebih)</span>
                                            @else
                                                <span class="text-emerald-600">Sesuai</span>
                                            @endif
                                        </td>
                                        <td class="py-1 pl-3 text-gray-600 italic">{{ $bItem->keterangan_selisih ?? '—' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endforeach
                </div>
            </x-card>
            @endif

            <!-- Konfirmasi Barang Datang (Status dikirim_ke_gudang) -->
            @can('purchasing.receive')
            @if($po->status === 'dikirim_ke_gudang')
            <x-card title="Konfirmasi Barang Datang di Gudang" subtitle="Input kuantitas fisik aktual yang diterima. Jika ada selisih, wajib isi kolom keterangan." variant="primary">
                <form method="POST" action="{{ route('purchasing.receive', $po) }}" class="space-y-4" x-data="bardatForm()">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Gudang Penerima</label>
                            @php
                                $targetGudang = $po->gudang ?? $gudang->firstWhere('id', $po->gudang_id) ?? $gudang->first();
                            @endphp
                            <input type="hidden" name="gudang_id" value="{{ $targetGudang?->id }}">
                            <input type="text" value="{{ $targetGudang?->nama ?? '—' }}" class="w-full rounded-sm border-gray-300 bg-gray-100 text-xs py-1.5 px-2.5 text-gray-700 cursor-not-allowed focus:outline-none" readonly>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Terima <span class="text-rose-500">*</span></label>
                            <input type="date" name="tanggal_terima" value="{{ date('Y-m-d') }}" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Kondisi Fisik <span class="text-rose-500">*</span></label>
                            <select name="kondisi" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                <option value="baik">Baik (Sesuai Standar)</option>
                                <option value="rusak_sebagian">Rusak Sebagian</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-3 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <p class="text-xs font-bold text-gray-700 uppercase tracking-wider">Kuantitas Diterima & Pengecekan Selisih:</p>
                            <span class="text-[10px] text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
                                Penerimaan gudang dicatat dalam satuan dasar (ml / gr / pcs)
                            </span>
                        </div>
                        
                        @foreach($po->items as $i => $it)
                        <div class="p-3 bg-gray-50 border border-gray-200 rounded-sm text-xs space-y-2">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div>
                                    <span class="font-bold text-gray-900">{{ $it->produk->nama }}</span>
                                    <span class="font-mono text-gray-500 text-[11px]">
                                        ({{ $it->produk->sku }}) · Order: 
                                        @if($it->qty_satuan_beli && $it->satuan_beli && strcasecmp($it->satuan_beli, $it->produk?->satuan ?? '') !== 0)
                                            <span class="font-semibold text-gray-700">{{ rtrim(rtrim(number_format($it->qty_satuan_beli, 2, ',', '.'), '0'), ',') }} {{ $it->satuan_beli }}</span>
                                            ({{ rtrim(rtrim(number_format($it->qty, 2, ',', '.'), '0'), ',') }} {{ $it->produk->satuan }})
                                        @else
                                            {{ rtrim(rtrim(number_format($it->qty, 2, ',', '.'), '0'), ',') }} {{ $it->produk->satuan }}
                                        @endif
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="hidden" name="items[{{ $i }}][po_item_id]" value="{{ $it->id }}">
                                    <span class="text-gray-600 font-medium">Qty Terima:</span>
                                    <input type="number" step="0.01" min="0"
                                        name="items[{{ $i }}][qty_diterima]"
                                        x-model.number="receiptItems[{{ $i }}].qty_terima"
                                        class="w-24 text-right rounded-sm border-gray-300 text-xs py-1 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-bold"
                                        required>
                                    <span class="text-gray-500">{{ $it->produk->satuan }}</span>
                                    
                                    <!-- Badge Selisih Real-Time (Requirement 13) -->
                                    <template x-if="getDiff({{ $i }}) < 0">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-rose-100 text-rose-800" x-text="'Kurang: ' + getDiff({{ $i }})"></span>
                                    </template>
                                    <template x-if="getDiff({{ $i }}) > 0">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-blue-100 text-blue-800" x-text="'Lebih: +' + getDiff({{ $i }})"></span>
                                    </template>
                                    <template x-if="getDiff({{ $i }}) === 0">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-emerald-100 text-emerald-800">✓ Sesuai</span>
                                    </template>
                                </div>
                            </div>

                            <!-- Kolom Keterangan Selisih (Requirement 13) -->
                            <div x-show="getDiff({{ $i }}) !== 0" x-cloak class="pt-2 border-t border-gray-200">
                                <label class="block text-[11px] font-semibold text-rose-700 mb-1">
                                    Keterangan Alasan Selisih <span class="text-rose-500">*</span>:
                                </label>
                                <input type="text"
                                    name="items[{{ $i }}][keterangan_selisih]"
                                    :required="getDiff({{ $i }}) !== 0"
                                    placeholder="Contoh: Bocor saat pengiriman / Stok supplier tidak lengkap"
                                    class="w-full rounded-sm border-rose-300 text-xs py-1 px-2 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 bg-white">
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end pt-2">
                        <x-button type="submit" variant="primary" size="md">
                            Konfirmasi Terima & Tambah Stok Gudang
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

                    @can('purchasing.edit')
                    @if(in_array($po->status, ['draft', 'diajukan', 'disetujui'], true) && $po->barangDatang->isEmpty())
                    <x-button href="{{ route('purchasing.edit', $po) }}" variant="secondary" size="md" class="w-full">
                        <x-slot:icon>
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </x-slot:icon>
                        Edit Purchase Order
                    </x-button>
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

            <!-- Panel Pembayaran & AP (Hanya untuk yang memiliki wewenang purchasing.price.view) -->
            @if($canSeePrice)
            <x-card title="Status Pembayaran (AP)" variant="default" id="form-pembayaran">
                <div class="space-y-2.5 text-xs pb-3 border-b border-gray-100">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Status Pembayaran (AP)</span>
                        <span>
                            <x-badge :variant="$payBadge ?? 'secondary'" :dot="true" size="xs">
                                {{ ucwords(str_replace('_',' ',$po->status_pembayaran ?? 'belum_lunas')) }}
                            </x-badge>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Skema Transaksi</span>
                        <span class="font-semibold text-gray-800">
                            @if($po->skema_bayar === 'cash')
                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded text-[11px] font-medium">Tunai (Cash / Penuh)</span>
                            @elseif($po->skema_bayar === 'tempo')
                                <span class="bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded text-[11px] font-medium">Tempo (Jatuh Tempo Tunggal)</span>
                            @else
                                <span class="bg-primary-50 text-primary-700 border border-primary-200 px-2 py-0.5 rounded text-[11px] font-medium">Termin (Cicilan Bertahap)</span>
                            @endif
                        </span>
                    </div>
                    @if($po->skema_bayar === 'tempo')
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-500">Jatuh Tempo Pembayaran</span>
                        <span class="font-mono font-bold {{ $po->tanggal_tempo && $po->tanggal_tempo->isPast() && !$po->isLunas() ? 'text-rose-600' : 'text-gray-900' }}">
                            {{ $po->tanggal_tempo ? $po->tanggal_tempo->format('d/m/Y') : ($po->eta ? $po->eta->format('d/m/Y') : '—') }}
                            @if($po->tanggal_tempo && $po->tanggal_tempo->isPast() && !$po->isLunas())
                                <span class="text-[10px] text-rose-600 font-semibold">(Overdue)</span>
                            @endif
                        </span>
                    </div>
                    @endif
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

                <!-- Daftar Termin Tagihan (Hanya ditampilkan jika skema termin dan ada jadwal) -->
                @if($po->skema_bayar === 'termin' && $po->termins->count())
                <div class="py-3 border-b border-gray-100">
                    <p class="text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-2">Jadwal Termin Tagihan:</p>
                    <div class="space-y-1.5">
                        @foreach($po->termins as $tm)
                        @php
                            $tmBadge = match($tm->status) {
                                'overdue' => 'danger',
                                'belum_dibayar' => 'warning',
                                'parsial' => 'info',
                                'lunas' => 'success',
                                default => 'secondary',
                            };
                        @endphp
                        <div class="p-2 bg-gray-50 rounded-sm border border-gray-100 text-xs flex items-center justify-between">
                            <div>
                                <div class="font-bold text-gray-800">Termin {{ $tm->termin_ke }} ({{ $tm->keterangan ?? 'Tagihan' }})</div>
                                <div class="text-[10px] text-gray-500">Tempo: {{ $tm->tanggal_tempo->format('d/m/Y') }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono font-bold text-gray-900">Rp {{ number_format($tm->nominal_tagihan, 0, ',', '.') }}</div>
                                <x-badge :variant="$tmBadge" :dot="true" size="xs">{{ ucfirst(str_replace('_',' ', $tm->status)) }}</x-badge>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @can('purchasing.pay')
                @if(!$po->isLunas() && !in_array($po->status, ['dibatalkan']))
                @php
                    $defaultPaySkema = match($po->skema_bayar) {
                        'cash' => 'cash',
                        'tempo' => 'tempo',
                        'termin' => 'termin',
                        default => 'tempo',
                    };
                    $nextUnpaidTermin = $po->termins->where('status', '!=', 'lunas')->first();
                    $initialPayNominal = ($po->skema_bayar === 'termin' && $nextUnpaidTermin)
                        ? (float) $nextUnpaidTermin->sisaNominal()
                        : (float) $po->sisaTagihan();
                @endphp
                <form method="POST" action="{{ route('purchasing.pay', $po) }}" class="space-y-3 pt-3" x-data="payForm({{ $po->sisaTagihan() }}, '{{ $defaultPaySkema }}', {{ $initialPayNominal }})">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">Skema Pembayaran</label>
                        <select name="skema" x-model="skema" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                            @if($po->skema_bayar === 'cash')
                                <option value="cash">Tunai (Cash / Transfer Penuh - Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }})</option>
                                <option value="pelunasan">Pelunasan Penuh (Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }})</option>
                            @elseif($po->skema_bayar === 'tempo')
                                <option value="tempo">Tempo (Pembayaran Parsial / Cicilan)</option>
                                <option value="pelunasan">Pelunasan Penuh (Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }})</option>
                            @else
                                <option value="termin">Termin (Pembayaran Cicilan)</option>
                                <option value="pelunasan">Pelunasan Penuh (Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }})</option>
                            @endif
                        </select>
                    </div>

                    @if($po->skema_bayar === 'termin' && $po->termins->where('status', '!=', 'lunas')->count())
                    <div x-show="skema === 'termin'">
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">Target Termin (Opsional)</label>
                        <select name="termin_id" @change="onTerminChange($event)" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                            <option value="">— Alokasikan Otomatis (FIFO) —</option>
                            @foreach($po->termins->where('status', '!=', 'lunas') as $tOpt)
                                <option value="{{ $tOpt->id }}" data-sisa="{{ $tOpt->sisaNominal() }}">
                                    Termin {{ $tOpt->termin_ke }} (Sisa: Rp {{ number_format($tOpt->sisaNominal(), 0, ',', '.') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">Tanggal Bayar</label>
                        <input type="date" name="tanggal_bayar" value="{{ date('Y-m-d') }}" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-700 mb-1">Nominal Bayar (Rp)</label>
                        <input type="hidden" name="nominal" :value="nominal">
                        <input type="text"
                            inputmode="numeric"
                            :value="formatThousand(nominal)"
                            @input="updateNominal($event)"
                            placeholder="Nominal bayar"
                            class="w-full rounded-sm border-gray-300 text-xs py-1.5 font-mono text-right font-bold focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                            required>
                        <span x-show="nominal > sisa" class="text-[10px] text-rose-600 mt-0.5 block font-semibold">
                            ⚠️ Nominal melebihi sisa tagihan (Maks: Rp <span x-text="formatThousand(sisa)"></span>)
                        </span>
                    </div>
                    <x-button type="submit" variant="primary" size="sm" class="w-full">
                        Catat Pembayaran
                    </x-button>
                </form>
                @endif
                @endcan

                @if($po->payments->count())
                <div class="mt-4 pt-3 border-t border-gray-100">
                    <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">Riwayat Transaksi Bayar:</p>
                    <ul class="space-y-1.5 text-xs text-gray-600">
                        @foreach($po->payments as $pay)
                        <li class="flex items-center justify-between py-1.5 px-2 bg-gray-50 rounded-sm border border-gray-100">
                            <div>
                                <span class="font-medium text-gray-800">{{ $pay->tanggal_bayar->format('d/m/Y') }}</span>
                                <span class="text-[10px] text-gray-400 capitalize block">{{ $pay->skema }}</span>
                            </div>
                            <span class="font-bold font-mono text-gray-900">Rp {{ number_format($pay->nominal, 0, ',', '.') }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </x-card>
            @endif
        </div>
    </div>

    <!-- Log & Jejak Riwayat Audit Dokumen PO -->
    @canany(['purchasing.audit', 'audit.view'])
    <div class="mt-6">
        <x-operational-audit-history :auditable="$po" title="Jejak Riwayat Audit Dokumen PO {{ $po->no_po }}" />
    </div>
    @endcanany

    @push('scripts')
    <script>
        function bardatForm() {
            return {
                receiptItems: [
                    @foreach($po->items as $it)
                    { qty_po: {{ (float)$it->qty }}, qty_terima: {{ (float)$it->qty }} },
                    @endforeach
                ],
                getDiff(idx) {
                    const item = this.receiptItems[idx];
                    if (!item) return 0;
                    const diff = (parseFloat(item.qty_terima) || 0) - (parseFloat(item.qty_po) || 0);
                    return Math.round(diff * 100) / 100;
                }
            };
        }

        function payForm(sisaTagihan, defaultSkema, defaultNominal) {
            return {
                sisa: sisaTagihan,
                skema: defaultSkema || 'tempo',
                nominal: defaultNominal !== undefined && defaultNominal !== null ? defaultNominal : sisaTagihan,
                formatThousand(val) {
                    if (val === '' || val === null || val === undefined) return '';
                    const clean = val.toString().replace(/\D/g, '');
                    return clean ? clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
                },
                updateNominal(event) {
                    let clean = event.target.value.replace(/\D/g, '');
                    this.nominal = clean ? parseInt(clean, 10) : 0;
                    event.target.value = this.formatThousand(this.nominal);
                },
                onTerminChange(event) {
                    const selected = event.target.selectedOptions[0];
                    if (selected && selected.dataset.sisa) {
                        const sisaTermin = parseFloat(selected.dataset.sisa) || 0;
                        if (sisaTermin > 0) {
                            this.nominal = sisaTermin;
                        }
                    }
                },
                init() {
                    this.$watch('skema', (val) => {
                        if (val === 'pelunasan' || val === 'cash') {
                            this.nominal = this.sisa;
                        }
                    });
                }
            };
        }
    </script>
    @endpush
</x-app-layout>

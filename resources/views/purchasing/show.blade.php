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
        $hasStages = $po->relationLoaded('stages') && $po->stages->isNotEmpty();
    @endphp

    <div x-data="{ openDirectEditModal: false, openRevisiModal: false, openInvalidateModal: false }">
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
                @if(($po->stage_aktif === null || $po->stage_aktif === \App\Models\PoStage::STAGE_1) && in_array($po->status, ['draft', 'diajukan', 'disetujui'], true) && $po->barangDatang->isEmpty())
                    <x-button href="{{ route('purchasing.edit', $po) }}" variant="warning" size="xs">
                        <x-slot:icon>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </x-slot:icon>
                        Edit PO
                    </x-button>
                @endif

                @if($hasStages && !in_array($po->status, ['selesai', 'dibatalkan']))
                    <button type="button" @click="openDirectEditModal = true" class="px-2.5 py-1 text-xs rounded-md border border-border bg-card text-foreground hover:bg-muted font-medium cursor-pointer shadow-xs">
                        Direct Edit
                    </button>
                    @if(!in_array($po->stage_aktif, [\App\Models\PoStage::STAGE_8, \App\Models\PoStage::STAGE_9]))
                        <button type="button" @click="openRevisiModal = true" class="px-2.5 py-1 text-xs rounded-md border border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100 font-medium cursor-pointer shadow-xs">
                            Ajukan Revisi
                        </button>
                    @endif
                @endif
            @endcan

            <x-button href="{{ route('purchasing.index') }}" variant="secondary" size="xs">
                &larr; Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <!-- Progress Status Stepper (Requirement 6) -->
    <div class="mb-5">
        <x-purchasing-progress-stepper :po="$po" :stageBenchmarks="$stageBenchmarks ?? null" />
    </div>

    {{-- Panel Approval Usulan Revisi Komitmen (PRD v2.4) --}}
    @if($po->relationLoaded('revisis') && $po->revisis->where('status', 'menunggu')->count() > 0)
    <div class="mb-5 bg-amber-50/90 border-2 border-amber-400 rounded-xl p-4 shadow-xs space-y-3">
        <div class="flex items-center gap-2 text-amber-950 font-bold text-xs">
            <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
            <span>Antrean Persetujuan Revisi Komitmen (Revisi ke-{{ $po->revisis->where('status', 'menunggu')->first()->revisi_ke }})</span>
        </div>
        @foreach($po->revisis->where('status', 'menunggu') as $rev)
            <div class="bg-white rounded-lg p-3.5 border border-amber-200 text-xs space-y-2.5">
                <div class="flex flex-wrap justify-between items-center gap-2">
                    <div>
                        <span class="text-muted-foreground">Diajukan oleh:</span> <strong>{{ $rev->requester?->name ?? 'Purchasing' }}</strong>
                        <span class="text-muted-foreground ml-2">pada:</span> {{ $rev->created_at->format('d/m/Y H:i') }}
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Menunggu Approval Manager</span>
                </div>
                <div class="p-2.5 bg-amber-50/60 rounded border border-amber-100 text-amber-900">
                    <strong>Alasan Revisi:</strong> {{ $rev->alasan }}
                </div>

                {{-- Tabel Perbandingan Data Usulan vs Sebelum --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-[11px] text-left border border-border">
                        <thead class="bg-muted text-muted-foreground font-semibold">
                            <tr>
                                <th class="p-2 border-b">Parameter</th>
                                <th class="p-2 border-b">Data Sebelumnya</th>
                                <th class="p-2 border-b text-blue-700 bg-blue-50/50">Data Usulan Baru</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @php
                                $dSeb = $rev->data_sebelum ?? [];
                                $dUsul = $rev->data_usulan ?? [];
                            @endphp
                            @foreach($dUsul as $k => $val)
                                @if($k === 'items' && is_array($val))
                                    <tr>
                                        <td class="p-2 font-bold" colspan="3">Item Bahan Baku / Harga:</td>
                                    </tr>
                                    @foreach($val as $itemIdx => $itUsul)
                                        @php
                                            $poItemMatch = $po->items->firstWhere('id', $itUsul['po_item_id'] ?? $itemIdx) ?? $po->items->first();
                                            $namaIt = $poItemMatch?->produk?->nama ?? 'Item #' . $itemIdx;
                                        @endphp
                                        <tr class="bg-muted/10">
                                            <td class="p-2 pl-4">{{ $namaIt }}</td>
                                            <td class="p-2 font-mono">
                                                Qty: {{ $poItemMatch?->qty }}
                                                @if($canSeePrice)
                                                    · Rp {{ number_format((float) ($poItemMatch?->harga_total ?? 0), 0, ',', '.') }}
                                                @endif
                                            </td>
                                            <td class="p-2 font-mono font-bold text-blue-700 bg-blue-50/40">
                                                Qty: {{ $itUsul['qty'] ?? '—' }}
                                                @if($canSeePrice && isset($itUsul['harga_total']))
                                                    · Rp {{ number_format((float) $itUsul['harga_total'], 0, ',', '.') }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td class="p-2 font-medium">{{ ucwords(str_replace('_', ' ', $k)) }}</td>
                                        <td class="p-2 font-mono text-muted-foreground">{{ is_array($dSeb[$k] ?? null) ? json_encode($dSeb[$k]) : ($dSeb[$k] ?? '—') }}</td>
                                        <td class="p-2 font-mono font-bold text-blue-700 bg-blue-50/40">{{ is_array($val) ? json_encode($val) : $val }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @can('purchasing.approve')
                <div class="pt-2 flex items-center justify-end gap-2" x-data="{ openRejectRev: false, alasanReject: '' }">
                    <form method="POST" action="{{ route('purchasing.revisi.approve', $rev) }}" class="inline">
                        @csrf
                        <x-button type="submit" variant="success" size="xs">
                            ✓ Setujui Revisi Komitmen
                        </x-button>
                    </form>

                    <button type="button" @click="openRejectRev = true" class="px-2.5 py-1 text-xs rounded-md bg-destructive text-destructive-foreground hover:bg-destructive/90 font-medium">
                        ✕ Tolak Usulan
                    </button>

                    <div x-show="openRejectRev" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" x-cloak>
                        <div class="bg-card rounded-xl border border-border p-4 max-w-sm w-full space-y-3">
                            <h4 class="font-bold text-xs">Alasan Penolakan Revisi</h4>
                            <form method="POST" action="{{ route('purchasing.revisi.reject', $rev) }}" class="space-y-3">
                                @csrf
                                <textarea name="alasan" x-model="alasanReject" required minlength="3" placeholder="Masukkan alasan penolakan..." class="w-full text-xs p-2 rounded border border-border bg-background" rows="3"></textarea>
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="openRejectRev = false" class="px-2 py-1 text-xs text-muted-foreground">Batal</button>
                                    <button type="submit" :disabled="alasanReject.trim().length < 3" class="px-2.5 py-1 text-xs rounded bg-destructive text-destructive-foreground disabled:opacity-50">Tolak Usulan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endcan
            </div>
        @endforeach
    </div>
    @endif

    <!-- Metadata Singkat PO -->
    <div class="grid grid-cols-2 {{ $po->skema_bayar === 'tempo' ? 'sm:grid-cols-5' : 'sm:grid-cols-4' }} gap-3 mb-5">
        <div class="bg-card p-3.5 rounded-xl border border-border shadow-xs">
            <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">No. Invoice Vendor</span>
            <p class="font-mono font-semibold text-xs text-foreground mt-0.5">{{ $po->no_invoice ?? '—' }}</p>
        </div>
        <div class="bg-card p-3.5 rounded-xl border border-border shadow-xs">
            <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">Lokasi PO (Gudang)</span>
            <p class="font-semibold text-xs text-foreground mt-0.5">{{ $po->gudang?->nama ?? 'Semua Gudang' }}</p>
        </div>
        <div class="bg-card p-3.5 rounded-xl border border-border shadow-xs">
            <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">Estimasi Tiba (ETA)</span>
            <p class="font-mono text-xs {{ $po->eta && $po->eta->isPast() && $po->status !== 'selesai' ? 'text-destructive font-bold' : 'text-foreground' }} mt-0.5">
                {{ $po->eta ? $po->eta->format('d/m/Y') : '—' }}
            </p>
        </div>
        <div class="bg-card p-3.5 rounded-xl border border-border shadow-xs">
            <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">Skema Bayar</span>
            <p class="font-semibold text-xs text-foreground mt-0.5 capitalize">{{ $po->skema_bayar ?? 'cash' }}</p>
        </div>
        @if($po->skema_bayar === 'tempo')
        <div class="bg-card p-3.5 rounded-xl border {{ $po->tanggal_tempo && $po->tanggal_tempo->isPast() && !$po->isLunas() ? 'border-destructive/30 bg-destructive/10' : 'border-border' }} shadow-xs">
            <span class="text-[10px] uppercase font-bold {{ $po->tanggal_tempo && $po->tanggal_tempo->isPast() && !$po->isLunas() ? 'text-destructive' : 'text-muted-foreground' }} tracking-wider">Deadline Tempo</span>
            <p class="font-mono text-xs {{ $po->tanggal_tempo && $po->tanggal_tempo->isPast() && !$po->isLunas() ? 'text-destructive font-bold' : 'text-foreground font-semibold' }} mt-0.5">
                {{ $po->tanggal_tempo ? $po->tanggal_tempo->format('d/m/Y') : ($po->eta ? $po->eta->format('d/m/Y') : '—') }}
                @if($po->tanggal_tempo && $po->tanggal_tempo->isPast() && !$po->isLunas())
                    <span class="text-[9px] uppercase font-bold text-destructive bg-destructive/10 px-1 py-0.5 rounded ml-1 border border-destructive/20">Overdue</span>
                @endif
            </p>
        </div>
        @endif
    </div>

    @if($po->catatan)
    <div class="mb-5 bg-muted/40 border border-border rounded-xl p-4 flex items-start gap-3 shadow-xs">
        <svg class="size-4 text-muted-foreground mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
        <div class="text-xs">
            <span class="font-semibold text-foreground block mb-0.5">Catatan / Instruksi Khusus PO:</span>
            <p class="text-muted-foreground whitespace-pre-line">{{ $po->catatan }}</p>
        </div>
    </div>
    @endif

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
                                    <th class="py-2.5 px-2 text-right">Adjustment</th>
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
                                        <div class="text-[10px] text-foreground bg-muted px-1.5 py-0.5 rounded border border-border inline-block mt-0.5">
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
                                    <td class="py-2.5 px-2 text-right font-mono {{ (float)$it->adjustment < 0 ? 'text-rose-600' : 'text-gray-600' }}">
                                        @if((float)$it->adjustment != 0)
                                            {{ (float)$it->adjustment > 0 ? '+Rp ' . number_format($it->adjustment, 0, ',', '.') : '-Rp ' . number_format(abs($it->adjustment), 0, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-semibold text-foreground">
                                        Rp {{ number_format($it->netTotal(), abs($it->netTotal() - round($it->netTotal())) < 0.00001 ? 0 : 2, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-foreground bg-muted/40" title="HPP / Satuan Dasar ({{ $it->produk?->satuan }}): {{ $it->formattedHpp() }}">
                                        <div>{{ $it->formattedHpp() }}</div>
                                        @if($it->qty_satuan_beli && $it->satuan_beli && strcasecmp($it->satuan_beli, $it->produk?->satuan ?? '') !== 0 && (float)$it->qty_satuan_beli > 0)
                                            @php
                                                $hppPerSatuanBeli = $it->netTotal() / (float)$it->qty_satuan_beli;
                                            @endphp
                                            <div class="text-[9px] font-normal text-muted-foreground">
                                                (Rp {{ number_format($hppPerSatuanBeli, 0, ',', '.') }} / {{ $it->satuan_beli }})
                                            </div>
                                        @endif
                                    </td>
                                @endif
                                <td class="py-2.5 px-3.5 text-right font-mono font-medium {{ (float)$it->qtyDiterima() >= (float)$it->qty ? 'text-emerald-700 dark:text-emerald-400 font-bold' : 'text-foreground' }}">
                                    {{ rtrim(rtrim(number_format($it->qtyDiterima(), 2, ',', '.'), '0'), ',') }}
                                    <span class="text-muted-foreground font-normal text-[10px]">{{ $it->produk->satuan }}</span>
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
                                <td class="py-2.5 px-2 text-right font-mono {{ (float)$po->adjustment < 0 ? 'text-rose-600' : 'text-gray-700' }}">
                                    @if((float)$po->adjustment != 0)
                                        {{ (float)$po->adjustment > 0 ? '+Rp ' . number_format($po->adjustment, 0, ',', '.') : '-Rp ' . number_format(abs($po->adjustment), 0, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td colspan="2" class="py-2.5 px-3 text-right font-mono text-foreground font-bold text-sm">
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

            {{-- Formulir Pemeriksaan Fisik & Unloading (Stage 8 PRD v2.4) --}}
            @can('purchasing.receive')
            @if($hasStages && $po->stage_aktif === \App\Models\PoStage::STAGE_8)
            <x-card title="Pemeriksaan Fisik & Unloading di Gudang (Tahap 8)" subtitle="Hitung kuantitas fisik aktual yang diturunkan di gudang. Selisih wajib dicatat dengan keterangan." variant="primary">
                <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_8]) }}" class="space-y-4" x-data="bardatForm()">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-foreground mb-1">Gudang Penerima</label>
                            @php
                                $targetGudang = $po->gudang ?? $gudang->firstWhere('id', $po->gudang_id) ?? $gudang->first();
                            @endphp
                            <input type="hidden" name="physical_check[gudang_id]" value="{{ $targetGudang?->id }}">
                            <input type="text" value="{{ $targetGudang?->nama ?? '—' }}" class="w-full rounded-lg border border-border bg-muted text-xs p-2 text-foreground cursor-not-allowed" readonly>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-foreground mb-1">Kondisi Fisik Muatan <span class="text-destructive">*</span></label>
                            <select name="physical_check[kondisi]" class="w-full rounded-lg border border-border bg-card text-xs p-2">
                                <option value="baik">Baik (Sesuai Standar)</option>
                                <option value="rusak_sebagian">Rusak Sebagian</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-border pt-3 space-y-3">
                        <p class="text-xs font-bold text-foreground uppercase tracking-wider">Perhitungan Fisik Bahan Baku:</p>
                        @foreach($po->items as $i => $it)
                        <div class="p-3 bg-muted/40 border border-border rounded-lg text-xs space-y-2">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div>
                                    <span class="font-bold text-foreground">{{ $it->produk->nama }}</span>
                                    <span class="font-mono text-muted-foreground text-[11px]">({{ $it->produk->sku }}) · Order: {{ $it->qty }} {{ $it->produk->satuan }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-muted-foreground font-medium">Qty Fisik:</span>
                                    <input type="number" step="0.01" min="0"
                                        name="physical_check[items][{{ $it->id }}][qty_fisik]"
                                        x-model.number="receiptItems[{{ $i }}].qty_terima"
                                        class="w-24 text-right rounded-lg border border-border bg-card text-xs p-1.5 font-mono font-bold"
                                        required>
                                    <span class="text-muted-foreground">{{ $it->produk->satuan }}</span>

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

                            <div x-show="getDiff({{ $i }}) !== 0" x-cloak class="pt-2 border-t border-border">
                                <label class="block text-[11px] font-semibold text-rose-700 mb-1">
                                    Keterangan Alasan Selisih <span class="text-destructive">*</span>:
                                </label>
                                <input type="text"
                                    name="physical_check[items][{{ $it->id }}][keterangan_selisih]"
                                    :required="getDiff({{ $i }}) !== 0"
                                    placeholder="Contoh: Bocor dalam perjalanan / Vendor mengirim kurang"
                                    class="w-full rounded-lg border border-rose-300 text-xs p-1.5 bg-card">
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end pt-2">
                        <x-button type="submit" variant="primary" size="md">
                            Konfirmasi Selesai Unloading Fisik di Gudang
                        </x-button>
                    </div>
                </form>
            </x-card>
            @endif
            @endcan
        </div>

        <!-- Kolom Kanan: Aksi & Status Pembayaran -->
        <div class="space-y-5">
            {{-- Panel Eksekusi Tahapan Aktif (PRD v2.4 9-Stage) --}}
            @if($hasStages && !in_array($po->status, ['selesai', 'dibatalkan']))
                @php
                    $stageLabels = \App\Models\PoStage::STAGE_LABELS;
                    $stgAktif = $po->stage_aktif;
                @endphp
                <x-card title="Eksekusi Tahap Aktif" subtitle="{{ $stageLabels[$stgAktif] ?? ucwords(str_replace('_', ' ', $stgAktif)) }}" variant="primary">
                    {{-- STAGE 1: Perencanaan & Pengajuan PO --}}
                    @if($stgAktif === \App\Models\PoStage::STAGE_1)
                        @can('purchasing.submit')
                        <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_1]) }}" class="space-y-3 text-xs">
                            @csrf
                            <div>
                                <label class="block font-semibold mb-1 text-foreground">Catatan Pengajuan (Opsional)</label>
                                <textarea name="catatan" rows="2" placeholder="Catatan pengajuan untuk Manager..." class="w-full rounded-lg border border-border bg-card p-2 text-xs"></textarea>
                            </div>
                            <x-button type="submit" variant="primary" size="sm" class="w-full">
                                Ajukan PO ke Manager (Selesaikan Tahap 1)
                            </x-button>
                        </form>
                        @else
                        <p class="text-xs text-muted-foreground italic">Menunggu staf Purchasing mengajukan dokumen.</p>
                        @endcan

                    {{-- STAGE 2: Persetujuan Manager --}}
                    @elseif($stgAktif === \App\Models\PoStage::STAGE_2)
                        @can('purchasing.approve')
                        <div class="space-y-3 text-xs" x-data="{ openRejectStage2: false, rejectAlasan: '' }">
                            <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_2]) }}" class="space-y-2">
                                @csrf
                                <x-button type="submit" variant="success" size="sm" class="w-full">
                                    ✓ Setujui PO (Lanjut Konfirmasi Supplier)
                                </x-button>
                            </form>

                            <button type="button" @click="openRejectStage2 = true" class="w-full px-3 py-1.5 text-xs rounded-lg border border-destructive text-destructive hover:bg-destructive/10 font-medium transition cursor-pointer">
                                ✕ Tolak &amp; Kembalikan ke Tahap 1
                            </button>

                            <div x-show="openRejectStage2" class="p-3 rounded-lg border border-amber-300 bg-amber-50 space-y-2" x-cloak>
                                <form method="POST" action="{{ route('purchasing.stages.reject-stage2', $po) }}" class="space-y-2">
                                    @csrf
                                    <label class="block font-semibold text-amber-950 text-[11px]">Alasan Penolakan / Pengembalian <span class="text-destructive">*</span></label>
                                    <textarea name="alasan" x-model="rejectAlasan" required minlength="3" rows="2" placeholder="Tuliskan alasan penolakan..." class="w-full text-xs p-2 rounded border border-amber-300 bg-white"></textarea>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="openRejectStage2 = false" class="text-xs text-muted-foreground px-2 py-1">Batal</button>
                                        <button type="submit" :disabled="rejectAlasan.trim().length < 3" class="px-3 py-1 text-xs rounded bg-destructive text-destructive-foreground disabled:opacity-50">Konfirmasi Tolak</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @else
                        <p class="text-xs text-muted-foreground italic">Menunggu persetujuan otorisasi dari Manager.</p>
                        @endcan

                    {{-- STAGE 3: Konfirmasi Supplier --}}
                    @elseif($stgAktif === \App\Models\PoStage::STAGE_3)
                        @can('purchasing.edit')
                        <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_3]) }}" class="space-y-3 text-xs">
                            @csrf
                            <div>
                                <label class="block font-semibold mb-1 text-foreground">No. Referensi / Sales Order Supplier</label>
                                <input type="text" name="confirmation[no_referensi]" value="{{ $po->no_referensi_supplier ?? '' }}" placeholder="Contoh: SO-SUPPLIER-1234" class="w-full rounded-lg border border-border bg-card p-2 text-xs">
                            </div>
                            <div>
                                <label class="block font-semibold mb-1 text-foreground">Estimasi Tanggal Tiba Supplier (ETA)</label>
                                <input type="date" name="confirmation[eta_supplier]" value="{{ $po->eta_supplier ? $po->eta_supplier->format('Y-m-d') : ($po->eta ? $po->eta->format('Y-m-d') : '') }}" class="w-full rounded-lg border border-border bg-card p-2 text-xs">
                            </div>
                            <div>
                                <label class="block font-semibold mb-1 text-foreground">Catatan Konfirmasi</label>
                                <textarea name="catatan" rows="2" placeholder="Catatan konfirmasi kesiapan barang..." class="w-full rounded-lg border border-border bg-card p-2 text-xs"></textarea>
                            </div>
                            @foreach($po->items as $it)
                                <input type="hidden" name="confirmation[items][{{ $it->id }}][qty_konfirmasi]" value="{{ $it->qty }}">
                                <input type="hidden" name="confirmation[items][{{ $it->id }}][harga_konfirmasi]" value="{{ $it->harga_total }}">
                            @endforeach
                            <x-button type="submit" variant="primary" size="sm" class="w-full">
                                Konfirmasi Supplier Selesai (Lanjut Pembayaran)
                            </x-button>
                        </form>
                        @else
                        <p class="text-xs text-muted-foreground italic">Menunggu konfirmasi ketersediaan barang dan harga dari supplier rekanan.</p>
                        @endcan

                    {{-- STAGE 4: Pembayaran / DP --}}
                    @elseif($stgAktif === \App\Models\PoStage::STAGE_4)
                        @can('purchasing.pay')
                        <div class="space-y-3 text-xs">
                            @if($po->payments->isNotEmpty())
                                <form method="POST" action="{{ route('purchasing.stages.dibayarkan', $po) }}">
                                    @csrf
                                    <x-button type="submit" variant="success" size="sm" class="w-full">
                                        ✓ Konfirmasi Telah Dibayarkan (Lanjut Terbit PO)
                                    </x-button>
                                </form>
                            @else
                                <div class="p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-[11px] leading-tight">
                                    Tercatat belum ada pembayaran. Catat pembayaran di form bawah untuk opsi "Dibayarkan".
                                </div>
                            @endif

                            @if($po->skema_bayar !== 'cash')
                                <form method="POST" action="{{ route('purchasing.stages.lanjut-tempo', $po) }}">
                                    @csrf
                                    <x-button type="submit" variant="outline" size="sm" class="w-full">
                                        Lanjut Skema Tempo (Tanpa Pembayaran di Muka)
                                    </x-button>
                                </form>
                            @endif
                        </div>
                        @else
                        <p class="text-xs text-muted-foreground italic">Menunggu konfirmasi pelunasan / DP atau pelepasan skema tempo dari Finance.</p>
                        @endcan

                    {{-- STAGE 5: Penerbitan PO Resmi --}}
                    @elseif($stgAktif === \App\Models\PoStage::STAGE_5)
                        @can('purchasing.edit')
                        <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_5]) }}" class="space-y-3 text-xs">
                            @csrf
                            <p class="text-[11px] text-muted-foreground">Penerbitan PO resmi mengunci komitmen pengadaan dan membuka visibilitas PO ke tim Gudang.</p>
                            <x-button type="submit" variant="primary" size="sm" class="w-full">
                                Terbitkan PO Resmi (Buka Akses Gudang)
                            </x-button>
                        </form>
                        @else
                        <p class="text-xs text-muted-foreground italic">Menunggu Purchasing menerbitkan PO resmi.</p>
                        @endcan

                    {{-- STAGE 6: Pengemasan & Kesiapan Kirim --}}
                    @elseif($stgAktif === \App\Models\PoStage::STAGE_6)
                        @can('purchasing.edit')
                        <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_6]) }}" class="space-y-3 text-xs">
                            @csrf
                            <div>
                                <label class="block font-semibold mb-1 text-foreground">Jumlah Koli / Kemasan</label>
                                <input type="number" min="1" step="1" name="packing[jumlah_koli]" value="{{ $po->pengiriman?->jumlah_koli ?? 1 }}" class="w-full rounded-lg border border-border bg-card p-2 text-xs">
                            </div>
                            <div>
                                <label class="block font-semibold mb-1 text-foreground">Estimasi Berat Total (Kg)</label>
                                <input type="number" min="0" step="0.1" name="packing[estimasi_berat_kg]" value="{{ $po->pengiriman?->berat_kg ?? '' }}" placeholder="0.0" class="w-full rounded-lg border border-border bg-card p-2 text-xs">
                            </div>
                            <div>
                                <label class="block font-semibold mb-1 text-foreground">Catatan Kondisi Kemasan</label>
                                <textarea name="packing[catatan_kemasan]" rows="2" placeholder="Cth: Dikemas dalam 4 kardus bersegel..." class="w-full rounded-lg border border-border bg-card p-2 text-xs">{{ $po->pengiriman?->catatan_pengemasan ?? '' }}</textarea>
                            </div>
                            <x-button type="submit" variant="primary" size="sm" class="w-full">
                                Konfirmasi Kesiapan Pengemasan (Lanjut Kirim)
                            </x-button>
                        </form>
                        @else
                        <p class="text-xs text-muted-foreground italic">Menunggu supplier menyelesaikan proses pengepakan.</p>
                        @endcan

                    {{-- STAGE 7: Pengiriman & Logistik --}}
                    @elseif($stgAktif === \App\Models\PoStage::STAGE_7)
                        @if(auth()->user()->can('purchasing.edit') || auth()->user()->can('purchasing.receive'))
                        <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_7]) }}" class="space-y-3 text-xs">
                            @csrf
                            <div>
                                <label class="block font-semibold mb-1 text-foreground">Nama Ekspedisi / Kurir <span class="text-destructive">*</span></label>
                                <input type="text" name="shipping[ekspedisi]" value="{{ $po->pengiriman?->ekspedisi ?? '' }}" required placeholder="Cth: JNE Cargo / Truk Vendor" class="w-full rounded-lg border border-border bg-card p-2 text-xs">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-semibold mb-1 text-foreground">No. Resi / AWB</label>
                                    <input type="text" name="shipping[no_resi]" value="{{ $po->pengiriman?->no_resi ?? '' }}" placeholder="No Resi..." class="w-full rounded-lg border border-border bg-card p-2 text-xs">
                                </div>
                                <div>
                                    <label class="block font-semibold mb-1 text-foreground">Tanggal Kirim</label>
                                    <input type="date" name="shipping[tgl_kirim]" value="{{ $po->pengiriman?->tgl_kirim ? $po->pengiriman->tgl_kirim->format('Y-m-d') : date('Y-m-d') }}" class="w-full rounded-lg border border-border bg-card p-2 text-xs">
                                </div>
                            </div>
                            <div>
                                <label class="block font-semibold mb-1 text-foreground">Estimasi Tiba di Gudang</label>
                                <input type="date" name="shipping[eta_tiba]" value="{{ $po->pengiriman?->eta_tiba ? $po->pengiriman->eta_tiba->format('Y-m-d') : ($po->eta ? $po->eta->format('Y-m-d') : '') }}" class="w-full rounded-lg border border-border bg-card p-2 text-xs">
                            </div>
                            <x-button type="submit" variant="primary" size="sm" class="w-full">
                                Konfirmasi Barang Telah Berangkat
                            </x-button>
                        </form>
                        @else
                        <p class="text-xs text-muted-foreground italic">Menunggu update pengiriman dan resi dari logistik / supplier.</p>
                        @endif

                    {{-- STAGE 8: Unloading di Gudang --}}
                    @elseif($stgAktif === \App\Models\PoStage::STAGE_8)
                        <div class="p-2.5 rounded-lg bg-blue-50 border border-blue-200 text-blue-900 text-xs">
                            Silakan input kuantitas fisik pada formulir pemeriksaan barang di bawah untuk menyelesaikan Unloading.
                        </div>

                    {{-- STAGE 9: Input Sistem & Posting Stok --}}
                    @elseif($stgAktif === \App\Models\PoStage::STAGE_9)
                        @can('purchasing.receive')
                        <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_9]) }}" class="space-y-3 text-xs">
                            @csrf
                            <p class="text-[11px] text-muted-foreground leading-relaxed">
                                Unloading fisik telah selesai. Klik tombol di bawah untuk memposting pergerakan stok ke ledger gudang dan mengunci durasi Lead Time supplier.
                            </p>
                            <x-button type="submit" variant="success" size="sm" class="w-full">
                                Posting Stok ke Gudang &amp; Selesaikan PO
                            </x-button>
                        </form>
                        @else
                        <p class="text-xs text-muted-foreground italic">Menunggu staf Gudang memposting transaksi ke sistem persediaan.</p>
                        @endcan
                    @endif
                </x-card>
            @endif

            <!-- Panel Kontrol Status PO & Modal Pembatalan -->
            <div x-data="{
                openCancelModal: false,
                alasan: '',
                isSubmitting: false,
                isCancelDisabled() {
                    return this.alasan.trim().length < 5 || this.isSubmitting;
                }
            }">
                <x-card title="Kontrol Purchase Order" variant="default">
                    <div class="space-y-2">
                        @if(!$hasStages)
                            @can('purchasing.submit')
                            @if($po->status === 'draft')
                            <form method="POST" action="{{ route('purchasing.submit', $po) }}">
                                @csrf
                                <x-button type="submit" variant="primary" size="md" class="w-full">
                                    Ajukan ke Manager
                                </x-button>
                            </form>
                            <p class="text-[11px] text-muted-foreground text-center">Mengirim PO ke antrean persetujuan Manager.</p>
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
                            <p class="text-[11px] text-muted-foreground text-center">Wewenang Manager untuk menyetujui pengadaan.</p>
                            @endif
                            @endcan

                            @can('purchasing.edit')
                            @if(in_array($po->status, ['draft', 'diajukan', 'disetujui'], true) && $po->barangDatang->isEmpty())
                            <x-button href="{{ route('purchasing.edit', $po) }}" variant="secondary" size="md" class="w-full">
                                <x-slot:icon>
                                    <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </x-slot:icon>
                                Edit Purchase Order
                            </x-button>
                            @endif
                            @endcan
                        @endif

                        @can('purchasing.cancel')
                        @php
                            $stage8Selesai = $hasStages && $po->stages->where('stage', \App\Models\PoStage::STAGE_8)->where('status', 'selesai')->isNotEmpty();
                        @endphp
                        @if(!in_array($po->status, ['selesai', 'dibatalkan']) && !$stage8Selesai)
                        <div class="pt-2 border-t border-border">
                            <x-button type="button" @click="openCancelModal = true; alasan = ''" variant="link" size="xs" class="w-full text-destructive hover:text-destructive/80 font-medium cursor-pointer">
                                <x-slot:icon>
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </x-slot:icon>
                                Batalkan Purchase Order
                            </x-button>
                        </div>
                        @endif
                        @endcan

                        @if(in_array($po->status, ['selesai', 'dibatalkan']))
                        <div class="py-2.5 px-3 bg-muted/60 rounded-lg text-center text-xs text-muted-foreground font-medium border border-border">
                            Dokumen ini telah berstatus final ({{ ucfirst($po->status) }}).
                        </div>
                        @endif
                    </div>
                </x-card>

                @can('purchasing.cancel')
                @if(!in_array($po->status, ['selesai', 'dibatalkan']))
                <x-modal name="openCancelModal" maxWidth="md">
                    <x-slot:header>
                        <div class="px-6 py-5 border-b border-border/60 bg-destructive/5 flex items-center justify-between">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="size-10 rounded-xl bg-destructive/10 text-destructive flex items-center justify-center shrink-0 border border-destructive/20">
                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-base font-bold text-foreground tracking-tight">Batalkan Purchase Order</h3>
                                    <p class="text-xs text-muted-foreground truncate">{{ $po->no_po }} &middot; {{ $po->supplier->nama }}</p>
                                </div>
                            </div>
                            <button type="button" @click="openCancelModal = false" class="text-muted-foreground hover:text-foreground transition cursor-pointer p-1.5 rounded-lg hover:bg-accent shrink-0">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </x-slot:header>

                    <form method="POST" action="{{ route('purchasing.cancel', $po) }}" @submit="if(alasan.trim().length >= 5) { isSubmitting = true } else { $event.preventDefault() }" class="space-y-4">
                        @csrf

                        <div class="p-3.5 bg-destructive/10 border border-destructive/20 rounded-xl text-xs text-destructive space-y-1">
                            <div class="font-bold flex items-center gap-1.5">
                                <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Peringatan Pembatalan Dokumen
                            </div>
                            <p class="text-destructive/90 leading-relaxed text-[11px]">
                                Setelah dibatalkan, status PO akan menjadi <strong>Dibatalkan</strong>. Pengadaan, penerimaan fisik gudang, dan pencatatan pembayaran tidak dapat dilanjutkan kembali.
                            </p>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="alasan_pembatalan" class="text-xs font-semibold text-foreground">
                                    Alasan Pembatalan <span class="text-destructive">*</span>
                                </label>
                                <span class="text-[10px] text-muted-foreground">Wajib diisi</span>
                            </div>
                            <textarea
                                id="alasan_pembatalan"
                                name="alasan"
                                x-model="alasan"
                                rows="3"
                                required
                                minlength="5"
                                maxlength="500"
                                placeholder="Contoh: Supplier kehabisan stok bahan baku, revisi kuantiti dari manajemen, atau salah input data..."
                                class="w-full rounded-xl border border-border bg-card p-3 text-xs text-foreground placeholder:text-muted-foreground/50 focus:border-destructive focus:ring-2 focus:ring-destructive/20 outline-none transition"
                            ></textarea>
                            <div class="flex items-center justify-between mt-1 text-[11px]">
                                <span class="text-muted-foreground">Minimal 5 karakter</span>
                                <span :class="alasan.trim().length >= 5 ? 'text-emerald-600 font-semibold' : 'text-muted-foreground'" x-text="alasan.trim().length + ' / 500 karakter'"></span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-border/60 flex items-center justify-end gap-2.5">
                            <x-button type="button" variant="secondary" size="sm" @click="openCancelModal = false">
                                Kembali
                            </x-button>
                            <x-button type="submit" variant="danger" size="sm" x-bind:disabled="isCancelDisabled()">
                                <x-slot:icon>
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </x-slot:icon>
                                <span x-text="isSubmitting ? 'Membatalkan...' : 'Konfirmasi Batalkan PO'"></span>
                            </x-button>
                        </div>
                    </form>
                </x-modal>
                @endif
                @endcan
            </div>

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
                                <span class="bg-muted text-foreground border border-border px-2 py-0.5 rounded text-[11px] font-medium">Termin (Cicilan Bertahap)</span>
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
                
                @if($po->skema_bayar === 'cash')
                <!-- Form Pembayaran Tunai (Cash / Transfer Penuh) -->
                <form method="POST" action="{{ route('purchasing.pay', $po) }}" class="space-y-4 pt-3 border-t border-border/60">
                    @csrf
                    <input type="hidden" name="skema" value="cash">
                    <input type="hidden" name="nominal" value="{{ (float)$po->sisaTagihan() }}">

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1.5">Tanggal Bayar</label>
                        <input type="date" name="tanggal_bayar" value="{{ date('Y-m-d') }}" class="w-full rounded-lg border border-border bg-card text-xs py-2 px-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition" required>
                    </div>

                    <div class="rounded-xl border border-emerald-500/20 bg-emerald-50/50 p-3.5 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-emerald-900">Total Pembayaran Tunai</span>
                            <span class="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded">Lunas Penuh</span>
                        </div>
                        <div class="text-lg font-mono font-bold text-emerald-700">
                            Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }}
                        </div>
                        <p class="text-[11px] text-emerald-600/90 leading-tight">
                            Skema transaksi tunai mensyaratkan pelunasan penuh senilai sisa tagihan.
                        </p>
                    </div>

                    <x-button type="submit" variant="success" size="sm" class="w-full">
                        <x-slot:icon>
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </x-slot:icon>
                        Catat Pembayaran Tunai Penuh (Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }})
                    </x-button>
                </form>
                @else
                <!-- Form Pembayaran Termin / Tempo dengan Custom Nominal & Adjustment Tempo -->
                @php
                    $defaultPaySkema = match($po->skema_bayar) {
                        'tempo' => 'tempo',
                        'termin' => 'termin',
                        default => 'tempo',
                    };
                    $nextUnpaidTermin = $po->termins->where('status', '!=', 'lunas')->first();
                    $initialPayNominal = ($po->skema_bayar === 'termin' && $nextUnpaidTermin)
                        ? (float) $nextUnpaidTermin->sisaNominal()
                        : (float) $po->sisaTagihan();
                @endphp
                <form method="POST" action="{{ route('purchasing.pay', $po) }}" class="space-y-4 pt-3 border-t border-border/60" x-data="payForm({{ $po->sisaTagihan() }}, '{{ $defaultPaySkema }}', {{ $initialPayNominal }}, '{{ $po->skema_bayar }}')">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1.5">Skema Pembayaran</label>
                        <select name="skema" x-model="skema" class="w-full rounded-lg border border-border bg-card text-xs py-2 px-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition">
                            @if($po->skema_bayar === 'tempo')
                                <option value="tempo">Tempo (Pembayaran Cicilan / Parsial)</option>
                                <option value="pelunasan">Pelunasan Penuh (Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }})</option>
                            @else
                                <option value="termin">Termin (Pembayaran Cicilan Sesuai Jadwal)</option>
                                <option value="pelunasan">Pelunasan Penuh (Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }})</option>
                            @endif
                        </select>
                    </div>

                    @if($po->skema_bayar === 'termin' && $po->termins->where('status', '!=', 'lunas')->count())
                    <div x-show="skema === 'termin'">
                        <label class="block text-xs font-semibold text-foreground mb-1.5">Target Termin (Opsional)</label>
                        <select name="termin_id" @change="onTerminChange($event)" class="w-full rounded-lg border border-border bg-card text-xs py-2 px-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition">
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
                        <label class="block text-xs font-semibold text-foreground mb-1.5">Tanggal Bayar</label>
                        <input type="date" name="tanggal_bayar" value="{{ date('Y-m-d') }}" class="w-full rounded-lg border border-border bg-card text-xs py-2 px-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition" required>
                    </div>

                    <!-- Kolom Nominal Bayar Custom (Termin / Tempo) -->
                    <div class="rounded-xl border border-border bg-card p-3.5 space-y-2.5 shadow-xs">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                <svg class="size-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                <span>Nominal Bayar (Custom / Diedit)</span>
                            </label>
                            <span class="text-[10px] text-muted-foreground bg-muted px-2 py-0.5 rounded font-mono font-medium">
                                Bebas diedit
                            </span>
                        </div>

                        <!-- Input Nominal dengan Prefix Rp & Tombol Clear -->
                        <div class="relative flex items-center rounded-lg border border-border bg-background focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 transition">
                            <span class="pl-3 pr-2.5 py-2 text-xs font-bold text-muted-foreground border-r border-border select-none">
                                Rp
                            </span>
                            <input type="hidden" name="nominal" :value="nominal">
                            <input type="text"
                                inputmode="numeric"
                                x-model="displayNominal"
                                @focus="onFocusNominal($event)"
                                @input="onInputNominal($event)"
                                placeholder="0"
                                class="w-full bg-transparent border-0 text-base font-bold font-mono text-foreground px-3 py-2 outline-none focus:ring-0 text-right placeholder:text-muted-foreground/30"
                                required>
                            <button type="button"
                                x-show="nominal > 0"
                                @click="clearNominal()"
                                class="mr-2 text-muted-foreground hover:text-foreground p-1 rounded transition cursor-pointer"
                                title="Kosongkan nominal">
                                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <!-- Shortcut Preset Nominal (Pill Buttons) -->
                        <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                            <button type="button"
                                @click="setNominal(sisa, 'pelunasan')"
                                :class="nominal === sisa ? 'bg-primary text-primary-foreground font-semibold shadow-xs' : 'bg-muted/70 hover:bg-muted text-foreground'"
                                class="px-2.5 py-1 rounded-md text-[11px] transition cursor-pointer">
                                100% Lunas (Rp <span x-text="formatThousand(sisa)"></span>)
                            </button>
                            @if($po->sisaTagihan() > 1000)
                            <button type="button"
                                @click="setNominal(Math.round(sisa / 2), '{{ $po->skema_bayar === 'termin' ? 'termin' : 'tempo' }}')"
                                :class="nominal === Math.round(sisa / 2) ? 'bg-primary text-primary-foreground font-semibold shadow-xs' : 'bg-muted/70 hover:bg-muted text-foreground'"
                                class="px-2.5 py-1 rounded-md text-[11px] transition cursor-pointer">
                                50% (Rp <span x-text="formatThousand(Math.round(sisa / 2))"></span>)
                            </button>
                            @endif
                            @if($po->skema_bayar === 'tempo' && $po->sisaTagihan() > 2000)
                            <button type="button"
                                @click="setNominal(Math.round(sisa / 4), 'tempo')"
                                :class="nominal === Math.round(sisa / 4) ? 'bg-primary text-primary-foreground font-semibold shadow-xs' : 'bg-muted/70 hover:bg-muted text-foreground'"
                                class="px-2.5 py-1 rounded-md text-[11px] transition cursor-pointer">
                                25% (Rp <span x-text="formatThousand(Math.round(sisa / 4))"></span>)
                            </button>
                            @endif
                            @if($po->skema_bayar === 'termin' && $nextUnpaidTermin)
                            <button type="button"
                                @click="setNominal({{ (float)$nextUnpaidTermin->sisaNominal() }}, 'termin', '{{ $nextUnpaidTermin->id }}')"
                                :class="nominal === {{ (float)$nextUnpaidTermin->sisaNominal() }} ? 'bg-primary text-primary-foreground font-semibold shadow-xs' : 'bg-muted/70 hover:bg-muted text-foreground'"
                                class="px-2.5 py-1 rounded-md text-[11px] transition cursor-pointer">
                                Termin {{ $nextUnpaidTermin->termin_ke }} (Rp {{ number_format($nextUnpaidTermin->sisaNominal(), 0, ',', '.') }})
                            </button>
                            @endif
                        </div>

                        <!-- Real-time Financial Impact Box -->
                        <div class="rounded-lg bg-muted/40 border border-border p-2.5 text-xs space-y-1">
                            <div class="flex justify-between items-center text-[11px] text-muted-foreground">
                                <span>Sisa Tagihan Saat Ini:</span>
                                <span class="font-mono font-medium">Rp <span x-text="formatThousand(sisa)"></span></span>
                            </div>
                            <div class="flex justify-between items-center text-[11px]">
                                <span class="text-muted-foreground">Sisa Setelah Bayar Ini:</span>
                                <span class="font-mono font-bold"
                                    :class="sisaAfterPay === 0 ? 'text-emerald-600' : (sisaAfterPay < 0 ? 'text-destructive' : 'text-foreground')"
                                    x-text="sisaAfterPay < 0 ? 'Kelebihan Bayar Rp ' + formatThousand(Math.abs(sisaAfterPay)) : 'Rp ' + formatThousand(sisaAfterPay)">
                                </span>
                            </div>
                        </div>

                        <!-- Error Banner jika Nominal > Sisa -->
                        <div x-show="nominal > sisa" class="p-2.5 bg-destructive/10 border border-destructive/20 rounded-lg text-[11px] text-destructive space-y-1">
                            <div class="font-semibold flex items-center gap-1">
                                <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>Nominal melebihi sisa tagihan!</span>
                            </div>
                            <p class="text-destructive/90">Maksimal pembayaran yang dapat dicatat adalah Rp <span x-text="formatThousand(sisa)"></span>.</p>
                            <button type="button" @click="setNominal(sisa, 'pelunasan')" class="underline font-semibold hover:opacity-80 cursor-pointer">
                                Klik untuk sesuaikan ke Rp <span x-text="formatThousand(sisa)"></span>
                            </button>
                        </div>
                    </div>

                    @if($po->skema_bayar === 'tempo')
                    <!-- Fitur Adjustment Tanggal Jatuh Tempo (Khusus Skema Tempo) -->
                    <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-3 space-y-2.5 shadow-xs" x-data="{ adjustTempo: false }">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-semibold text-amber-950 flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" x-model="adjustTempo" class="rounded text-amber-600 focus:ring-amber-500 size-3.5 cursor-pointer">
                                <span>Adjustment Jatuh Tempo PO</span>
                            </label>
                            <span class="text-[10px] text-amber-800 font-mono font-medium bg-amber-100/80 px-1.5 py-0.5 rounded">
                                Tempo Saat Ini: {{ $po->tanggal_tempo ? $po->tanggal_tempo->format('d/m/Y') : ($po->eta ? $po->eta->format('d/m/Y') : '—') }}
                            </span>
                        </div>
                        <div x-show="adjustTempo" x-cloak class="pt-2 border-t border-amber-200/60 space-y-1.5">
                            <label class="block text-[11px] font-semibold text-amber-900">
                                Tanggal Jatuh Tempo Baru untuk Sisa Tagihan:
                            </label>
                            <input type="date"
                                name="tanggal_tempo_baru"
                                value="{{ $po->tanggal_tempo ? $po->tanggal_tempo->format('Y-m-d') : date('Y-m-d', strtotime('+30 days')) }}"
                                min="{{ date('Y-m-d') }}"
                                class="w-full rounded-lg border border-amber-300 bg-white text-xs py-1.5 px-3 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none">
                            <p class="text-[10px] text-amber-700 leading-tight">
                                Tanggal jatuh tempo PO akan diperbarui otomatis di sistem untuk sisa tagihan tempo berikutnya.
                            </p>
                        </div>
                    </div>
                    @endif

                    <x-button type="submit" variant="primary" size="sm" class="w-full" x-bind:disabled="isPayDisabled()">
                        <x-slot:icon>
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </x-slot:icon>
                        Catat Pembayaran {{ ucfirst($po->skema_bayar) }}
                    </x-button>
                </form>
                @endif

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

    {{-- Tabel Riwayat Log Perubahan Operasional (PO_LOG_PERUBAHAN) --}}
    @if($po->relationLoaded('logPerubahan') && $po->logPerubahan->isNotEmpty())
    <div class="mt-6">
        <x-card title="Riwayat Perubahan Operasional (Direct Edit Log)" subtitle="Jejak audit perubahan field operasional tanpa persetujuan manager" :noPadding="true">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-muted text-muted-foreground font-semibold uppercase text-[10px] border-b border-border">
                        <tr>
                            <th class="px-4 py-2.5">Field Diubah</th>
                            <th class="px-4 py-2.5">Nilai Lama</th>
                            <th class="px-4 py-2.5">Nilai Baru</th>
                            <th class="px-4 py-2.5">Diubah Oleh</th>
                            <th class="px-4 py-2.5">Waktu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($po->logPerubahan as $log)
                        <tr class="hover:bg-muted/30">
                            <td class="px-4 py-2 font-mono font-medium text-foreground">{{ $log->nama_field }}</td>
                            <td class="px-4 py-2 text-muted-foreground italic font-mono">{{ $log->nilai_lama ?? '—' }}</td>
                            <td class="px-4 py-2 text-primary font-mono font-semibold">{{ $log->nilai_baru ?? '—' }}</td>
                            <td class="px-4 py-2 text-foreground">{{ $log->changer?->name ?? 'User #' . $log->diubah_oleh }}</td>
                            <td class="px-4 py-2 text-muted-foreground font-mono text-[11px]">{{ $log->diubah_pada ? $log->diubah_pada->format('d/m/Y H:i') : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
    @endif

    {{-- Kartu Lead Time Transaksi PO & Invalidation --}}
    @if($po->leadTime)
    <div class="mt-6">
        <x-card title="Lead Time Transaksi PO {{ $po->no_po }}" subtitle="Rekapitulasi durasi realisasi pengadaan aktual per stage & per kompartemen">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-lg border {{ $po->leadTime->is_valid ? 'bg-emerald-50/60 border-emerald-300' : 'bg-rose-50/80 border-rose-300' }}">
                    <div class="flex items-center gap-3">
                        <span class="size-8 rounded-full flex items-center justify-center font-bold text-xs {{ $po->leadTime->is_valid ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white' }}">
                            {{ $po->leadTime->is_valid ? '✓' : '✕' }}
                        </span>
                        <div>
                            <div class="font-bold text-xs {{ $po->leadTime->is_valid ? 'text-emerald-950' : 'text-rose-950' }}">
                                Status Validitas: {{ $po->leadTime->is_valid ? 'Valid (Masuk Benchmark)' : 'Tidak Valid (Dikeluarkan dari Benchmark)' }}
                            </div>
                            @if(!$po->leadTime->is_valid)
                                <p class="text-[11px] text-rose-800 mt-0.5">
                                    <strong>Alasan:</strong> {{ $po->leadTime->alasan_tidak_valid }} · <em>Divalidasi oleh: {{ $po->leadTime->validator?->name ?? 'Manager' }}</em>
                                </p>
                            @endif
                        </div>
                    </div>

                    @can('purchasing.approve')
                    @if($po->leadTime->is_valid)
                        <button type="button" @click="openInvalidateModal = true" class="px-3 py-1.5 text-xs rounded-lg border border-destructive text-destructive hover:bg-destructive/10 font-medium transition cursor-pointer">
                            Invalidasi Transaksi Ini
                        </button>
                    @endif
                    @endcan
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="p-3 bg-muted/40 rounded-lg border border-border">
                        <span class="text-[10px] uppercase font-bold text-muted-foreground block">LT Internal</span>
                        <p class="font-mono font-bold text-sm text-foreground mt-0.5">
                            {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($po->leadTime->lt_internal_menit) }} hari
                            <span class="text-[10px] text-muted-foreground font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($po->leadTime->lt_internal_menit) }} j)</span>
                        </p>
                    </div>

                    <div class="p-3 bg-muted/40 rounded-lg border border-border">
                        <span class="text-[10px] uppercase font-bold text-muted-foreground block">LT Supplier</span>
                        <p class="font-mono font-bold text-sm text-foreground mt-0.5">
                            {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($po->leadTime->lt_supplier_menit) }} hari
                            <span class="text-[10px] text-muted-foreground font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($po->leadTime->lt_supplier_menit) }} j)</span>
                        </p>
                    </div>

                    <div class="p-3 bg-muted/40 rounded-lg border border-border">
                        <span class="text-[10px] uppercase font-bold text-muted-foreground block">LT Logistik</span>
                        <p class="font-mono font-bold text-sm text-foreground mt-0.5">
                            {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($po->leadTime->lt_logistik_menit) }} hari
                            <span class="text-[10px] text-muted-foreground font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($po->leadTime->lt_logistik_menit) }} j)</span>
                        </p>
                    </div>

                    <div class="p-3 bg-primary-50/60 rounded-lg border border-primary-200">
                        <span class="text-[10px] uppercase font-bold text-primary-900 block">Total Realisasi LT</span>
                        <p class="font-mono font-bold text-sm text-primary-700 mt-0.5">
                            {{ (float) $po->leadTime->total_lt_hari }} hari
                            <span class="text-[10px] text-primary-600 font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($po->leadTime->total_lt_menit) }} j)</span>
                        </p>
                    </div>
                </div>
            </div>
        </x-card>
    </div>
    @endif

    <!-- Log & Jejak Riwayat Audit Dokumen PO -->
    @canany(['purchasing.audit', 'audit.view'])
    <div class="mt-6">
        <x-operational-audit-history :auditable="$po" title="Jejak Riwayat Audit Dokumen PO {{ $po->no_po }}" />
    </div>
    @endcanany

    {{-- MODAL DIRECT EDIT (purchasing.edit) --}}
    @can('purchasing.edit')
    <div x-show="openDirectEditModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" x-cloak>
        <div class="bg-card rounded-xl border border-border p-5 max-w-lg w-full space-y-4 shadow-xl">
            <div class="flex justify-between items-center border-b border-border pb-2.5">
                <div>
                    <h3 class="font-bold text-sm text-foreground">Direct Edit Field Operasional</h3>
                    <p class="text-[11px] text-muted-foreground">Perubahan langsung tercatat di PO_LOG_PERUBAHAN tanpa memerlukan approval</p>
                </div>
                <button type="button" @click="openDirectEditModal = false" class="text-muted-foreground hover:text-foreground">✕</button>
            </div>

            <form method="POST" action="{{ route('purchasing.direct-edit', $po) }}" class="space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">No Invoice Vendor</label>
                    <input type="text" name="fields[no_invoice]" value="{{ $po->no_invoice ?? '' }}" class="w-full rounded-lg border border-border bg-background p-2">
                </div>
                <div>
                    <label class="block font-semibold mb-1">No Referensi Supplier</label>
                    <input type="text" name="fields[no_referensi_supplier]" value="{{ $po->no_referensi_supplier ?? '' }}" class="w-full rounded-lg border border-border bg-background p-2">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Estimasi Tiba Supplier (ETA)</label>
                    <input type="date" name="fields[eta_supplier]" value="{{ $po->eta_supplier ? $po->eta_supplier->format('Y-m-d') : '' }}" class="w-full rounded-lg border border-border bg-background p-2">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Catatan Dokumen</label>
                    <textarea name="fields[catatan]" rows="2" class="w-full rounded-lg border border-border bg-background p-2">{{ $po->catatan ?? '' }}</textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-border">
                    <button type="button" @click="openDirectEditModal = false" class="px-3 py-1.5 rounded-lg border border-border text-muted-foreground hover:bg-muted">Batal</button>
                    <x-button type="submit" variant="primary" size="sm">Simpan Perubahan Langsung</x-button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL AJUKAN REVISI KOMITMEN (purchasing.edit) --}}
    <div x-show="openRevisiModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" x-cloak>
        <div class="bg-card rounded-xl border border-border p-5 max-w-xl w-full space-y-4 shadow-xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center border-b border-border pb-2.5">
                <div>
                    <h3 class="font-bold text-sm text-foreground">Ajukan Revisi Komitmen Pengadaan</h3>
                    <p class="text-[11px] text-muted-foreground">Usulan revisi kuantitas/harga akan dikirim ke antrean Manager untuk persetujuan</p>
                </div>
                <button type="button" @click="openRevisiModal = false" class="text-muted-foreground hover:text-foreground">✕</button>
            </div>

            <form method="POST" action="{{ route('purchasing.revisi.submit', $po) }}" class="space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Alasan Revisi <span class="text-destructive">*</span></label>
                    <textarea name="alasan" required minlength="5" rows="2" placeholder="Contoh: Supplier mengonfirmasi perubahan kuantiti atau harga..." class="w-full rounded-lg border border-border bg-background p-2"></textarea>
                </div>

                <div class="space-y-2 border-t border-border pt-2">
                    <span class="font-bold text-[11px] uppercase tracking-wider text-muted-foreground block">Usulan Data Item Bahan Baku:</span>
                    @foreach($po->items as $it)
                    <div class="p-2.5 bg-muted/40 rounded-lg border border-border space-y-2">
                        <div class="font-semibold text-foreground">{{ $it->produk->nama }} ({{ $it->produk->sku }})</div>
                        <input type="hidden" name="data_usulan[items][{{ $it->id }}][po_item_id]" value="{{ $it->id }}">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[10px] text-muted-foreground block">Qty Baru (Satuan Dasar)</label>
                                <input type="number" step="0.01" min="0.01" name="data_usulan[items][{{ $it->id }}][qty]" value="{{ $it->qty }}" class="w-full rounded border border-border bg-background p-1.5 font-mono">
                            </div>
                            @if($canSeePrice)
                            <div>
                                <label class="text-[10px] text-muted-foreground block">Harga Total Baru (Rp)</label>
                                <input type="number" step="1" min="0" name="data_usulan[items][{{ $it->id }}][harga_total]" value="{{ (float)$it->harga_total }}" class="w-full rounded border border-border bg-background p-1.5 font-mono">
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-border">
                    <button type="button" @click="openRevisiModal = false" class="px-3 py-1.5 rounded-lg border border-border text-muted-foreground hover:bg-muted">Batal</button>
                    <x-button type="submit" variant="primary" size="sm">Kirim Usulan Revisi</x-button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    {{-- MODAL INVALIDATE LEAD TIME (Manager) --}}
    @if($po->leadTime)
    @can('purchasing.approve')
    <div x-show="openInvalidateModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" x-cloak>
        <div class="bg-card rounded-xl border border-border p-5 max-w-sm w-full space-y-3 shadow-xl">
            <h3 class="font-bold text-sm text-foreground">Invalidasi Transaksi Lead Time</h3>
            <p class="text-[11px] text-muted-foreground leading-relaxed">
                Tindakan ini akan mengeluarkan durasi transaksi ini dari perhitungan rata-rata Lead Time supplier.
            </p>
            <form method="POST" action="{{ route('purchasing.lead-time.invalidate', $po->leadTime) }}" class="space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">Alasan Invalidasi <span class="text-destructive">*</span></label>
                    <textarea name="alasan" required minlength="5" rows="3" placeholder="Contoh: Ekspedisi mogok luar biasa (anomali force majeure)..." class="w-full rounded-lg border border-border bg-background p-2"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-border">
                    <button type="button" @click="openInvalidateModal = false" class="px-3 py-1.5 rounded-lg border border-border text-muted-foreground hover:bg-muted">Batal</button>
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-destructive text-destructive-foreground hover:bg-destructive/90 font-medium">Invalidasi</button>
                </div>
            </form>
        </div>
    </div>
    @endcan
    @endif
    </div>{{-- Close outer x-data --}}

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

        function payForm(sisaTagihan, defaultSkema, defaultNominal, poSkemaBayar) {
            return {
                sisa: Number(sisaTagihan) || 0,
                skema: defaultSkema || 'tempo',
                nominal: defaultNominal !== undefined && defaultNominal !== null ? Math.round(Number(defaultNominal)) : Math.round(Number(sisaTagihan)),
                displayNominal: '',
                isPayDisabled() {
                    return this.nominal <= 0 || this.nominal > this.sisa;
                },
                get sisaAfterPay() {
                    return Math.round(this.sisa - (Number(this.nominal) || 0));
                },
                formatThousand(val) {
                    if (val === '' || val === null || val === undefined) return '0';
                    const num = Math.round(Number(val)) || 0;
                    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                },
                init() {
                    this.displayNominal = this.formatThousand(this.nominal);
                    this.$watch('skema', (val) => {
                        if (val === 'pelunasan' || (val === 'cash' && poSkemaBayar === 'cash')) {
                            this.setNominal(this.sisa);
                        }
                    });
                },
                onFocusNominal(event) {
                    event.target.select();
                },
                onInputNominal(event) {
                    const raw = event.target.value.replace(/\D/g, '');
                    if (!raw) {
                        this.nominal = 0;
                        this.displayNominal = '';
                        return;
                    }
                    this.nominal = parseInt(raw, 10);
                    this.displayNominal = this.formatThousand(this.nominal);

                    if (this.nominal < this.sisa && this.skema === 'pelunasan') {
                        this.skema = (poSkemaBayar === 'termin') ? 'termin' : 'tempo';
                    } else if (this.nominal === this.sisa && this.skema !== 'cash') {
                        this.skema = 'pelunasan';
                    }
                },
                setNominal(val, newSkema = null, terminId = null) {
                    this.nominal = Math.max(0, Math.round(Number(val)));
                    this.displayNominal = this.formatThousand(this.nominal);
                    if (newSkema) {
                        this.skema = newSkema;
                    }
                    if (terminId) {
                        const terminSelect = document.querySelector('select[name="termin_id"]');
                        if (terminSelect) {
                            terminSelect.value = terminId;
                        }
                    }
                },
                clearNominal() {
                    this.nominal = 0;
                    this.displayNominal = '';
                },
                onTerminChange(event) {
                    const selected = event.target.selectedOptions[0];
                    if (selected && selected.dataset.sisa) {
                        const sisaTermin = parseFloat(selected.dataset.sisa) || 0;
                        if (sisaTermin > 0) {
                            this.setNominal(sisaTermin, 'termin');
                        }
                    }
                }
            };
        }
    </script>
    @endpush
</x-app-layout>

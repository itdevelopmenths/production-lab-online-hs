<x-app-layout title="Detail Purchase Order {{ $purchaseOrder->no_po }}">
    @php
        $po = $purchaseOrder;
        $poBadgeVariant = match($po->status) {
            'dibatalkan' => 'danger',
            default => 'neutral',
        };
        $canSeePrice = $canSeePrice ?? auth()->user()->can('purchasing.price.view');
        $hasStages = $po->relationLoaded('stages') && $po->stages->isNotEmpty();
        $uomList = $uomList ?? \App\Models\Uom::active()->orderBy('nama')->get(['id', 'kode', 'nama', 'kategori', 'satuan_dasar', 'faktor_konversi']);
        $initialRevisiData = [
            'uomList' => $uomList->toArray(),
            'oldGrandTotal' => (float) ($po->grand_total ?? 0),
            'headerDiskon' => (float) ($po->diskon_total ?? 0),
            'headerDiskonMode' => 'nominal',
            'headerDiskonVal' => 0,
            'headerPpn' => (float) ($po->ppn_nominal ?? 0),
            'headerPpnMode' => 'nominal',
            'headerPpnVal' => 0,
            'headerOngkir' => (float) ($po->ongkos_kirim ?? 0),
            'headerAdj' => (float) ($po->adjustment ?? 0),
            'rows' => $po->items->map(function ($it) {
                $qBeli = $it->qty_satuan_beli ? (float) $it->qty_satuan_beli : (float) $it->qty;
                $satBeli = $it->satuan_beli ?: ($it->produk?->satuan ?? 'pcs');
                $faktor = (float) ($it->faktor_konversi ?? 1.0);
                if ($faktor <= 0) $faktor = 1.0;
                return [
                    'po_item_id' => $it->id,
                    'produk_id' => $it->produk_id,
                    'sku' => $it->produk?->sku ?? '—',
                    'nama' => $it->produk?->nama ?? '—',
                    'satuan' => $it->produk?->satuan ?? 'pcs',
                    'current_hpp' => (float) ($it->hpp_per_satuan ?: ($it->produk?->harga_hpp ?? 0)),
                    'qty_satuan_beli' => $qBeli,
                    'satuan_beli' => $satBeli,
                    'faktor_konversi' => $faktor,
                    'qty' => (float) $it->qty,
                    'harga' => (float) $it->harga_total,
                ];
            })->values()->toArray(),
        ];

        $effectivePayStatus = $po->status === 'dibatalkan' ? 'batal' : ($po->status_pembayaran ?? 'belum_lunas');
        $payBadge = match($effectivePayStatus) {
            'overdue', 'batal' => 'danger',
            default => 'neutral',
        };
        $isTempoOverdue = $po->status !== 'dibatalkan' && $po->skema_bayar === 'tempo' && $po->tanggal_tempo && $po->tanggal_tempo->isPast() && !$po->isLunas();
        $isEtaPast = $po->eta && $po->eta->isPast() && $po->status !== 'selesai';
        $fmtQtyDisplay = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    @endphp

    <div x-data="{ openDirectEditModal: false, openRevisiModal: false, openInvalidateModal: false, openInvoiceEtaModal: {{ $errors->hasAny(['no_invoice', 'eta']) ? 'true' : 'false' }} }">
        <!-- Page Header & Action Tools -->
        <x-page-header
            :title="$po->no_po . ($po->no_invoice ? ' · ' . $po->no_invoice : '')"
            :subtitle="$po->supplier->nama . ' · Tanggal PO: ' . $po->tanggal->format('d/m/Y') . ($po->gudang ? ' · Lokasi: ' . $po->gudang->nama : '')"
            :breadcrumbs="[
                'Purchasing' => route('purchasing.index'),
                $po->no_po => null,
            ]"
            :backUrl="route('purchasing.index')"
            backSize="xs"
        >
            <x-slot:actions>
                <x-badge :variant="$poBadgeVariant" :dot="true" size="sm">
                    {{ ucwords(str_replace('_',' ',$po->status)) }}
                </x-badge>

                @if($canSeePrice && $effectivePayStatus)
                    <x-badge :variant="$payBadge" :dot="true" size="sm">
                        AP: {{ ucwords(str_replace('_',' ',$effectivePayStatus)) }}
                    </x-badge>
                @endif

                @if($po->dari_analisa)
                    <x-badge variant="neutral" size="sm">Dari Analisa</x-badge>
                @endif

                @can('purchasing.edit')
                    @if(($po->stage_aktif === null || in_array($po->stage_aktif, [\App\Models\PoStage::STAGE_1, \App\Models\PoStage::STAGE_3], true)) && in_array($po->status, ['draft', 'diajukan', 'disetujui'], true) && $po->barangDatang->isEmpty())
                        <x-button href="{{ route('purchasing.edit', $po) }}" variant="outline" size="xs">
                            <x-slot:icon>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </x-slot:icon>
                            Edit PO
                        </x-button>
                    @endif

                @endcan

                @can('purchasing.edit_invoice_eta')
                    @if($po->canEditInvoiceEta())
                        <x-button type="button" variant="outline" size="xs" @click="openInvoiceEtaModal = true">
                            <x-slot:icon>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </x-slot:icon>
                            Ubah Invoice &amp; ETA
                        </x-button>
                    @endif
                @endcan
            </x-slot:actions>
        </x-page-header>

        {{-- Panel Approval Usulan Revisi Komitmen & Helper Histori Revisi (PRD v2.4) --}}
        @php
            $hasRevisis = $po->relationLoaded('revisis') && $po->revisis->isNotEmpty();
            $pendingRevisis = (! $po->isFinal() && $hasRevisis)
                ? $po->revisis->where('status', \App\Models\PoRevisi::STATUS_MENUNGGU)->values()
                : collect();
            $hasPendingRevisi = $pendingRevisis->isNotEmpty();

            if ($hasRevisis) {
                $fmtQty = static fn (float $n): string => rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',') ?: '0';
                $fmtRp = static fn (float $n): string => 'Rp ' . number_format($n, 0, ',', '.');
                $fmtSignedRp = static function (float $delta): string {
                    if (abs($delta) < 0.5) {
                        return 'Rp 0';
                    }
                    return ($delta > 0 ? '+Rp ' : '-Rp ') . number_format(abs($delta), 0, ',', '.');
                };

                $poItemsById = $po->items->keyBy('id');
                $poItemsByProdukId = $po->items->keyBy('produk_id');

                $buildRevisionComparison = static function (\App\Models\PoRevisi $rev, bool $fallbackToCurrentPo = false) use ($po, $poItemsById, $poItemsByProdukId, $canSeePrice): array {
                    $dLama = is_array($rev->data_lama ?? null) ? $rev->data_lama : [];
                    $dUsul = is_array($rev->data_usulan ?? null) ? $rev->data_usulan : [];

                    $oldItemsRaw = collect($dLama['items'] ?? [])->filter(fn ($it) => is_array($it));
                    $oldByPoItemId = $oldItemsRaw->filter(fn ($it) => !empty($it['po_item_id']))->keyBy('po_item_id');
                    $oldByProdukId = $oldItemsRaw->filter(fn ($it) => !empty($it['produk_id']))->keyBy('produk_id');
                    $fallbackPo = $fallbackToCurrentPo ? $po : null;

                    $itemComparisons = collect($dUsul['items'] ?? [])
                        ->filter(fn ($it) => is_array($it))
                        ->values()
                        ->map(function ($itUsul, $idx) use ($oldByPoItemId, $oldByProdukId, $poItemsById, $poItemsByProdukId, $canSeePrice, $fallbackPo) {
                            $poItemId = $itUsul['po_item_id'] ?? null;
                            $produkId = $itUsul['produk_id'] ?? null;

                            $poItemMatch = ($poItemId ? $poItemsById->get($poItemId) : null)
                                ?? ($produkId ? $poItemsByProdukId->get($produkId) : null);

                            $oldIt = ($poItemId ? $oldByPoItemId->get($poItemId) : null)
                                ?? ($produkId ? $oldByProdukId->get($produkId) : null);

                            $satDasar = $poItemMatch?->produk?->satuan ?? 'unit';
                            $oldQtyDasar = (float) ($oldIt['qty'] ?? ($fallbackPo ? $poItemMatch?->qty : null) ?? 0);
                            $newQtyDasar = (float) ($itUsul['qty'] ?? $oldQtyDasar);

                            $oldSatBeli = ($oldIt['satuan_beli'] ?? ($fallbackPo ? $poItemMatch?->satuan_beli : null)) ?: $satDasar;
                            $newSatBeli = ($itUsul['satuan_beli'] ?? null) ?: $oldSatBeli;

                            $oldQtyBeliRaw = $oldIt['qty_satuan_beli'] ?? ($fallbackPo ? $poItemMatch?->qty_satuan_beli : null);
                            $oldQtyBeli = (float) ($oldQtyBeliRaw !== null && $oldQtyBeliRaw > 0 ? $oldQtyBeliRaw : $oldQtyDasar);

                            $newQtyBeliRaw = $itUsul['qty_satuan_beli'] ?? null;
                            $newQtyBeli = (float) ($newQtyBeliRaw !== null && $newQtyBeliRaw > 0 ? $newQtyBeliRaw : $newQtyDasar);

                            $oldHarga = (float) ($oldIt['harga_total'] ?? ($fallbackPo ? $poItemMatch?->harga_total : null) ?? 0);
                            $newHarga = (float) ($itUsul['harga_total'] ?? $oldHarga);

                            $sameUnitBeli = strcasecmp((string) $oldSatBeli, (string) $newSatBeli) === 0;
                            $deltaQtyBeli = $sameUnitBeli ? ($newQtyBeli - $oldQtyBeli) : null;
                            $deltaQtyDasar = $newQtyDasar - $oldQtyDasar;
                            $deltaHarga = $newHarga - $oldHarga;

                            $qtyChanged = !$sameUnitBeli
                                || abs($newQtyBeli - $oldQtyBeli) > 0.0001
                                || abs($deltaQtyDasar) > 0.0001;
                            $hargaChanged = $canSeePrice && abs($deltaHarga) > 0.5;

                            return [
                                'nama' => $poItemMatch?->produk?->nama ?? ('Item #' . ($idx + 1)),
                                'sku' => $poItemMatch?->produk?->sku ?? '—',
                                'sat_dasar' => $satDasar,
                                'old_sat_beli' => $oldSatBeli,
                                'new_sat_beli' => $newSatBeli,
                                'old_qty_beli' => $oldQtyBeli,
                                'new_qty_beli' => $newQtyBeli,
                                'old_qty_dasar' => $oldQtyDasar,
                                'new_qty_dasar' => $newQtyDasar,
                                'old_harga' => $oldHarga,
                                'new_harga' => $newHarga,
                                'delta_qty_beli' => $deltaQtyBeli,
                                'delta_qty_dasar' => $deltaQtyDasar,
                                'delta_harga' => $deltaHarga,
                                'same_unit_beli' => $sameUnitBeli,
                                'qty_changed' => $qtyChanged,
                                'harga_changed' => $hargaChanged,
                                'changed' => $qtyChanged || $hargaChanged,
                            ];
                        });

                    $changedItemCount = $itemComparisons->where('changed', true)->count();
                    $totalItemCount = $itemComparisons->count();

                    $usulItemsSubtotal = (float) $itemComparisons->sum('new_harga');
                    $oldSubtotal = (float) ($dLama['subtotal_produk'] ?? ($fallbackPo?->subtotal_produk ?? 0));
                    $newSubtotal = $itemComparisons->isNotEmpty()
                        ? $usulItemsSubtotal
                        : (float) ($dUsul['subtotal_produk'] ?? $oldSubtotal);

                    $oldDiskon = (float) ($dLama['diskon_total'] ?? ($fallbackPo?->diskon_total ?? 0));
                    $newDiskon = (float) ($dUsul['diskon_total'] ?? $oldDiskon);

                    $oldPpn = (float) ($dLama['ppn_nominal'] ?? ($fallbackPo?->ppn_nominal ?? 0));
                    $newPpn = (float) ($dUsul['ppn_nominal'] ?? $oldPpn);

                    $oldOngkir = (float) ($dLama['ongkos_kirim'] ?? ($fallbackPo?->ongkos_kirim ?? 0));
                    $newOngkir = (float) ($dUsul['ongkos_kirim'] ?? $oldOngkir);

                    $oldAdj = (float) ($dLama['adjustment'] ?? ($fallbackPo?->adjustment ?? 0));
                    $newAdj = (float) ($dUsul['adjustment'] ?? $oldAdj);

                    $oldGrandTotal = (float) ($dLama['grand_total'] ?? ($fallbackPo?->grand_total ?? 0));
                    $newGrandTotal = max(0, round($newSubtotal - $newDiskon + $newPpn + $newOngkir + $newAdj));
                    $deltaGrandTotal = $newGrandTotal - $oldGrandTotal;

                    $financeRows = [
                        ['label' => 'Subtotal Bahan (Kotor)', 'old' => $oldSubtotal, 'new' => $newSubtotal, 'is_discount' => false, 'is_signed' => false],
                        ['label' => 'Diskon Global PO', 'old' => $oldDiskon, 'new' => $newDiskon, 'is_discount' => true, 'is_signed' => false],
                        ['label' => 'PPN Global PO', 'old' => $oldPpn, 'new' => $newPpn, 'is_discount' => false, 'is_signed' => false],
                        ['label' => 'Ongkos Kirim Global', 'old' => $oldOngkir, 'new' => $newOngkir, 'is_discount' => false, 'is_signed' => false],
                        ['label' => 'Adjustment / Pembulatan', 'old' => $oldAdj, 'new' => $newAdj, 'is_discount' => false, 'is_signed' => true],
                    ];

                    $submittedAt = ($rev->diajukan_pada ?? $rev->created_at)?->format('d/m/Y H:i') ?? '—';
                    $decidedAt = $rev->diputuskan_pada?->format('d/m/Y H:i') ?? '—';
                    $stageLabel = \App\Models\PoStage::STAGE_LABELS[$rev->stage_saat_ajukan]
                        ?? ($rev->stage_saat_ajukan ? ucwords(str_replace('_', ' ', $rev->stage_saat_ajukan)) : '—');

                    return [
                        'itemComparisons' => $itemComparisons,
                        'changedItemCount' => $changedItemCount,
                        'totalItemCount' => $totalItemCount,
                        'oldGrandTotal' => $oldGrandTotal,
                        'newGrandTotal' => $newGrandTotal,
                        'deltaGrandTotal' => $deltaGrandTotal,
                        'financeRows' => $financeRows,
                        'submittedAt' => $submittedAt,
                        'decidedAt' => $decidedAt,
                        'stageLabel' => $stageLabel,
                    ];
                };
            }
        @endphp
        @if($hasPendingRevisi)
        <section aria-labelledby="pending-revisi-heading" class="mb-5 bg-card border border-border rounded-xl overflow-hidden">
            <div class="px-4 sm:px-5 py-3.5 bg-card border-b border-border flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="size-8 rounded-lg bg-card border border-border text-foreground flex items-center justify-center shrink-0" aria-hidden="true">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 id="pending-revisi-heading" class="text-sm font-semibold text-foreground tracking-tight">
                                Antrean Persetujuan Revisi Komitmen
                            </h2>
                            <x-badge variant="neutral" size="sm">Revisi ke-{{ $pendingRevisis->first()->revisi_ke }}</x-badge>
                            @if($pendingRevisis->count() > 1)
                                <x-badge variant="neutral" size="sm">{{ $pendingRevisis->count() }} Antrean</x-badge>
                            @endif
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            Tinjau selisih kuantitas dan dampak biaya sebelum menyetujui pembaruan komitmen PO aktif.
                        </p>
                    </div>
                </div>
                <x-badge variant="neutral" :dot="true" size="sm">Menunggu Approval Manager</x-badge>
            </div>

            <div class="divide-y divide-border">
                @foreach($pendingRevisis as $rev)
                    @php
                        $revDiff = $buildRevisionComparison($rev, true);
                        $itemComparisons = $revDiff['itemComparisons'];
                        $changedItemCount = $revDiff['changedItemCount'];
                        $totalItemCount = $revDiff['totalItemCount'];
                        $oldGrandTotal = $revDiff['oldGrandTotal'];
                        $newGrandTotal = $revDiff['newGrandTotal'];
                        $deltaGrandTotal = $revDiff['deltaGrandTotal'];
                        $financeRows = $revDiff['financeRows'];
                        $submittedAt = $revDiff['submittedAt'];
                        $stageLabel = $revDiff['stageLabel'];
                    @endphp

                    <div class="p-4 sm:p-5 space-y-4 text-xs">
                        {{-- 1. Konteks Pengajuan: Alasan & Audit Metadata --}}
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                            <div class="lg:col-span-8 rounded-xl border border-border bg-gray-50 dark:bg-gray-800/40 p-3.5 flex items-start gap-3">
                                <span class="size-7 rounded-lg bg-card border border-border text-muted-foreground flex items-center justify-center shrink-0 mt-0.5" aria-hidden="true">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h6m-2 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground block mb-1">
                                        Alasan Pengajuan Revisi
                                    </span>
                                    <p class="text-xs sm:text-[13px] text-foreground leading-relaxed font-medium break-words">
                                        {{ $rev->alasan }}
                                    </p>
                                </div>
                            </div>

                            <dl class="lg:col-span-4 rounded-xl border border-border bg-card p-3.5 space-y-2">
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-[11px] text-muted-foreground">Diajukan oleh</dt>
                                    <dd class="font-medium text-foreground text-right truncate">{{ $rev->requester?->name ?? 'Purchasing' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-[11px] text-muted-foreground">Waktu pengajuan</dt>
                                    <dd class=" tabular-nums text-foreground text-right">{{ $submittedAt }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-[11px] text-muted-foreground">Stage saat ajukan</dt>
                                    <dd class="font-medium text-foreground text-right truncate" title="{{ $stageLabel }}">{{ $stageLabel }}</dd>
                                </div>
                            </dl>
                        </div>

                        {{-- 2. KPI Ringkas Dampak Perubahan --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 {{ $canSeePrice ? 'lg:grid-cols-4' : '' }} gap-3">
                            <div class="rounded-xl border border-border bg-card px-3.5 py-2.5 flex items-center justify-between gap-3">
                                <div>
                                    <span class="text-[10px] uppercase tracking-wider font-medium text-muted-foreground block">Item Diubah</span>
                                    <span class=" tabular-nums text-sm font-medium text-foreground mt-0.5 block">
                                        {{ $changedItemCount }} / {{ $totalItemCount }} item
                                    </span>
                                </div>
                                <x-badge :variant="$changedItemCount > 0 ? 'warning' : 'neutral'" size="sm">
                                    {{ $changedItemCount > 0 ? 'Ada Perubahan' : 'Item Tetap' }}
                                </x-badge>
                            </div>

                            <div class="rounded-xl border border-border bg-card px-3.5 py-2.5 flex items-center justify-between gap-3">
                                <div>
                                    <span class="text-[10px] uppercase tracking-wider font-medium text-muted-foreground block">Versi Komitmen</span>
                                    <span class=" tabular-nums text-sm font-medium text-foreground mt-0.5 block">
                                        Rev ke-{{ (int) $po->revisi_ke }} &rarr; ke-{{ $rev->revisi_ke }}
                                    </span>
                                </div>
                                <x-badge variant="neutral" size="sm">Usulan</x-badge>
                            </div>

                            @if($canSeePrice)
                                <div class="rounded-xl border border-border bg-card px-3.5 py-2.5">
                                    <span class="text-[10px] uppercase tracking-wider font-semibold text-muted-foreground block">Grand Total Sebelumnya</span>
                                    <span class=" tabular-nums text-sm font-medium text-muted-foreground mt-0.5 block whitespace-nowrap">
                                        {{ $fmtRp($oldGrandTotal) }}
                                    </span>
                                </div>

                                <div class="rounded-xl border {{ abs($deltaGrandTotal) >= 0.5 ? 'border-border bg-card' : 'border-border bg-card' }} px-3.5 py-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-[10px] uppercase tracking-wider font-semibold text-muted-foreground">Grand Total Usulan Baru</span>
                                        @php
                                            $deltaGtVariant = $deltaGrandTotal > 0.5 ? 'warning' : ($deltaGrandTotal < -0.5 ? 'success' : 'neutral');
                                        @endphp
                                        <x-badge :variant="$deltaGtVariant" size="sm">{{ $fmtSignedRp($deltaGrandTotal) }}</x-badge>
                                    </div>
                                    <span class=" tabular-nums text-sm font-medium text-foreground mt-0.5 block whitespace-nowrap">
                                        {{ $fmtRp($newGrandTotal) }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- 3. Tabel Perbandingan Rincian Item --}}
                        @if($itemComparisons->isNotEmpty())
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h3 id="revisi-items-title-{{ $rev->id }}" class="text-xs font-semibold text-foreground uppercase tracking-wider">
                                        Perbandingan Rincian Item
                                    </h3>
                                    <span class="text-[11px] text-muted-foreground tabular-nums">
                                        {{ $totalItemCount }} item · {{ $changedItemCount }} berubah
                                    </span>
                                </div>

                                <div tabindex="0" role="region" aria-labelledby="revisi-items-title-{{ $rev->id }}" class="overflow-x-auto rounded-xl border border-border bg-card">
                                    <table class="w-full text-xs text-left">
                                        <caption class="sr-only">Perbandingan item Purchase Order {{ $po->no_po }} pada Revisi ke-{{ $rev->revisi_ke }}</caption>
                                        <thead class="bg-gray-50 dark:bg-gray-800/40 text-muted-foreground font-medium text-[11px] uppercase tracking-wider border-b border-border">
                                            <tr>
                                                <th scope="col" class="py-2.5 px-3.5 min-w-44">Bahan Baku / SKU</th>
                                                <th scope="col" class="py-2.5 px-3.5 text-right whitespace-nowrap">Sebelum</th>
                                                <th scope="col" class="py-2.5 px-3.5 text-right whitespace-nowrap bg-gray-50 dark:bg-gray-800/40 text-foreground">Usulan Baru</th>
                                                <th scope="col" class="py-2.5 px-3.5 text-right whitespace-nowrap">Selisih</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border bg-card">
                                            @foreach($itemComparisons as $itRow)
                                                <tr class="{{ $itRow['changed'] ? 'bg-card' : 'opacity-80' }} hover:bg-gray-50 transition-colors">
                                                    <th scope="row" class="py-3 px-3.5 font-normal text-left align-top">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="font-medium text-foreground leading-snug">{{ $itRow['nama'] }}</span>
                                                            <x-badge :variant="$itRow['changed'] ? 'warning' : 'muted'" size="sm">
                                                                {{ $itRow['changed'] ? 'Berubah' : 'Tetap' }}
                                                            </x-badge>
                                                        </div>
                                                        <div class="tabular-nums text-[10px] text-muted-foreground mt-0.5">
                                                            {{ $itRow['sku'] }} · Satuan dasar: {{ $itRow['sat_dasar'] }}
                                                        </div>
                                                    </th>

                                                    <td class="py-3 px-3.5 text-right align-top tabular-nums text-muted-foreground whitespace-nowrap">
                                                        <div class="text-foreground/90 font-medium">
                                                            {{ $fmtQty($itRow['old_qty_beli']) }} <span class="text-[11px] font-normal text-muted-foreground">{{ $itRow['old_sat_beli'] }}</span>
                                                        </div>
                                                        @if(strcasecmp((string) $itRow['old_sat_beli'], (string) $itRow['sat_dasar']) !== 0)
                                                            <div class="text-[10px] text-muted-foreground mt-0.5">
                                                                = {{ $fmtQty($itRow['old_qty_dasar']) }} {{ $itRow['sat_dasar'] }}
                                                            </div>
                                                        @endif
                                                        @if($canSeePrice)
                                                            <div class="text-[11px] text-muted-foreground mt-1">
                                                                {{ $fmtRp($itRow['old_harga']) }}
                                                            </div>
                                                        @endif
                                                    </td>

                                                    <td class="py-3 px-3.5 text-right align-top tabular-nums bg-gray-50 dark:bg-gray-800/40 whitespace-nowrap">
                                                        <div class="font-medium text-foreground">
                                                            {{ $fmtQty($itRow['new_qty_beli']) }} <span class="text-[11px] font-normal text-muted-foreground">{{ $itRow['new_sat_beli'] }}</span>
                                                        </div>
                                                        @if(strcasecmp((string) $itRow['new_sat_beli'], (string) $itRow['sat_dasar']) !== 0)
                                                            <div class="text-[10px] text-muted-foreground mt-0.5">
                                                                = {{ $fmtQty($itRow['new_qty_dasar']) }} {{ $itRow['sat_dasar'] }}
                                                            </div>
                                                        @endif
                                                        @if($canSeePrice)
                                                            <div class="text-[11px] font-medium text-foreground mt-1">
                                                                {{ $fmtRp($itRow['new_harga']) }}
                                                            </div>
                                                        @endif
                                                    </td>

                                                    <td class="py-3 px-3.5 text-right align-top tabular-nums whitespace-nowrap">
                                                        @if($itRow['qty_changed'])
                                                            @if($itRow['delta_qty_beli'] !== null && abs($itRow['delta_qty_beli']) > 0.0001)
                                                                <div class="font-medium {{ $itRow['delta_qty_beli'] > 0 ? 'text-foreground' : 'text-foreground' }}">
                                                                    {{ ($itRow['delta_qty_beli'] > 0 ? '+' : '-') . $fmtQty(abs($itRow['delta_qty_beli'])) }} {{ $itRow['new_sat_beli'] }}
                                                                </div>
                                                            @else
                                                                <div class="text-[11px] font-medium text-foreground">Ubah UOM</div>
                                                            @endif
                                                            @if(abs($itRow['delta_qty_dasar']) > 0.0001 && !$itRow['same_unit_beli'])
                                                                <div class="text-[10px] text-muted-foreground mt-0.5">
                                                                    ({{ ($itRow['delta_qty_dasar'] > 0 ? '+' : '-') . $fmtQty(abs($itRow['delta_qty_dasar'])) }} {{ $itRow['sat_dasar'] }})
                                                                </div>
                                                            @endif
                                                        @else
                                                            <div class="text-[11px] text-muted-foreground">0</div>
                                                        @endif

                                                        @if($canSeePrice)
                                                            @if(abs($itRow['delta_harga']) >= 0.5)
                                                                <div class="text-[11px] font-medium mt-1 {{ $itRow['delta_harga'] > 0 ? 'text-foreground' : 'text-foreground' }}">
                                                                    {{ $fmtSignedRp($itRow['delta_harga']) }}
                                                                </div>
                                                            @else
                                                                <div class="text-[11px] text-muted-foreground mt-1">Rp 0</div>
                                                            @endif
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- 4. Tabel Dampak Finansial PO (Khusus Role Dengan Izin Harga) --}}
                        @if($canSeePrice)
                            <div class="space-y-2">
                                <h3 id="revisi-finance-title-{{ $rev->id }}" class="text-xs font-semibold text-foreground uppercase tracking-wider">
                                    Dampak Finansial PO
                                </h3>

                                <div tabindex="0" role="region" aria-labelledby="revisi-finance-title-{{ $rev->id }}" class="overflow-x-auto rounded-xl border border-border bg-card">
                                    <table class="w-full text-xs text-left">
                                        <caption class="sr-only">Perbandingan komponen biaya global dan Grand Total PO {{ $po->no_po }}</caption>
                                        <thead class="bg-gray-50 dark:bg-gray-800/40 text-muted-foreground font-medium text-[11px] uppercase tracking-wider border-b border-border">
                                            <tr>
                                                <th scope="col" class="py-2.5 px-3.5 min-w-44">Komponen Biaya</th>
                                                <th scope="col" class="py-2.5 px-3.5 text-right whitespace-nowrap">Sebelum</th>
                                                <th scope="col" class="py-2.5 px-3.5 text-right whitespace-nowrap bg-gray-50 dark:bg-gray-800/40 text-foreground">Usulan Baru</th>
                                                <th scope="col" class="py-2.5 px-3.5 text-right whitespace-nowrap">Selisih</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border bg-card">
                                            @foreach($financeRows as $fRow)
                                                @php
                                                    $fDelta = $fRow['is_discount']
                                                        ? -($fRow['new'] - $fRow['old'])
                                                        : ($fRow['new'] - $fRow['old']);
                                                    $fChanged = abs($fDelta) >= 0.5;
                                                    $fmtFinanceCell = static function (float $v) use ($fRow, $fmtRp, $fmtSignedRp): string {
                                                        if ($fRow['is_discount']) {
                                                            return $v > 0 ? '-' . $fmtRp($v) : 'Rp 0';
                                                        }
                                                        if ($fRow['is_signed']) {
                                                            return $fmtSignedRp($v);
                                                        }
                                                        return $fmtRp($v);
                                                    };
                                                @endphp
                                                <tr class="{{ $fChanged ? 'bg-card' : '' }} hover:bg-gray-50 transition-colors">
                                                    <th scope="row" class="py-2.5 px-3.5 font-medium text-foreground text-left">
                                                        {{ $fRow['label'] }}
                                                    </th>
                                                    <td class="py-2.5 px-3.5 text-right tabular-nums text-muted-foreground whitespace-nowrap">
                                                        {{ $fmtFinanceCell($fRow['old']) }}
                                                    </td>
                                                    <td class="py-2.5 px-3.5 text-right tabular-nums font-medium text-foreground bg-gray-50 dark:bg-gray-800/40 whitespace-nowrap">
                                                        {{ $fmtFinanceCell($fRow['new']) }}
                                                    </td>
                                                    <td class="py-2.5 px-3.5 text-right tabular-nums whitespace-nowrap {{ $fChanged ? ($fDelta > 0 ? 'text-foreground font-medium' : 'text-foreground font-medium') : 'text-muted-foreground' }}">
                                                        {{ $fmtSignedRp($fDelta) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-gray-50 dark:bg-gray-800/40 border-t-2 border-border">
                                                <th scope="row" class="py-3 px-3.5 font-medium text-foreground text-left">
                                                    Grand Total Tagihan
                                                </th>
                                                <td class="py-3 px-3.5 text-right tabular-nums font-medium text-muted-foreground whitespace-nowrap">
                                                    {{ $fmtRp($oldGrandTotal) }}
                                                </td>
                                                <td class="py-3 px-3.5 text-right tabular-nums font-medium text-sm text-foreground bg-gray-50 dark:bg-gray-800/40 whitespace-nowrap">
                                                    {{ $fmtRp($newGrandTotal) }}
                                                </td>
                                                <td class="py-3 px-3.5 text-right tabular-nums font-medium whitespace-nowrap {{ $deltaGrandTotal > 0.5 ? 'text-foreground' : ($deltaGrandTotal < -0.5 ? 'text-foreground' : 'text-muted-foreground') }}">
                                                    {{ $fmtSignedRp($deltaGrandTotal) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- 5. Footer Keputusan Manager --}}
                        @can('purchasing.approve')
                        <div class="pt-3 border-t border-border flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                             x-data="{
                                 alasanReject: '',
                                 openRejectDialog() { this.$refs.rejectDialog.showModal(); },
                                 closeRejectDialog() { this.$refs.rejectDialog.close(); },
                                 isRejectDisabled() { return this.alasanReject.trim().length < 3; }
                             }">
                            <p class="text-[11px] text-muted-foreground leading-relaxed">
                                Menyetujui usulan akan memperbarui nilai komitmen PO aktif ke <strong class="text-foreground font-medium">Revisi ke-{{ $rev->revisi_ke }}</strong> tanpa mereset waktu stage.
                            </p>

                            <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
                                <x-button type="button" variant="outline" size="sm" class="border-destructive/30 !text-destructive hover:!text-destructive hover:bg-destructive/10" @click="openRejectDialog()">
                                    ✕ Tolak Usulan
                                </x-button>

                                <form method="POST" action="{{ route('purchasing.revisi.approve', $rev) }}" class="inline">
                                    @csrf
                                    <x-button type="submit" variant="primary" size="sm">
                                        ✓ Setujui Revisi Komitmen
                                    </x-button>
                                </form>
                            </div>

                            <dialog id="reject-revisi-dialog-{{ $rev->id }}"
                                    x-ref="rejectDialog"
                                    aria-labelledby="reject-revisi-title-{{ $rev->id }}"
                                    closedby="closerequest"
                                    class="w-full max-w-md">
                                <div class="bg-white dark:bg-gray-900 rounded-[12px] border border-[#E8E8E8] dark:border-border shadow-2xl overflow-hidden text-left">
                                    <div class="px-4 py-3 border-b border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 flex items-center justify-between gap-3">
                                        <h2 id="reject-revisi-title-{{ $rev->id }}" class="font-semibold text-base text-text-active tracking-tight leading-snug">
                                            Tolak Usulan Revisi ke-{{ $rev->revisi_ke }}
                                        </h2>
                                        <button type="button"
                                                @click="closeRejectDialog()"
                                                aria-label="Tutup dialog penolakan revisi"
                                                class="size-7 text-text-muted hover:text-text-active hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-center transition cursor-pointer rounded-[6px]">
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                    <form method="POST" action="{{ route('purchasing.revisi.reject', $rev) }}">
                                        @csrf
                                        <div class="bg-white dark:bg-gray-900 p-4 sm:p-5 space-y-3">
                                            <p id="reject-hint-{{ $rev->id }}" class="text-xs text-text-muted">
                                                Nilai PO aktif tetap dipertahankan. Masukkan alasan penolakan minimal 3 karakter untuk tim Purchasing.
                                            </p>
                                            <x-form-field label="Alasan Penolakan" name="alasan" :required="true" align="top">
                                                <textarea id="reject-alasan-{{ $rev->id }}"
                                                          name="alasan"
                                                          x-model="alasanReject"
                                                          autofocus
                                                          required
                                                          minlength="3"
                                                          aria-describedby="reject-hint-{{ $rev->id }}"
                                                          placeholder="Masukkan alasan penolakan usulan revisi..."
                                                          class="w-full bg-transparent border-0 p-0 text-[13px] font-medium text-text-active placeholder:text-text-placeholder focus:outline-none focus:ring-0"
                                                          rows="3"></textarea>
                                            </x-form-field>
                                        </div>

                                        <div class="bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-border px-4 py-3 flex items-center justify-end gap-2">
                                            <x-button type="button" variant="secondary" size="sm" @click="closeRejectDialog()">Batal</x-button>
                                            <x-button type="submit" variant="danger" size="sm" x-bind:disabled="isRejectDisabled()">Konfirmasi Tolak</x-button>
                                        </div>
                                    </form>
                                </div>
                            </dialog>
                        </div>
                        @endcan
                    </div>
                @endforeach
            </div>
        </section>
        @endif

        <!-- Ringkasan PO: nilai, statistik, fakta -->
        @php
            $stageList = array_values(array_diff(\App\Models\PoStage::STAGES, [\App\Models\PoStage::LEGACY_STAGE_PENGEMASAN]));
            $stageIdx = $po->status === 'selesai' ? count($stageList) : ((int) array_search($po->stage_aktif, $stageList, true) + 1);
            $stageIdx = $hasStages ? min($stageIdx, count($stageList)) : 0;
            $stageLabelNow = \App\Models\PoStage::STAGE_LABELS[$po->stage_aktif] ?? ucwords(str_replace('_', ' ', (string) $po->stage_aktif));
            $etaDays = $po->eta ? (int) now()->startOfDay()->diffInDays($po->eta->copy()->startOfDay(), false) : null;
            $umurPo = (int) $po->tanggal->copy()->startOfDay()->diffInDays(now()->startOfDay());
            $qtyOrdered = (float) $po->items->sum('qty');
            $qtyReceived = (float) $po->barangDatang->flatMap(fn ($bd) => $bd->items)->sum(fn ($bi) => (float) ($bi->qty_fisik ?? $bi->qty_diterima ?? 0));
            $recvPct = $qtyOrdered > 0 ? (int) min(100, round($qtyReceived / $qtyOrdered * 100)) : 0;
            $nilaiPo = (float) $po->totalNilai();
            $dibayarPo = (float) $po->totalDibayar();
            $bayarPct = $nilaiPo > 0 ? (int) min(100, round($dibayarPo / $nilaiPo * 100)) : 0;
            $supplierInitials = collect(preg_split('/\s+/', trim($po->supplier->nama)))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        @endphp
        <section class="mb-5 space-y-4" aria-label="Ringkasan Purchase Order">
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
                {{-- Nilai pesanan + progres pembayaran --}}
                <div class="po-hero card-elevated rounded-[14px] border border-border p-5 xl:col-span-5 flex flex-col justify-between gap-5 min-w-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="size-10 rounded-[10px] bg-[#111111] text-white dark:bg-white dark:text-[#0A0A0A] text-[13px] font-semibold flex items-center justify-center shrink-0" aria-hidden="true">{{ $supplierInitials }}</span>
                        <div class="min-w-0">
                            <p class="text-[12px] text-muted-foreground">Supplier</p>
                            <p class="text-[15px] font-semibold text-foreground leading-snug truncate" title="{{ $po->supplier->nama }}">{{ $po->supplier->nama }}</p>
                        </div>
                    </div>

                    @if($canSeePrice)
                        <div>
                            <p class="text-[12px] text-muted-foreground">Nilai Pesanan</p>
                            <p class="mt-1 flex items-baseline gap-1.5 tabular-nums">
                                <span class="text-base font-medium text-muted-foreground">Rp</span>
                                <span class="text-[36px] leading-none font-semibold tracking-[-0.035em] text-foreground">{{ number_format((float) ($po->grand_total ?? 0), 0, ',', '.') }}</span>
                            </p>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[12px] tabular-nums mb-1.5">
                                <span class="text-muted-foreground">Dibayar <strong class="font-semibold text-foreground">Rp {{ number_format($dibayarPo, 0, ',', '.') }}</strong></span>
                                <span class="font-medium text-foreground">{{ $bayarPct }}%</span>
                            </div>
                            <div class="h-2 rounded-full bg-[#EEF0F3] dark:bg-gray-800 overflow-hidden" role="progressbar" aria-valuenow="{{ $bayarPct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progres pembayaran">
                                <div class="h-full rounded-full bg-gradient-to-r from-[#111111] to-[#3b4258] dark:from-white dark:to-[#c9ceda]" style="width: {{ $bayarPct }}%"></div>
                            </div>
                            <div class="mt-1.5 flex items-center justify-between text-[11px] text-muted-foreground tabular-nums">
                                <span>Sisa Rp {{ number_format((float) $po->sisaTagihan(), 0, ',', '.') }}</span>
                                <span class="capitalize">{{ $po->skema_bayar ?? 'cash' }}@if($po->skema_bayar === 'tempo' && $po->tanggal_tempo) · jatuh tempo {{ $po->tanggal_tempo->format('d/m/Y') }}@endif</span>
                            </div>
                        </div>
                    @else
                        <p class="text-[13px] text-muted-foreground tabular-nums">{{ $po->items->count() }} item bahan baku · {{ $fmtQtyDisplay($qtyOrdered) }} total kuantitas</p>
                    @endif
                </div>

                {{-- Statistik cepat --}}
                <div class="xl:col-span-7 grid grid-cols-2 gap-4 min-w-0">
                    {{-- Tahap --}}
                    <div class="card-elevated rounded-[14px] border border-border bg-card p-4 flex flex-col justify-between gap-3 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[12px] text-muted-foreground">Posisi Tahap</span>
                            <span class="size-8 rounded-[9px] bg-[#F4F6FE] dark:bg-[#627EF2]/15 text-[#3C55C9] dark:text-[#AEBBF9] flex items-center justify-center" aria-hidden="true"><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg></span>
                        </div>
                        <div>
                            @if($hasStages)
                                <p class="tabular-nums leading-none"><span class="text-[30px] font-semibold tracking-[-0.03em] text-foreground">{{ $stageIdx }}</span><span class="text-[15px] text-muted-foreground"> / {{ count($stageList) }}</span></p>
                                <p class="mt-1.5 text-[12px] text-muted-foreground truncate" title="{{ $stageLabelNow }}">{{ $po->status === 'selesai' ? 'Seluruh tahap selesai' : $stageLabelNow }}</p>
                                <div class="mt-2 flex gap-1" aria-hidden="true">
                                    @for($i = 1; $i <= count($stageList); $i++)
                                        <span class="h-1.5 flex-1 rounded-full {{ $i < $stageIdx || $po->status === 'selesai' ? 'bg-[#111111] dark:bg-white' : ($i === $stageIdx ? 'bg-[#627EF2]' : 'bg-[#E4E7EC] dark:bg-gray-800') }}"></span>
                                    @endfor
                                </div>
                            @else
                                <p class="text-[22px] font-semibold text-foreground leading-tight">{{ ucwords(str_replace('_', ' ', $po->status)) }}</p>
                                <p class="mt-1.5 text-[12px] text-muted-foreground">Alur dokumen lama</p>
                            @endif
                        </div>
                    </div>

                    {{-- ETA --}}
                    <div class="card-elevated rounded-[14px] border {{ $isEtaPast && $etaDays !== null ? 'border-destructive/30' : 'border-border' }} bg-card p-4 flex flex-col justify-between gap-3 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[12px] text-muted-foreground">Estimasi Tiba (ETA)</span>
                        </div>
                        <div>
                            @if($etaDays === null)
                                <p class="text-[22px] font-semibold text-foreground leading-tight">—</p>
                                <p class="mt-1.5 text-[12px] text-muted-foreground">ETA belum diisi</p>
                            @else
                                <p class="tabular-nums leading-none {{ $isEtaPast ? 'text-destructive' : 'text-foreground' }}"><span class="text-[30px] font-semibold tracking-[-0.03em]">{{ abs($etaDays) }}</span><span class="text-[15px] font-medium opacity-70"> hari</span></p>
                                <p class="mt-1.5 text-[12px] {{ $isEtaPast ? 'text-destructive font-medium' : 'text-muted-foreground' }}">
                                    {{ $etaDays === 0 ? 'Tiba hari ini' : ($etaDays > 0 ? 'lagi menuju ETA' : 'terlewat dari ETA') }}
                                </p>
                                <p class="mt-2 inline-flex items-center rounded-[6px] border border-border bg-[#FBFBFC] dark:bg-gray-800/60 px-2 py-0.5 text-[11px] tabular-nums text-muted-foreground">{{ $po->eta->format('d/m/Y') }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Penerimaan fisik --}}
                    <div class="card-elevated rounded-[14px] border border-border bg-card p-4 flex items-center gap-4 min-w-0">
                        <div class="relative size-[68px] shrink-0 rounded-full" style="background: conic-gradient(#627EF2 {{ $recvPct }}%, rgba(152,162,179,0.25) 0)" role="img" aria-label="Penerimaan fisik {{ $recvPct }} persen">
                            <div class="absolute inset-[7px] rounded-full bg-card flex items-center justify-center">
                                <span class="text-[15px] font-semibold tabular-nums text-foreground">{{ $recvPct }}<span class="text-[10px] font-medium text-muted-foreground">%</span></span>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[12px] text-muted-foreground">Penerimaan Fisik</p>
                            <p class="mt-1 text-[15px] font-semibold tabular-nums text-foreground leading-tight">{{ $fmtQtyDisplay($qtyReceived) }}<span class="text-[12px] font-normal text-muted-foreground"> / {{ $fmtQtyDisplay($qtyOrdered) }}</span></p>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">{{ $po->items->count() }} item · {{ $po->barangDatang->count() }} penerimaan</p>
                        </div>
                    </div>

                    {{-- Umur PO --}}
                    <div class="card-elevated rounded-[14px] border border-border bg-card p-4 flex flex-col justify-between gap-3 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[12px] text-muted-foreground">Umur PO</span>
                            <span class="size-8 rounded-[9px] bg-[#F4F6FE] dark:bg-[#627EF2]/15 text-[#3C55C9] dark:text-[#AEBBF9] flex items-center justify-center" aria-hidden="true"><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                        </div>
                        <div>
                            <p class="tabular-nums leading-none text-foreground"><span class="text-[30px] font-semibold tracking-[-0.03em]">{{ $umurPo }}</span><span class="text-[15px] font-medium text-muted-foreground"> hari</span></p>
                            <p class="mt-1.5 text-[12px] text-muted-foreground tabular-nums">sejak {{ $po->tanggal->format('d/m/Y') }}</p>
                            <p class="mt-0.5 text-[11px] text-muted-foreground truncate">Dibuat oleh {{ $po->creator?->name ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fakta dokumen --}}
            <dl class="card-elevated rounded-[14px] border border-border bg-card grid grid-cols-2 {{ $po->skema_bayar === 'tempo' ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }} [&>div]:p-4 [&>div]:min-w-0 divide-border">
                <div>
                    <dt class="text-[12px] text-muted-foreground">No. Invoice Vendor</dt>
                    <dd class="tabular-nums font-medium text-[13px] text-foreground mt-1 break-words">{{ $po->no_invoice ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[12px] text-muted-foreground">Lokasi PO (Gudang)</dt>
                    <dd class="font-medium text-[13px] text-foreground mt-1 break-words">{{ $po->gudang?->nama ?? 'Semua Gudang' }}</dd>
                </div>
                <div>
                    <dt class="text-[12px] text-muted-foreground">Skema Bayar</dt>
                    <dd class="font-medium text-[13px] text-foreground mt-1 capitalize">{{ $po->skema_bayar ?? 'cash' }}</dd>
                </div>
                @if($po->skema_bayar === 'tempo')
                    <div class="{{ $isTempoOverdue ? 'bg-destructive/5 rounded-r-[14px]' : '' }}">
                        <dt class="text-[12px] {{ $isTempoOverdue ? 'text-destructive' : 'text-muted-foreground' }}">Deadline Tempo</dt>
                        <dd class="tabular-nums text-[13px] mt-1 {{ $isTempoOverdue ? 'text-destructive font-semibold' : 'text-foreground font-medium' }}">
                            {{ $po->tanggal_tempo ? $po->tanggal_tempo->format('d/m/Y') : ($po->eta ? $po->eta->format('d/m/Y') : '—') }}
                            @if($isTempoOverdue)
                                <span class="ml-1 text-[11px] font-medium text-destructive bg-destructive/10 px-1.5 py-0.5 rounded border border-destructive/20">Overdue</span>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>
        </section>

        <!-- Progress Status Stepper (Requirement 6) -->
        <div class="mb-5">
            <x-purchasing-progress-stepper :po="$po" :stageBenchmarks="$stageBenchmarks ?? null" />
            <x-qa-po-time-panel :po="$po" />
        </div>

        @if($po->catatan)
        <div class="mb-5 bg-gray-50 dark:bg-gray-800/40 border border-border rounded-xl p-4 flex items-start gap-3">
            <svg class="size-4 text-muted-foreground mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
            <div class="text-xs min-w-0">
                <span class="font-medium text-foreground block mb-0.5">Catatan / Instruksi Khusus PO:</span>
                <p class="text-muted-foreground whitespace-pre-line">{{ $po->catatan }}</p>
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 items-start">
            <!-- Kolom Kiri: Tabel Rincian Item PO & Penerimaan Barang -->
            <div class="xl:col-span-2 space-y-5 min-w-0">
                <!-- Tabel Item PO -->
                <x-card class="tbl-modern" variant="elevated" title="Rincian Item Purchase Order" :noPadding="true">
                    <x-slot:tools>
                        <span class="text-xs text-muted-foreground tabular-nums font-medium">{{ $po->items->count() }} item</span>
                    </x-slot:tools>

                    <div tabindex="0" role="region" aria-label="Tabel Rincian Item Purchase Order" class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <caption class="sr-only">Rincian Item Purchase Order {{ $po->no_po }}</caption>
                            <thead class="bg-gray-50 dark:bg-gray-800/40 border-b border-border text-muted-foreground font-medium text-[11px] uppercase tracking-wider">
                                <tr>
                                    <th scope="col" class="py-3 px-4 min-w-36">Bahan Baku</th>
                                    <th scope="col" class="py-3 px-4 text-right whitespace-nowrap">Qty PO</th>
                                    @if($canSeePrice)
                                        <th scope="col" class="py-3 px-4 text-right whitespace-nowrap">Total Kotor</th>
                                        <th scope="col" class="py-3 px-4 text-right whitespace-nowrap">Net Total</th>
                                        <th scope="col" class="py-3 px-4 text-right whitespace-nowrap bg-gray-50 dark:bg-gray-800/40 text-foreground">HPP / Unit</th>
                                    @endif
                                    <th scope="col" class="py-3 px-4 text-right whitespace-nowrap">Diterima Fisik</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border bg-card">
                                @forelse($po->items as $it)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <th scope="row" class="py-3.5 px-4 font-normal text-left align-top">
                                        <div class="font-medium text-foreground leading-snug">{{ $it->produk?->nama ?? '—' }}</div>
                                        <div class="tabular-nums text-[11px] text-muted-foreground mt-0.5">{{ $it->produk?->sku ?? '—' }}</div>
                                    </th>
                                    <td class="py-3.5 px-4 text-right align-top tabular-nums font-medium text-foreground whitespace-nowrap">
                                        @if($it->qty_satuan_beli && $it->satuan_beli && strcasecmp($it->satuan_beli, $it->produk?->satuan ?? '') !== 0)
                                            <div class="text-foreground font-medium">
                                                {{ rtrim(rtrim(number_format($it->qty_satuan_beli, 2, ',', '.'), '0'), ',') }}
                                                <span class="text-xs font-normal text-muted-foreground">{{ $it->satuan_beli }}</span>
                                            </div>
                                            <div class="text-[10px] text-muted-foreground bg-gray-50 dark:bg-gray-800/40 px-1.5 py-0.5 rounded border border-border inline-block mt-1">
                                                = {{ rtrim(rtrim(number_format($it->qty, 2, ',', '.'), '0'), ',') }} {{ $it->produk?->satuan }}
                                            </div>
                                        @else
                                            <div>
                                                {{ rtrim(rtrim(number_format($it->qty, 2, ',', '.'), '0'), ',') }}
                                                <span class="text-muted-foreground font-normal text-[10px]">{{ $it->produk?->satuan }}</span>
                                            </div>
                                        @endif
                                    </td>
                                    @if($canSeePrice)
                                        <td class="py-3.5 px-4 text-right align-top tabular-nums text-foreground whitespace-nowrap">
                                            Rp {{ number_format($it->harga_total, 0, ',', '.') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right align-top tabular-nums font-medium text-foreground whitespace-nowrap">
                                            Rp {{ number_format($it->netTotal(), abs($it->netTotal() - round($it->netTotal())) < 0.00001 ? 0 : 2, ',', '.') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right align-top tabular-nums font-medium text-foreground bg-gray-50 dark:bg-gray-800/40 whitespace-nowrap" title="HPP / Satuan Dasar ({{ $it->produk?->satuan }}): {{ $it->formattedHpp() }}">
                                            <div>{{ $it->formattedHpp() }}</div>
                                            @if($it->qty_satuan_beli && $it->satuan_beli && strcasecmp($it->satuan_beli, $it->produk?->satuan ?? '') !== 0 && (float)$it->qty_satuan_beli > 0)
                                                @php
                                                    $hppPerSatuanBeli = $it->netTotal() / (float)$it->qty_satuan_beli;
                                                @endphp
                                                <div class="text-[10px] font-normal text-muted-foreground mt-0.5">
                                                    (Rp {{ number_format($hppPerSatuanBeli, 0, ',', '.') }} / {{ $it->satuan_beli }})
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="py-3.5 px-4 text-right align-top tabular-nums font-medium whitespace-nowrap {{ (float)$it->qtyDiterima() >= (float)$it->qty ? 'text-foreground dark:text-foreground font-medium' : 'text-foreground' }}">
                                        {{ rtrim(rtrim(number_format($it->qtyDiterima(), 2, ',', '.'), '0'), ',') }}
                                        <span class="text-muted-foreground font-normal text-[10px]">{{ $it->produk?->satuan }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="{{ $canSeePrice ? 6 : 3 }}" class="py-6 px-4 text-center text-muted-foreground">
                                        Belum ada item pada Purchase Order ini.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($canSeePrice)
                        @php
                            $subtotalKotor = (float) ($po->subtotal_produk ?: $po->items->sum('harga_total'));
                            $diskonGlobal = (float) ($po->diskon_total ?: $po->items->sum('diskon'));
                            $ppnGlobal = (float) ($po->ppn_nominal ?: $po->items->sum('ppn'));
                            $ongkirGlobal = (float) ($po->ongkos_kirim ?: $po->items->sum('ongkir'));
                            $adjustmentGlobal = (float) $po->adjustment != 0
                                ? (float) $po->adjustment
                                : (float) $po->items->sum('adjustment');
                        @endphp
                        <div class="border-t border-border bg-gray-50 dark:bg-gray-800/40 p-4 sm:px-5 sm:py-4">
                            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                                <p class="text-[11px] leading-relaxed text-muted-foreground max-w-sm">
                                    Komponen <strong class="font-medium text-foreground">Diskon</strong>, <strong class="font-medium text-foreground">PPN</strong>, <strong class="font-medium text-foreground">Ongkir</strong>, dan <strong class="font-medium text-foreground">Adjustment</strong> dikelompokkan di tingkat PO serta dialokasikan proporsional ke Net Total dan HPP per unit.
                                </p>

                                <div class="w-full md:w-[360px] md:ms-auto shrink-0 rounded-xl border border-border bg-card p-3.5 sm:p-4 space-y-3">
                                    <dl class="flex items-center justify-between gap-4 text-xs">
                                        <dt class="text-muted-foreground font-medium">Subtotal Bahan (Kotor)</dt>
                                        <dd class=" tabular-nums font-medium text-foreground whitespace-nowrap">
                                            Rp {{ number_format($subtotalKotor, 0, ',', '.') }}
                                        </dd>
                                    </dl>

                                    <div class="rounded-lg bg-gray-50 dark:bg-gray-800/40 border border-border/70 px-3 py-2.5 space-y-2">
                                        <div class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground pb-1 border-b border-border/60">
                                            Biaya &amp; Penyesuaian Global PO
                                        </div>
                                        <dl class="space-y-1.5 text-xs">
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-muted-foreground">Diskon</dt>
                                                <dd class=" tabular-nums whitespace-nowrap {{ $diskonGlobal > 0 ? 'text-destructive font-medium' : 'text-muted-foreground' }}">
                                                    {{ $diskonGlobal > 0 ? '-Rp ' . number_format($diskonGlobal, 0, ',', '.') : 'Rp 0' }}
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-muted-foreground">PPN</dt>
                                                <dd class=" tabular-nums whitespace-nowrap {{ $ppnGlobal > 0 ? 'text-foreground font-medium' : 'text-muted-foreground' }}">
                                                    {{ $ppnGlobal > 0 ? '+Rp ' . number_format($ppnGlobal, 0, ',', '.') : 'Rp 0' }}
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-muted-foreground">Ongkir</dt>
                                                <dd class=" tabular-nums whitespace-nowrap {{ $ongkirGlobal > 0 ? 'text-foreground font-medium' : 'text-muted-foreground' }}">
                                                    {{ $ongkirGlobal > 0 ? '+Rp ' . number_format($ongkirGlobal, 0, ',', '.') : 'Rp 0' }}
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-muted-foreground">Adjustment</dt>
                                                <dd class=" tabular-nums whitespace-nowrap {{ $adjustmentGlobal < 0 ? 'text-destructive font-medium' : ($adjustmentGlobal > 0 ? 'text-foreground dark:text-foreground font-medium' : 'text-muted-foreground') }}">
                                                    @if($adjustmentGlobal > 0)
                                                        +Rp {{ number_format($adjustmentGlobal, 0, ',', '.') }}
                                                    @elseif($adjustmentGlobal < 0)
                                                        -Rp {{ number_format(abs($adjustmentGlobal), 0, ',', '.') }}
                                                    @else
                                                        Rp 0
                                                    @endif
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <dl class="pt-2.5 border-t border-border flex items-baseline justify-between gap-4">
                                        <dt class="text-xs sm:text-sm font-semibold text-foreground">Grand Total Nilai Pesanan</dt>
                                        <dd class=" tabular-nums font-medium text-base text-foreground whitespace-nowrap">
                                            Rp {{ number_format($po->totalNilai(), 0, ',', '.') }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    @endif
                </x-card>

                <!-- Riwayat Penerimaan Barang Datang & Selisih (Requirement 13) -->
                @if($po->barangDatang->count())
                <x-card class="tbl-modern" variant="elevated" title="Riwayat Penerimaan Barang di Gudang" :noPadding="true">
                    <div class="divide-y divide-border">
                        @foreach($po->barangDatang as $bardat)
                        <div class="p-4 space-y-2.5">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-foreground">Terima: {{ $bardat->tanggal_terima->format('d/m/Y') }}</span>
                                    <x-badge :variant="$bardat->kondisi === 'baik' ? 'success' : 'warning'" size="sm">
                                        Kondisi {{ ucfirst(str_replace('_',' ', $bardat->kondisi)) }}
                                    </x-badge>
                                    @if($hasStages && $bardat->status_posting === 'belum')
                                        <x-badge variant="neutral" size="sm">Menunggu Posting Stok</x-badge>
                                    @elseif($hasStages && $bardat->status_posting === 'diposting')
                                        <x-badge variant="neutral" :dot="true" size="sm">Stok Diposting</x-badge>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-muted-foreground text-[11px]">
                                    <span>Dicatat oleh: {{ $bardat->creator->name ?? 'Gudang' }}</span>
                                    @if($bardat->diposting_pada)
                                        <span>· Posting: {{ $bardat->diposting_pada->format('d/m/Y H:i') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div tabindex="0" role="region" aria-label="Detail Barang Datang" class="overflow-x-auto bg-gray-50 dark:bg-gray-800/40 rounded-lg border border-border">
                                <table class="w-full text-xs text-left">
                                    <caption class="sr-only">Detail penerimaan barang di gudang pada {{ $bardat->tanggal_terima->format('d/m/Y') }}</caption>
                                    <thead class="bg-gray-50 dark:bg-gray-800/40 border-b border-border text-muted-foreground text-[10px] font-medium uppercase tracking-wider">
                                        <tr>
                                            <th scope="col" class="py-2 px-3">Bahan</th>
                                            <th scope="col" class="py-2 px-3 text-right whitespace-nowrap">Diterima</th>
                                            <th scope="col" class="py-2 px-3 text-right whitespace-nowrap">Selisih</th>
                                            <th scope="col" class="py-2 px-3">Keterangan Selisih</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border bg-card">
                                        @foreach($bardat->items as $bItem)
                                        @php
                                            $poItem = $bItem->relationLoaded('poItem')
                                                ? $bItem->poItem
                                                : $po->items->firstWhere('id', $bItem->po_item_id);
                                            $namaProduk = $poItem?->produk?->nama ?? '—';
                                            $satuanProduk = $poItem?->produk?->satuan ?? '';
                                            $qtyRiwayatDiterima = $bItem->qty_diterima !== null
                                                ? (float) $bItem->qty_diterima
                                                : (float) ($bItem->qty_fisik ?? 0);
                                            $selisihVal = (float) ($bItem->selisih ?? 0);
                                        @endphp
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <th scope="row" class="py-2.5 px-3 font-medium text-foreground text-left">{{ $namaProduk }}</th>
                                            <td class="py-2.5 px-3 text-right tabular-nums font-medium text-foreground whitespace-nowrap">
                                                {{ $fmtQtyDisplay($qtyRiwayatDiterima) }}
                                                @if($satuanProduk !== '')
                                                    <span class="text-muted-foreground font-normal text-[10px]">{{ $satuanProduk }}</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3 text-right tabular-nums whitespace-nowrap">
                                                @if($selisihVal < 0)
                                                    <span class="text-destructive font-medium">{{ $fmtQtyDisplay($selisihVal) }} (Kurang)</span>
                                                @elseif($selisihVal > 0)
                                                    <span class="text-foreground dark:text-foreground font-medium">+{{ $fmtQtyDisplay($selisihVal) }} (Lebih)</span>
                                                @else
                                                    <span class="text-foreground dark:text-foreground">Sesuai</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3 text-muted-foreground italic">{{ $bItem->keterangan_selisih ?? '—' }}</td>
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
                @if($po->status === 'dikirim_ke_gudang' && !$hasStages)
                <x-card variant="elevated" title="Konfirmasi Barang Datang di Gudang" subtitle="Input kuantitas fisik aktual yang diterima. Jika ada selisih, wajib isi kolom keterangan.">
                    <form method="POST" action="{{ route('purchasing.receive', $po) }}" class="space-y-4" x-data="bardatForm()">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-foreground mb-1">Gudang Penerima</label>
                                @php
                                    $targetGudang = $po->gudang ?? $gudang->firstWhere('id', $po->gudang_id) ?? $gudang->first();
                                @endphp
                                <input type="hidden" name="gudang_id" value="{{ $targetGudang?->id }}">
                                <input type="text" value="{{ $targetGudang?->nama ?? '—' }}" class="w-full border border-border bg-muted text-xs py-1.5 px-2.5 text-muted-foreground cursor-not-allowed focus:outline-none rounded-[6px]" readonly>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-foreground mb-1">Tanggal Terima <span class="text-destructive">*</span></label>
                                <input type="date" name="tanggal_terima" value="{{ date('Y-m-d') }}" class="w-full border border-input bg-background text-xs py-1.5 px-2.5 focus:border-ring focus:ring-1 focus:ring-ring outline-none rounded-[6px]" required>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-foreground mb-1">Kondisi Fisik <span class="text-destructive">*</span></label>
                                <select name="kondisi" class="w-full border border-input bg-background text-xs py-1.5 px-2.5 focus:border-ring focus:ring-1 focus:ring-ring outline-none rounded-[6px]">
                                    <option value="baik">Baik (Sesuai Standar)</option>
                                    <option value="rusak_sebagian">Rusak Sebagian</option>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-border pt-3 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                <p class="text-xs font-medium text-foreground uppercase tracking-wider">Kuantitas Diterima &amp; Pengecekan Selisih:</p>
                                <x-badge variant="neutral" size="sm">Penerimaan dicatat dalam satuan dasar (ml / gr / pcs)</x-badge>
                            </div>

                            @foreach($po->items as $i => $it)
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/40 border border-border rounded-lg text-xs space-y-2">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div>
                                        <span class="font-medium text-foreground">{{ $it->produk->nama }}</span>
                                        <span class="tabular-nums text-muted-foreground text-[11px]">
                                            ({{ $it->produk->sku }}) · Order:
                                            @if($it->qty_satuan_beli && $it->satuan_beli && strcasecmp($it->satuan_beli, $it->produk?->satuan ?? '') !== 0)
                                                <span class="font-medium text-foreground">{{ rtrim(rtrim(number_format($it->qty_satuan_beli, 2, ',', '.'), '0'), ',') }} {{ $it->satuan_beli }}</span>
                                                ({{ rtrim(rtrim(number_format($it->qty, 2, ',', '.'), '0'), ',') }} {{ $it->produk->satuan }})
                                            @else
                                                {{ rtrim(rtrim(number_format($it->qty, 2, ',', '.'), '0'), ',') }} {{ $it->produk->satuan }}
                                            @endif
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="hidden" name="items[{{ $i }}][po_item_id]" value="{{ $it->id }}">
                                        <span class="text-muted-foreground font-medium">Qty Terima:</span>
                                        <input type="number" step="0.01" min="0"
                                            name="items[{{ $i }}][qty_diterima]"
                                            x-model.number="receiptItems[{{ $i }}].qty_terima"
                                            class="w-24 text-right border border-input bg-card text-xs py-1 px-2 focus:border-ring focus:ring-1 focus:ring-ring tabular-nums font-medium outline-none rounded-[6px]"
                                            required>
                                        <span class="text-muted-foreground">{{ $it->produk->satuan }}</span>

                                        <template x-if="getDiff({{ $i }}) < 0">
                                            <span class="px-2 py-0.5 rounded text-[10px] tabular-nums font-medium bg-destructive/10 text-destructive border border-destructive/20" x-text="'Kurang: ' + getDiff({{ $i }})"></span>
                                        </template>
                                        <template x-if="getDiff({{ $i }}) > 0">
                                            <span class="px-2 py-0.5 rounded text-[10px] tabular-nums font-medium bg-card text-foreground border border-border" x-text="'Lebih: +' + getDiff({{ $i }})"></span>
                                        </template>
                                        <template x-if="getDiff({{ $i }}) === 0">
                                            <span class="px-2 py-0.5 rounded text-[10px] tabular-nums font-medium bg-card text-foreground border border-border">✓ Sesuai</span>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="getDiff({{ $i }}) !== 0" x-cloak class="pt-2 border-t border-border">
                                    <label class="block text-[11px] font-medium text-destructive mb-1">
                                        Keterangan Alasan Selisih <span class="text-destructive">*</span>:
                                    </label>
                                    <input type="text"
                                        name="items[{{ $i }}][keterangan_selisih]"
                                        :required="getDiff({{ $i }}) !== 0"
                                        placeholder="Contoh: Bocor saat pengiriman / Stok supplier tidak lengkap"
                                        class="w-full border border-destructive/40 text-xs py-1.5 px-2.5 focus:border-destructive focus:ring-1 focus:ring-destructive bg-card outline-none rounded-[6px]">
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <div class="flex justify-end pt-2">
                            <x-button type="submit" variant="primary" size="md">
                                Konfirmasi Terima &amp; Tambah Stok Gudang
                            </x-button>
                        </div>
                    </form>
                </x-card>
                @endif

                {{-- Formulir Pemeriksaan Fisik & Unloading (Stage 7 PRD v2.4 8-Stage) --}}
                @if($hasStages && $po->stage_aktif === \App\Models\PoStage::STAGE_7 && !in_array($po->status, ['selesai', 'dibatalkan']))
                <x-card variant="elevated" title="Pemeriksaan Fisik & Unloading di Gudang (Tahap 7)" subtitle="Hitung kuantitas fisik aktual yang diturunkan di gudang. Jika ada bahan rusak/kurang, input kuantitas riil yang diterima dan catat keterangan selisih.">
                    <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_7]) }}" class="space-y-4" x-data="bardatForm()">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-foreground mb-1">Gudang Penerima</label>
                                @php
                                    $targetGudang = $po->gudang ?? $gudang->firstWhere('id', $po->gudang_id) ?? $gudang->first();
                                @endphp
                                <input type="hidden" name="physical_check[gudang_id]" value="{{ $targetGudang?->id }}">
                                <input type="text" value="{{ $targetGudang?->nama ?? '—' }}" class="w-full border border-border bg-muted text-xs p-2 text-muted-foreground cursor-not-allowed rounded-[6px]" readonly>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-foreground mb-1">Kondisi Fisik Muatan <span class="text-destructive">*</span></label>
                                <select name="physical_check[kondisi]" x-model="kondisiMuatan" class="w-full border border-input bg-background text-xs p-2 focus:border-ring focus:ring-1 focus:ring-ring outline-none rounded-[6px]">
                                    <option value="baik">Baik (Sesuai Standar)</option>
                                    <option value="rusak_sebagian">Rusak Sebagian</option>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-border pt-3 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-medium text-foreground uppercase tracking-wider">Perhitungan Fisik Bahan Baku Masuk:</p>
                                <template x-if="kondisiMuatan === 'rusak_sebagian'">
                                    <span class="text-[11px] text-foreground bg-card border border-border px-2 py-0.5 rounded font-medium">
                                        Muatan rusak sebagian: sesuaikan Qty Fisik yang layak terima &amp; wajib isi keterangan
                                    </span>
                                </template>
                            </div>

                            @foreach($po->items as $i => $it)
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/40 border border-border rounded-lg text-xs space-y-2">
                                <input type="hidden" name="physical_check[items][{{ $it->id }}][po_item_id]" value="{{ $it->id }}">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div>
                                        <span class="font-medium text-foreground">{{ $it->produk->nama }}</span>
                                        <span class="tabular-nums text-muted-foreground text-[11px]">({{ $it->produk->sku }}) · Order PO: {{ $it->qty }} {{ $it->produk->satuan }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-muted-foreground font-medium">Qty Fisik:</span>
                                        <input type="number" step="0.01" min="0"
                                            name="physical_check[items][{{ $it->id }}][qty_fisik]"
                                            x-model.number="receiptItems[{{ $i }}].qty_terima"
                                            class="w-24 text-right border border-input bg-card text-xs p-1.5 tabular-nums font-medium focus:border-ring focus:ring-1 focus:ring-ring outline-none rounded-[6px]"
                                            required>
                                        <span class="text-muted-foreground">{{ $it->produk->satuan }}</span>

                                        <template x-if="getDiff({{ $i }}) < 0">
                                            <span class="px-2 py-0.5 rounded text-[10px] tabular-nums font-medium bg-destructive/10 text-destructive border border-destructive/20" x-text="'Kurang: ' + getDiff({{ $i }})"></span>
                                        </template>
                                        <template x-if="getDiff({{ $i }}) > 0">
                                            <span class="px-2 py-0.5 rounded text-[10px] tabular-nums font-medium bg-card text-foreground border border-border" x-text="'Lebih: +' + getDiff({{ $i }})"></span>
                                        </template>
                                        <template x-if="getDiff({{ $i }}) === 0">
                                            <span class="px-2 py-0.5 rounded text-[10px] tabular-nums font-medium bg-card text-foreground border border-border">✓ Sesuai</span>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="isDiscrepancyRequired({{ $i }})" x-cloak class="pt-2 border-t border-border">
                                    <label class="block text-[11px] font-medium text-destructive mb-1">
                                        Keterangan Alasan Selisih / Kerusakan <span class="text-destructive">*</span>:
                                    </label>
                                    <input type="text"
                                        name="physical_check[items][{{ $it->id }}][keterangan_selisih]"
                                        :required="isDiscrepancyRequired({{ $i }})"
                                        placeholder="Contoh: 2 botol pecah saat unloading / vendor mengirim kurang"
                                        class="w-full border border-destructive/40 text-xs p-1.5 bg-card text-foreground focus:border-destructive focus:ring-1 focus:ring-destructive outline-none rounded-[6px]">
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <x-qa-stage-duration />
                        <div class="flex justify-end pt-2">
                            <x-button type="submit" variant="primary" size="md">
                                Konfirmasi Selesai Unloading Fisik di Gudang
                            </x-button>
                        </div>
                    </form>
                </x-card>
                @endif

                {{-- Panel Konfirmasi & Posting Stok (Stage 8 PRD v2.4 8-Stage - Hanya muncul saat menunggu Posting Stok) --}}
                @if($hasStages && $po->stage_aktif === \App\Models\PoStage::STAGE_8 && !in_array($po->status, ['selesai', 'dibatalkan']))
                @php
                    $latestBardat = $po->barangDatang->where('status_posting', 'belum')->sortByDesc('created_at')->first();
                @endphp
                @if($latestBardat)
                <x-card variant="elevated" title="Verifikasi dan Input Sistem Penerimaan (Tahap 8)"
                        subtitle="Hasil unloading fisik telah tersimpan. Verifikasi data sebelum memposting stok ke inventori gudang.">
                    <div class="space-y-4 text-xs">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/40 border border-border">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <span class="font-medium text-foreground">Kondisi Hasil Bongkar:</span>
                                <x-badge :variant="$latestBardat->kondisi === 'baik' ? 'success' : 'warning'" size="sm">
                                    {{ str_replace('_', ' ', $latestBardat->kondisi) }}
                                </x-badge>
                                <span class="text-muted-foreground text-[11px] hidden sm:inline">
                                    · Waktu: {{ $latestBardat->created_at?->format('d/m/Y H:i') }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-muted-foreground text-[11px]">
                                <span>Dicatat oleh: <strong class="text-foreground">{{ $latestBardat->creator?->name ?? 'Gudang' }}</strong></span>
                            </div>
                        </div>

                        <div tabindex="0" role="region" aria-label="Verifikasi Penerimaan Tahap 8" class="overflow-x-auto rounded-lg border border-border">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-gray-50 dark:bg-gray-800/40 text-muted-foreground font-medium text-[10px] uppercase border-b border-border">
                                    <tr>
                                        <th class="p-2.5">Bahan Baku</th>
                                        <th class="p-2.5 text-right">Dipesan (PO)</th>
                                        <th class="p-2.5 text-right font-medium text-foreground">Fisik Diterima</th>
                                        <th class="p-2.5 text-right">Selisih</th>
                                        <th class="p-2.5">Keterangan Selisih / Kerusakan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    @foreach($latestBardat->items as $bItem)
                                    @php
                                        $pItem = $bItem->poItem;
                                        $stg9Fisik = $bItem->qty_fisik !== null ? (float) $bItem->qty_fisik : (float) ($bItem->qty_diterima ?? 0);
                                        $stg9Selisih = (float) ($bItem->selisih ?? 0);
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-2.5 font-medium text-foreground">{{ $pItem?->produk?->nama ?? '—' }}</td>
                                        <td class="p-2.5 text-right tabular-nums text-muted-foreground whitespace-nowrap">{{ $fmtQtyDisplay((float) ($pItem?->qty ?? 0)) }} {{ $pItem?->produk?->satuan }}</td>
                                        <td class="p-2.5 text-right tabular-nums font-medium text-foreground dark:text-foreground whitespace-nowrap">
                                            {{ $fmtQtyDisplay($stg9Fisik) }} {{ $pItem?->produk?->satuan }}
                                        </td>
                                        <td class="p-2.5 text-right tabular-nums font-medium whitespace-nowrap">
                                            @if($stg9Selisih < 0)
                                                <span class="text-destructive">{{ $fmtQtyDisplay($stg9Selisih) }} (Kurang/Rusak)</span>
                                            @elseif($stg9Selisih > 0)
                                                <span class="text-foreground dark:text-foreground">+{{ $fmtQtyDisplay($stg9Selisih) }} (Lebih)</span>
                                            @else
                                                <span class="text-foreground dark:text-foreground">✓ Sesuai</span>
                                            @endif
                                        </td>
                                        <td class="p-2.5 text-muted-foreground italic">{{ $bItem->keterangan_selisih ?? '—' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @php
                            $existingResi = trim($po->pengiriman?->no_resi ?? '');
                        @endphp
                        <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_8]) }}" class="space-y-3 pt-2" x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf
                            <x-qa-stage-duration />
                            <div class="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-lg border border-border flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="max-w-md w-full sm:w-auto">
                                    @if($existingResi !== '')
                                        <div class="space-y-1.5">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-medium text-foreground text-xs">No. Resi / AWB:</span>
                                                <span class="tabular-nums text-xs font-medium text-foreground px-2 py-0.5 rounded bg-gray-50 dark:bg-gray-800/40 border border-border">
                                                    {{ $existingResi }}
                                                </span>
                                                <span class="inline-flex items-center gap-1 text-[11px] text-foreground dark:text-foreground font-medium">
                                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Sudah Tercatat di Pengiriman
                                                </span>
                                            </div>
                                            <div class="pt-1">
                                                <label class="block text-[11px] text-muted-foreground mb-1">
                                                    Perbarui No. Resi <span class="font-normal">(Opsional)</span>:
                                                </label>
                                                <input type="text" name="no_resi" value="{{ old('no_resi', $existingResi) }}" placeholder="Ketik nomor resi baru jika ada revisi..." class="w-full sm:w-72 border border-input bg-card text-xs py-1.5 px-2.5 tabular-nums text-foreground focus:border-ring focus:ring-1 focus:ring-ring outline-none rounded-[6px]">
                                            </div>
                                        </div>
                                    @else
                                        <div class="space-y-1">
                                            <label class="block font-medium text-foreground text-xs mb-1">
                                                No. Resi / AWB <span class="text-rose-500 font-medium">* (Wajib diisi)</span>
                                            </label>
                                            <input type="text" name="no_resi" required value="{{ old('no_resi') }}" placeholder="Masukkan nomor resi pengiriman..." class="w-full sm:w-72 border border-rose-300 dark:border-rose-800 bg-card text-xs py-1.5 px-2.5 tabular-nums text-foreground focus:border-rose-500 focus:ring-1 focus:ring-rose-500 outline-none rounded-[6px]">
                                            <p class="text-[10px] text-foreground dark:text-foreground font-medium mt-1">
                                                Nomor resi belum dicatat pada tahap pengiriman, wajib diisi sebelum posting stok.
                                            </p>
                                        </div>
                                    @endif
                                    @error('no_resi')
                                        <p class="text-[11px] text-rose-500 font-medium mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="shrink-0 flex items-center">
                                    <x-button type="submit" variant="primary" size="md" x-bind:disabled="submitting">
                                        <span x-show="!submitting">Posting Stok ke Gudang &amp; Selesaikan PO</span>
                                        <span x-show="submitting" class="flex items-center gap-2" style="display: none;">
                                            <svg class="animate-spin size-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                            Memproses...
                                        </span>
                                    </x-button>
                                </div>
                            </div>
                        </form>
                    </div>
                </x-card>
                @endif
                @endif
                @endcan
            </div>

            <!-- Kolom Kanan: Aksi & Status Pembayaran -->
            <div class="space-y-5 min-w-0">
                {{-- Panel Eksekusi Tahapan Aktif (PRD v2.4 9-Stage) --}}
                @if($hasStages && !in_array($po->status, ['selesai', 'dibatalkan']))
                    @php
                        $stageLabels = \App\Models\PoStage::STAGE_LABELS;
                        $stgAktif = $po->stage_aktif;
                    @endphp
                    <x-card variant="elevated" bodyClass="p-4" title="Eksekusi Tahap Aktif" subtitle="{{ $stageLabels[$stgAktif] ?? ucwords(str_replace('_', ' ', $stgAktif)) }}">
                        {{-- STAGE 1: Perencanaan & Pengajuan PO --}}
                        @if($stgAktif === \App\Models\PoStage::STAGE_1)
                            @can('purchasing.submit')
                            <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_1]) }}" class="space-y-2.5 text-xs">
                                @csrf
                                <div>
                                    <label class="po-label">Catatan (opsional)</label>
                                    <textarea name="catatan" rows="2" placeholder="Catatan perencanaan (opsional)" class="po-field"></textarea>
                                </div>
                                <x-qa-stage-duration />
                                <x-button type="submit" variant="primary" size="sm" class="w-full">
                                    Lanjut ke Konfirmasi Supplier
                                </x-button>
                            </form>
                            @else
                            <p class="text-xs text-muted-foreground italic">Menunggu Purchasing mengajukan PO.</p>
                            @endcan

                        {{-- STAGE 2: Persetujuan Manager --}}
                        @elseif($stgAktif === \App\Models\PoStage::STAGE_2)
                            @can('purchasing.approve')
                            <div class="space-y-2.5 text-xs" x-data="{ openRejectStage2: false, rejectAlasan: '', isRejectDisabled() { return this.rejectAlasan.trim().length < 3; } }">
                                <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_2]) }}" class="space-y-2">
                                    @csrf
                                    <x-qa-stage-duration />
                                    <x-button type="submit" variant="primary" size="sm" class="w-full">
                                        Setujui PO
                                    </x-button>
                                </form>

                                <button type="button" @click="openRejectStage2 = !openRejectStage2" class="w-full px-3 py-1.5 text-xs rounded-md border border-destructive/30 text-destructive hover:bg-destructive/10 font-medium transition cursor-pointer">
                                    Tolak &amp; Kembalikan
                                </button>

                                <div x-show="openRejectStage2" class="p-3 rounded-lg border border-destructive/30 bg-destructive/5 space-y-2" x-cloak>
                                    <form method="POST" action="{{ route('purchasing.stages.reject-stage2', $po) }}" class="space-y-2">
                                        @csrf
                                        <label class="po-label">Alasan penolakan <span class="text-destructive">*</span></label>
                                        <textarea name="alasan" x-model="rejectAlasan" required minlength="3" rows="2" placeholder="Tulis alasan" class="po-field"></textarea>
                                        <x-qa-stage-duration />
                                        <div class="flex justify-end gap-2">
                                            <x-button type="button" variant="outline" size="xs" @click="openRejectStage2 = false">Batal</x-button>
                                            <x-button type="submit" variant="danger" size="xs" x-bind:disabled="isRejectDisabled()">Konfirmasi Tolak</x-button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            @else
                            <p class="text-xs text-muted-foreground italic">Menunggu persetujuan Manager.</p>
                            @endcan

                        {{-- STAGE 3: Konfirmasi Supplier --}}
                        @elseif($stgAktif === \App\Models\PoStage::STAGE_3)
                            @can('purchasing.edit')
                            <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_3]) }}" class="space-y-2.5 text-xs">
                                @csrf
                                <div>
                                    <label class="po-label">No. Referensi Supplier</label>
                                    <input type="text" name="confirmation[no_referensi_supplier]" value="{{ $po->no_referensi_supplier ?? '' }}" placeholder="SO-SUPPLIER-1234" class="po-field">
                                </div>
                                <div>
                                    <label class="po-label">Catatan (opsional)</label>
                                    <textarea name="catatan" rows="2" placeholder="Kesiapan barang, jadwal, dll." class="po-field"></textarea>
                                </div>
                                @foreach($po->items as $it)
                                    <input type="hidden" name="confirmation[items][{{ $it->id }}][qty_konfirmasi]" value="{{ $it->qty }}">
                                    <input type="hidden" name="confirmation[items][{{ $it->id }}][harga_konfirmasi]" value="{{ $it->harga_total }}">
                                @endforeach
                                <x-qa-stage-duration />
                                <x-button type="submit" variant="primary" size="sm" class="w-full">
                                    Konfirmasi Supplier &amp; Ajukan ke Manager
                                </x-button>
                            </form>
                            @else
                            <p class="text-xs text-muted-foreground italic">Menunggu konfirmasi supplier.</p>
                            @endcan

                        {{-- STAGE 4: Pembayaran / DP --}}
                        @elseif($stgAktif === \App\Models\PoStage::STAGE_4)
                            @if($hasPendingRevisi)
                            <div role="status" class="p-3 rounded-lg bg-card border border-border text-foreground text-xs flex items-start gap-2 leading-relaxed">
                                <svg class="size-4 shrink-0 mt-0.5 text-foreground" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <span><strong>Pembayaran dikunci.</strong> Selesaikan revisi komitmen di atas dulu.</span>
                            </div>
                            @else
                            @can('purchasing.pay')
                            <div class="space-y-2.5 text-xs">
                                @if($po->payments->isNotEmpty())
                                    <form method="POST" action="{{ route('purchasing.stages.dibayarkan', $po) }}">
                                        @csrf
                                        <x-qa-stage-duration />
                                        <x-button type="submit" variant="primary" size="sm" class="w-full">
                                            Konfirmasi Dibayarkan
                                        </x-button>
                                    </form>
                                @else
                                    <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800/40 border border-border text-muted-foreground text-[11px] leading-tight">
                                        Belum ada pembayaran. Catat dulu di panel Status Pembayaran.
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('purchasing.stages.lanjut-tempo', $po) }}">
                                    @csrf
                                    <x-qa-stage-duration />
                                    <x-button type="submit" variant="outline" size="sm" class="w-full">
                                        Lanjut tanpa DP
                                    </x-button>
                                </form>
                                <p class="text-[11px] text-muted-foreground leading-tight">Pembayaran tetap bisa dicatat menyusul di panel Status Pembayaran.</p>
                            </div>
                            @else
                            <p class="text-xs text-muted-foreground italic">Menunggu konfirmasi pembayaran.</p>
                            @endcan
                            @endif

                        {{-- STAGE 5: Penerbitan PO Resmi --}}
                        @elseif($stgAktif === \App\Models\PoStage::STAGE_5)
                            @can('purchasing.edit')
                            <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_5]) }}" class="space-y-2.5 text-xs">
                                @csrf
                                <p class="text-[11px] text-muted-foreground">Mengunci komitmen dan membuka PO ke tim Gudang.</p>
                                <x-qa-stage-duration />
                                <x-button type="submit" variant="primary" size="sm" class="w-full">
                                    Terbitkan PO Resmi
                                </x-button>
                            </form>
                            @else
                            <p class="text-xs text-muted-foreground italic">Menunggu Purchasing menerbitkan PO.</p>
                            @endcan

                        {{-- STAGE 6: Pengiriman & Logistik (Konsolidasi Alur: Pengemasan Dilebur) --}}
                        @elseif($stgAktif === \App\Models\PoStage::STAGE_6)
                            @if(auth()->user()->can('purchasing.edit') || auth()->user()->can('purchasing.receive'))
                            <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_6]) }}" class="space-y-2.5 text-xs">
                                @csrf
                                <div>
                                    <label class="po-label">Ekspedisi / Kurir <span class="text-destructive">*</span></label>
                                    <input type="text" name="shipping[ekspedisi]" value="{{ $po->pengiriman?->ekspedisi ?? '' }}" required placeholder="JNE Cargo, Truk Vendor" class="po-field">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="po-label">No. Resi <span class="text-muted-foreground font-normal">(ops.)</span></label>
                                        <input type="text" name="shipping[no_resi]" value="{{ $po->pengiriman?->no_resi ?? '' }}" placeholder="AWB" class="po-field">
                                    </div>
                                    <div>
                                        <label class="po-label">Tanggal Kirim</label>
                                        <input type="date" name="shipping[tgl_kirim]" value="{{ $po->pengiriman?->tgl_kirim ? $po->pengiriman->tgl_kirim->format('Y-m-d') : date('Y-m-d') }}" class="po-field">
                                    </div>
                                </div>
                                <x-qa-stage-duration />
                                <x-button type="submit" variant="primary" size="sm" class="w-full">
                                    Konfirmasi Barang Berangkat
                                </x-button>
                            </form>
                            @else
                            <p class="text-xs text-muted-foreground italic">Menunggu update pengiriman.</p>
                            @endif

                        {{-- STAGE 7: Unloading di Gudang --}}
                        @elseif($stgAktif === \App\Models\PoStage::STAGE_7)
                            <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800/40 border border-border text-foreground text-xs">
                                Input kuantitas fisik di formulir pemeriksaan (kiri) untuk menyelesaikan Unloading.
                            </div>

                        {{-- STAGE 8: Input Sistem & Posting Stok --}}
                        @elseif($stgAktif === \App\Models\PoStage::STAGE_8)
                            @can('purchasing.receive')
                            <div class="p-3 rounded-lg bg-card border border-border text-xs space-y-2">
                                <div class="flex items-center gap-2 font-medium text-foreground">
                                    <span class="size-2 rounded-full bg-foreground"></span>
                                    Siap Posting Stok
                                </div>
                                @php
                                    $hasPengirimanResi = !empty(trim($po->pengiriman?->no_resi ?? ''));
                                @endphp
                                @if($hasPengirimanResi)
                                    <p class="text-[11px] text-muted-foreground leading-relaxed">
                                        Resi <strong class="text-foreground tabular-nums">{{ $po->pengiriman->no_resi }}</strong>. Verifikasi kuantitas di panel kiri, lalu klik <strong>Posting Stok ke Gudang</strong>.
                                    </p>
                                @else
                                    <p class="text-[11px] text-foreground dark:text-foreground leading-relaxed font-medium">
                                        Nomor resi belum dicatat. Isi di formulir kiri sebelum posting stok.
                                    </p>
                                @endif
                            </div>
                            @else
                            <p class="text-xs text-muted-foreground italic">Menunggu Gudang memposting stok.</p>
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
                    <x-card variant="elevated" bodyClass="p-3" title="Kontrol Purchase Order">
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
                                    <x-button type="submit" variant="primary" size="md" class="w-full">
                                        Setujui &amp; Kirim ke Gudang
                                    </x-button>
                                </form>
                                <p class="text-[11px] text-muted-foreground text-center">Wewenang Manager untuk menyetujui pengadaan.</p>
                                @endif
                                @endcan

                                @can('purchasing.edit')
                                @if(in_array($po->status, ['draft', 'diajukan', 'disetujui'], true) && $po->barangDatang->isEmpty())
                                <x-button href="{{ route('purchasing.edit', $po) }}" variant="outline" size="md" class="w-full">
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
                                $stageUnloadingSelesai = $hasStages && $po->stages->where('stage', \App\Models\PoStage::STAGE_7)->where('status', 'selesai')->isNotEmpty();
                            @endphp
                            @if(!in_array($po->status, ['selesai', 'dibatalkan']) && !$stageUnloadingSelesai)
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
                            <div class="py-2.5 px-3 bg-gray-50 dark:bg-gray-800/40 rounded-lg text-center text-xs text-muted-foreground font-medium border border-border">
                                Dokumen ini telah berstatus final ({{ ucfirst($po->status) }}).
                            </div>
                            @endif
                        </div>
                    </x-card>

                    @can('purchasing.cancel')
                    @if(!in_array($po->status, ['selesai', 'dibatalkan']))
                    <x-modal name="openCancelModal" maxWidth="md">
                        <x-slot:header>
                            <div class="px-6 py-5 border-b border-border bg-destructive/5 flex items-center justify-between">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="size-10 rounded-xl bg-destructive/10 text-destructive flex items-center justify-center shrink-0 border border-destructive/20">
                                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-base font-semibold text-foreground tracking-tight">Batalkan Purchase Order</h3>
                                        <p class="text-xs text-muted-foreground truncate">{{ $po->no_po }} &middot; {{ $po->supplier->nama }}</p>
                                    </div>
                                </div>
                                <button type="button" @click="openCancelModal = false" class="text-muted-foreground hover:text-foreground transition cursor-pointer p-1.5 hover:bg-accent shrink-0 rounded-[6px]">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </x-slot:header>

                        <form method="POST" action="{{ route('purchasing.cancel', $po) }}" @submit="if(alasan.trim().length >= 5) { isSubmitting = true } else { $event.preventDefault() }" class="space-y-4">
                            @csrf

                            <div class="p-3.5 bg-destructive/10 border border-destructive/20 rounded-xl text-xs text-destructive space-y-1">
                                <div class="font-medium flex items-center gap-1.5">
                                    <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Peringatan Pembatalan Dokumen
                                </div>
                                <p class="text-destructive/90 leading-relaxed text-[11px]">
                                    Setelah dibatalkan, status PO akan menjadi <strong>Dibatalkan</strong>. Pengadaan, penerimaan fisik gudang, dan pencatatan pembayaran tidak dapat dilanjutkan kembali.
                                </p>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label for="alasan_pembatalan" class="text-xs font-medium text-foreground">
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
                                    class="w-full border border-input bg-card p-3 text-xs text-foreground placeholder:text-muted-foreground/50 focus:border-destructive focus:ring-2 focus:ring-destructive/20 outline-none transition rounded-[6px]"
                                ></textarea>
                                <div class="flex items-center justify-between mt-1 text-[11px]">
                                    <span class="text-muted-foreground">Minimal 5 karakter</span>
                                    <span :class="alasan.trim().length >= 5 ? 'text-foreground font-medium' : 'text-muted-foreground'" x-text="alasan.trim().length + ' / 500 karakter'"></span>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-border flex items-center justify-end gap-2.5">
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
                <x-card variant="elevated" title="Status Pembayaran (AP)" id="status-pembayaran-card">
                    <div class="space-y-2.5 text-xs pb-3 border-b border-border">
                        <div class="flex justify-between items-center">
                            <span class="text-muted-foreground">Status Pembayaran (AP)</span>
                            <span>
                                <x-badge :variant="$payBadge" :dot="true" size="sm">
                                    {{ ucwords(str_replace('_',' ',$effectivePayStatus)) }}
                                </x-badge>
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-muted-foreground">Skema Transaksi</span>
                            <span>
                                <x-badge :variant="$po->skema_bayar === 'cash' ? 'success' : ($po->skema_bayar === 'tempo' ? 'warning' : 'neutral')" size="sm">
                                    @if($po->skema_bayar === 'cash')
                                        Tunai (Cash / Penuh)
                                    @elseif($po->skema_bayar === 'tempo')
                                        Tempo (Jatuh Tempo Tunggal)
                                    @else
                                        Termin (Cicilan Bertahap)
                                    @endif
                                </x-badge>
                            </span>
                        </div>
                        @if($po->skema_bayar === 'tempo')
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-muted-foreground">Jatuh Tempo Pembayaran</span>
                            <span class="tabular-nums font-medium {{ $isTempoOverdue ? 'text-destructive' : 'text-foreground' }}">
                                {{ $po->tanggal_tempo ? $po->tanggal_tempo->format('d/m/Y') : ($po->eta ? $po->eta->format('d/m/Y') : '—') }}
                                @if($isTempoOverdue)
                                    <span class="text-[10px] text-destructive font-medium">(Overdue)</span>
                                @endif
                            </span>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Total Tagihan</span>
                            <span class="font-medium tabular-nums text-foreground">Rp {{ number_format($po->totalNilai(), 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Telah Dibayar</span>
                            <span class="font-medium tabular-nums text-foreground">Rp {{ number_format($po->totalDibayar(), 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between pt-1.5 border-t border-border">
                            <span class="font-medium text-foreground">Sisa Tagihan</span>
                            <span class="font-medium tabular-nums {{ $po->status === 'dibatalkan' ? 'text-muted-foreground' : ($po->isLunas() ? 'text-foreground' : 'text-destructive') }}">
                                Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <!-- Daftar Termin Tagihan (Hanya ditampilkan jika skema termin dan ada jadwal) -->
                    @if($po->skema_bayar === 'termin' && $po->termins->count())
                    <div class="py-3 border-b border-border">
                        <p class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider mb-2">Jadwal Termin Tagihan:</p>
                        <div class="space-y-1.5">
                            @foreach($po->termins as $tm)
                            @php
                                $tmBadge = match($tm->status) {
                                    'overdue' => 'danger',
                                    'belum_dibayar' => 'warning',
                                    'parsial' => 'info',
                                    'lunas' => 'success',
                                    default => 'neutral',
                                };
                            @endphp
                            <div class="p-2.5 bg-gray-50 dark:bg-gray-800/40 rounded-lg border border-border text-xs flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-medium text-foreground truncate">Termin {{ $tm->termin_ke }} ({{ $tm->keterangan ?? 'Tagihan' }})</div>
                                    <div class="text-[10px] text-muted-foreground tabular-nums">Tempo: {{ $tm->tanggal_tempo->format('d/m/Y') }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="tabular-nums font-medium text-foreground">Rp {{ number_format($tm->nominal_tagihan, 0, ',', '.') }}</div>
                                    <x-badge :variant="$tmBadge" :dot="true" size="sm">{{ ucfirst(str_replace('_',' ', $tm->status)) }}</x-badge>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @can('purchasing.pay')
                    @if(!$po->isLunas() && !$po->isCancelled() && $hasPendingRevisi)
                        <div role="status" class="mt-3 p-3.5 rounded-xl border border-border bg-card text-foreground text-xs space-y-1.5">
                            <div class="font-medium flex items-center gap-1.5">
                                <svg class="size-4 shrink-0 text-foreground" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <span>Pembayaran dikunci sementara</span>
                            </div>
                            <p class="text-[11px] leading-relaxed">
                                Pengajuan revisi harga/komitmen masih menunggu persetujuan Manager. Pencatatan pembayaran baru dapat dilakukan setelah usulan revisi disetujui atau ditolak agar nominal mengikuti harga komitmen final.
                            </p>
                        </div>
                    @elseif(!$po->isLunas() && !$po->isCancelled() && !$po->isApprovedForPayment())
                        <div role="status" class="mt-3 p-3.5 rounded-xl border border-border bg-card text-foreground text-xs space-y-1.5">
                            <div class="font-medium flex items-center gap-1.5">
                                <svg class="size-4 shrink-0 text-foreground" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <span>Pembayaran belum tersedia</span>
                            </div>
                            <p class="text-[11px] leading-relaxed">
                                Pembayaran tersedia setelah PO disetujui Manager (Tahap Approval selesai).
                            </p>
                        </div>
                    @elseif(!$po->isLunas() && !$po->isCancelled())
                        <div id="form-pembayaran" class="pt-3 border-t border-border">
                        @if($po->skema_bayar === 'cash')
                        <!-- Form Pembayaran Tunai (Cash / Transfer Penuh) -->
                        <form method="POST" action="{{ route('purchasing.pay', $po) }}" aria-label="Formulir Pembayaran Tunai" class="space-y-4">
                            @csrf
                            <input type="hidden" name="skema" value="cash">
                            <input type="hidden" name="nominal" value="{{ (float)$po->sisaTagihan() }}">

                            <div>
                                <label for="pay_tanggal_cash" class="block text-xs font-medium text-foreground mb-1.5">Tanggal Bayar</label>
                                <input id="pay_tanggal_cash" type="date" name="tanggal_bayar" value="{{ date('Y-m-d') }}" class="w-full border border-input bg-background text-xs py-2 px-3 focus:border-ring focus:ring-1 focus:ring-ring outline-none transition rounded-[6px]" required>
                            </div>

                            <div class="rounded-xl border border-border bg-card p-3.5 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-foreground">Total Pembayaran Tunai</span>
                                    <x-badge variant="neutral" size="sm">Lunas Penuh</x-badge>
                                </div>
                                <div class="text-lg tabular-nums font-medium text-foreground">
                                    Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }}
                                </div>
                                <p class="text-[11px] text-muted-foreground leading-tight">
                                    Skema transaksi tunai mensyaratkan pelunasan penuh senilai sisa tagihan.
                                </p>
                            </div>

                            <x-button type="submit" variant="primary" size="sm" class="w-full">
                                <x-slot:icon>
                                    <svg class="size-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
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
                        <form method="POST" action="{{ route('purchasing.pay', $po) }}" aria-label="Formulir Pembayaran Termin atau Tempo" class="space-y-4" x-data="payForm({{ $po->sisaTagihan() }}, '{{ $defaultPaySkema }}', {{ $initialPayNominal }}, '{{ $po->skema_bayar }}')">
                            @csrf
                            <div>
                                <label for="pay_skema" class="block text-xs font-medium text-foreground mb-1.5">Skema Pembayaran</label>
                                <select id="pay_skema" name="skema" x-model="skema" class="w-full border border-input bg-background text-xs py-2 px-3 focus:border-ring focus:ring-1 focus:ring-ring outline-none transition rounded-[6px]">
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
                                <label for="pay_termin_id" class="block text-xs font-medium text-foreground mb-1.5">Target Termin (Opsional)</label>
                                <select id="pay_termin_id" name="termin_id" @change="onTerminChange($event)" class="w-full border border-input bg-background text-xs py-2 px-3 focus:border-ring focus:ring-1 focus:ring-ring outline-none transition rounded-[6px]">
                                    <option value="">— Alokasikan Otomatis (FIFO) —</option>
                                    @php $firstUnpaidTerminKe = $po->termins->where('status', '!=', 'lunas')->min('termin_ke'); @endphp
                                    @foreach($po->termins->where('status', '!=', 'lunas')->sortBy('termin_ke') as $tOpt)
                                        @php $terminTerkunci = $tOpt->termin_ke > $firstUnpaidTerminKe; @endphp
                                        <option value="{{ $tOpt->id }}" data-sisa="{{ $tOpt->sisaNominal() }}" @disabled($terminTerkunci)>
                                            Termin {{ $tOpt->termin_ke }} (Sisa: Rp {{ number_format($tOpt->sisaNominal(), 0, ',', '.') }}){{ $terminTerkunci ? ' — lunasi Termin '.$firstUnpaidTerminKe.' dulu' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif

                            <div>
                                <label for="pay_tanggal_tempo" class="block text-xs font-medium text-foreground mb-1.5">Tanggal Bayar</label>
                                <input id="pay_tanggal_tempo" type="date" name="tanggal_bayar" value="{{ date('Y-m-d') }}" class="w-full border border-input bg-background text-xs py-2 px-3 focus:border-ring focus:ring-1 focus:ring-ring outline-none transition rounded-[6px]" required>
                            </div>

                            <!-- Kolom Nominal Bayar Custom (Termin / Tempo) -->
                            <div class="rounded-xl border border-border bg-gray-50 dark:bg-gray-800/40 p-3.5 space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <label for="pay_nominal_display" class="text-xs font-medium text-foreground flex items-center gap-1.5">
                                        <svg class="size-3.5 text-muted-foreground" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        <span>Nominal Bayar (Custom / Diedit)</span>
                                    </label>
                                    <span class="text-[10px] text-muted-foreground bg-gray-50 dark:bg-gray-800/40 px-2 py-0.5 rounded tabular-nums font-medium border border-border">
                                        Bebas diedit
                                    </span>
                                </div>

                                <div class="relative flex items-center rounded-lg border border-input bg-card focus-within:border-ring focus-within:ring-1 focus-within:ring-ring transition">
                                    <span class="pl-3 pr-2.5 py-2 text-xs font-medium text-muted-foreground border-r border-border select-none">
                                        Rp
                                    </span>
                                    <input type="hidden" name="nominal" :value="nominal">
                                    <input id="pay_nominal_display" type="text"
                                        inputmode="numeric"
                                        x-model="displayNominal"
                                        @focus="onFocusNominal($event)"
                                        @input="onInputNominal($event)"
                                        placeholder="0"
                                        class="w-full bg-transparent border-0 text-base font-medium tabular-nums text-foreground px-3 py-2 outline-none focus:ring-0 text-right placeholder:text-muted-foreground/30"
                                        required>
                                    <button type="button"
                                        x-show="nominal > 0"
                                        @click="clearNominal()"
                                        aria-label="Kosongkan nominal"
                                        class="mr-2 text-muted-foreground hover:text-foreground p-1 transition cursor-pointer rounded-[6px]"
                                        title="Kosongkan nominal">
                                        <svg class="size-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                    <button type="button"
                                        @click="setNominal(sisa, 'pelunasan')"
                                        :class="nominal === sisa ? 'bg-primary text-primary-foreground font-medium' : 'bg-card border border-border hover:bg-gray-50 text-foreground'"
                                        class="px-2.5 py-1 text-[11px] transition cursor-pointer rounded-[6px]">
                                        100% Lunas (Rp <span x-text="formatThousand(sisa)"></span>)
                                    </button>
                                    @if($po->sisaTagihan() > 1000)
                                    <button type="button"
                                        @click="setNominal(Math.round(sisa / 2), '{{ $po->skema_bayar === 'termin' ? 'termin' : 'tempo' }}')"
                                        :class="nominal === Math.round(sisa / 2) ? 'bg-primary text-primary-foreground font-medium' : 'bg-card border border-border hover:bg-gray-50 text-foreground'"
                                        class="px-2.5 py-1 text-[11px] transition cursor-pointer rounded-[6px]">
                                        50% (Rp <span x-text="formatThousand(Math.round(sisa / 2))"></span>)
                                    </button>
                                    @endif
                                    @if($po->skema_bayar === 'tempo' && $po->sisaTagihan() > 2000)
                                    <button type="button"
                                        @click="setNominal(Math.round(sisa / 4), 'tempo')"
                                        :class="nominal === Math.round(sisa / 4) ? 'bg-primary text-primary-foreground font-medium' : 'bg-card border border-border hover:bg-gray-50 text-foreground'"
                                        class="px-2.5 py-1 text-[11px] transition cursor-pointer rounded-[6px]">
                                        25% (Rp <span x-text="formatThousand(Math.round(sisa / 4))"></span>)
                                    </button>
                                    @endif
                                    @if($po->skema_bayar === 'termin' && $nextUnpaidTermin)
                                    <button type="button"
                                        @click="setNominal({{ (float)$nextUnpaidTermin->sisaNominal() }}, 'termin', '{{ $nextUnpaidTermin->id }}')"
                                        :class="nominal === {{ (float)$nextUnpaidTermin->sisaNominal() }} ? 'bg-primary text-primary-foreground font-medium' : 'bg-card border border-border hover:bg-gray-50 text-foreground'"
                                        class="px-2.5 py-1 text-[11px] transition cursor-pointer rounded-[6px]">
                                        Termin {{ $nextUnpaidTermin->termin_ke }} (Rp {{ number_format($nextUnpaidTermin->sisaNominal(), 0, ',', '.') }})
                                    </button>
                                    @endif
                                </div>

                                <div class="rounded-lg bg-card border border-border p-2.5 text-xs space-y-1">
                                    <div class="flex justify-between items-center text-[11px] text-muted-foreground">
                                        <span>Sisa Tagihan Saat Ini:</span>
                                        <span class="tabular-nums font-medium text-foreground">Rp <span x-text="formatThousand(sisa)"></span></span>
                                    </div>
                                    <div class="flex justify-between items-center text-[11px]">
                                        <span class="text-muted-foreground">Sisa Setelah Bayar Ini:</span>
                                        <span class="tabular-nums font-medium"
                                            :class="sisaAfterPay === 0 ? 'text-foreground' : (sisaAfterPay < 0 ? 'text-destructive' : 'text-foreground')"
                                            x-text="sisaAfterPay < 0 ? 'Kelebihan Bayar Rp ' + formatThousand(Math.abs(sisaAfterPay)) : 'Rp ' + formatThousand(sisaAfterPay)">
                                        </span>
                                    </div>
                                </div>

                                <div x-show="nominal > sisa" role="alert" class="p-2.5 bg-destructive/10 border border-destructive/20 rounded-lg text-[11px] text-destructive space-y-1">
                                    <div class="font-medium flex items-center gap-1">
                                        <svg class="size-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <span>Nominal melebihi sisa tagihan!</span>
                                    </div>
                                    <p class="text-destructive/90">Maksimal pembayaran yang dapat dicatat adalah Rp <span x-text="formatThousand(sisa)"></span>.</p>
                                    <button type="button" @click="setNominal(sisa, 'pelunasan')" class="underline font-medium hover:opacity-80 cursor-pointer">
                                        Klik untuk sesuaikan ke Rp <span x-text="formatThousand(sisa)"></span>
                                    </button>
                                </div>
                            </div>

                            @if($po->skema_bayar === 'tempo')
                            <!-- Fitur Adjustment Tanggal Jatuh Tempo (Khusus Skema Tempo) -->
                            <div class="rounded-xl border border-border bg-gray-50 dark:bg-gray-800/40 p-3 space-y-2.5" x-data="{ adjustTempo: false }">
                                <div class="flex items-center justify-between gap-2">
                                    <label class="text-xs font-medium text-foreground flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox" x-model="adjustTempo" class="rounded border-input text-primary focus:ring-ring size-3.5 cursor-pointer">
                                        <span>Adjustment Jatuh Tempo PO</span>
                                    </label>
                                    <span class="text-[10px] text-muted-foreground tabular-nums font-medium bg-card px-1.5 py-0.5 rounded border border-border">
                                        Tempo Saat Ini: {{ $po->tanggal_tempo ? $po->tanggal_tempo->format('d/m/Y') : ($po->eta ? $po->eta->format('d/m/Y') : '—') }}
                                    </span>
                                </div>
                                <div x-show="adjustTempo" x-cloak class="pt-2 border-t border-border space-y-1.5">
                                    <label for="pay_tanggal_tempo_baru" class="block text-[11px] font-medium text-foreground">
                                        Tanggal Jatuh Tempo Baru untuk Sisa Tagihan:
                                    </label>
                                    <input id="pay_tanggal_tempo_baru" type="date"
                                        name="tanggal_tempo_baru"
                                        value="{{ $po->tanggal_tempo ? $po->tanggal_tempo->format('Y-m-d') : date('Y-m-d', strtotime('+30 days')) }}"
                                        min="{{ date('Y-m-d') }}"
                                        class="w-full border border-input bg-card text-xs py-1.5 px-3 focus:border-ring focus:ring-1 focus:ring-ring outline-none rounded-[6px]">
                                    <p class="text-[10px] text-muted-foreground leading-tight">
                                        Tanggal jatuh tempo PO akan diperbarui otomatis di sistem untuk sisa tagihan tempo berikutnya.
                                    </p>
                                </div>
                            </div>
                            @endif

                            <x-button type="submit" variant="primary" size="sm" class="w-full" x-bind:disabled="isPayDisabled()">
                                <x-slot:icon>
                                    <svg class="size-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </x-slot:icon>
                                Catat Pembayaran {{ ucfirst($po->skema_bayar) }}
                            </x-button>
                        </form>
                        @endif
                        </div>
                    @endif
                    @endcan

                    @if($po->payments->count())
                    <div class="mt-4 pt-3 border-t border-border">
                        <p class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider mb-2">Riwayat Transaksi Bayar:</p>
                        <ul class="space-y-1.5 text-xs text-muted-foreground">
                            @foreach($po->payments as $pay)
                            <li class="flex items-center justify-between py-1.5 px-2.5 bg-gray-50 dark:bg-gray-800/40 rounded-lg border border-border">
                                <div>
                                    <span class="font-medium text-foreground">{{ $pay->tanggal_bayar->format('d/m/Y') }}</span>
                                    <span class="text-[10px] text-muted-foreground capitalize block">{{ $pay->skema }}</span>
                                </div>
                                <span class="font-medium tabular-nums text-foreground">Rp {{ number_format($pay->nominal, 0, ',', '.') }}</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </x-card>
                @endif
            </div>
        </div>

        {{-- Tabel Histori Revisi Komitmen (tampil hanya jika PO memiliki histori revisi) --}}
        @if($hasRevisis)
        @php
            $revisionStatusMap = [
                \App\Models\PoRevisi::STATUS_DISETUJUI => ['label' => 'Disetujui', 'variant' => 'success', 'dot' => true],
                \App\Models\PoRevisi::STATUS_DITOLAK => ['label' => 'Ditolak', 'variant' => 'danger', 'dot' => true],
                \App\Models\PoRevisi::STATUS_DIBATALKAN => ['label' => 'Dibatalkan', 'variant' => 'neutral', 'dot' => false],
                \App\Models\PoRevisi::STATUS_MENUNGGU => ['label' => 'Menunggu Approval', 'variant' => 'warning', 'dot' => true],
            ];
            $sortedRevisis = $po->revisis->sortByDesc('revisi_ke')->values();
        @endphp
        <div class="mt-6">
            <x-card class="tbl-modern" variant="elevated"
                title="Histori Revisi Komitmen"
                :subtitle="'Jejak seluruh usulan perubahan komitmen PO (' . $sortedRevisis->count() . ' revisi tercatat)'"
                :noPadding="true"
            >
                <div tabindex="0" role="region" aria-labelledby="histori-revisi-caption" class="overflow-x-auto max-h-[32rem]">
                    <table class="w-full text-xs text-left border-separate border-spacing-0">
                        <caption id="histori-revisi-caption" class="sr-only">Histori Revisi Komitmen Purchase Order {{ $po->no_po }}</caption>
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800/40 backdrop-blur-xs text-muted-foreground font-medium uppercase text-[10px] border-b border-border">
                            <tr>
                                <th scope="col" class="px-4 py-2.5 whitespace-nowrap border-b border-border">Revisi &amp; Status</th>
                                <th scope="col" class="px-4 py-2.5 min-w-44 border-b border-border">Alasan &amp; Catatan Keputusan</th>
                                <th scope="col" class="px-4 py-2.5 min-w-44 border-b border-border">Perubahan Item</th>
                                @if($canSeePrice)
                                    <th scope="col" class="px-4 py-2.5 text-right whitespace-nowrap border-b border-border">Dampak Finansial</th>
                                @endif
                                <th scope="col" class="px-4 py-2.5 whitespace-nowrap border-b border-border">Diajukan</th>
                                <th scope="col" class="px-4 py-2.5 whitespace-nowrap border-b border-border">Diputuskan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-card">
                            @foreach($sortedRevisis as $rev)
                                @php
                                    $diff = $buildRevisionComparison($rev, $rev->status === \App\Models\PoRevisi::STATUS_MENUNGGU);
                                    $statusMeta = $revisionStatusMap[$rev->status]
                                        ?? ['label' => ucfirst((string) $rev->status), 'variant' => 'neutral', 'dot' => false];
                                    $deltaGtClass = $diff['deltaGrandTotal'] > 0.5
                                        ? 'text-foreground'
                                        : ($diff['deltaGrandTotal'] < -0.5 ? 'text-foreground' : 'text-muted-foreground');
                                @endphp
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <th scope="row" class="px-4 py-3 align-top text-left font-normal whitespace-nowrap">
                                        <div class=" tabular-nums font-medium text-foreground">
                                            Revisi ke-{{ $rev->revisi_ke }}
                                        </div>
                                        <div class="mt-1">
                                            <x-badge :variant="$statusMeta['variant']" :dot="$statusMeta['dot']" size="sm">
                                                {{ $statusMeta['label'] }}
                                            </x-badge>
                                        </div>
                                        <div class="text-[11px] text-muted-foreground mt-1">
                                            Stage: {{ $diff['stageLabel'] }}
                                        </div>
                                    </th>

                                    <td class="px-4 py-3 align-top max-w-xs">
                                        <p class="font-medium text-foreground break-words leading-snug">
                                            {{ $rev->alasan ?: '—' }}
                                        </p>
                                        @if(filled($rev->catatan_keputusan))
                                            <p class="mt-1.5 text-[11px] leading-snug break-words {{ $rev->status === \App\Models\PoRevisi::STATUS_DITOLAK ? 'text-red-700 bg-red-50/70 border border-red-200/70 rounded-md px-2 py-1' : 'text-muted-foreground bg-gray-50 dark:bg-gray-800/40 rounded-md px-2 py-1' }}">
                                                <span class="font-medium">Catatan:</span> {{ $rev->catatan_keputusan }}
                                            </p>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 align-top">
                                        @if($diff['itemComparisons']->isNotEmpty())
                                            <ul class="space-y-1.5">
                                                @foreach($diff['itemComparisons'] as $itRow)
                                                    <li class="text-[11px] leading-snug">
                                                        <span class="font-medium text-foreground">{{ $itRow['nama'] }}</span>
                                                        <div class=" tabular-nums text-muted-foreground mt-0.5">
                                                            <span>{{ $fmtQty($itRow['old_qty_beli']) }} {{ $itRow['old_sat_beli'] }}</span>
                                                            <span aria-hidden="true"> &rarr; </span>
                                                            <span class="text-foreground font-medium">{{ $fmtQty($itRow['new_qty_beli']) }} {{ $itRow['new_sat_beli'] }}</span>
                                                            @if($canSeePrice && $itRow['harga_changed'])
                                                                <span class="block text-[10px] text-muted-foreground">
                                                                    {{ $fmtRp($itRow['old_harga']) }} &rarr; {{ $fmtRp($itRow['new_harga']) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="text-muted-foreground italic">Penyesuaian komponen biaya PO</span>
                                        @endif
                                    </td>

                                    @if($canSeePrice)
                                        <td class="px-4 py-3 align-top text-right tabular-nums whitespace-nowrap">
                                            <div class="text-[11px] text-muted-foreground">
                                                {{ $fmtRp($diff['oldGrandTotal']) }} &rarr; <span class="text-foreground font-medium">{{ $fmtRp($diff['newGrandTotal']) }}</span>
                                            </div>
                                            <div class="text-[11px] font-medium mt-0.5 {{ $deltaGtClass }}">
                                                {{ $fmtSignedRp($diff['deltaGrandTotal']) }}
                                            </div>
                                        </td>
                                    @endif

                                    <td class="px-4 py-3 align-top whitespace-nowrap">
                                        <div class="font-medium text-foreground">
                                            {{ $rev->requester?->name ?? ($rev->diajukan_oleh ? 'User #' . $rev->diajukan_oleh : '—') }}
                                        </div>
                                        <div class="text-muted-foreground tabular-nums text-[11px] mt-0.5">
                                            {{ $diff['submittedAt'] }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 align-top whitespace-nowrap">
                                        @if($rev->status === \App\Models\PoRevisi::STATUS_MENUNGGU)
                                            <span class="text-muted-foreground italic">Belum diputuskan</span>
                                        @else
                                            <div class="font-medium text-foreground">
                                                {{ $rev->decider?->name ?? ($rev->diputuskan_oleh ? 'User #' . $rev->diputuskan_oleh : 'Sistem') }}
                                            </div>
                                            <div class="text-muted-foreground tabular-nums text-[11px] mt-0.5">
                                                {{ $diff['decidedAt'] }}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
        @endif

        {{-- Tabel Riwayat Log Perubahan Operasional (PO_LOG_PERUBAHAN) --}}
        @if($po->relationLoaded('logPerubahan') && $po->logPerubahan->isNotEmpty())
        <div class="mt-6">
            <x-card class="tbl-modern" variant="elevated" title="Riwayat Perubahan Operasional (Direct Edit Log)" subtitle="Jejak audit perubahan field operasional tanpa persetujuan manager" :noPadding="true">
                <div tabindex="0" role="region" aria-label="Riwayat Perubahan Operasional Direct Edit" class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 dark:bg-gray-800/40 text-muted-foreground font-medium uppercase text-[10px] border-b border-border">
                            <tr>
                                <th class="px-4 py-2.5">Field Diubah</th>
                                <th class="px-4 py-2.5">Nilai Lama</th>
                                <th class="px-4 py-2.5">Nilai Baru</th>
                                <th class="px-4 py-2.5">Diubah Oleh</th>
                                <th class="px-4 py-2.5">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-card">
                            @foreach($po->logPerubahan as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 tabular-nums font-medium text-foreground">{{ $log->nama_field }}</td>
                                <td class="px-4 py-2 text-muted-foreground italic tabular-nums">{{ $log->nilai_lama ?? '—' }}</td>
                                <td class="px-4 py-2 text-foreground tabular-nums font-medium">{{ $log->nilai_baru ?? '—' }}</td>
                                <td class="px-4 py-2 text-foreground">{{ $log->changer?->name ?? 'User #' . $log->diubah_oleh }}</td>
                                <td class="px-4 py-2 text-muted-foreground tabular-nums text-[11px]">{{ $log->diubah_pada ? $log->diubah_pada->format('d/m/Y H:i') : '—' }}</td>
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
            <x-card variant="elevated" title="Lead Time Transaksi PO {{ $po->no_po }}" subtitle="Rekapitulasi durasi realisasi pengadaan aktual per stage & per kompartemen">
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-lg border {{ $po->leadTime->is_valid ? 'bg-card border-border' : 'bg-destructive/10 border-destructive/20' }}">
                        <div class="flex items-center gap-3">
                            <span class="size-8 rounded-full flex items-center justify-center font-medium text-xs {{ $po->leadTime->is_valid ? 'bg-primary text-primary-foreground' : 'bg-destructive text-destructive-foreground' }}">
                                {{ $po->leadTime->is_valid ? '✓' : '✕' }}
                            </span>
                            <div>
                                <div class="font-medium text-xs text-foreground">
                                    Status Validitas: {{ $po->leadTime->is_valid ? 'Valid (Masuk Benchmark)' : 'Tidak Valid (Dikeluarkan dari Benchmark)' }}
                                </div>
                                @if(!$po->leadTime->is_valid)
                                    <p class="text-[11px] text-destructive mt-0.5">
                                        <strong>Alasan:</strong> {{ $po->leadTime->alasan_tidak_valid }} · <em>Divalidasi oleh: {{ $po->leadTime->validator?->name ?? 'Manager' }}</em>
                                    </p>
                                @endif
                            </div>
                        </div>

                        @can('purchasing.approve')
                        @if($po->leadTime->is_valid)
                            <x-button type="button" variant="outline" size="xs" class="border-destructive/30 text-destructive hover:bg-destructive/10" @click="openInvalidateModal = true">
                                Invalidasi Transaksi Ini
                            </x-button>
                        @endif
                        @endcan
                    </div>

                    @php
                        $hasPoStages = $po->relationLoaded('stages') && $po->stages->isNotEmpty();
                        $cardInternalMenit = $hasPoStages
                            ? (int) $po->stages->whereIn('stage', [\App\Models\PoStage::STAGE_1, \App\Models\PoStage::STAGE_2, \App\Models\PoStage::STAGE_3, \App\Models\PoStage::STAGE_4])->sum('durasi_menit')
                            : (int) ($po->leadTime->lt_internal_menit ?? 0);
                        $cardSupplierMenit = $hasPoStages
                            ? (int) $po->stages->whereIn('stage', [\App\Models\PoStage::STAGE_5, \App\Models\PoStage::LEGACY_STAGE_PENGEMASAN])->sum('durasi_menit')
                            : (int) ($po->leadTime->lt_supplier_menit ?? 0);
                        $cardTransportMenit = $hasPoStages
                            ? (int) $po->stages->where('stage', \App\Models\PoStage::STAGE_6)->sum('durasi_menit')
                            : (int) ($po->leadTime->lt_logistik_menit ?? 0);
                        $cardReceivingMenit = $hasPoStages
                            ? (int) $po->stages->whereIn('stage', [\App\Models\PoStage::STAGE_7, \App\Models\PoStage::STAGE_8])->sum('durasi_menit')
                            : 0;
                        $cardTotalMenit = $hasPoStages
                            ? ($cardInternalMenit + $cardSupplierMenit + $cardTransportMenit + $cardReceivingMenit)
                            : (int) ($po->leadTime->total_lt_menit ?? 0);
                    @endphp

                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs">
                        <div class="p-3 bg-card rounded-xl border border-border " title="Stage 1-4: Perencanaan, Approval, Supplier Confirm, Payment">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="size-2 rounded-full bg-gray-400 dark:bg-gray-500 shrink-0"></span>
                                <span class="text-[10px] uppercase font-medium text-muted-foreground tracking-wider truncate">Internal Processing</span>
                            </div>
                            <p class="tabular-nums font-medium text-sm text-foreground mt-0.5">
                                {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($cardInternalMenit) }} hari
                                <span class="text-[10px] text-muted-foreground font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($cardInternalMenit) }} j)</span>
                            </p>
                        </div>

                        <div class="p-3 bg-card rounded-xl border border-border " title="Stage 5: Penerbitan PO Resmi">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="size-2 rounded-full bg-gray-400 dark:bg-gray-500 shrink-0"></span>
                                <span class="text-[10px] uppercase font-medium text-muted-foreground tracking-wider truncate">Supplier Processing</span>
                            </div>
                            <p class="tabular-nums font-medium text-sm text-foreground mt-0.5">
                                {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($cardSupplierMenit) }} hari
                                <span class="text-[10px] text-muted-foreground font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($cardSupplierMenit) }} j)</span>
                            </p>
                        </div>

                        <div class="p-3 bg-card rounded-xl border border-border " title="Stage 7: Pengiriman">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="size-2 rounded-full bg-gray-400 dark:bg-gray-500 shrink-0"></span>
                                <span class="text-[10px] uppercase font-medium text-muted-foreground tracking-wider truncate">Transportation Time</span>
                            </div>
                            <p class="tabular-nums font-medium text-sm text-foreground mt-0.5">
                                {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($cardTransportMenit) }} hari
                                <span class="text-[10px] text-muted-foreground font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($cardTransportMenit) }} j)</span>
                            </p>
                        </div>

                        <div class="p-3 bg-card rounded-xl border border-border " title="Stage 8-9: Unloading, Input">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="size-2 rounded-full bg-gray-400 dark:bg-gray-500 shrink-0"></span>
                                <span class="text-[10px] uppercase font-medium text-muted-foreground tracking-wider truncate">Receiving</span>
                            </div>
                            <p class="tabular-nums font-medium text-sm text-foreground mt-0.5">
                                {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($cardReceivingMenit) }} hari
                                <span class="text-[10px] text-muted-foreground font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($cardReceivingMenit) }} j)</span>
                            </p>
                        </div>

                        <div class="p-3 bg-card rounded-xl border border-border ">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="size-2 rounded-full bg-primary shrink-0"></span>
                                <span class="text-[10px] uppercase font-medium text-foreground tracking-wider truncate">Total Realisasi LT</span>
                            </div>
                            <p class="tabular-nums font-medium text-sm text-foreground mt-0.5">
                                {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($cardTotalMenit) }} hari
                                <span class="text-[10px] text-muted-foreground font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($cardTotalMenit) }} j)</span>
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


        {{-- MODAL INVALIDATE LEAD TIME (Manager) --}}
        @if($po->leadTime)
        @can('purchasing.approve')
        <div x-show="openInvalidateModal" class="fixed inset-0 z-[999] bg-black/50 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
            <div class="bg-white dark:bg-gray-900 rounded-[12px] border border-[#E8E8E8] dark:border-border max-w-sm w-full shadow-2xl overflow-hidden">
                <div class="px-4 py-3 bg-white dark:bg-gray-900 border-b border-[#E8E8E8] dark:border-border flex items-center justify-between">
                    <h3 class="font-semibold text-base text-text-active tracking-tight">Invalidasi Transaksi Lead Time</h3>
                    <button type="button" @click="openInvalidateModal = false" class="text-text-muted hover:text-text-active cursor-pointer p-1 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-[6px]">✕</button>
                </div>
                <form method="POST" action="{{ route('purchasing.lead-time.invalidate', $po->leadTime) }}" class="text-xs">
                    @csrf
                    <div class="bg-white dark:bg-gray-900 p-4 space-y-3">
                        <p class="text-xs text-text-muted leading-relaxed">
                            Tindakan ini akan mengeluarkan durasi transaksi ini dari perhitungan rata-rata Lead Time supplier.
                        </p>
                        <x-form-field label="Alasan Invalidasi" name="alasan" :required="true" align="top">
                            <textarea name="alasan" required minlength="5" rows="3" placeholder="Contoh: Ekspedisi mogok luar biasa (anomali force majeure)..." class="w-full bg-transparent border-0 p-0 text-[13px] font-medium text-text-active focus:outline-none focus:ring-0"></textarea>
                        </x-form-field>
                    </div>
                    <div class="bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-border px-4 py-3 flex justify-end gap-2">
                        <x-button type="button" variant="secondary" size="xs" @click="openInvalidateModal = false">Batal</x-button>
                        <x-button type="submit" variant="danger" size="xs">Invalidasi</x-button>
                    </div>
                </form>
            </div>
        </div>
        @endcan
        @endif

        {{-- MODAL UBAH NO. INVOICE & ETA (sejak keluar dari draft sampai PO final) --}}
        @can('purchasing.edit_invoice_eta')
        @if($po->canEditInvoiceEta())
        <x-modal name="openInvoiceEtaModal" title="Ubah No. Invoice &amp; ETA" :subtitle="e($po->no_po) . ' · Perubahan tercatat di riwayat audit PO.'" maxWidth="md">
            <form id="form-invoice-eta" method="POST" action="{{ route('purchasing.invoice-eta', $po) }}" class="flex flex-col gap-3.5">
                @csrf
                @method('PUT')

                <x-form-field label="No. Invoice Vendor" name="no_invoice" help="Nomor invoice referensi dari vendor. Kosongkan bila belum ada.">
                    <x-input type="text" name="no_invoice" variant="seamless" maxlength="50" value="{{ old('no_invoice', $po->no_invoice) }}" placeholder="Contoh: INV/PO/202609/0001" />
                </x-form-field>

                <x-form-field label="Estimasi (ETA)" name="eta" help="Perkiraan pesanan tiba di gudang.">
                    <x-input type="date" name="eta" variant="seamless" value="{{ old('eta', $po->eta?->format('Y-m-d')) }}" />
                </x-form-field>
            </form>

            <x-slot:footer>
                <x-button type="button" variant="secondary" size="xs" @click="openInvoiceEtaModal = false">Batal</x-button>
                <x-button type="submit" form="form-invoice-eta" variant="primary" size="xs">Simpan Perubahan</x-button>
            </x-slot:footer>
        </x-modal>
        @endif
        @endcan
    </div>{{-- Close outer x-data --}}

    @push('scripts')
    <script>
        function bardatForm() {
            return {
                kondisiMuatan: 'baik',
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
                },
                isDiscrepancyRequired(idx) {
                    return this.getDiff(idx) !== 0 || this.kondisiMuatan === 'rusak_sebagian';
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

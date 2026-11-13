<x-app-layout title="Detail Purchase Order {{ $purchaseOrder->no_po }}">
    @php
        $po = $purchaseOrder;
        $poBadgeVariant = match($po->status) {
            'diajukan' => 'warning',
            'disetujui' => 'primary',
            'dikirim_ke_gudang' => 'info',
            'selesai' => 'success',
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
            'overdue' => 'danger',
            'belum_lunas' => 'warning',
            'parsial' => 'info',
            'lunas' => 'success',
            'batal' => 'danger',
            default => 'neutral',
        };
        $isTempoOverdue = $po->status !== 'dibatalkan' && $po->skema_bayar === 'tempo' && $po->tanggal_tempo && $po->tanggal_tempo->isPast() && !$po->isLunas();
        $isEtaPast = $po->eta && $po->eta->isPast() && $po->status !== 'selesai';
        $fmtQtyDisplay = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
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
                    <x-badge variant="warning" size="sm">Dari Analisa</x-badge>
                @endif

                @can('purchasing.edit')
                    @if(($po->stage_aktif === null || $po->stage_aktif === \App\Models\PoStage::STAGE_1) && in_array($po->status, ['draft', 'diajukan', 'disetujui'], true) && $po->barangDatang->isEmpty())
                        <x-button href="{{ route('purchasing.edit', $po) }}" variant="outline" size="xs">
                            <x-slot:icon>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </x-slot:icon>
                            Edit PO
                        </x-button>
                    @endif

                    @if($hasStages && !in_array($po->status, ['selesai', 'dibatalkan']))
                        <x-button type="button" @click="openDirectEditModal = true" variant="outline" size="xs">
                            Direct Edit
                        </x-button>
                        @if(!in_array($po->stage_aktif, [\App\Models\PoStage::STAGE_7, \App\Models\PoStage::STAGE_8]))
                            <x-button type="button" @click="openRevisiModal = true" variant="outline" size="xs" class="border-amber-500/40 text-amber-800 hover:bg-amber-500/10">
                                Ajukan Revisi
                            </x-button>
                        @endif
                    @endif
                @endcan
            </x-slot:actions>
        </x-page-header>

        <!-- Progress Status Stepper (Requirement 6) -->
        <div class="mb-5">
            <x-purchasing-progress-stepper :po="$po" :stageBenchmarks="$stageBenchmarks ?? null" />
        </div>

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
        <section aria-labelledby="pending-revisi-heading" class="mb-5 bg-card border border-amber-300/80 rounded-xl shadow-xs overflow-hidden">
            <div class="px-4 sm:px-5 py-3.5 bg-amber-50/70 border-b border-amber-200/80 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="size-8 rounded-lg bg-amber-100 border border-amber-200 text-amber-700 flex items-center justify-center shrink-0" aria-hidden="true">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 id="pending-revisi-heading" class="text-sm font-semibold text-foreground tracking-tight">
                                Antrean Persetujuan Revisi Komitmen
                            </h2>
                            <x-badge variant="warning" size="sm">Revisi ke-{{ $pendingRevisis->first()->revisi_ke }}</x-badge>
                            @if($pendingRevisis->count() > 1)
                                <x-badge variant="neutral" size="sm">{{ $pendingRevisis->count() }} Antrean</x-badge>
                            @endif
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">
                            Tinjau selisih kuantitas dan dampak biaya sebelum menyetujui pembaruan komitmen PO aktif.
                        </p>
                    </div>
                </div>
                <x-badge variant="warning" :dot="true" size="sm">Menunggu Approval Manager</x-badge>
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
                            <div class="lg:col-span-8 rounded-xl border border-border bg-muted/25 p-3.5 flex items-start gap-3">
                                <span class="size-7 rounded-lg bg-card border border-border text-muted-foreground flex items-center justify-center shrink-0 mt-0.5" aria-hidden="true">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h6m-2 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground block mb-1">
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
                                    <dd class="font-semibold text-foreground text-right truncate">{{ $rev->requester?->name ?? 'Purchasing' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-[11px] text-muted-foreground">Waktu pengajuan</dt>
                                    <dd class="font-mono tabular-nums text-foreground text-right">{{ $submittedAt }}</dd>
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
                                    <span class="text-[10px] uppercase tracking-wider font-semibold text-muted-foreground block">Item Diubah</span>
                                    <span class="font-mono tabular-nums text-sm font-bold text-foreground mt-0.5 block">
                                        {{ $changedItemCount }} / {{ $totalItemCount }} item
                                    </span>
                                </div>
                                <x-badge :variant="$changedItemCount > 0 ? 'warning' : 'neutral'" size="sm">
                                    {{ $changedItemCount > 0 ? 'Ada Perubahan' : 'Item Tetap' }}
                                </x-badge>
                            </div>

                            <div class="rounded-xl border border-border bg-card px-3.5 py-2.5 flex items-center justify-between gap-3">
                                <div>
                                    <span class="text-[10px] uppercase tracking-wider font-semibold text-muted-foreground block">Versi Komitmen</span>
                                    <span class="font-mono tabular-nums text-sm font-bold text-foreground mt-0.5 block">
                                        Rev ke-{{ (int) $po->revisi_ke }} &rarr; ke-{{ $rev->revisi_ke }}
                                    </span>
                                </div>
                                <x-badge variant="info" size="sm">Usulan</x-badge>
                            </div>

                            @if($canSeePrice)
                                <div class="rounded-xl border border-border bg-card px-3.5 py-2.5">
                                    <span class="text-[10px] uppercase tracking-wider font-semibold text-muted-foreground block">Grand Total Sebelumnya</span>
                                    <span class="font-mono tabular-nums text-sm font-semibold text-muted-foreground mt-0.5 block whitespace-nowrap">
                                        {{ $fmtRp($oldGrandTotal) }}
                                    </span>
                                </div>

                                <div class="rounded-xl border {{ abs($deltaGrandTotal) >= 0.5 ? 'border-amber-300/80 bg-amber-50/40' : 'border-border bg-card' }} px-3.5 py-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-[10px] uppercase tracking-wider font-semibold text-muted-foreground">Grand Total Usulan Baru</span>
                                        @php
                                            $deltaGtVariant = $deltaGrandTotal > 0.5 ? 'warning' : ($deltaGrandTotal < -0.5 ? 'success' : 'neutral');
                                        @endphp
                                        <x-badge :variant="$deltaGtVariant" size="sm">{{ $fmtSignedRp($deltaGrandTotal) }}</x-badge>
                                    </div>
                                    <span class="font-mono tabular-nums text-sm font-extrabold text-foreground mt-0.5 block whitespace-nowrap">
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
                                    <span class="text-[11px] text-muted-foreground font-mono tabular-nums">
                                        {{ $totalItemCount }} item · {{ $changedItemCount }} berubah
                                    </span>
                                </div>

                                <div tabindex="0" role="region" aria-labelledby="revisi-items-title-{{ $rev->id }}" class="overflow-x-auto rounded-xl border border-border bg-card">
                                    <table class="w-full text-xs text-left">
                                        <caption class="sr-only">Perbandingan item Purchase Order {{ $po->no_po }} pada Revisi ke-{{ $rev->revisi_ke }}</caption>
                                        <thead class="bg-muted/60 text-muted-foreground font-semibold text-[11px] uppercase tracking-wider border-b border-border">
                                            <tr>
                                                <th scope="col" class="py-2.5 px-3.5 min-w-44">Bahan Baku / SKU</th>
                                                <th scope="col" class="py-2.5 px-3.5 text-right whitespace-nowrap">Sebelum</th>
                                                <th scope="col" class="py-2.5 px-3.5 text-right whitespace-nowrap bg-muted/80 text-foreground">Usulan Baru</th>
                                                <th scope="col" class="py-2.5 px-3.5 text-right whitespace-nowrap">Selisih</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border bg-card">
                                            @foreach($itemComparisons as $itRow)
                                                <tr class="{{ $itRow['changed'] ? 'bg-amber-50/35' : 'opacity-80' }} hover:bg-muted/30 transition-colors">
                                                    <th scope="row" class="py-3 px-3.5 font-normal text-left align-top">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="font-semibold text-foreground leading-snug">{{ $itRow['nama'] }}</span>
                                                            <x-badge :variant="$itRow['changed'] ? 'warning' : 'muted'" size="sm">
                                                                {{ $itRow['changed'] ? 'Berubah' : 'Tetap' }}
                                                            </x-badge>
                                                        </div>
                                                        <div class="font-mono text-[10px] text-muted-foreground mt-0.5">
                                                            {{ $itRow['sku'] }} · Satuan dasar: {{ $itRow['sat_dasar'] }}
                                                        </div>
                                                    </th>

                                                    <td class="py-3 px-3.5 text-right align-top font-mono tabular-nums text-muted-foreground whitespace-nowrap">
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

                                                    <td class="py-3 px-3.5 text-right align-top font-mono tabular-nums bg-muted/20 whitespace-nowrap">
                                                        <div class="font-semibold text-foreground">
                                                            {{ $fmtQty($itRow['new_qty_beli']) }} <span class="text-[11px] font-normal text-muted-foreground">{{ $itRow['new_sat_beli'] }}</span>
                                                        </div>
                                                        @if(strcasecmp((string) $itRow['new_sat_beli'], (string) $itRow['sat_dasar']) !== 0)
                                                            <div class="text-[10px] text-muted-foreground mt-0.5">
                                                                = {{ $fmtQty($itRow['new_qty_dasar']) }} {{ $itRow['sat_dasar'] }}
                                                            </div>
                                                        @endif
                                                        @if($canSeePrice)
                                                            <div class="text-[11px] font-bold text-foreground mt-1">
                                                                {{ $fmtRp($itRow['new_harga']) }}
                                                            </div>
                                                        @endif
                                                    </td>

                                                    <td class="py-3 px-3.5 text-right align-top font-mono tabular-nums whitespace-nowrap">
                                                        @if($itRow['qty_changed'])
                                                            @if($itRow['delta_qty_beli'] !== null && abs($itRow['delta_qty_beli']) > 0.0001)
                                                                <div class="font-semibold {{ $itRow['delta_qty_beli'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                                                                    {{ ($itRow['delta_qty_beli'] > 0 ? '+' : '-') . $fmtQty(abs($itRow['delta_qty_beli'])) }} {{ $itRow['new_sat_beli'] }}
                                                                </div>
                                                            @else
                                                                <div class="text-[11px] font-semibold text-amber-700">Ubah UOM</div>
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
                                                                <div class="text-[11px] font-semibold mt-1 {{ $itRow['delta_harga'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
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
                                        <thead class="bg-muted/60 text-muted-foreground font-semibold text-[11px] uppercase tracking-wider border-b border-border">
                                            <tr>
                                                <th scope="col" class="py-2.5 px-3.5 min-w-44">Komponen Biaya</th>
                                                <th scope="col" class="py-2.5 px-3.5 text-right whitespace-nowrap">Sebelum</th>
                                                <th scope="col" class="py-2.5 px-3.5 text-right whitespace-nowrap bg-muted/80 text-foreground">Usulan Baru</th>
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
                                                <tr class="{{ $fChanged ? 'bg-amber-50/30' : '' }} hover:bg-muted/30 transition-colors">
                                                    <th scope="row" class="py-2.5 px-3.5 font-medium text-foreground text-left">
                                                        {{ $fRow['label'] }}
                                                    </th>
                                                    <td class="py-2.5 px-3.5 text-right font-mono tabular-nums text-muted-foreground whitespace-nowrap">
                                                        {{ $fmtFinanceCell($fRow['old']) }}
                                                    </td>
                                                    <td class="py-2.5 px-3.5 text-right font-mono tabular-nums font-semibold text-foreground bg-muted/20 whitespace-nowrap">
                                                        {{ $fmtFinanceCell($fRow['new']) }}
                                                    </td>
                                                    <td class="py-2.5 px-3.5 text-right font-mono tabular-nums whitespace-nowrap {{ $fChanged ? ($fDelta > 0 ? 'text-amber-700 font-semibold' : 'text-emerald-700 font-semibold') : 'text-muted-foreground' }}">
                                                        {{ $fmtSignedRp($fDelta) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-muted/50 border-t-2 border-border">
                                                <th scope="row" class="py-3 px-3.5 font-bold text-foreground text-left">
                                                    Grand Total Tagihan
                                                </th>
                                                <td class="py-3 px-3.5 text-right font-mono tabular-nums font-semibold text-muted-foreground whitespace-nowrap">
                                                    {{ $fmtRp($oldGrandTotal) }}
                                                </td>
                                                <td class="py-3 px-3.5 text-right font-mono tabular-nums font-extrabold text-sm text-foreground bg-muted/40 whitespace-nowrap">
                                                    {{ $fmtRp($newGrandTotal) }}
                                                </td>
                                                <td class="py-3 px-3.5 text-right font-mono tabular-nums font-bold whitespace-nowrap {{ $deltaGrandTotal > 0.5 ? 'text-amber-700' : ($deltaGrandTotal < -0.5 ? 'text-emerald-700' : 'text-muted-foreground') }}">
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
                                Menyetujui usulan akan memperbarui nilai komitmen PO aktif ke <strong class="text-foreground font-semibold">Revisi ke-{{ $rev->revisi_ke }}</strong> tanpa mereset waktu stage.
                            </p>

                            <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
                                <x-button type="button" variant="outline" size="sm" class="border-destructive/30 !text-destructive hover:!text-destructive hover:bg-destructive/10" @click="openRejectDialog()">
                                    ✕ Tolak Usulan
                                </x-button>

                                <form method="POST" action="{{ route('purchasing.revisi.approve', $rev) }}" class="inline">
                                    @csrf
                                    <x-button type="submit" variant="success" size="sm">
                                        ✓ Setujui Revisi Komitmen
                                    </x-button>
                                </form>
                            </div>

                            <dialog id="reject-revisi-dialog-{{ $rev->id }}"
                                    x-ref="rejectDialog"
                                    aria-labelledby="reject-revisi-title-{{ $rev->id }}"
                                    closedby="closerequest"
                                    class="w-full max-w-md">
                                <div class="bg-card rounded-2xl border border-border shadow-2xl overflow-hidden text-left">
                                    <div class="px-5 py-4 border-b border-border bg-card flex items-center justify-between gap-3">
                                        <h2 id="reject-revisi-title-{{ $rev->id }}" class="text-sm font-semibold text-foreground tracking-tight">
                                            Tolak Usulan Revisi ke-{{ $rev->revisi_ke }}
                                        </h2>
                                        <button type="button"
                                                @click="closeRejectDialog()"
                                                aria-label="Tutup dialog penolakan revisi"
                                                class="size-7 rounded-lg text-muted-foreground hover:text-foreground hover:bg-accent flex items-center justify-center transition cursor-pointer">
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                    <form method="POST" action="{{ route('purchasing.revisi.reject', $rev) }}" class="p-5 space-y-4">
                                        @csrf
                                        <div>
                                            <label for="reject-alasan-{{ $rev->id }}" class="block text-xs font-semibold text-foreground mb-1">
                                                Alasan Penolakan <span class="text-destructive">*</span>
                                            </label>
                                            <p id="reject-hint-{{ $rev->id }}" class="text-[11px] text-muted-foreground mb-2">
                                                Nilai PO aktif tetap dipertahankan. Masukkan alasan penolakan minimal 3 karakter untuk tim Purchasing.
                                            </p>
                                            <textarea id="reject-alasan-{{ $rev->id }}"
                                                      name="alasan"
                                                      x-model="alasanReject"
                                                      autofocus
                                                      required
                                                      minlength="3"
                                                      aria-describedby="reject-hint-{{ $rev->id }}"
                                                      placeholder="Masukkan alasan penolakan usulan revisi..."
                                                      class="w-full text-xs p-2.5 rounded-lg border border-input bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                                                      rows="3"></textarea>
                                        </div>

                                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                                            <x-button type="button" variant="outline" size="sm" @click="closeRejectDialog()">Batal</x-button>
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

        <!-- Metadata Singkat PO -->
        <div class="grid grid-cols-2 {{ $po->skema_bayar === 'tempo' ? 'sm:grid-cols-5' : 'sm:grid-cols-4' }} gap-3 mb-5">
            <div class="bg-card p-3.5 rounded-xl border border-border shadow-2xs min-w-0">
                <span class="text-[10px] uppercase font-semibold text-muted-foreground tracking-wider block">No. Invoice Vendor</span>
                <p class="font-mono font-semibold text-xs text-foreground mt-0.5 truncate">{{ $po->no_invoice ?? '—' }}</p>
            </div>
            <div class="bg-card p-3.5 rounded-xl border border-border shadow-2xs min-w-0">
                <span class="text-[10px] uppercase font-semibold text-muted-foreground tracking-wider block">Lokasi PO (Gudang)</span>
                <p class="font-semibold text-xs text-foreground mt-0.5 truncate">{{ $po->gudang?->nama ?? 'Semua Gudang' }}</p>
            </div>
            <div class="bg-card p-3.5 rounded-xl border border-border shadow-2xs min-w-0">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] uppercase font-semibold text-muted-foreground tracking-wider block">Estimasi Tiba (ETA)</span>
                    @can('purchasing.edit')
                        @if($hasStages && !in_array($po->status, ['selesai', 'dibatalkan']))
                            <button type="button" @click="openDirectEditModal = true" aria-label="Edit ETA" title="Edit ETA" class="text-xs font-medium text-primary hover:underline cursor-pointer">
                                Edit
                            </button>
                        @endif
                    @endcan
                </div>
                <p class="font-mono text-xs {{ $isEtaPast ? 'text-destructive font-bold' : 'text-foreground font-medium' }} mt-0.5">
                    {{ $po->eta ? $po->eta->format('d/m/Y') : '—' }}
                </p>
            </div>
            <div class="bg-card p-3.5 rounded-xl border border-border shadow-2xs min-w-0">
                <span class="text-[10px] uppercase font-semibold text-muted-foreground tracking-wider block">Skema Bayar</span>
                <p class="font-semibold text-xs text-foreground mt-0.5 capitalize">{{ $po->skema_bayar ?? 'cash' }}</p>
            </div>
            @if($po->skema_bayar === 'tempo')
            <div class="bg-card p-3.5 rounded-xl border {{ $isTempoOverdue ? 'border-destructive/30 bg-destructive/5' : 'border-border' }} shadow-2xs min-w-0">
                <span class="text-[10px] uppercase font-semibold {{ $isTempoOverdue ? 'text-destructive' : 'text-muted-foreground' }} tracking-wider block">Deadline Tempo</span>
                <p class="font-mono text-xs {{ $isTempoOverdue ? 'text-destructive font-bold' : 'text-foreground font-semibold' }} mt-0.5">
                    {{ $po->tanggal_tempo ? $po->tanggal_tempo->format('d/m/Y') : ($po->eta ? $po->eta->format('d/m/Y') : '—') }}
                    @if($isTempoOverdue)
                        <span class="text-[9px] uppercase font-bold text-destructive bg-destructive/10 px-1 py-0.5 rounded ml-1 border border-destructive/20">Overdue</span>
                    @endif
                </p>
            </div>
            @endif
        </div>

        @if($po->catatan)
        <div class="mb-5 bg-muted/40 border border-border rounded-xl p-4 flex items-start gap-3">
            <svg class="size-4 text-muted-foreground mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
            <div class="text-xs min-w-0">
                <span class="font-semibold text-foreground block mb-0.5">Catatan / Instruksi Khusus PO:</span>
                <p class="text-muted-foreground whitespace-pre-line">{{ $po->catatan }}</p>
            </div>
        </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Kolom Kiri: Tabel Rincian Item PO & Penerimaan Barang -->
            <div class="lg:col-span-2 space-y-5 min-w-0">
                <!-- Tabel Item PO -->
                <x-card title="Rincian Item Purchase Order" :noPadding="true">
                    <x-slot:tools>
                        <span class="text-xs text-muted-foreground font-mono font-medium">{{ $po->items->count() }} item</span>
                    </x-slot:tools>

                    <div tabindex="0" role="region" aria-label="Tabel Rincian Item Purchase Order" class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <caption class="sr-only">Rincian Item Purchase Order {{ $po->no_po }}</caption>
                            <thead class="bg-muted/60 border-b border-border text-muted-foreground font-semibold text-[11px] uppercase tracking-wider">
                                <tr>
                                    <th scope="col" class="py-3 px-4 min-w-36">Bahan Baku</th>
                                    <th scope="col" class="py-3 px-4 text-right whitespace-nowrap">Qty PO</th>
                                    @if($canSeePrice)
                                        <th scope="col" class="py-3 px-4 text-right whitespace-nowrap">Total Kotor</th>
                                        <th scope="col" class="py-3 px-4 text-right whitespace-nowrap">Net Total</th>
                                        <th scope="col" class="py-3 px-4 text-right whitespace-nowrap bg-muted/80 text-foreground">HPP / Unit</th>
                                    @endif
                                    <th scope="col" class="py-3 px-4 text-right whitespace-nowrap">Diterima Fisik</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border bg-card">
                                @forelse($po->items as $it)
                                <tr class="hover:bg-muted/30 transition-colors">
                                    <th scope="row" class="py-3.5 px-4 font-normal text-left align-top">
                                        <div class="font-semibold text-foreground leading-snug">{{ $it->produk?->nama ?? '—' }}</div>
                                        <div class="font-mono text-[11px] text-muted-foreground mt-0.5">{{ $it->produk?->sku ?? '—' }}</div>
                                    </th>
                                    <td class="py-3.5 px-4 text-right align-top font-mono tabular-nums font-medium text-foreground whitespace-nowrap">
                                        @if($it->qty_satuan_beli && $it->satuan_beli && strcasecmp($it->satuan_beli, $it->produk?->satuan ?? '') !== 0)
                                            <div class="text-foreground font-semibold">
                                                {{ rtrim(rtrim(number_format($it->qty_satuan_beli, 2, ',', '.'), '0'), ',') }}
                                                <span class="text-xs font-normal text-muted-foreground">{{ $it->satuan_beli }}</span>
                                            </div>
                                            <div class="text-[10px] text-muted-foreground bg-muted px-1.5 py-0.5 rounded border border-border inline-block mt-1">
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
                                        <td class="py-3.5 px-4 text-right align-top font-mono tabular-nums text-foreground whitespace-nowrap">
                                            Rp {{ number_format($it->harga_total, 0, ',', '.') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right align-top font-mono tabular-nums font-semibold text-foreground whitespace-nowrap">
                                            Rp {{ number_format($it->netTotal(), abs($it->netTotal() - round($it->netTotal())) < 0.00001 ? 0 : 2, ',', '.') }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right align-top font-mono tabular-nums font-bold text-foreground bg-muted/40 whitespace-nowrap" title="HPP / Satuan Dasar ({{ $it->produk?->satuan }}): {{ $it->formattedHpp() }}">
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
                                    <td class="py-3.5 px-4 text-right align-top font-mono tabular-nums font-medium whitespace-nowrap {{ (float)$it->qtyDiterima() >= (float)$it->qty ? 'text-emerald-700 dark:text-emerald-400 font-bold' : 'text-foreground' }}">
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
                        <div class="border-t border-border bg-muted/20 p-4 sm:px-5 sm:py-4">
                            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                                <p class="text-[11px] leading-relaxed text-muted-foreground max-w-sm">
                                    Komponen <strong class="font-semibold text-foreground">Diskon</strong>, <strong class="font-semibold text-foreground">PPN</strong>, <strong class="font-semibold text-foreground">Ongkir</strong>, dan <strong class="font-semibold text-foreground">Adjustment</strong> dikelompokkan di tingkat PO serta dialokasikan proporsional ke Net Total dan HPP per unit.
                                </p>

                                <div class="w-full md:w-[360px] md:ms-auto shrink-0 rounded-xl border border-border bg-card p-3.5 sm:p-4 shadow-2xs space-y-3">
                                    <dl class="flex items-center justify-between gap-4 text-xs">
                                        <dt class="text-muted-foreground font-medium">Subtotal Bahan (Kotor)</dt>
                                        <dd class="font-mono tabular-nums font-semibold text-foreground whitespace-nowrap">
                                            Rp {{ number_format($subtotalKotor, 0, ',', '.') }}
                                        </dd>
                                    </dl>

                                    <div class="rounded-lg bg-muted/40 border border-border/70 px-3 py-2.5 space-y-2">
                                        <div class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground pb-1 border-b border-border/60">
                                            Biaya &amp; Penyesuaian Global PO
                                        </div>
                                        <dl class="space-y-1.5 text-xs">
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-muted-foreground">Diskon</dt>
                                                <dd class="font-mono tabular-nums whitespace-nowrap {{ $diskonGlobal > 0 ? 'text-destructive font-semibold' : 'text-muted-foreground' }}">
                                                    {{ $diskonGlobal > 0 ? '-Rp ' . number_format($diskonGlobal, 0, ',', '.') : 'Rp 0' }}
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-muted-foreground">PPN</dt>
                                                <dd class="font-mono tabular-nums whitespace-nowrap {{ $ppnGlobal > 0 ? 'text-foreground font-semibold' : 'text-muted-foreground' }}">
                                                    {{ $ppnGlobal > 0 ? '+Rp ' . number_format($ppnGlobal, 0, ',', '.') : 'Rp 0' }}
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-muted-foreground">Ongkir</dt>
                                                <dd class="font-mono tabular-nums whitespace-nowrap {{ $ongkirGlobal > 0 ? 'text-foreground font-semibold' : 'text-muted-foreground' }}">
                                                    {{ $ongkirGlobal > 0 ? '+Rp ' . number_format($ongkirGlobal, 0, ',', '.') : 'Rp 0' }}
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between gap-4">
                                                <dt class="text-muted-foreground">Adjustment</dt>
                                                <dd class="font-mono tabular-nums whitespace-nowrap {{ $adjustmentGlobal < 0 ? 'text-destructive font-semibold' : ($adjustmentGlobal > 0 ? 'text-emerald-700 dark:text-emerald-400 font-semibold' : 'text-muted-foreground') }}">
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
                                        <dt class="text-xs sm:text-sm font-bold text-foreground">Grand Total Nilai Pesanan</dt>
                                        <dd class="font-mono tabular-nums font-extrabold text-base text-foreground whitespace-nowrap">
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
                <x-card title="Riwayat Penerimaan Barang di Gudang" :noPadding="true">
                    <div class="divide-y divide-border">
                        @foreach($po->barangDatang as $bardat)
                        <div class="p-4 space-y-2.5">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-foreground">Terima: {{ $bardat->tanggal_terima->format('d/m/Y') }}</span>
                                    <x-badge :variant="$bardat->kondisi === 'baik' ? 'success' : 'warning'" size="sm">
                                        Kondisi {{ ucfirst(str_replace('_',' ', $bardat->kondisi)) }}
                                    </x-badge>
                                    @if($hasStages && $bardat->status_posting === 'belum')
                                        <x-badge variant="info" size="sm">Menunggu Posting Stok</x-badge>
                                    @elseif($hasStages && $bardat->status_posting === 'diposting')
                                        <x-badge variant="success" :dot="true" size="sm">Stok Diposting</x-badge>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-muted-foreground text-[11px]">
                                    <span>Dicatat oleh: {{ $bardat->creator->name ?? 'Gudang' }}</span>
                                    @if($bardat->diposting_pada)
                                        <span>· Posting: {{ $bardat->diposting_pada->format('d/m/Y H:i') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div tabindex="0" role="region" aria-label="Detail Barang Datang" class="overflow-x-auto bg-muted/20 rounded-lg border border-border">
                                <table class="w-full text-xs text-left">
                                    <caption class="sr-only">Detail penerimaan barang di gudang pada {{ $bardat->tanggal_terima->format('d/m/Y') }}</caption>
                                    <thead class="bg-muted/50 border-b border-border text-muted-foreground text-[10px] font-semibold uppercase tracking-wider">
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
                                        <tr class="hover:bg-muted/30 transition-colors">
                                            <th scope="row" class="py-2.5 px-3 font-medium text-foreground text-left">{{ $namaProduk }}</th>
                                            <td class="py-2.5 px-3 text-right font-mono tabular-nums font-semibold text-foreground whitespace-nowrap">
                                                {{ $fmtQtyDisplay($qtyRiwayatDiterima) }}
                                                @if($satuanProduk !== '')
                                                    <span class="text-muted-foreground font-normal text-[10px]">{{ $satuanProduk }}</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono tabular-nums whitespace-nowrap">
                                                @if($selisihVal < 0)
                                                    <span class="text-destructive font-semibold">{{ $fmtQtyDisplay($selisihVal) }} (Kurang)</span>
                                                @elseif($selisihVal > 0)
                                                    <span class="text-sky-700 dark:text-sky-400 font-semibold">+{{ $fmtQtyDisplay($selisihVal) }} (Lebih)</span>
                                                @else
                                                    <span class="text-emerald-700 dark:text-emerald-400">Sesuai</span>
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
                @if($po->status === 'dikirim_ke_gudang')
                <x-card title="Konfirmasi Barang Datang di Gudang" subtitle="Input kuantitas fisik aktual yang diterima. Jika ada selisih, wajib isi kolom keterangan.">
                    <form method="POST" action="{{ route('purchasing.receive', $po) }}" class="space-y-4" x-data="bardatForm()">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-foreground mb-1">Gudang Penerima</label>
                                @php
                                    $targetGudang = $po->gudang ?? $gudang->firstWhere('id', $po->gudang_id) ?? $gudang->first();
                                @endphp
                                <input type="hidden" name="gudang_id" value="{{ $targetGudang?->id }}">
                                <input type="text" value="{{ $targetGudang?->nama ?? '—' }}" class="w-full rounded-lg border border-border bg-muted text-xs py-1.5 px-2.5 text-muted-foreground cursor-not-allowed focus:outline-none" readonly>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-foreground mb-1">Tanggal Terima <span class="text-destructive">*</span></label>
                                <input type="date" name="tanggal_terima" value="{{ date('Y-m-d') }}" class="w-full rounded-lg border border-input bg-background text-xs py-1.5 px-2.5 focus:border-ring focus:ring-1 focus:ring-ring outline-none" required>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-foreground mb-1">Kondisi Fisik <span class="text-destructive">*</span></label>
                                <select name="kondisi" class="w-full rounded-lg border border-input bg-background text-xs py-1.5 px-2.5 focus:border-ring focus:ring-1 focus:ring-ring outline-none">
                                    <option value="baik">Baik (Sesuai Standar)</option>
                                    <option value="rusak_sebagian">Rusak Sebagian</option>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-border pt-3 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                <p class="text-xs font-semibold text-foreground uppercase tracking-wider">Kuantitas Diterima &amp; Pengecekan Selisih:</p>
                                <x-badge variant="info" size="sm">Penerimaan dicatat dalam satuan dasar (ml / gr / pcs)</x-badge>
                            </div>

                            @foreach($po->items as $i => $it)
                            <div class="p-3 bg-muted/30 border border-border rounded-lg text-xs space-y-2">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div>
                                        <span class="font-bold text-foreground">{{ $it->produk->nama }}</span>
                                        <span class="font-mono text-muted-foreground text-[11px]">
                                            ({{ $it->produk->sku }}) · Order:
                                            @if($it->qty_satuan_beli && $it->satuan_beli && strcasecmp($it->satuan_beli, $it->produk?->satuan ?? '') !== 0)
                                                <span class="font-semibold text-foreground">{{ rtrim(rtrim(number_format($it->qty_satuan_beli, 2, ',', '.'), '0'), ',') }} {{ $it->satuan_beli }}</span>
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
                                            class="w-24 text-right rounded-lg border border-input bg-card text-xs py-1 px-2 focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold outline-none"
                                            required>
                                        <span class="text-muted-foreground">{{ $it->produk->satuan }}</span>

                                        <template x-if="getDiff({{ $i }}) < 0">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-destructive/10 text-destructive border border-destructive/20" x-text="'Kurang: ' + getDiff({{ $i }})"></span>
                                        </template>
                                        <template x-if="getDiff({{ $i }}) > 0">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-sky-50 text-sky-700 border border-sky-200" x-text="'Lebih: +' + getDiff({{ $i }})"></span>
                                        </template>
                                        <template x-if="getDiff({{ $i }}) === 0">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-emerald-500/10 text-emerald-700 border border-emerald-500/20">✓ Sesuai</span>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="getDiff({{ $i }}) !== 0" x-cloak class="pt-2 border-t border-border">
                                    <label class="block text-[11px] font-semibold text-destructive mb-1">
                                        Keterangan Alasan Selisih <span class="text-destructive">*</span>:
                                    </label>
                                    <input type="text"
                                        name="items[{{ $i }}][keterangan_selisih]"
                                        :required="getDiff({{ $i }}) !== 0"
                                        placeholder="Contoh: Bocor saat pengiriman / Stok supplier tidak lengkap"
                                        class="w-full rounded-lg border border-destructive/40 text-xs py-1.5 px-2.5 focus:border-destructive focus:ring-1 focus:ring-destructive bg-card outline-none">
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
                <x-card title="Pemeriksaan Fisik & Unloading di Gudang (Tahap 7)" subtitle="Hitung kuantitas fisik aktual yang diturunkan di gudang. Jika ada bahan rusak/kurang, input kuantitas riil yang diterima dan catat keterangan selisih.">
                    <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_7]) }}" class="space-y-4" x-data="bardatForm()">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-foreground mb-1">Gudang Penerima</label>
                                @php
                                    $targetGudang = $po->gudang ?? $gudang->firstWhere('id', $po->gudang_id) ?? $gudang->first();
                                @endphp
                                <input type="hidden" name="physical_check[gudang_id]" value="{{ $targetGudang?->id }}">
                                <input type="text" value="{{ $targetGudang?->nama ?? '—' }}" class="w-full rounded-lg border border-border bg-muted text-xs p-2 text-muted-foreground cursor-not-allowed" readonly>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-foreground mb-1">Kondisi Fisik Muatan <span class="text-destructive">*</span></label>
                                <select name="physical_check[kondisi]" x-model="kondisiMuatan" class="w-full rounded-lg border border-input bg-background text-xs p-2 focus:border-ring focus:ring-1 focus:ring-ring outline-none">
                                    <option value="baik">Baik (Sesuai Standar)</option>
                                    <option value="rusak_sebagian">Rusak Sebagian</option>
                                </select>
                            </div>
                        </div>

                        <div class="border-t border-border pt-3 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-semibold text-foreground uppercase tracking-wider">Perhitungan Fisik Bahan Baku Masuk:</p>
                                <template x-if="kondisiMuatan === 'rusak_sebagian'">
                                    <span class="text-[11px] text-amber-800 bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 rounded font-medium">
                                        Muatan rusak sebagian: sesuaikan Qty Fisik yang layak terima &amp; wajib isi keterangan
                                    </span>
                                </template>
                            </div>

                            @foreach($po->items as $i => $it)
                            <div class="p-3 bg-muted/30 border border-border rounded-lg text-xs space-y-2">
                                <input type="hidden" name="physical_check[items][{{ $it->id }}][po_item_id]" value="{{ $it->id }}">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div>
                                        <span class="font-bold text-foreground">{{ $it->produk->nama }}</span>
                                        <span class="font-mono text-muted-foreground text-[11px]">({{ $it->produk->sku }}) · Order PO: {{ $it->qty }} {{ $it->produk->satuan }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-muted-foreground font-medium">Qty Fisik:</span>
                                        <input type="number" step="0.01" min="0"
                                            name="physical_check[items][{{ $it->id }}][qty_fisik]"
                                            x-model.number="receiptItems[{{ $i }}].qty_terima"
                                            class="w-24 text-right rounded-lg border border-input bg-card text-xs p-1.5 font-mono font-bold focus:border-ring focus:ring-1 focus:ring-ring outline-none"
                                            required>
                                        <span class="text-muted-foreground">{{ $it->produk->satuan }}</span>

                                        <template x-if="getDiff({{ $i }}) < 0">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-destructive/10 text-destructive border border-destructive/20" x-text="'Kurang: ' + getDiff({{ $i }})"></span>
                                        </template>
                                        <template x-if="getDiff({{ $i }}) > 0">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-sky-50 text-sky-700 border border-sky-200" x-text="'Lebih: +' + getDiff({{ $i }})"></span>
                                        </template>
                                        <template x-if="getDiff({{ $i }}) === 0">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-emerald-500/10 text-emerald-700 border border-emerald-500/20">✓ Sesuai</span>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="isDiscrepancyRequired({{ $i }})" x-cloak class="pt-2 border-t border-border">
                                    <label class="block text-[11px] font-semibold text-destructive mb-1">
                                        Keterangan Alasan Selisih / Kerusakan <span class="text-destructive">*</span>:
                                    </label>
                                    <input type="text"
                                        name="physical_check[items][{{ $it->id }}][keterangan_selisih]"
                                        :required="isDiscrepancyRequired({{ $i }})"
                                        placeholder="Contoh: 2 botol pecah saat unloading / vendor mengirim kurang"
                                        class="w-full rounded-lg border border-destructive/40 text-xs p-1.5 bg-card text-foreground focus:border-destructive focus:ring-1 focus:ring-destructive outline-none">
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

                {{-- Panel Konfirmasi & Posting Stok (Stage 8 PRD v2.4 8-Stage - Hanya muncul saat menunggu Posting Stok) --}}
                @if($hasStages && $po->stage_aktif === \App\Models\PoStage::STAGE_8 && !in_array($po->status, ['selesai', 'dibatalkan']))
                @php
                    $latestBardat = $po->barangDatang->where('status_posting', 'belum')->sortByDesc('created_at')->first();
                @endphp
                @if($latestBardat)
                <x-card title="Verifikasi dan Input Sistem Penerimaan (Tahap 8)"
                        subtitle="Hasil unloading fisik telah tersimpan. Verifikasi data sebelum memposting stok ke inventori gudang.">
                    <div class="space-y-4 text-xs">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3 rounded-lg bg-muted/40 border border-border">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <span class="font-semibold text-foreground">Kondisi Hasil Bongkar:</span>
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
                                <thead class="bg-muted/60 text-muted-foreground font-semibold text-[10px] uppercase border-b border-border">
                                    <tr>
                                        <th class="p-2.5">Bahan Baku</th>
                                        <th class="p-2.5 text-right">Dipesan (PO)</th>
                                        <th class="p-2.5 text-right font-bold text-foreground">Fisik Diterima</th>
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
                                    <tr class="hover:bg-muted/20">
                                        <td class="p-2.5 font-medium text-foreground">{{ $pItem?->produk?->nama ?? '—' }}</td>
                                        <td class="p-2.5 text-right font-mono tabular-nums text-muted-foreground whitespace-nowrap">{{ $fmtQtyDisplay((float) ($pItem?->qty ?? 0)) }} {{ $pItem?->produk?->satuan }}</td>
                                        <td class="p-2.5 text-right font-mono tabular-nums font-bold text-emerald-700 dark:text-emerald-400 whitespace-nowrap">
                                            {{ $fmtQtyDisplay($stg9Fisik) }} {{ $pItem?->produk?->satuan }}
                                        </td>
                                        <td class="p-2.5 text-right font-mono tabular-nums font-semibold whitespace-nowrap">
                                            @if($stg9Selisih < 0)
                                                <span class="text-destructive">{{ $fmtQtyDisplay($stg9Selisih) }} (Kurang/Rusak)</span>
                                            @elseif($stg9Selisih > 0)
                                                <span class="text-sky-700 dark:text-sky-400">+{{ $fmtQtyDisplay($stg9Selisih) }} (Lebih)</span>
                                            @else
                                                <span class="text-emerald-700 dark:text-emerald-400">✓ Sesuai</span>
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
                            <div class="p-3 bg-muted/30 rounded-lg border border-border flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="max-w-md w-full sm:w-auto">
                                    @if($existingResi !== '')
                                        <div class="space-y-1.5">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-semibold text-foreground text-xs">No. Resi / AWB:</span>
                                                <span class="font-mono text-xs font-bold text-foreground px-2 py-0.5 rounded bg-muted/80 border border-border">
                                                    {{ $existingResi }}
                                                </span>
                                                <span class="inline-flex items-center gap-1 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">
                                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Sudah Tercatat di Pengiriman
                                                </span>
                                            </div>
                                            <div class="pt-1">
                                                <label class="block text-[11px] text-muted-foreground mb-1">
                                                    Perbarui No. Resi <span class="font-normal">(Opsional)</span>:
                                                </label>
                                                <input type="text" name="no_resi" value="{{ old('no_resi', $existingResi) }}" placeholder="Ketik nomor resi baru jika ada revisi..." class="w-full sm:w-72 rounded-lg border border-input bg-card text-xs py-1.5 px-2.5 font-mono text-foreground focus:border-ring focus:ring-1 focus:ring-ring outline-none">
                                            </div>
                                        </div>
                                    @else
                                        <div class="space-y-1">
                                            <label class="block font-semibold text-foreground text-xs mb-1">
                                                No. Resi / AWB <span class="text-rose-500 font-bold">* (Wajib diisi)</span>
                                            </label>
                                            <input type="text" name="no_resi" required value="{{ old('no_resi') }}" placeholder="Masukkan nomor resi pengiriman..." class="w-full sm:w-72 rounded-lg border border-rose-300 dark:border-rose-800 bg-card text-xs py-1.5 px-2.5 font-mono text-foreground focus:border-rose-500 focus:ring-1 focus:ring-rose-500 outline-none">
                                            <p class="text-[10px] text-amber-600 dark:text-amber-400 font-medium mt-1">
                                                Nomor resi belum dicatat pada tahap pengiriman, wajib diisi sebelum posting stok.
                                            </p>
                                        </div>
                                    @endif
                                    @error('no_resi')
                                        <p class="text-[11px] text-rose-500 font-medium mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="shrink-0 flex items-center">
                                    <x-button type="submit" variant="success" size="md" x-bind:disabled="submitting">
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
                    <x-card title="Eksekusi Tahap Aktif" subtitle="{{ $stageLabels[$stgAktif] ?? ucwords(str_replace('_', ' ', $stgAktif)) }}">
                        {{-- STAGE 1: Perencanaan & Pengajuan PO --}}
                        @if($stgAktif === \App\Models\PoStage::STAGE_1)
                            @can('purchasing.submit')
                            <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_1]) }}" class="space-y-3 text-xs">
                                @csrf
                                <div>
                                    <label class="block font-semibold mb-1 text-foreground">Catatan Pengajuan (Opsional)</label>
                                    <textarea name="catatan" rows="2" placeholder="Catatan pengajuan untuk Manager..." class="w-full rounded-lg border border-input bg-background p-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring"></textarea>
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
                            <div class="space-y-3 text-xs" x-data="{ openRejectStage2: false, rejectAlasan: '', isRejectDisabled() { return this.rejectAlasan.trim().length < 3; } }">
                                <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_2]) }}" class="space-y-2">
                                    @csrf
                                    <x-button type="submit" variant="success" size="sm" class="w-full">
                                        ✓ Setujui PO (Lanjut Konfirmasi Supplier)
                                    </x-button>
                                </form>

                                <button type="button" @click="openRejectStage2 = !openRejectStage2" class="w-full px-3 py-1.5 text-xs rounded-md border border-destructive/30 text-destructive hover:bg-destructive/10 font-medium transition cursor-pointer">
                                    ✕ Tolak &amp; Kembalikan ke Tahap 1
                                </button>

                                <div x-show="openRejectStage2" class="p-3 rounded-lg border border-destructive/30 bg-destructive/5 space-y-2" x-cloak>
                                    <form method="POST" action="{{ route('purchasing.stages.reject-stage2', $po) }}" class="space-y-2">
                                        @csrf
                                        <label class="block font-semibold text-foreground text-[11px]">Alasan Penolakan / Pengembalian <span class="text-destructive">*</span></label>
                                        <textarea name="alasan" x-model="rejectAlasan" required minlength="3" rows="2" placeholder="Tuliskan alasan penolakan..." class="w-full text-xs p-2 rounded-lg border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"></textarea>
                                        <div class="flex justify-end gap-2">
                                            <x-button type="button" variant="outline" size="xs" @click="openRejectStage2 = false">Batal</x-button>
                                            <x-button type="submit" variant="danger" size="xs" x-bind:disabled="isRejectDisabled()">Konfirmasi Tolak</x-button>
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
                                    <input type="text" name="confirmation[no_referensi_supplier]" value="{{ $po->no_referensi_supplier ?? '' }}" placeholder="Contoh: SO-SUPPLIER-1234" class="w-full rounded-lg border border-input bg-background p-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring">
                                </div>
                                <div>
                                    <label class="block font-semibold mb-1 text-foreground">Catatan Konfirmasi</label>
                                    <textarea name="catatan" rows="2" placeholder="Catatan konfirmasi kesiapan barang..." class="w-full rounded-lg border border-input bg-background p-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring"></textarea>
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
                            @if($hasPendingRevisi)
                            <div role="status" class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-800 text-xs flex items-start gap-2 leading-relaxed">
                                <svg class="size-4 shrink-0 mt-0.5 text-amber-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <span><strong>Stage Pembayaran dikunci.</strong> Selesaikan persetujuan revisi komitmen di atas sebelum mengonfirmasi pembayaran atau lanjut tempo.</span>
                            </div>
                            @else
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
                                    <div class="p-2.5 rounded-lg bg-muted/60 border border-border text-muted-foreground text-[11px] leading-tight">
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
                            @endif

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

                        {{-- STAGE 6: Pengiriman & Logistik (Konsolidasi Alur: Pengemasan Dilebur) --}}
                        @elseif($stgAktif === \App\Models\PoStage::STAGE_6)
                            @if(auth()->user()->can('purchasing.edit') || auth()->user()->can('purchasing.receive'))
                            <form method="POST" action="{{ route('purchasing.stages.complete', [$po, \App\Models\PoStage::STAGE_6]) }}" class="space-y-3 text-xs">
                                @csrf
                                <div>
                                    <label class="block font-semibold mb-1 text-foreground">Nama Ekspedisi / Kurir <span class="text-destructive">*</span></label>
                                    <input type="text" name="shipping[ekspedisi]" value="{{ $po->pengiriman?->ekspedisi ?? '' }}" required placeholder="Cth: JNE Cargo / Truk Vendor" class="w-full rounded-lg border border-input bg-background p-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block font-semibold mb-1 text-foreground">No. Resi / AWB <span class="text-muted-foreground font-normal">(Opsional)</span></label>
                                        <input type="text" name="shipping[no_resi]" value="{{ $po->pengiriman?->no_resi ?? '' }}" placeholder="Dapat diisi saat input sistem..." class="w-full rounded-lg border border-input bg-background p-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring">
                                    </div>
                                    <div>
                                        <label class="block font-semibold mb-1 text-foreground">Tanggal Kirim</label>
                                        <input type="date" name="shipping[tgl_kirim]" value="{{ $po->pengiriman?->tgl_kirim ? $po->pengiriman->tgl_kirim->format('Y-m-d') : date('Y-m-d') }}" class="w-full rounded-lg border border-input bg-background p-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring">
                                    </div>
                                </div>
                                <x-button type="submit" variant="primary" size="sm" class="w-full">
                                    Konfirmasi Barang Telah Berangkat
                                </x-button>
                            </form>
                            @else
                            <p class="text-xs text-muted-foreground italic">Menunggu update pengiriman dari logistik / supplier.</p>
                            @endif

                        {{-- STAGE 7: Unloading di Gudang --}}
                        @elseif($stgAktif === \App\Models\PoStage::STAGE_7)
                            <div class="p-2.5 rounded-lg bg-muted/60 border border-border text-foreground text-xs">
                                Silakan input kuantitas fisik pada formulir pemeriksaan barang di sebelah kiri untuk menyelesaikan Unloading.
                            </div>

                        {{-- STAGE 8: Input Sistem & Posting Stok --}}
                        @elseif($stgAktif === \App\Models\PoStage::STAGE_8)
                            @can('purchasing.receive')
                            <div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-xs space-y-2">
                                <div class="flex items-center gap-2 font-semibold text-emerald-800">
                                    <span class="size-2 rounded-full bg-emerald-600 animate-pulse"></span>
                                    Siap Posting Stok
                                </div>
                                @php
                                    $hasPengirimanResi = !empty(trim($po->pengiriman?->no_resi ?? ''));
                                @endphp
                                @if($hasPengirimanResi)
                                    <p class="text-[11px] text-muted-foreground leading-relaxed">
                                        No. Resi tercatat: <strong class="text-foreground font-mono">{{ $po->pengiriman->no_resi }}</strong>. Verifikasi kuantitas fisik pada panel verifikasi di sebelah kiri, lalu klik <strong>Posting Stok ke Gudang</strong> untuk menyelesaikan transaksi.
                                    </p>
                                @else
                                    <p class="text-[11px] text-amber-700 dark:text-amber-400 leading-relaxed font-medium">
                                        ⚠️ Nomor resi belum dicatat pada pengiriman. Harap isi nomor resi pada formulir di sebelah kiri sebelum posting stok.
                                    </p>
                                @endif
                            </div>
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
                    <x-card title="Kontrol Purchase Order">
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
                            <div class="py-2.5 px-3 bg-muted/50 rounded-lg text-center text-xs text-muted-foreground font-medium border border-border">
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
                                    class="w-full rounded-xl border border-input bg-card p-3 text-xs text-foreground placeholder:text-muted-foreground/50 focus:border-destructive focus:ring-2 focus:ring-destructive/20 outline-none transition"
                                ></textarea>
                                <div class="flex items-center justify-between mt-1 text-[11px]">
                                    <span class="text-muted-foreground">Minimal 5 karakter</span>
                                    <span :class="alasan.trim().length >= 5 ? 'text-emerald-600 font-semibold' : 'text-muted-foreground'" x-text="alasan.trim().length + ' / 500 karakter'"></span>
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
                <x-card title="Status Pembayaran (AP)" id="status-pembayaran-card">
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
                            <span class="font-mono font-bold {{ $isTempoOverdue ? 'text-destructive' : 'text-foreground' }}">
                                {{ $po->tanggal_tempo ? $po->tanggal_tempo->format('d/m/Y') : ($po->eta ? $po->eta->format('d/m/Y') : '—') }}
                                @if($isTempoOverdue)
                                    <span class="text-[10px] text-destructive font-semibold">(Overdue)</span>
                                @endif
                            </span>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Total Tagihan</span>
                            <span class="font-bold font-mono text-foreground">Rp {{ number_format($po->totalNilai(), 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Telah Dibayar</span>
                            <span class="font-bold font-mono text-emerald-700">Rp {{ number_format($po->totalDibayar(), 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between pt-1.5 border-t border-border">
                            <span class="font-semibold text-foreground">Sisa Tagihan</span>
                            <span class="font-bold font-mono {{ $po->status === 'dibatalkan' ? 'text-muted-foreground' : ($po->isLunas() ? 'text-emerald-700' : 'text-destructive') }}">
                                Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <!-- Daftar Termin Tagihan (Hanya ditampilkan jika skema termin dan ada jadwal) -->
                    @if($po->skema_bayar === 'termin' && $po->termins->count())
                    <div class="py-3 border-b border-border">
                        <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-2">Jadwal Termin Tagihan:</p>
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
                            <div class="p-2.5 bg-muted/30 rounded-lg border border-border text-xs flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-semibold text-foreground truncate">Termin {{ $tm->termin_ke }} ({{ $tm->keterangan ?? 'Tagihan' }})</div>
                                    <div class="text-[10px] text-muted-foreground font-mono">Tempo: {{ $tm->tanggal_tempo->format('d/m/Y') }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="font-mono font-bold text-foreground">Rp {{ number_format($tm->nominal_tagihan, 0, ',', '.') }}</div>
                                    <x-badge :variant="$tmBadge" :dot="true" size="sm">{{ ucfirst(str_replace('_',' ', $tm->status)) }}</x-badge>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @can('purchasing.pay')
                    @if(!$po->isLunas() && !$po->isCancelled() && $hasPendingRevisi)
                        <div role="status" class="mt-3 p-3.5 rounded-xl border border-amber-500/30 bg-amber-500/10 text-amber-800 text-xs space-y-1.5">
                            <div class="font-semibold flex items-center gap-1.5">
                                <svg class="size-4 shrink-0 text-amber-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                <span>Pembayaran dikunci sementara</span>
                            </div>
                            <p class="text-[11px] leading-relaxed">
                                Pengajuan revisi harga/komitmen masih menunggu persetujuan Manager. Pencatatan pembayaran baru dapat dilakukan setelah usulan revisi disetujui atau ditolak agar nominal mengikuti harga komitmen final.
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
                                <label for="pay_tanggal_cash" class="block text-xs font-semibold text-foreground mb-1.5">Tanggal Bayar</label>
                                <input id="pay_tanggal_cash" type="date" name="tanggal_bayar" value="{{ date('Y-m-d') }}" class="w-full rounded-lg border border-input bg-background text-xs py-2 px-3 focus:border-ring focus:ring-1 focus:ring-ring outline-none transition" required>
                            </div>

                            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3.5 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold text-foreground">Total Pembayaran Tunai</span>
                                    <x-badge variant="success" size="sm">Lunas Penuh</x-badge>
                                </div>
                                <div class="text-lg font-mono font-bold text-emerald-700">
                                    Rp {{ number_format($po->sisaTagihan(), 0, ',', '.') }}
                                </div>
                                <p class="text-[11px] text-muted-foreground leading-tight">
                                    Skema transaksi tunai mensyaratkan pelunasan penuh senilai sisa tagihan.
                                </p>
                            </div>

                            <x-button type="submit" variant="success" size="sm" class="w-full">
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
                                <label for="pay_skema" class="block text-xs font-semibold text-foreground mb-1.5">Skema Pembayaran</label>
                                <select id="pay_skema" name="skema" x-model="skema" class="w-full rounded-lg border border-input bg-background text-xs py-2 px-3 focus:border-ring focus:ring-1 focus:ring-ring outline-none transition">
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
                                <label for="pay_termin_id" class="block text-xs font-semibold text-foreground mb-1.5">Target Termin (Opsional)</label>
                                <select id="pay_termin_id" name="termin_id" @change="onTerminChange($event)" class="w-full rounded-lg border border-input bg-background text-xs py-2 px-3 focus:border-ring focus:ring-1 focus:ring-ring outline-none transition">
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
                                <label for="pay_tanggal_tempo" class="block text-xs font-semibold text-foreground mb-1.5">Tanggal Bayar</label>
                                <input id="pay_tanggal_tempo" type="date" name="tanggal_bayar" value="{{ date('Y-m-d') }}" class="w-full rounded-lg border border-input bg-background text-xs py-2 px-3 focus:border-ring focus:ring-1 focus:ring-ring outline-none transition" required>
                            </div>

                            <!-- Kolom Nominal Bayar Custom (Termin / Tempo) -->
                            <div class="rounded-xl border border-border bg-muted/20 p-3.5 space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <label for="pay_nominal_display" class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                                        <svg class="size-3.5 text-muted-foreground" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        <span>Nominal Bayar (Custom / Diedit)</span>
                                    </label>
                                    <span class="text-[10px] text-muted-foreground bg-muted px-2 py-0.5 rounded font-mono font-medium border border-border">
                                        Bebas diedit
                                    </span>
                                </div>

                                <div class="relative flex items-center rounded-lg border border-input bg-card focus-within:border-ring focus-within:ring-1 focus-within:ring-ring transition">
                                    <span class="pl-3 pr-2.5 py-2 text-xs font-bold text-muted-foreground border-r border-border select-none">
                                        Rp
                                    </span>
                                    <input type="hidden" name="nominal" :value="nominal">
                                    <input id="pay_nominal_display" type="text"
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
                                        aria-label="Kosongkan nominal"
                                        class="mr-2 text-muted-foreground hover:text-foreground p-1 rounded transition cursor-pointer"
                                        title="Kosongkan nominal">
                                        <svg class="size-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                    <button type="button"
                                        @click="setNominal(sisa, 'pelunasan')"
                                        :class="nominal === sisa ? 'bg-primary text-primary-foreground font-semibold' : 'bg-card border border-border hover:bg-muted text-foreground'"
                                        class="px-2.5 py-1 rounded-md text-[11px] transition cursor-pointer">
                                        100% Lunas (Rp <span x-text="formatThousand(sisa)"></span>)
                                    </button>
                                    @if($po->sisaTagihan() > 1000)
                                    <button type="button"
                                        @click="setNominal(Math.round(sisa / 2), '{{ $po->skema_bayar === 'termin' ? 'termin' : 'tempo' }}')"
                                        :class="nominal === Math.round(sisa / 2) ? 'bg-primary text-primary-foreground font-semibold' : 'bg-card border border-border hover:bg-muted text-foreground'"
                                        class="px-2.5 py-1 rounded-md text-[11px] transition cursor-pointer">
                                        50% (Rp <span x-text="formatThousand(Math.round(sisa / 2))"></span>)
                                    </button>
                                    @endif
                                    @if($po->skema_bayar === 'tempo' && $po->sisaTagihan() > 2000)
                                    <button type="button"
                                        @click="setNominal(Math.round(sisa / 4), 'tempo')"
                                        :class="nominal === Math.round(sisa / 4) ? 'bg-primary text-primary-foreground font-semibold' : 'bg-card border border-border hover:bg-muted text-foreground'"
                                        class="px-2.5 py-1 rounded-md text-[11px] transition cursor-pointer">
                                        25% (Rp <span x-text="formatThousand(Math.round(sisa / 4))"></span>)
                                    </button>
                                    @endif
                                    @if($po->skema_bayar === 'termin' && $nextUnpaidTermin)
                                    <button type="button"
                                        @click="setNominal({{ (float)$nextUnpaidTermin->sisaNominal() }}, 'termin', '{{ $nextUnpaidTermin->id }}')"
                                        :class="nominal === {{ (float)$nextUnpaidTermin->sisaNominal() }} ? 'bg-primary text-primary-foreground font-semibold' : 'bg-card border border-border hover:bg-muted text-foreground'"
                                        class="px-2.5 py-1 rounded-md text-[11px] transition cursor-pointer">
                                        Termin {{ $nextUnpaidTermin->termin_ke }} (Rp {{ number_format($nextUnpaidTermin->sisaNominal(), 0, ',', '.') }})
                                    </button>
                                    @endif
                                </div>

                                <div class="rounded-lg bg-card border border-border p-2.5 text-xs space-y-1">
                                    <div class="flex justify-between items-center text-[11px] text-muted-foreground">
                                        <span>Sisa Tagihan Saat Ini:</span>
                                        <span class="font-mono font-medium text-foreground">Rp <span x-text="formatThousand(sisa)"></span></span>
                                    </div>
                                    <div class="flex justify-between items-center text-[11px]">
                                        <span class="text-muted-foreground">Sisa Setelah Bayar Ini:</span>
                                        <span class="font-mono font-bold"
                                            :class="sisaAfterPay === 0 ? 'text-emerald-600' : (sisaAfterPay < 0 ? 'text-destructive' : 'text-foreground')"
                                            x-text="sisaAfterPay < 0 ? 'Kelebihan Bayar Rp ' + formatThousand(Math.abs(sisaAfterPay)) : 'Rp ' + formatThousand(sisaAfterPay)">
                                        </span>
                                    </div>
                                </div>

                                <div x-show="nominal > sisa" role="alert" class="p-2.5 bg-destructive/10 border border-destructive/20 rounded-lg text-[11px] text-destructive space-y-1">
                                    <div class="font-semibold flex items-center gap-1">
                                        <svg class="size-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
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
                            <div class="rounded-xl border border-border bg-muted/30 p-3 space-y-2.5" x-data="{ adjustTempo: false }">
                                <div class="flex items-center justify-between gap-2">
                                    <label class="text-xs font-semibold text-foreground flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox" x-model="adjustTempo" class="rounded border-input text-primary focus:ring-ring size-3.5 cursor-pointer">
                                        <span>Adjustment Jatuh Tempo PO</span>
                                    </label>
                                    <span class="text-[10px] text-muted-foreground font-mono font-medium bg-card px-1.5 py-0.5 rounded border border-border">
                                        Tempo Saat Ini: {{ $po->tanggal_tempo ? $po->tanggal_tempo->format('d/m/Y') : ($po->eta ? $po->eta->format('d/m/Y') : '—') }}
                                    </span>
                                </div>
                                <div x-show="adjustTempo" x-cloak class="pt-2 border-t border-border space-y-1.5">
                                    <label for="pay_tanggal_tempo_baru" class="block text-[11px] font-semibold text-foreground">
                                        Tanggal Jatuh Tempo Baru untuk Sisa Tagihan:
                                    </label>
                                    <input id="pay_tanggal_tempo_baru" type="date"
                                        name="tanggal_tempo_baru"
                                        value="{{ $po->tanggal_tempo ? $po->tanggal_tempo->format('Y-m-d') : date('Y-m-d', strtotime('+30 days')) }}"
                                        min="{{ date('Y-m-d') }}"
                                        class="w-full rounded-lg border border-input bg-card text-xs py-1.5 px-3 focus:border-ring focus:ring-1 focus:ring-ring outline-none">
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
                        <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider mb-2">Riwayat Transaksi Bayar:</p>
                        <ul class="space-y-1.5 text-xs text-muted-foreground">
                            @foreach($po->payments as $pay)
                            <li class="flex items-center justify-between py-1.5 px-2.5 bg-muted/30 rounded-lg border border-border">
                                <div>
                                    <span class="font-medium text-foreground">{{ $pay->tanggal_bayar->format('d/m/Y') }}</span>
                                    <span class="text-[10px] text-muted-foreground capitalize block">{{ $pay->skema }}</span>
                                </div>
                                <span class="font-bold font-mono text-foreground">Rp {{ number_format($pay->nominal, 0, ',', '.') }}</span>
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
            <x-card
                title="Histori Revisi Komitmen"
                :subtitle="'Jejak seluruh usulan perubahan komitmen PO (' . $sortedRevisis->count() . ' revisi tercatat)'"
                :noPadding="true"
            >
                <div tabindex="0" role="region" aria-labelledby="histori-revisi-caption" class="overflow-x-auto max-h-[32rem]">
                    <table class="w-full text-xs text-left border-separate border-spacing-0">
                        <caption id="histori-revisi-caption" class="sr-only">Histori Revisi Komitmen Purchase Order {{ $po->no_po }}</caption>
                        <thead class="sticky top-0 z-10 bg-muted/95 backdrop-blur-xs text-muted-foreground font-semibold uppercase text-[10px] border-b border-border">
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
                                        ? 'text-amber-700'
                                        : ($diff['deltaGrandTotal'] < -0.5 ? 'text-emerald-700' : 'text-muted-foreground');
                                @endphp
                                <tr class="hover:bg-muted/30 transition-colors">
                                    <th scope="row" class="px-4 py-3 align-top text-left font-normal whitespace-nowrap">
                                        <div class="font-mono tabular-nums font-semibold text-foreground">
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
                                            <p class="mt-1.5 text-[11px] leading-snug break-words {{ $rev->status === \App\Models\PoRevisi::STATUS_DITOLAK ? 'text-red-700 bg-red-50/70 border border-red-200/70 rounded-md px-2 py-1' : 'text-muted-foreground bg-muted/40 rounded-md px-2 py-1' }}">
                                                <span class="font-semibold">Catatan:</span> {{ $rev->catatan_keputusan }}
                                            </p>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 align-top">
                                        @if($diff['itemComparisons']->isNotEmpty())
                                            <ul class="space-y-1.5">
                                                @foreach($diff['itemComparisons'] as $itRow)
                                                    <li class="text-[11px] leading-snug">
                                                        <span class="font-semibold text-foreground">{{ $itRow['nama'] }}</span>
                                                        <div class="font-mono tabular-nums text-muted-foreground mt-0.5">
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
                                        <td class="px-4 py-3 align-top text-right font-mono tabular-nums whitespace-nowrap">
                                            <div class="text-[11px] text-muted-foreground">
                                                {{ $fmtRp($diff['oldGrandTotal']) }} &rarr; <span class="text-foreground font-semibold">{{ $fmtRp($diff['newGrandTotal']) }}</span>
                                            </div>
                                            <div class="text-[11px] font-semibold mt-0.5 {{ $deltaGtClass }}">
                                                {{ $fmtSignedRp($diff['deltaGrandTotal']) }}
                                            </div>
                                        </td>
                                    @endif

                                    <td class="px-4 py-3 align-top whitespace-nowrap">
                                        <div class="font-medium text-foreground">
                                            {{ $rev->requester?->name ?? ($rev->diajukan_oleh ? 'User #' . $rev->diajukan_oleh : '—') }}
                                        </div>
                                        <div class="text-muted-foreground font-mono tabular-nums text-[11px] mt-0.5">
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
                                            <div class="text-muted-foreground font-mono tabular-nums text-[11px] mt-0.5">
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
            <x-card title="Riwayat Perubahan Operasional (Direct Edit Log)" subtitle="Jejak audit perubahan field operasional tanpa persetujuan manager" :noPadding="true">
                <div tabindex="0" role="region" aria-label="Riwayat Perubahan Operasional Direct Edit" class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-muted/60 text-muted-foreground font-semibold uppercase text-[10px] border-b border-border">
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
                            <tr class="hover:bg-muted/30">
                                <td class="px-4 py-2 font-mono font-medium text-foreground">{{ $log->nama_field }}</td>
                                <td class="px-4 py-2 text-muted-foreground italic font-mono">{{ $log->nilai_lama ?? '—' }}</td>
                                <td class="px-4 py-2 text-foreground font-mono font-semibold">{{ $log->nilai_baru ?? '—' }}</td>
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
                    <div class="flex flex-wrap items-center justify-between gap-3 p-3 rounded-lg border {{ $po->leadTime->is_valid ? 'bg-emerald-500/10 border-emerald-500/20' : 'bg-destructive/10 border-destructive/20' }}">
                        <div class="flex items-center gap-3">
                            <span class="size-8 rounded-full flex items-center justify-center font-bold text-xs {{ $po->leadTime->is_valid ? 'bg-emerald-600 text-white' : 'bg-destructive text-destructive-foreground' }}">
                                {{ $po->leadTime->is_valid ? '✓' : '✕' }}
                            </span>
                            <div>
                                <div class="font-semibold text-xs text-foreground">
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
                        <div class="p-3 bg-card rounded-xl border border-border shadow-2xs" title="Stage 1-4: Perencanaan, Approval, Supplier Confirm, Payment">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="size-2 rounded-full bg-blue-600 shrink-0"></span>
                                <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider truncate">Internal Processing</span>
                            </div>
                            <p class="font-mono font-bold text-sm text-foreground mt-0.5">
                                {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($cardInternalMenit) }} hari
                                <span class="text-[10px] text-muted-foreground font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($cardInternalMenit) }} j)</span>
                            </p>
                        </div>

                        <div class="p-3 bg-card rounded-xl border border-border shadow-2xs" title="Stage 5: Penerbitan PO Resmi">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="size-2 rounded-full bg-amber-500 shrink-0"></span>
                                <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider truncate">Supplier Processing</span>
                            </div>
                            <p class="font-mono font-bold text-sm text-foreground mt-0.5">
                                {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($cardSupplierMenit) }} hari
                                <span class="text-[10px] text-muted-foreground font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($cardSupplierMenit) }} j)</span>
                            </p>
                        </div>

                        <div class="p-3 bg-card rounded-xl border border-border shadow-2xs" title="Stage 7: Pengiriman">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="size-2 rounded-full bg-purple-600 shrink-0"></span>
                                <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider truncate">Transportation Time</span>
                            </div>
                            <p class="font-mono font-bold text-sm text-foreground mt-0.5">
                                {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($cardTransportMenit) }} hari
                                <span class="text-[10px] text-muted-foreground font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($cardTransportMenit) }} j)</span>
                            </p>
                        </div>

                        <div class="p-3 bg-card rounded-xl border border-border shadow-2xs" title="Stage 8-9: Unloading, Input">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="size-2 rounded-full bg-emerald-600 shrink-0"></span>
                                <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider truncate">Receiving</span>
                            </div>
                            <p class="font-mono font-bold text-sm text-foreground mt-0.5">
                                {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($cardReceivingMenit) }} hari
                                <span class="text-[10px] text-muted-foreground font-normal">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($cardReceivingMenit) }} j)</span>
                            </p>
                        </div>

                        <div class="p-3 bg-card rounded-xl border border-border shadow-2xs">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="size-2 rounded-full bg-primary shrink-0"></span>
                                <span class="text-[10px] uppercase font-bold text-foreground tracking-wider truncate">Total Realisasi LT</span>
                            </div>
                            <p class="font-mono font-bold text-sm text-foreground mt-0.5">
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

        {{-- MODAL DIRECT EDIT (purchasing.edit) --}}
        @can('purchasing.edit')
        <div x-show="openDirectEditModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" x-cloak>
            <div class="bg-card rounded-xl border border-border p-5 max-w-lg w-full space-y-4 shadow-xl">
                <div class="flex justify-between items-center border-b border-border pb-2.5">
                    <div>
                        <h3 class="font-bold text-sm text-foreground">Direct Edit Field Operasional</h3>
                        <p class="text-[11px] text-muted-foreground">Perubahan langsung tercatat di PO_LOG_PERUBAHAN tanpa memerlukan approval</p>
                    </div>
                    <button type="button" @click="openDirectEditModal = false" class="text-muted-foreground hover:text-foreground cursor-pointer p-1">✕</button>
                </div>

                <form method="POST" action="{{ route('purchasing.direct-edit', $po) }}" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="block font-semibold mb-1 text-foreground">No Invoice Vendor</label>
                        <input type="text" name="fields[no_invoice]" value="{{ $po->no_invoice ?? '' }}" class="w-full rounded-lg border border-input bg-background p-2 focus:outline-none focus:ring-1 focus:ring-ring">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-foreground">No Referensi Supplier</label>
                        <input type="text" name="fields[no_referensi_supplier]" value="{{ $po->no_referensi_supplier ?? '' }}" class="w-full rounded-lg border border-input bg-background p-2 focus:outline-none focus:ring-1 focus:ring-ring">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-foreground">Estimasi Tiba (ETA)</label>
                        <input type="date" name="fields[eta]" value="{{ $po->eta ? $po->eta->format('Y-m-d') : '' }}" class="w-full rounded-lg border border-input bg-background p-2 focus:outline-none focus:ring-1 focus:ring-ring">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-foreground">Catatan Dokumen</label>
                        <textarea name="fields[catatan]" rows="2" class="w-full rounded-lg border border-input bg-background p-2 focus:outline-none focus:ring-1 focus:ring-ring">{{ $po->catatan ?? '' }}</textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-border">
                        <x-button type="button" variant="outline" size="sm" @click="openDirectEditModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Perubahan Langsung</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- MODAL AJUKAN REVISI KOMITMEN (purchasing.edit) --}}
        <div x-show="openRevisiModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-3 sm:p-6" x-cloak>
            <div class="bg-card rounded-2xl border border-border p-5 sm:p-6 max-w-5xl w-full space-y-4 shadow-2xl max-h-[92vh] overflow-y-auto"
                 x-data="revisiForm({{ Js::from($initialRevisiData) }})"
                 x-init="init()">

                <div class="flex justify-between items-start border-b border-border pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-base text-foreground">Ajukan Usulan Revisi Komitmen Pengadaan</h3>
                            <x-badge variant="warning" size="sm">Revisi ke-{{ (int)$po->revisi_ke + 1 }}</x-badge>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Sesuaikan kuantitas beli, harga, diskon, PPN, dan ongkos kirim. Jenis bahan baku terkunci sesuai PO asli.
                        </p>
                    </div>
                    <button type="button" @click="openRevisiModal = false" class="size-8 rounded-lg border border-border flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition cursor-pointer">✕</button>
                </div>

                <form method="POST" action="{{ route('purchasing.revisi.submit', $po) }}" class="space-y-4 text-xs" @submit="handleSubmit($event)">
                    @csrf

                    <div class="bg-muted/30 p-3 rounded-xl border border-border space-y-1.5">
                        <label class="block font-semibold text-xs text-foreground">
                            Alasan Pengajuan Revisi Komitmen <span class="text-destructive">*</span>
                        </label>
                        <textarea name="alasan" x-model="alasan" required minlength="5" rows="2"
                                  placeholder="Jelaskan alasan perubahan kuantitas, harga bahan baku, diskon, atau ongkir untuk ditinjau dan disetujui Manager..."
                                  class="w-full rounded-lg border border-input bg-background p-2.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"></textarea>
                    </div>

                    <x-card title="Daftar Bahan Baku" subtitle="Input kuantitas dan total harga per bahan. Diskon, PPN, ongkir, dan adjustment diatur pada formulir Global di bawah dan dialokasikan ke HPP secara realtime." :noPadding="true" class="relative z-10" overflow="visible">
                        <div class="overflow-x-auto min-h-[160px]">
                            <table class="w-full text-xs">
                                <thead class="bg-muted/70 border-b border-border text-muted-foreground font-semibold uppercase text-[10px] tracking-wider">
                                    <tr>
                                        <th class="px-3.5 py-2.5 text-left w-[36%]">Bahan Baku <span class="text-destructive">*</span></th>
                                        <th class="px-3 py-2.5 text-left w-64">Kuantitas &amp; Satuan Beli <span class="text-destructive">*</span></th>
                                        <th class="px-3 py-2.5 text-right w-44">Total Harga <span class="text-destructive">*</span></th>
                                        <th class="px-3.5 py-2.5 text-right w-52 bg-muted/40 text-foreground">HPP / Satuan Dasar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    <template x-for="(row, i) in rows" :key="row.po_item_id || i">
                                        <tr class="hover:bg-muted/30 transition">
                                            <td class="px-3.5 py-2.5">
                                                <input type="hidden" :name="`data_usulan[items][${i}][po_item_id]`" :value="row.po_item_id">
                                                <input type="hidden" :name="`data_usulan[items][${i}][produk_id]`" :value="row.produk_id">
                                                <div class="space-y-1">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <span class="font-mono text-foreground font-semibold bg-muted px-1.5 py-0.5 rounded border border-border text-[10px]" x-text="row.sku"></span>
                                                        <span class="font-bold text-foreground text-xs" x-text="row.nama"></span>
                                                        <span class="text-[9px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded border border-border" x-text="row.satuan"></span>
                                                    </div>
                                                    <div class="flex items-center gap-1.5 text-[10px] font-mono">
                                                        <span class="text-muted-foreground">HPP Saat Ini:</span>
                                                        <span class="font-semibold text-foreground bg-muted px-1.5 py-0.5 rounded border border-border" x-text="'Rp ' + formatHpp(row.current_hpp) + ' / ' + row.satuan"></span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-3 py-2.5">
                                                <div class="space-y-1">
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="relative flex items-center shrink-0">
                                                            <span class="absolute left-2.5 text-[10px] font-bold font-mono tracking-wider text-muted-foreground/70 uppercase select-none pointer-events-none">QTY</span>
                                                            <input
                                                                type="number"
                                                                step="1"
                                                                min="1"
                                                                inputmode="numeric"
                                                                :name="`data_usulan[items][${i}][qty_satuan_beli]`"
                                                                x-model.number="row.qty_satuan_beli"
                                                                @keydown="if (['.', ',', 'e', 'E', '+', '-'].includes($event.key)) $event.preventDefault()"
                                                                @input="handleQtyInput(row, $event)"
                                                                @paste="handleQtyPaste(row, $event)"
                                                                placeholder="0"
                                                                required
                                                                class="w-24 rounded-lg border border-input bg-background text-xs py-1.5 pl-9 pr-2 font-mono font-bold text-right text-foreground focus:border-ring focus:ring-1 focus:ring-ring shadow-xs"
                                                            />
                                                        </div>

                                                        <select
                                                            :name="`data_usulan[items][${i}][satuan_beli]`"
                                                            x-model="row.satuan_beli"
                                                            @change="onSatuanBeliChange(row)"
                                                            class="flex-1 rounded-lg border border-input bg-background text-xs py-1.5 px-2 text-foreground focus:border-ring focus:ring-1 focus:ring-ring shadow-xs"
                                                        >
                                                            <template x-for="opt in getUomOptions(row.satuan, row.satuan_beli, row.faktor_konversi)" :key="opt.code">
                                                                <option :value="opt.code" x-text="opt.label" :selected="opt.code === row.satuan_beli || opt.code.toLowerCase() === (row.satuan_beli || '').toLowerCase()"></option>
                                                            </template>
                                                        </select>
                                                    </div>

                                                    <input type="hidden" :name="`data_usulan[items][${i}][faktor_konversi]`" :value="row.faktor_konversi">
                                                    <input type="hidden" :name="`data_usulan[items][${i}][qty]`" :value="row.qty">

                                                    <div class="text-[10px] font-mono text-foreground bg-muted px-2 py-0.5 rounded border border-border flex items-center justify-between select-none" x-show="row.qty > 0 && row.satuan_beli && row.satuan_beli.toLowerCase() !== (row.satuan || '').toLowerCase()">
                                                        <span class="text-muted-foreground">Masuk fisik:</span>
                                                        <span class="font-bold text-foreground" x-text="formatQty(row.qty) + ' ' + (row.satuan || '')"></span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-3 py-2.5">
                                                <div class="relative flex items-center">
                                                    <input type="hidden" :name="`data_usulan[items][${i}][harga_total]`" :value="row.harga">
                                                    <input
                                                        type="text"
                                                        inputmode="numeric"
                                                        :value="formatThousand(row.harga)"
                                                        @input="updateRowField(row, 'harga', $event)"
                                                        @keydown="handleKeyDown($event)"
                                                        placeholder="0"
                                                        required
                                                        class="w-full rounded-lg border border-input bg-background text-xs py-1.5 px-2 font-mono text-right text-foreground focus:border-ring focus:ring-1 focus:ring-ring shadow-xs font-semibold"
                                                    />
                                                </div>
                                            </td>

                                            <td class="px-3.5 py-2.5 text-right bg-muted/20">
                                                <template x-if="calculateRowHpp(row) > 0">
                                                    <div>
                                                        <div class="font-mono font-bold text-xs text-foreground" x-text="'Rp ' + formatHpp(calculateRowHpp(row)) + ' / ' + (row.satuan || 'unit')"></div>
                                                        <div class="text-[10px] text-muted-foreground font-mono mt-0.5" x-text="'Net: Rp ' + formatHpp(calculateRowNet(row))"></div>
                                                    </div>
                                                </template>
                                                <template x-if="!calculateRowHpp(row) || calculateRowHpp(row) <= 0">
                                                    <span class="text-muted-foreground italic text-[11px]">—</span>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="px-4 py-2.5 bg-muted/30 border-t border-border flex items-center justify-between text-xs text-muted-foreground">
                            <span class="italic font-medium">
                                * Jenis bahan baku terkunci sesuai PO asli (tidak dapat menambah / menghapus bahan baku).
                            </span>
                            <span class="font-mono">
                                Total: <strong class="text-foreground" x-text="rows.length"></strong> item
                            </span>
                        </div>
                    </x-card>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-card title="Biaya Tambahan Global (Opsional)" subtitle="Biaya tingkat PO yang otomatis dialokasikan ke HPP bahan baku">
                            <div class="space-y-3.5 text-xs">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <label class="text-xs text-foreground font-medium">Diskon Global PO</label>
                                        <span class="text-[10px] text-muted-foreground font-mono truncate" x-show="headerDiskonMode === 'persen' && headerDiskon > 0" x-text="'(= Rp ' + formatThousand(headerDiskon) + ')'"></span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <input type="hidden" name="data_usulan[diskon_total]" :value="headerDiskon">
                                        <div class="inline-flex p-0.5 rounded-lg bg-muted border border-border shrink-0 select-none">
                                            <button type="button" @click="setHeaderDiskonMode('nominal')" class="px-2 py-0.5 text-xs font-semibold rounded transition cursor-pointer" :class="headerDiskonMode === 'nominal' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">Rp</button>
                                            <button type="button" @click="setHeaderDiskonMode('persen')" class="px-2 py-0.5 text-xs font-semibold rounded transition cursor-pointer" :class="headerDiskonMode === 'persen' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">%</button>
                                        </div>
                                        <div class="relative w-36">
                                            <template x-if="headerDiskonMode === 'persen'">
                                                <div class="relative flex items-center">
                                                    <input type="number" step="any" min="0" max="100" x-model.number="headerDiskonVal" @input="recalcHeaderDiskon()" placeholder="0" class="w-full text-right text-xs py-1.5 pl-2 pr-6 rounded-lg border border-input bg-background font-mono text-destructive font-semibold focus:ring-1 focus:ring-ring shadow-xs" />
                                                    <span class="absolute right-2 text-xs text-destructive font-mono font-bold pointer-events-none">%</span>
                                                </div>
                                            </template>
                                            <template x-if="headerDiskonMode !== 'persen'">
                                                <div class="relative flex items-center">
                                                    <span class="absolute left-2.5 text-[10px] text-muted-foreground font-mono pointer-events-none">Rp</span>
                                                    <input type="text" inputmode="numeric" :value="formatThousand(headerDiskon)" @input="updateHeaderField('headerDiskon', $event)" @keydown="handleKeyDown($event)" placeholder="0" class="w-full text-right text-xs py-1.5 pl-7 pr-2.5 rounded-lg border border-input bg-background font-mono text-destructive font-semibold focus:ring-1 focus:ring-ring shadow-xs" />
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <label class="text-xs text-foreground font-medium">PPN Global PO</label>
                                        <span class="text-[10px] text-muted-foreground font-mono truncate" x-show="headerPpnMode === 'persen' && headerPpn > 0" x-text="'(= Rp ' + formatThousand(headerPpn) + ')'"></span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <input type="hidden" name="data_usulan[ppn_nominal]" :value="headerPpn">
                                        <div class="inline-flex p-0.5 rounded-lg bg-muted border border-border shrink-0 select-none">
                                            <button type="button" @click="setHeaderPpnMode('nominal')" class="px-2 py-0.5 text-xs font-semibold rounded transition cursor-pointer" :class="headerPpnMode === 'nominal' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">Rp</button>
                                            <button type="button" @click="setHeaderPpnMode('persen')" class="px-2 py-0.5 text-xs font-semibold rounded transition cursor-pointer" :class="headerPpnMode === 'persen' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">%</button>
                                        </div>
                                        <div class="relative w-36">
                                            <template x-if="headerPpnMode === 'persen'">
                                                <div class="relative flex items-center">
                                                    <input type="number" step="any" min="0" max="100" x-model.number="headerPpnVal" @input="recalcHeaderPpn()" placeholder="0" class="w-full text-right text-xs py-1.5 pl-2 pr-6 rounded-lg border border-input bg-background font-mono text-foreground font-semibold focus:ring-1 focus:ring-ring shadow-xs" />
                                                    <span class="absolute right-2 text-xs text-muted-foreground font-mono font-bold pointer-events-none">%</span>
                                                </div>
                                            </template>
                                            <template x-if="headerPpnMode !== 'persen'">
                                                <div class="relative flex items-center">
                                                    <span class="absolute left-2.5 text-[10px] text-muted-foreground font-mono pointer-events-none">Rp</span>
                                                    <input type="text" inputmode="numeric" :value="formatThousand(headerPpn)" @input="updateHeaderField('headerPpn', $event)" @keydown="handleKeyDown($event)" placeholder="0" class="w-full text-right text-xs py-1.5 pl-7 pr-2.5 rounded-lg border border-input bg-background font-mono text-foreground font-semibold focus:ring-1 focus:ring-ring shadow-xs" />
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <label class="text-xs text-foreground font-medium">Ongkos Kirim Global</label>
                                    <div class="relative w-36 shrink-0">
                                        <input type="hidden" name="data_usulan[ongkos_kirim]" :value="headerOngkir">
                                        <div class="relative flex items-center">
                                            <span class="absolute left-2.5 text-[10px] text-muted-foreground font-mono pointer-events-none">Rp</span>
                                            <input type="text" inputmode="numeric" :value="formatThousand(headerOngkir)" @input="updateHeaderField('headerOngkir', $event)" @keydown="handleKeyDown($event)" placeholder="0" class="w-full text-right text-xs py-1.5 pl-7 pr-2.5 rounded-lg border border-input bg-background text-foreground font-mono font-medium focus:ring-1 focus:ring-ring shadow-xs" />
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <label class="text-xs text-foreground font-medium">Adjustment / Pembulatan</label>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <input type="hidden" name="data_usulan[adjustment]" :value="headerAdj">
                                        <div class="inline-flex p-0.5 rounded-lg bg-muted border border-border shrink-0 select-none">
                                            <button type="button" @click="setHeaderAdjSign(1)" class="px-2 py-0.5 text-xs font-medium rounded transition cursor-pointer flex items-center gap-1" :class="headerAdj >= 0 ? 'bg-background text-emerald-600 font-bold shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                                                <span class="font-bold">+</span> Tambah
                                            </button>
                                            <button type="button" @click="setHeaderAdjSign(-1)" class="px-2 py-0.5 text-xs font-medium rounded transition cursor-pointer flex items-center gap-1" :class="headerAdj < 0 ? 'bg-background text-destructive font-bold shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                                                <span class="font-bold">−</span> Kurang
                                            </button>
                                        </div>
                                        <div class="relative w-36">
                                            <div class="relative flex items-center">
                                                <span class="absolute left-2.5 text-[10px] font-mono font-bold pointer-events-none" :class="headerAdj < 0 ? 'text-destructive' : (headerAdj > 0 ? 'text-emerald-600' : 'text-muted-foreground')" x-text="headerAdj < 0 ? '- Rp' : (headerAdj > 0 ? '+ Rp' : 'Rp')"></span>
                                                <input type="text" inputmode="numeric" :value="formatThousand(Math.abs(headerAdj))" @input="updateHeaderAdj($event)" @keydown="handleKeyDown($event)" placeholder="0" class="w-full text-right text-xs py-1.5 pl-10 pr-2.5 rounded-lg border border-input bg-background font-mono font-semibold focus:ring-1 focus:ring-ring shadow-xs" :class="headerAdj < 0 ? 'text-destructive' : (headerAdj > 0 ? 'text-emerald-600' : 'text-foreground')" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </x-card>

                        <x-card title="Ringkasan Nilai Pengadaan" subtitle="Total kalkulasi finansial komitmen baru">
                            <input type="hidden" name="data_usulan[subtotal_produk]" :value="subtotalProduk()">
                            <input type="hidden" name="data_usulan[grand_total]" :value="grandTotal()">

                            <div class="space-y-2.5 text-xs">
                                <div class="flex justify-between text-muted-foreground">
                                    <span>Subtotal Bahan (Kotor):</span>
                                    <span class="font-mono font-semibold text-foreground" x-text="'Rp ' + formatThousand(subtotalProduk())"></span>
                                </div>
                                <div class="flex justify-between text-destructive">
                                    <span>Total Diskon:</span>
                                    <span class="font-mono font-semibold" x-text="'- Rp ' + formatThousand(totalDiskon())"></span>
                                </div>
                                <div class="flex justify-between text-muted-foreground">
                                    <span>Total PPN:</span>
                                    <span class="font-mono font-semibold text-foreground" x-text="'+ Rp ' + formatThousand(totalPpn())"></span>
                                </div>
                                <div class="flex justify-between text-muted-foreground">
                                    <span>Total Ongkos Kirim:</span>
                                    <span class="font-mono font-semibold text-foreground" x-text="'+ Rp ' + formatThousand(totalOngkir())"></span>
                                </div>
                                <div class="flex justify-between" :class="totalAdj() < 0 ? 'text-destructive' : 'text-muted-foreground'">
                                    <span>Total Adjustment:</span>
                                    <span class="font-mono font-semibold" :class="totalAdj() < 0 ? 'text-destructive' : 'text-foreground'" x-text="(totalAdj() >= 0 ? '+ Rp ' : '- Rp ') + formatThousand(Math.abs(totalAdj()))"></span>
                                </div>
                                <div class="pt-2.5 border-t border-border flex justify-between items-center">
                                    <span class="font-bold text-foreground">Grand Total Usulan:</span>
                                    <span class="font-mono font-extrabold text-foreground text-base" x-text="'Rp ' + formatThousand(grandTotal())"></span>
                                </div>
                                <div class="p-2.5 rounded-lg bg-muted/50 border border-border flex items-center justify-between text-[11px]">
                                    <span class="text-muted-foreground">PO Sebelumnya: <strong class="text-foreground">Rp {{ number_format($po->grand_total, 0, ',', '.') }}</strong></span>
                                    <span class="font-mono font-bold" :class="grandTotal() - oldGrandTotal > 0 ? 'text-destructive' : (grandTotal() - oldGrandTotal < 0 ? 'text-emerald-600' : 'text-muted-foreground')">
                                        Selisih: <span x-text="(grandTotal() - oldGrandTotal >= 0 ? '+Rp ' : '-Rp ') + formatThousand(Math.abs(grandTotal() - oldGrandTotal))"></span>
                                    </span>
                                </div>
                            </div>
                        </x-card>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-border">
                        <span class="text-[11px] text-muted-foreground">
                            Usulan akan otomatis masuk ke antrean Manager. Data PO aktif tidak akan berubah sampai disetujui.
                        </span>
                        <div class="flex items-center gap-2 justify-end">
                            <x-button type="button" variant="outline" size="md" @click="openRevisiModal = false">Batal</x-button>
                            <x-button type="submit" variant="primary" size="md" x-bind:disabled="isSubmitDisabled()">
                                <span x-show="!isSubmitting">Kirim Usulan Revisi ke Manager</span>
                                <span x-show="isSubmitting" class="flex items-center gap-2" style="display: none;">
                                    <svg class="animate-spin size-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    Mengirim Usulan...
                                </span>
                            </x-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @endcan

        {{-- MODAL INVALIDATE LEAD TIME (Manager) --}}
        @if($po->leadTime)
        @can('purchasing.approve')
        <div x-show="openInvalidateModal" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" x-cloak>
            <div class="bg-card rounded-xl border border-border p-5 max-w-sm w-full space-y-3 shadow-xl">
                <h3 class="font-bold text-sm text-foreground">Invalidasi Transaksi Lead Time</h3>
                <p class="text-[11px] text-muted-foreground leading-relaxed">
                    Tindakan ini akan mengeluarkan durasi transaksi ini dari perhitungan rata-rata Lead Time supplier.
                </p>
                <form method="POST" action="{{ route('purchasing.lead-time.invalidate', $po->leadTime) }}" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="block font-semibold mb-1 text-foreground">Alasan Invalidasi <span class="text-destructive">*</span></label>
                        <textarea name="alasan" required minlength="5" rows="3" placeholder="Contoh: Ekspedisi mogok luar biasa (anomali force majeure)..." class="w-full rounded-lg border border-input bg-background p-2 focus:outline-none focus:ring-1 focus:ring-ring"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-border">
                        <x-button type="button" variant="outline" size="xs" @click="openInvalidateModal = false">Batal</x-button>
                        <x-button type="submit" variant="danger" size="xs">Invalidasi</x-button>
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

        function revisiForm(initial = {}) {
            return {
                uomList: initial.uomList || [],
                oldGrandTotal: parseFloat(initial.oldGrandTotal) || 0,
                alasan: '',
                isSubmitting: false,

                headerDiskonMode: initial.headerDiskonMode || 'nominal',
                headerDiskonVal: parseFloat(initial.headerDiskonVal) || 0,
                headerDiskon: parseFloat(initial.headerDiskon) || 0,

                headerPpnMode: initial.headerPpnMode || 'nominal',
                headerPpnVal: parseFloat(initial.headerPpnVal) || 0,
                headerPpn: parseFloat(initial.headerPpn) || 0,

                headerOngkir: parseFloat(initial.headerOngkir) || 0,
                headerAdj: parseFloat(initial.headerAdj) || 0,

                rows: (initial.rows || []).map(r => ({
                    ...r,
                    qty_satuan_beli: r.qty_satuan_beli !== undefined && r.qty_satuan_beli !== null ? r.qty_satuan_beli : r.qty,
                    satuan_beli: r.satuan_beli || r.satuan || 'pcs',
                    faktor_konversi: parseFloat(r.faktor_konversi) || 1,
                    qty: parseFloat(r.qty) || 0,
                    harga: parseFloat(r.harga) || 0,
                })),

                init() {
                    this.recalcAll();
                },

                getUomOptions(baseSatuan, currentSatuanBeli = null, currentFactor = null) {
                    const s = (baseSatuan || '').toLowerCase().trim();
                    const curKey = (currentSatuanBeli || '').toLowerCase().trim();
                    const options = [];
                    const seen = new Set();

                    if (Array.isArray(this.uomList)) {
                        this.uomList.forEach(u => {
                            const uKode = (u.kode || '').toLowerCase().trim();
                            const uBasis = (u.satuan_dasar || '').toLowerCase().trim();
                            const uKat = (u.kategori || '').toLowerCase().trim();
                            const factor = parseFloat(u.faktor_konversi) || 1;

                            let isMatch = (uBasis === s) || (uKode === s);
                            if (!isMatch && (s === 'ml' || s === 'l' || s === 'liter' || s === 'mililiter')) {
                                isMatch = (uBasis === 'ml' || uBasis === 'l' || uKat === 'volume' || uKode === 'galon' || uKode === 'drum');
                            } else if (!isMatch && (s === 'gr' || s === 'g' || s === 'gram' || s === 'kg' || s === 'kilogram')) {
                                isMatch = (uBasis === 'gr' || uBasis === 'kg' || uKat === 'massa');
                            } else if (!isMatch && (s === 'pcs' || s === 'unit' || s === 'botol' || s === 'box')) {
                                isMatch = (uBasis === 'pcs' || uKat === 'satuan hitung' || uKat === 'kemasan');
                            }

                            if (isMatch && !seen.has(uKode)) {
                                seen.add(uKode);
                                const factorDisplay = (factor == parseInt(factor) ? parseInt(factor) : factor).toLocaleString('id-ID');
                                const label = factor > 1
                                    ? `${u.nama} (${factorDisplay} ${baseSatuan || ''})`
                                    : (uBasis === s || uKode === s ? `${u.nama} (${u.kode} - Satuan Dasar)` : `${u.nama} (${u.kode})`);
                                options.push({
                                    code: u.kode,
                                    label: label,
                                    factor: factor,
                                    isBase: (uKode === s || (uBasis === s && factor === 1))
                                });
                            }
                        });
                    }

                    if (!seen.has(s) && baseSatuan) {
                        seen.add(s);
                        options.unshift({ code: baseSatuan, label: `${baseSatuan} (Satuan Dasar)`, factor: 1, isBase: true });
                    }

                    if (curKey) {
                        const found = options.some(o => o.code.toLowerCase().trim() === curKey);
                        if (!found) {
                            seen.add(curKey);
                            const factorNum = parseFloat(currentFactor) || 1;
                            const factorDisplay = (factorNum == parseInt(factorNum) ? parseInt(factorNum) : factorNum).toLocaleString('id-ID');
                            options.push({
                                code: currentSatuanBeli,
                                label: `${currentSatuanBeli} (${factorDisplay} ${baseSatuan || ''})`,
                                factor: factorNum
                            });
                        }
                    }

                    options.sort((a, b) => {
                        const aIsBase = a.isBase || (a.code.toLowerCase().trim() === s);
                        const bIsBase = b.isBase || (b.code.toLowerCase().trim() === s);
                        if (aIsBase && !bIsBase) return -1;
                        if (!aIsBase && bIsBase) return 1;
                        return (parseFloat(a.factor) || 0) - (parseFloat(b.factor) || 0);
                    });

                    return options;
                },

                onSatuanBeliChange(row) {
                    const options = this.getUomOptions(row.satuan, row.satuan_beli, row.faktor_konversi);
                    const opt = options.find(o => o.code === row.satuan_beli || o.code.toLowerCase() === (row.satuan_beli || '').toLowerCase());
                    if (opt && opt.factor !== null && opt.factor !== undefined) {
                        row.faktor_konversi = opt.factor;
                    }
                    this.updateRowQty(row);
                },

                handleQtyInput(row, event) {
                    if (event && event.target) {
                        let raw = event.target.value;
                        if (typeof raw === 'string' && (raw.includes('.') || raw.includes(',') || /\D/.test(raw))) {
                            const clean = raw.replace(/\D/g, '');
                            event.target.value = clean;
                            row.qty_satuan_beli = clean ? parseInt(clean, 10) : '';
                        }
                    }
                    if (row.qty_satuan_beli !== '' && row.qty_satuan_beli !== null && row.qty_satuan_beli !== undefined) {
                        const num = parseFloat(row.qty_satuan_beli);
                        if (!isNaN(num) && num !== Math.round(num)) {
                            row.qty_satuan_beli = Math.round(num);
                        }
                    }
                    this.updateRowQty(row);
                },

                handleQtyPaste(row, event) {
                    event.preventDefault();
                    const pasteText = (event.clipboardData || window.clipboardData)?.getData('text') || '';
                    const clean = pasteText.replace(/\D/g, '');
                    if (clean) {
                        const val = parseInt(clean, 10);
                        row.qty_satuan_beli = val > 0 ? val : '';
                    } else {
                        row.qty_satuan_beli = '';
                    }
                    this.updateRowQty(row);
                },

                updateRowQty(row) {
                    const qtyBeli = parseInt(row.qty_satuan_beli, 10) || 0;
                    const factor = parseFloat(row.faktor_konversi) || 1;
                    row.qty = Math.round(qtyBeli * factor * 100) / 100;
                    this.recalcAll();
                },

                formatQty(val) {
                    if (val === '' || val === null || val === undefined || isNaN(val)) return '0';
                    const num = Number(val);
                    if (Math.abs(num - Math.round(num)) < 0.00001) {
                        return num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                    }
                    return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                recalcAll() {
                    if (this.headerDiskonMode === 'persen') this.recalcHeaderDiskon();
                    if (this.headerPpnMode === 'persen') this.recalcHeaderPpn();
                },

                setHeaderDiskonMode(mode) {
                    if (this.headerDiskonMode === mode) return;
                    this.headerDiskonMode = mode;
                    if (mode === 'persen') {
                        const sub = this.subtotalProduk();
                        if (!this.headerDiskonVal && this.headerDiskon > 0 && sub > 0) {
                            this.headerDiskonVal = Math.round(((parseFloat(this.headerDiskon) || 0) / sub) * 10000) / 100;
                        }
                    }
                    this.recalcHeaderDiskon();
                },

                recalcHeaderDiskon() {
                    if (this.headerDiskonMode === 'persen') {
                        const sub = this.subtotalProduk();
                        const pct = parseFloat(this.headerDiskonVal) || 0;
                        this.headerDiskon = Math.round((sub * pct) / 100);
                    }
                    if (this.headerPpnMode === 'persen') {
                        this.recalcHeaderPpn();
                    }
                },

                setHeaderPpnMode(mode) {
                    if (this.headerPpnMode === mode) return;
                    this.headerPpnMode = mode;
                    if (mode === 'persen') {
                        const dpp = Math.max(0, this.subtotalProduk() - this.totalDiskon());
                        if (!this.headerPpnVal && this.headerPpn > 0 && dpp > 0) {
                            this.headerPpnVal = Math.round(((parseFloat(this.headerPpn) || 0) / dpp) * 10000) / 100;
                        } else if (!this.headerPpnVal) {
                            this.headerPpnVal = 11;
                        }
                    }
                    this.recalcHeaderPpn();
                },

                recalcHeaderPpn() {
                    if (this.headerPpnMode === 'persen') {
                        const dpp = Math.max(0, this.subtotalProduk() - this.totalDiskon());
                        const pct = parseFloat(this.headerPpnVal) || 0;
                        this.headerPpn = Math.round((dpp * pct) / 100);
                    }
                },

                setHeaderAdjSign(sign) {
                    const currentAbs = Math.abs(parseFloat(this.headerAdj) || 0);
                    this.headerAdj = sign >= 0 ? currentAbs : -currentAbs;
                    this.recalcAll();
                },

                updateHeaderAdj(event) {
                    const input = event.target;
                    const oldVal = input.value;
                    const oldPos = input.selectionEnd || 0;
                    const digitsBeforeCursor = (oldVal.slice(0, oldPos).match(/\d/g) || []).length;

                    const isNegative = oldVal.includes('-') || (this.headerAdj < 0 && !oldVal.includes('+'));
                    let rawDigits = oldVal.replace(/\D/g, '');
                    if (rawDigits === '') {
                        this.headerAdj = 0;
                        input.value = '';
                        this.recalcAll();
                        return;
                    }
                    if (rawDigits.length > 1 && rawDigits.startsWith('0')) {
                        rawDigits = String(parseInt(rawDigits, 10) || 0);
                    }

                    const num = parseInt(rawDigits, 10);
                    const absNum = isNaN(num) ? 0 : num;
                    this.headerAdj = isNegative ? -absNum : absNum;

                    const formatted = this.formatThousand(absNum);
                    input.value = formatted;
                    this.repositionCursor(input, formatted, digitsBeforeCursor);
                    this.recalcAll();
                },

                formatThousand(val) {
                    if (val === '' || val === null || val === undefined) return '';
                    if (typeof val === 'number') {
                        return Math.round(val).toLocaleString('id-ID');
                    }
                    const str = val.toString().trim();
                    if (str.includes('.')) {
                        const num = parseFloat(str);
                        if (!isNaN(num)) {
                            return Math.round(num).toLocaleString('id-ID');
                        }
                    }
                    const clean = str.replace(/\D/g, '');
                    if (!clean) return '';
                    return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                },

                formatHpp(val) {
                    if (val === '' || val === null || val === undefined || isNaN(val)) return '0';
                    const num = Number(val);
                    if (Math.abs(num - Math.round(num)) < 0.00001) {
                        return num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                    }
                    return num.toLocaleString('id-ID', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 2
                    });
                },

                updateRowField(row, field, event) {
                    const input = event.target;
                    const oldVal = input.value;
                    const oldPos = input.selectionEnd || 0;
                    const digitsBeforeCursor = (oldVal.slice(0, oldPos).match(/\d/g) || []).length;

                    let rawDigits = oldVal.replace(/\D/g, '');
                    if (rawDigits === '') {
                        row[field] = 0;
                        input.value = '';
                        this.recalcAll();
                        return;
                    }
                    if (rawDigits.length > 1 && rawDigits.startsWith('0')) {
                        rawDigits = String(parseInt(rawDigits, 10) || 0);
                    }

                    const num = parseInt(rawDigits, 10);
                    row[field] = isNaN(num) ? 0 : num;

                    const formatted = this.formatThousand(rawDigits);
                    input.value = formatted;
                    this.repositionCursor(input, formatted, digitsBeforeCursor);
                    this.recalcAll();
                },

                updateHeaderField(field, event) {
                    const input = event.target;
                    const oldVal = input.value;
                    const oldPos = input.selectionEnd || 0;
                    const digitsBeforeCursor = (oldVal.slice(0, oldPos).match(/\d/g) || []).length;

                    let rawDigits = oldVal.replace(/\D/g, '');
                    if (rawDigits === '') {
                        this[field] = 0;
                        input.value = '';
                        this.recalcAll();
                        return;
                    }
                    if (rawDigits.length > 1 && rawDigits.startsWith('0')) {
                        rawDigits = String(parseInt(rawDigits, 10) || 0);
                    }

                    const num = parseInt(rawDigits, 10);
                    this[field] = isNaN(num) ? 0 : num;

                    const formatted = this.formatThousand(rawDigits);
                    input.value = formatted;
                    this.repositionCursor(input, formatted, digitsBeforeCursor);
                    this.recalcAll();
                },

                repositionCursor(input, formatted, digitsBeforeCursor) {
                    let newPos = 0;
                    let countedDigits = 0;
                    for (let i = 0; i < formatted.length; i++) {
                        if (/\d/.test(formatted[i])) countedDigits++;
                        if (countedDigits === digitsBeforeCursor) {
                            newPos = i + 1;
                            break;
                        }
                    }
                    if (countedDigits < digitsBeforeCursor || newPos > formatted.length) {
                        newPos = formatted.length;
                    }
                    try { input.setSelectionRange(newPos, newPos); } catch (e) {}
                },

                handleKeyDown(event) {
                    if (event.key === 'Backspace') {
                        const input = event.target;
                        if (input.selectionStart === input.selectionEnd && input.selectionStart > 0) {
                            if (input.value[input.selectionStart - 1] === '.') {
                                event.preventDefault();
                                const pos = input.selectionStart - 1;
                                const val = input.value;
                                input.value = val.slice(0, pos - 1) + val.slice(pos);
                                input.dispatchEvent(new Event('input'));
                            }
                        }
                    } else if (event.key === 'Delete') {
                        const input = event.target;
                        if (input.selectionStart === input.selectionEnd && input.selectionStart < input.value.length) {
                            if (input.value[input.selectionStart] === '.') {
                                event.preventDefault();
                                const pos = input.selectionStart;
                                const val = input.value;
                                input.value = val.slice(0, pos) + val.slice(pos + 2);
                                input.dispatchEvent(new Event('input'));
                            }
                        }
                    }
                },

                calculateRowNet(row) {
                    const h = parseFloat(row.harga) || 0;
                    if (h <= 0) return 0;

                    const sub = this.subtotalProduk();
                    const diskonTotal = parseFloat(this.headerDiskon) || 0;
                    const ppnTotal = parseFloat(this.headerPpn) || 0;
                    const ongkirTotal = parseFloat(this.headerOngkir) || 0;
                    const adjTotal = parseFloat(this.headerAdj) || 0;

                    const ratio = sub > 0 ? (h / sub) : (this.rows.length > 0 ? (1 / this.rows.length) : 0);

                    const d = diskonTotal * ratio;
                    const p = ppnTotal * ratio;
                    const o = ongkirTotal * ratio;
                    const a = adjTotal * ratio;

                    return Math.max(0, Math.round((h - d + p + o + a) * 100) / 100);
                },

                calculateRowHpp(row) {
                    const q = parseFloat(row.qty) || 0;
                    if (q <= 0) return 0;
                    const net = this.calculateRowNet(row);
                    return Math.round((net / q) * 10000) / 10000;
                },

                subtotalProduk() {
                    return this.rows.reduce((acc, r) => acc + (parseFloat(r.harga) || 0), 0);
                },

                totalDiskon() {
                    return Math.round((parseFloat(this.headerDiskon) || 0) * 100) / 100;
                },

                totalPpn() {
                    return Math.round((parseFloat(this.headerPpn) || 0) * 100) / 100;
                },

                totalOngkir() {
                    return Math.round((parseFloat(this.headerOngkir) || 0) * 100) / 100;
                },

                totalAdj() {
                    return Math.round((parseFloat(this.headerAdj) || 0) * 100) / 100;
                },

                grandTotal() {
                    const gt = this.subtotalProduk() - this.totalDiskon() + this.totalPpn() + this.totalOngkir() + this.totalAdj();
                    return Math.max(0, Math.round(gt));
                },

                isSubmitDisabled() {
                    return this.isSubmitting || (this.alasan || '').trim().length < 5 || this.rows.length === 0;
                },

                handleSubmit(event) {
                    if (this.isSubmitDisabled()) {
                        if (event) event.preventDefault();
                        return false;
                    }
                    this.isSubmitting = true;
                    return true;
                }
            };
        }
    </script>
    @endpush
</x-app-layout>

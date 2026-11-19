<x-app-layout title="Beranda">
    <x-page-header title="Dashboard Operasional">
        <x-slot:actions>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <span class="text-xs text-text-muted hidden sm:inline">User: <strong class="text-text-active font-semibold">{{ auth()->user()->name }}</strong></span>
                <x-badge variant="neutral" size="sm" :pill="true">{{ auth()->user()->roles->first()?->name ?? 'Staff' }}</x-badge>
                @can('analisa.view')
                <x-button href="{{ route('analisa.index') }}" variant="secondary" size="sm">
                    <x-slot:icon>
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </x-slot:icon>
                    Analisa Stok
                </x-button>
                @endcan
                @can('purchasing.create')
                <x-button href="{{ route('purchasing.create') }}" variant="primary" size="sm">
                    <x-slot:icon>
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Buat PO
                </x-button>
                @endcan
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- Tier 1: Contextual KPI Strip --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 mb-5">
        @foreach($kpis as $kpi)
            @php
                $kpiTitleLower = strtolower((string) ($kpi['title'] ?? ''));
                $kpiIconColor = match (true) {
                    str_contains($kpiTitleLower, 'rusak') || str_contains($kpiTitleLower, 'order') || str_contains($kpiTitleLower, 'terlambat') || str_contains($kpiTitleLower, 'kritis') => 'rose',
                    str_contains($kpiTitleLower, 'menunggu') || str_contains($kpiTitleLower, 'approval') || str_contains($kpiTitleLower, 'pending') || str_contains($kpiTitleLower, 'tagihan') => 'amber',
                    str_contains($kpiTitleLower, 'selesai') || str_contains($kpiTitleLower, 'stok') || str_contains($kpiTitleLower, 'aktif') => 'emerald',
                    str_contains($kpiTitleLower, 'transfer') || str_contains($kpiTitleLower, 'batch') || str_contains($kpiTitleLower, 'produksi') => 'blue',
                    default => 'indigo',
                };
            @endphp
            <x-stat-box
                :title="$kpi['title']"
                :value="$kpi['value']"
                :subtitle="$kpi['subtitle'] ?? null"
                :href="$kpi['href'] ?? null"
                :footerText="$kpi['footerText'] ?? null"
                :iconColor="$kpi['iconColor'] ?? $kpiIconColor"
            >
                @if(isset($kpi['icon']))
                    <x-slot:icon>{!! $kpi['icon'] !!}</x-slot:icon>
                @endif
            </x-stat-box>
        @endforeach
    </div>

    {{-- Tier 2: Urgent Action Center --}}
    <x-card :title="$actionQueue['title']" :subtitle="$actionQueue['subtitle']" :count="count($actionQueue['items'] ?? [])" :noPadding="true" class="mb-5">
        <x-slot:icon>
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </x-slot:icon>

        @if(!empty($actionQueue['viewAllUrl']))
            <x-slot:tools>
                <a href="{{ $actionQueue['viewAllUrl'] }}" class="text-xs font-semibold text-text-active hover:text-text-muted transition-colors inline-flex items-center gap-1">
                    Lihat Semua &rarr;
                </a>
            </x-slot:tools>
        @endif

        <div class="divide-y divide-[#E8E8E8] dark:divide-gray-800/60">
            @forelse($actionQueue['items'] as $item)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 px-5 py-3 hover:bg-gray-50/80 dark:hover:bg-gray-800/30 transition-colors">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-badge :variant="$item->badge_variant ?? 'neutral'" :dot="true" :pill="true" size="sm">
                            {{ $item->badge_label ?? $item->type }}
                        </x-badge>
                        @if(!empty($item->is_terlambat))
                            <x-badge variant="danger" :dot="true" :pill="true" size="sm">
                                Terlambat
                            </x-badge>
                        @endif
                        <div class="flex flex-col min-w-0">
                            <span class="font-semibold text-[#101828] dark:text-white text-xs sm:text-[13px] tabular-nums truncate">{{ $item->nomor }}</span>
                            <span class="text-text-muted text-[11px] truncate">{{ $item->meta }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0 self-end sm:self-center">
                        @if(isset($item->created_at))
                            <span class="text-[11px] text-text-muted hidden md:inline">
                                {{ $item->created_at instanceof \Carbon\CarbonInterface ? $item->created_at->diffForHumans() : $item->created_at }}
                            </span>
                        @endif
                        <x-button :href="$item->url" variant="secondary" size="xs">
                            {{ $item->action_label ?? 'Buka' }}
                        </x-button>
                    </div>
                </div>
            @empty
                <div class="py-10 text-center text-text-muted text-xs flex flex-col items-center justify-center">
                    <svg class="size-6 mb-1.5 opacity-50 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Semua tugas prioritas peran ini telah ditindaklanjuti. Antrean bersih.
                </div>
            @endforelse
        </div>
    </x-card>

    {{-- Tier 3: Operational Activity Streams --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach([$streamLeft, $streamRight] as $streamIndex => $stream)
            <x-card :title="$stream['title']" :subtitle="$stream['subtitle']" :count="count($stream['items'] ?? [])" :noPadding="true">
                <x-slot:icon>
                    @if($streamIndex === 0)
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    @else
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                </x-slot:icon>

                @if(!empty($stream['viewAllUrl']))
                    <x-slot:tools>
                        <a href="{{ $stream['viewAllUrl'] }}" class="text-xs font-semibold text-text-active hover:text-text-muted transition-colors inline-flex items-center gap-1">
                            Lihat Semua &rarr;
                        </a>
                    </x-slot:tools>
                @endif

                <div class="divide-y divide-[#E8E8E8] dark:divide-gray-800/60">
                    @forelse($stream['items'] as $item)
                        @php
                            $rowUrl = '#';
                            $rowTitle = '-';
                            $rowSubtitle = '-';
                            $rowBadge = null;
                            $rowVariant = 'neutral';
                            $badgeDot = true;

                            if ($item instanceof \App\Models\PurchaseOrder) {
                                $rowUrl = route('purchasing.show', $item);
                                $rowTitle = $item->no_po;
                                $rowSubtitle = ($item->supplier?->nama ?? '-') . ' · ' . ($item->tanggal?->format('d M Y') ?? '');
                                $rowBadge = $item->stage_aktif
                                    ? (\App\Models\PoStage::STAGE_LABELS[$item->stage_aktif] ?? ucwords(str_replace('_', ' ', $item->stage_aktif)))
                                    : ucwords(str_replace('_', ' ', $item->status));
                                $rowVariant = match($item->status) {
                                    'diajukan' => 'warning',
                                    'disetujui' => 'primary',
                                    'dikirim_ke_gudang' => 'info',
                                    'selesai' => 'success',
                                    default => 'neutral'
                                };
                                if ($item->stage_aktif) {
                                    $rowVariant = match($item->stage_aktif) {
                                        'approval' => 'warning',
                                        'pengiriman', 'unloading', 'input_sistem' => 'info',
                                        default => 'neutral'
                                    };
                                }
                                if (!empty($item->is_terlambat)) {
                                    $rowVariant = 'danger';
                                    $rowBadge = 'Terlambat SLA';
                                }
                            } elseif ($item instanceof \App\Models\Transfer) {
                                $rowUrl = route('transfers.show', $item);
                                $rowTitle = $item->no_transaksi;
                                $rowSubtitle = ($item->gudangAsal?->nama ?? '-') . ' → ' . ($item->gudangTujuan?->nama ?? '-');
                                $rowBadge = ucwords(str_replace('_', ' ', $item->status));
                                $rowVariant = match($item->status) {
                                    'draft' => 'warning',
                                    'dikirim' => 'info',
                                    'selesai' => 'success',
                                    default => 'neutral'
                                };
                            } elseif ($item instanceof \App\Models\StockRequest) {
                                $rowUrl = route('requests.show', $item);
                                $rowTitle = $item->no_request;
                                $rowSubtitle = ($item->gudangAsal?->nama ?? '-') . ' → ' . ($item->gudangTujuan?->nama ?? '-');
                                $rowBadge = ucwords(str_replace('_', ' ', $item->status));
                                $rowVariant = match($item->status) {
                                    'draft' => 'neutral',
                                    'diajukan' => 'warning',
                                    'disetujui' => 'primary',
                                    'selesai' => 'success',
                                    default => 'neutral'
                                };
                            } elseif ($item instanceof \App\Models\BatchProduksi) {
                                $rowUrl = route('batches.show', $item);
                                $rowTitle = $item->no_batch;
                                $rowSubtitle = ($item->produk?->nama ?? '-') . ' · ' . number_format($item->qty_rencana, 0) . ' ' . ($item->produk?->satuan ?? 'pcs');
                                $rowBadge = ucwords(str_replace('_', ' ', $item->status));
                                $rowVariant = match($item->status) {
                                    'rencana' => 'warning',
                                    'release' => 'primary',
                                    'selesai' => 'success',
                                    default => 'neutral'
                                };
                            } elseif ($item instanceof \App\Models\Stok) {
                                $rowUrl = route('stok.index');
                                $rowTitle = $item->produk?->nama ?? ('SKU #' . $item->produk_id);
                                $rowSubtitle = ($item->gudang?->nama ?? 'Gudang') . ' · ' . ($item->produk?->sku ?? '');
                                $rowBadge = number_format($item->qty_saat_ini, 0) . ' ' . ($item->produk?->satuan ?? '');
                                $rowVariant = $item->qty_saat_ini <= 10 ? 'danger' : 'warning';
                            } elseif ($item instanceof \App\Models\TransferItem) {
                                $rowUrl = $item->transfer ? route('transfers.show', $item->transfer) : route('transfers.index');
                                $rowTitle = $item->produk?->nama ?? 'Item Rusak';
                                $rowSubtitle = implode(' · ', array_filter([
                                    $item->transfer?->no_transaksi,
                                    $item->transfer?->gudangTujuan?->nama ?? 'Gudang',
                                    $item->keterangan_rusak ?: 'Kerusakan fisik',
                                ]));
                                $rowBadge = \App\Helpers\NumberHelper::formatQty($item->qty_rusak) . ' ' . ($item->produk?->satuan ?? 'pcs') . ' Rusak';
                                $rowVariant = 'danger';
                            } elseif (is_object($item) && !empty($item->is_defect_log)) {
                                $rowUrl = $item->url ?? route('dashboard');
                                $rowTitle = $item->produk_nama ?? 'Item Rusak';
                                $rowSubtitle = implode(' · ', array_filter([
                                    $item->nomor ?? null,
                                    $item->gudang_nama ?? 'Gudang',
                                    $item->keterangan ?? 'Kerusakan fisik',
                                ]));
                                $rowBadge = '[' . ($item->source_badge ?? 'Defect') . '] ' . \App\Helpers\NumberHelper::formatQty($item->qty_rusak ?? 0) . ' ' . ($item->satuan ?? 'pcs') . ' Rusak';
                                $rowVariant = 'danger';
                            } elseif (is_object($item)) {
                                $rowUrl = $item->url ?? '#';
                                $rowTitle = $item->nomor ?? $item->title ?? '-';
                                $rowSubtitle = $item->meta ?? $item->subtitle ?? '-';
                                $rowBadge = $item->badge_label ?? null;
                                $rowVariant = $item->badge_variant ?? 'neutral';
                            }
                        @endphp
                        <a href="{{ $rowUrl }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50/80 dark:hover:bg-gray-800/30 transition-colors group">
                            <div class="flex flex-col min-w-0 pr-4">
                                <span class="font-semibold text-[#101828] dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors text-xs sm:text-[13px] tabular-nums truncate">{{ $rowTitle }}</span>
                                <span class="text-text-muted text-[11px] truncate mt-0.5">
                                    {{ $rowSubtitle }}
                                </span>
                            </div>
                            <div class="shrink-0 flex items-center gap-2">
                                @if($rowBadge)
                                    <x-badge :variant="$rowVariant" :dot="$badgeDot" :pill="true" size="sm">
                                        {{ $rowBadge }}
                                    </x-badge>
                                @endif
                                <span class="size-7 rounded-[6px] border border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 flex items-center justify-center text-text-muted group-hover:bg-gray-50 dark:group-hover:bg-gray-800 group-hover:text-text-active transition-colors">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </span>
                            </div>
                        </a>
                    @empty
                        <div class="py-10 text-center text-text-muted text-xs flex flex-col items-center justify-center">
                            <svg class="size-6 mb-1.5 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Tidak ada data aktivitas untuk saat ini.
                        </div>
                    @endforelse
                </div>
            </x-card>
        @endforeach
    </div>

    {{-- Section: Dashboard Barang Rusak & Defect Mutasi --}}
    @can('defect.view')
        <div class="mt-6">
            @include('partials.defect-dashboard', ['formAction' => route('dashboard')])
        </div>
    @endcan
</x-app-layout>

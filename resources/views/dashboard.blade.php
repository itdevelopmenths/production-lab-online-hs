<x-app-layout title="Beranda">
    <x-page-header title="Dashboard Operasional">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <span class="text-xs text-muted-foreground hidden sm:inline">User: <strong class="text-foreground font-semibold">{{ auth()->user()->name }}</strong></span>
                <x-badge variant="neutral" size="sm">{{ auth()->user()->roles->first()?->name ?? 'Staff' }}</x-badge>
                @can('analisa.view')
                <x-button href="{{ route('analisa.index') }}" variant="outline" size="sm">
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
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 mb-4">
        @foreach($kpis as $kpi)
            <x-stat-box
                :title="$kpi['title']"
                :value="$kpi['value']"
                :subtitle="$kpi['subtitle'] ?? null"
                :href="$kpi['href'] ?? null"
                :footerText="$kpi['footerText'] ?? null"
            >
                @if(isset($kpi['icon']))
                    <x-slot:icon>{!! $kpi['icon'] !!}</x-slot:icon>
                @endif
            </x-stat-box>
        @endforeach
    </div>

    {{-- Tier 2: Urgent Action Center --}}
    <x-card :title="$actionQueue['title']" :subtitle="$actionQueue['subtitle']" :noPadding="true" class="mb-4">
        @if(!empty($actionQueue['viewAllUrl']))
            <x-slot:tools>
                <a href="{{ $actionQueue['viewAllUrl'] }}" class="text-xs font-semibold text-foreground hover:text-muted-foreground transition-colors inline-flex items-center gap-1">
                    Lihat Semua &rarr;
                </a>
            </x-slot:tools>
        @endif

        <div class="divide-y divide-border/60">
            @forelse($actionQueue['items'] as $item)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-3.5 py-2.5 hover:bg-muted/40 transition-colors">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-badge :variant="$item->badge_variant ?? 'neutral'" :dot="true" size="sm">
                            {{ $item->badge_label ?? $item->type }}
                        </x-badge>
                        <div class="flex flex-col min-w-0">
                            <span class="font-semibold text-foreground text-xs tabular-nums truncate">{{ $item->nomor }}</span>
                            <span class="text-muted-foreground text-[11px] truncate">{{ $item->meta }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0 self-end sm:self-center">
                        @if(isset($item->created_at))
                            <span class="text-[11px] text-muted-foreground hidden md:inline">
                                {{ $item->created_at instanceof \Carbon\CarbonInterface ? $item->created_at->diffForHumans() : $item->created_at }}
                            </span>
                        @endif
                        <x-button :href="$item->url" variant="outline" size="sm" class="h-7 text-xs px-2.5">
                            {{ $item->action_label ?? 'Buka' }}
                        </x-button>
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-muted-foreground text-xs flex flex-col items-center justify-center">
                    <svg class="size-6 mb-1.5 opacity-40 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Semua tugas prioritas peran ini telah ditindaklanjuti. Antrean bersih.
                </div>
            @endforelse
        </div>
    </x-card>

    {{-- Operational Action Lists Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Card 1: PO Perlu Tindakan --}}
        <x-card title="Purchase Order Perlu Tindakan" :noPadding="true">
            <x-slot:tools>
                <a href="{{ route('purchasing.index') }}" class="text-xs font-semibold text-foreground hover:text-muted-foreground transition-colors inline-flex items-center gap-1">
                    Lihat Semua &rarr;
                </a>
            </x-slot:tools>

            <div class="divide-y divide-border/60">
                @forelse($poTerbaru as $po)
                <a href="{{ route('purchasing.show', $po) }}" class="flex items-center justify-between px-3.5 py-2.5 hover:bg-muted/40 transition-colors group">
                    <div class="flex flex-col min-w-0 pr-4">
                        <span class="font-semibold text-foreground group-hover:text-primary transition-colors text-sm truncate">{{ $po->no_po }}</span>
                        <span class="text-muted-foreground text-xs truncate mt-0.5">
                            {{ $po->supplier?->nama ?? '-' }} · {{ $po->tanggal?->format('d M Y') }}
                        </span>
                    </div>
                    <div class="shrink-0 flex items-center gap-2 flex-wrap justify-end">
                        @if(!empty($po->has_revisi_menunggu))
                            <x-badge variant="warning" :dot="true" size="sm">
                                Menunggu Revisi
                            </x-badge>
                        @endif

                        @if(!empty($po->is_terlambat))
                            <x-badge variant="danger" :dot="true" size="sm">
                                Terlambat
                            </x-badge>
                        @endif

                        @php
                            $stageLabel = $po->stage_aktif
                                ? (\App\Models\PoStage::STAGE_LABELS[$po->stage_aktif] ?? ucwords(str_replace('_', ' ', $po->stage_aktif)))
                                : ucwords(str_replace('_', ' ', $po->status));

                            $badgeVariant = match($po->status) {
                                'diajukan' => 'warning',
                                'dikirim_ke_gudang' => 'info',
                                'disetujui' => 'primary',
                                'selesai' => 'success',
                                default => 'neutral',
                            };

                            if ($po->stage_aktif) {
                                $badgeVariant = match($po->stage_aktif) {
                                    'approval' => 'warning',
                                    'pengiriman', 'unloading', 'input_sistem' => 'info',
                                    default => 'neutral',
                                };
                            }
                        @endphp
                        <x-badge :variant="$badgeVariant" :dot="true" size="sm">
                            {{ $stageLabel }}
                        </x-badge>
                        <span class="size-7 rounded-full border border-border flex items-center justify-center text-muted-foreground group-hover:bg-accent group-hover:text-foreground transition-colors">
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    </div>
                </a>
                @empty
                <div class="py-12 text-center text-muted-foreground text-sm flex flex-col items-center justify-center">
                    <svg class="size-8 mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Semua Purchase Order telah ditindaklanjuti.
                </div>
                @endforelse
            </div>
        </x-card>

        {{-- Card 2: Request & Transfer Aktif --}}
        <x-card title="Request & Transfer Aktif" :noPadding="true">
            <x-slot:tools>
                <div class="flex items-center gap-2">
                    @canany(['request.view', 'rt.view'])
                    <a href="{{ route('requests.index') }}" class="text-xs font-semibold text-foreground hover:text-muted-foreground transition-colors inline-flex items-center gap-1">
                        Requests &rarr;
                    </a>
                    @endcanany
                    @canany(['request.view', 'rt.view'])
                        @canany(['transfer.view', 'rt.view'])
                        <span class="text-muted-foreground text-xs">·</span>
                        @endcanany
                    @endcanany
                    @canany(['transfer.view', 'rt.view'])
                    <a href="{{ route('transfers.index') }}" class="text-xs font-semibold text-foreground hover:text-muted-foreground transition-colors inline-flex items-center gap-1">
                        Transfers &rarr;
                    </a>
                    @endcanany
                </div>
            </x-slot:tools>

            <div class="divide-y divide-border/60">
                @forelse($rtTerbaru as $item)
                @php
                    $itemUrl = $item->url ?? (isset($item->id) ? route('rt.show', $item) : '#');
                    $itemNomor = $item->nomor ?? $item->no_transaksi ?? $item->no_request ?? '-';
                    $itemJenis = $item->label_jenis ?? ucwords(str_replace('_', ' ', $item->jenis ?? 'Logistik'));
                    $itemAsal = $item->gudang_asal_nama ?? $item->gudangAsal?->nama ?? '-';
                    $itemTujuan = $item->gudang_tujuan_nama ?? $item->gudangTujuan?->nama ?? '-';
                    $itemStatus = $item->badge_label ?? ucwords(str_replace('_', ' ', $item->status ?? ''));
                    $itemVariant = $item->badge_variant ?? match($item->status ?? '') {
                        'diajukan' => 'warning',
                        'disetujui' => 'primary',
                        'diproses' => 'info',
                        'dikirim' => 'warning',
                        'selesai' => 'success',
                        default => 'neutral',
                    };
                @endphp
                <a href="{{ $itemUrl }}" class="flex items-center justify-between px-3.5 py-2.5 hover:bg-muted/40 transition-colors group">
                    <div class="flex flex-col min-w-0 pr-4">
                        <span class="font-semibold text-foreground group-hover:text-primary transition-colors text-sm truncate">{{ $itemNomor }}</span>
                        <span class="text-muted-foreground text-xs truncate mt-0.5">
                            {{ $itemJenis }} · {{ $itemAsal }} &rarr; {{ $itemTujuan }}
                        </span>
                    </div>
                    <div class="shrink-0 flex items-center gap-3">
                        <x-badge :variant="$itemVariant" :dot="true" size="sm">
                            {{ $itemStatus }}
                        </x-badge>
                        <span class="size-7 rounded-full border border-border flex items-center justify-center text-muted-foreground group-hover:bg-accent group-hover:text-foreground transition-colors">
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    </div>
                </a>
                @empty
                <div class="py-12 text-center text-muted-foreground text-sm flex flex-col items-center justify-center">
                    <svg class="size-8 mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Tidak ada Request & Transfer yang memerlukan tindakan.
                </div>
                @endforelse
            </div>
        </x-card>
    </div>
</x-app-layout>

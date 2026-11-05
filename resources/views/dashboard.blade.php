<x-app-layout title="Beranda" subtitle="Pusat Kontrol & Ringkasan Operasional Laboratorium">
    {{-- Header actions --}}
    <x-slot:actions>
        @can('analisa.view')
        <x-button href="{{ route('analisa.index') }}" variant="outline" size="sm">
            <x-slot:icon>
                <svg class="size-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </x-slot:icon>
            Analisa Stok
        </x-button>
        @endcan
        @can('purchasing.create')
        <x-button href="{{ route('purchasing.create') }}" variant="primary" size="sm">
            <x-slot:icon>
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </x-slot:icon>
            Buat PO Baru
        </x-button>
        @endcan
    </x-slot:actions>

    {{-- DevScore Welcome Banner --}}
    <div class="mb-8 p-6 sm:p-8 rounded-2xl bg-gradient-to-r from-primary/10 via-primary/5 to-transparent border border-primary/10 relative overflow-hidden">
        <div class="relative z-10 max-w-2xl">
            <h2 class="text-2xl font-bold tracking-tight text-foreground">Selamat Datang, {{ auth()->user()->name }} 👋</h2>
            <p class="text-muted-foreground mt-1.5 text-sm leading-relaxed">Berikut adalah ringkasan operasional laboratorium, antrean pengadaan, dan status pergerakan batch produksi saat ini.</p>
        </div>
        <div class="absolute top-0 right-0 -mt-10 -mr-10 opacity-10 pointer-events-none">
            <svg class="w-64 h-64 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>

    {{-- DevScore StatCards Grid --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-8">
        <x-stat-box
            title="Item Perlu Order"
            :value="$stats['item_perlu_order']"
            :subtitle="'Lokal: ' . $orderSummary['lokal'] . ' · Impor: ' . $orderSummary['impor']"
            :href="auth()->user()->can('analisa.view') ? route('analisa.index') : null"
            :footerText="auth()->user()->can('analisa.view') ? 'Lihat Analisa Stok' : null"
        >
            <x-slot:icon>
                <svg class="size-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </x-slot:icon>
        </x-stat-box>

        <x-stat-box
            title="PO Perlu Tindakan"
            :value="$stats['po_perlu_tindakan']"
            subtitle="Menunggu Verifikasi"
            :href="auth()->user()->can('purchasing.view') ? route('purchasing.index') : null"
            :footerText="auth()->user()->can('purchasing.view') ? 'Buka Purchasing' : null"
        >
            <x-slot:icon>
                <svg class="size-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </x-slot:icon>
        </x-stat-box>

        <x-stat-box
            title="Request & Transfer"
            :value="$stats['rt_perlu_tindakan']"
            subtitle="Mutasi & Permintaan"
            :href="auth()->user()->canany(['transfer.view', 'rt.view']) ? route('transfers.index') : (auth()->user()->canany(['request.view', 'rt.view']) ? route('requests.index') : null)"
            :footerText="auth()->user()->canany(['transfer.view', 'request.view', 'rt.view']) ? 'Kelola Logistik' : null"
        >
            <x-slot:icon>
                <svg class="size-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            </x-slot:icon>
        </x-stat-box>

        <x-stat-box
            title="Batch Aktif"
            :value="$stats['batch_aktif']"
            subtitle="Proses Produksi Lab"
            :href="auth()->user()->can('batch.view') ? route('batches.index') : null"
            :footerText="auth()->user()->can('batch.view') ? 'Daftar Batch' : null"
        >
            <x-slot:icon>
                <svg class="size-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </x-slot:icon>
        </x-stat-box>

        <x-stat-box
            title="Batch Selesai"
            :value="$stats['batch_selesai']"
            subtitle="Siap Didistribusikan"
            :href="auth()->user()->can('batch.view') ? route('batches.index') : null"
            :footerText="auth()->user()->can('batch.view') ? 'Riwayat Batch' : null"
        >
            <x-slot:icon>
                <svg class="size-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </x-slot:icon>
        </x-stat-box>
    </div>

    {{-- DevScore 2-Column Operational Action Lists Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Card 1: PO Perlu Tindakan --}}
        <x-card title="Purchase Order Perlu Tindakan" subtitle="Antrean verifikasi dan persetujuan pengadaan bahan" :noPadding="true">
            <x-slot:tools>
                <a href="{{ route('purchasing.index') }}" class="text-xs font-semibold text-foreground hover:text-muted-foreground transition-colors inline-flex items-center gap-1">
                    Lihat Semua &rarr;
                </a>
            </x-slot:tools>

            <div class="divide-y divide-border/60">
                @forelse($poTerbaru as $po)
                <a href="{{ route('purchasing.show', $po) }}" class="flex items-center justify-between p-4 hover:bg-muted/40 transition-colors group">
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
        <x-card title="Request & Transfer Aktif" subtitle="Status perpindahan barang dan logistik antar gudang" :noPadding="true">
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
                <a href="{{ $itemUrl }}" class="flex items-center justify-between p-4 hover:bg-muted/40 transition-colors group">
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

<x-app-layout title="Dashboard">
    {{-- Page Header with Breadcrumbs & Actions --}}
    <x-page-header title="Dashboard v3" subtitle="Pusat Kontrol & Ringkasan Operasional Laboratorium Produksi" :breadcrumbs="['Dashboard' => null]">
        <x-slot:actions>
            <x-button href="{{ route('analisa.index') }}" variant="secondary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </x-slot:icon>
                Analisa Stok
            </x-button>
            @can('purchasing.create')
            <x-button href="{{ route('purchasing.create') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Buat PO Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- AdminLTE 4 KPI Small Boxes Grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3.5 mb-6">
        <x-stat-box
            title="Item Perlu Order"
            :value="$stats['item_perlu_order']"
            :subtitle="'Lokal: ' . $orderSummary['lokal'] . ' · Impor: ' . $orderSummary['impor'] . ' · FF: ' . $orderSummary['fulfillment']"
            variant="danger"
            :href="route('analisa.index')"
            footerText="Lihat Analisa Stok"
        >
            <x-slot:badge>
                @if($stats['item_perlu_order'] > 0)
                    <span class="w-2 h-2 rounded-full bg-rose-600 animate-pulse block"></span>
                @endif
            </x-slot:badge>
        </x-stat-box>

        <x-stat-box
            title="PO Perlu Tindakan"
            :value="$stats['po_perlu_tindakan']"
            subtitle="Antrean Verifikasi Purchasing"
            variant="warning"
            :href="route('purchasing.index')"
            footerText="Kelola Purchasing"
        />

        <x-stat-box
            title="Request & Transfer"
            :value="$stats['rt_perlu_tindakan']"
            subtitle="Logistik & Mutasi Antar Gudang"
            variant="info"
            :href="route('rt.index')"
            footerText="Buka Logistik"
        />

        <x-stat-box
            title="Batch Aktif"
            :value="$stats['batch_aktif']"
            subtitle="Rencana & Release Produksi"
            variant="primary"
            :href="route('batches.index')"
            footerText="Daftar Batch"
        />

        <x-stat-box
            title="Batch Selesai"
            :value="$stats['batch_selesai']"
            subtitle="Produk Siap Distribusi"
            variant="success"
            :href="route('batches.index')"
            footerText="Riwayat Batch"
        />
    </div>

    {{-- Operational Action Lists Grid (AdminLTE 4 Widgets) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Widget 1: PO Perlu Tindakan --}}
        <x-card title="Purchase Order Perlu Tindakan" variant="warning" :noPadding="true">
            <x-slot:tools>
                <a href="{{ route('purchasing.index') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-800">
                    Lihat Semua &rarr;
                </a>
            </x-slot:tools>

            <div class="divide-y divide-gray-100">
                @forelse($poTerbaru as $po)
                <a href="{{ route('purchasing.show', $po) }}" class="flex items-center justify-between p-3 hover:bg-gray-50/80 transition text-xs group">
                    <div class="flex flex-col min-w-0 pr-3">
                        <span class="font-bold text-gray-900 group-hover:text-primary-700 truncate">{{ $po->no_po }}</span>
                        <span class="text-gray-500 text-[11px] truncate mt-0.5">
                            {{ $po->supplier->nama }} · {{ $po->tanggal?->format('d/m/Y') }}
                        </span>
                    </div>
                    <div class="shrink-0">
                        @php
                            $badgeVariant = match($po->status) {
                                'diajukan' => 'warning',
                                'dikirim_ke_gudang' => 'info',
                                'disetujui' => 'primary',
                                'selesai' => 'success',
                                default => 'gray',
                            };
                        @endphp
                        <x-badge :variant="$badgeVariant" :dot="true">
                            {{ ucwords(str_replace('_',' ',$po->status)) }}
                        </x-badge>
                    </div>
                </a>
                @empty
                <div class="py-8 text-center text-gray-400 text-xs">
                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Semua Purchase Order telah ditindaklanjuti.
                </div>
                @endforelse
            </div>
        </x-card>

        {{-- Widget 2: Request & Transfer Aktif --}}
        <x-card title="Request & Transfer Aktif" variant="info" :noPadding="true">
            <x-slot:tools>
                <a href="{{ route('rt.index') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-800">
                    Lihat Semua &rarr;
                </a>
            </x-slot:tools>

            <div class="divide-y divide-gray-100">
                @forelse($rtTerbaru as $rt)
                <a href="{{ route('rt.show', $rt) }}" class="flex items-center justify-between p-3 hover:bg-gray-50/80 transition text-xs group">
                    <div class="flex flex-col min-w-0 pr-3">
                        <span class="font-bold text-gray-900 group-hover:text-primary-700 truncate">{{ $rt->no_transaksi }}</span>
                        <span class="text-gray-500 text-[11px] truncate mt-0.5">
                            {{ ucwords(str_replace('_',' ',$rt->jenis)) }} · {{ $rt->gudangAsal?->nama ?? '-' }} &rarr; {{ $rt->gudangTujuan?->nama ?? '-' }}
                        </span>
                    </div>
                    <div class="shrink-0">
                        @php
                            $rtVariant = match($rt->status) {
                                'diajukan' => 'warning',
                                'disetujui' => 'primary',
                                'diproses' => 'info',
                                'dikirim' => 'gold',
                                'selesai' => 'success',
                                default => 'gray',
                            };
                        @endphp
                        <x-badge :variant="$rtVariant" :dot="true">
                            {{ ucwords(str_replace('_',' ',$rt->status)) }}
                        </x-badge>
                    </div>
                </a>
                @empty
                <div class="py-8 text-center text-gray-400 text-xs">
                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Tidak ada Request & Transfer yang memerlukan tindakan.
                </div>
                @endforelse
            </div>
        </x-card>
    </div>
</x-app-layout>

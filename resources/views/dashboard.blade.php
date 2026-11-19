<x-app-layout title="Beranda">
    <x-page-header title="Dashboard Operasional">
        <x-slot:actions>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-[#e8e8e8] dark:border-border px-2.5 py-1 text-[11px] font-medium text-text-muted tabular-nums">
                    <span class="size-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    Diperbarui {{ $generatedAt->format('H:i') }}
                </span>
                <span class="text-xs text-text-muted hidden sm:inline">User: <strong class="text-text-active font-medium">{{ auth()->user()->name }}</strong></span>
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

    {{-- Warna data dashboard: biru untuk bar/alur, merah dan amber hanya untuk masalah (lihat status KPI). --}}
    <div class="space-y-5 [--hs-data:#627EF2] dark:[--hs-data:#8EA2F7]">
        {{-- Tier 1: KPI strip --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($kpis as $kpi)
                <x-dashboard.kpi :kpi="$kpi" />
            @endforeach
        </div>

        {{-- Tier 2: alur / pipeline (hanya untuk lima role sistem) --}}
        <x-dashboard.pipeline :alur="$alur ?? null" />

        {{-- Tier 3: antrean tindakan --}}
        <x-dashboard.queue :queue="$actionQueue" />

        {{-- Tier 4: aliran aktivitas --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <x-dashboard.stream :stream="$streamLeft" icon='<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>' />
            <x-dashboard.stream :stream="$streamRight" icon='<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' />
        </div>

        {{-- Dashboard Barang Rusak & Defect Mutasi --}}
        @can('defect.view')
            @include('partials.defect-dashboard', ['formAction' => route('dashboard')])
        @endcan
    </div>
</x-app-layout>

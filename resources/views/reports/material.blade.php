<x-app-layout title="Pemakaian Bahan Baku">
    <x-page-header
        title="Laporan Pemakaian Bahan Baku"
        subtitle="Akumulasi konsumsi bahan baku dan kemasan yang telah dikeluarkan untuk proses manufaktur batch"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Pemakaian Bahan' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('reports.index') }}" variant="outline" size="sm">
                &larr; Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalItems = $rows->count();
        $topItem = $rows->sortByDesc('total_keluar')->first();
        $totalQty = $rows->sum('total_keluar');
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total SKU Bahan Terpakai</p>
            <p class="text-xl font-bold font-mono text-foreground mt-1">{{ number_format($totalItems) }} <span class="text-xs font-normal text-muted-foreground">Item</span></p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Akumulasi Total Keluar</p>
            <p class="text-xl font-bold font-mono text-foreground mt-1">{{ number_format($totalQty, 2, ',', '.') }} <span class="text-xs font-normal text-muted-foreground">Unit/Gram/Pcs</span></p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Konsumsi Tertinggi</p>
            <p class="text-sm font-semibold text-foreground truncate mt-1.5" title="{{ $topItem?->produk?->nama }}">
                {{ $topItem?->produk?->nama ?? '-' }}
            </p>
        </div>
    </div>

    <!-- Table Card -->
    <x-card title="Konsumsi Bahan Baku Manufaktur" subtitle="Data agregat pengeluaran bahan dari kartu stok dengan referensi batch produksi">
        <div class="-mx-4 -my-4 overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-muted/70 text-muted-foreground uppercase text-[11px] font-semibold tracking-wider border-b border-border">
                    <tr>
                        <th class="px-4 py-3 w-40">Kode SKU</th>
                        <th class="px-4 py-3">Nama Bahan / Material</th>
                        <th class="px-4 py-3 text-right">Total Keluar (Produksi)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($rows as $r)
                    <tr class="hover:bg-muted/40 transition-colors">
                        <td class="px-4 py-2.5 font-mono font-medium text-foreground">
                            {{ $r->produk->sku ?? '-' }}
                        </td>
                        <td class="px-4 py-2.5 font-medium text-foreground">
                            {{ $r->produk->nama ?? '-' }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono text-foreground font-semibold">
                            {{ number_format($r->total_keluar, 2, ',', '.') }}
                            <span class="text-muted-foreground font-normal text-[11px] ml-1">{{ $r->produk->satuan ?? '' }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-12 text-center text-xs text-muted-foreground">
                            <svg class="mx-auto h-8 w-8 text-muted-foreground/50 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            Belum ada catatan pemakaian bahan baku.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

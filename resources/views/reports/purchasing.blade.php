<x-app-layout title="Purchasing & Hutang Dagang">
    <x-page-header
        title="Laporan Purchasing & Hutang Dagang"
        subtitle="Rekapitulasi komitmen nilai purchase order, realisasi pembayaran, dan outstanding liabilitas hutang supplier"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Purchasing & Hutang' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('reports.index') }}" variant="outline" size="sm">
                &larr; Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalPoCount = $pos->count();
        $totalNilai = $pos->sum('total_nilai');
        $totalDibayar = $pos->sum('total_dibayar');
        $totalSisa = $pos->sum(fn($p) => ($p->total_nilai ?? 0) - ($p->total_dibayar ?? 0));
    @endphp

    <!-- Financial KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total Pesanan (PO)</p>
            <p class="text-xl font-bold font-mono text-foreground mt-1">{{ number_format($totalPoCount) }} <span class="text-xs font-normal text-muted-foreground">PO</span></p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total Nilai Komitmen</p>
            <p class="text-xl font-bold font-mono text-foreground mt-1">Rp {{ number_format($totalNilai, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total Realisasi Bayar</p>
            <p class="text-xl font-bold font-mono text-emerald-600 mt-1">Rp {{ number_format($totalDibayar, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Sisa Hutang Berjalan</p>
            <p class="text-xl font-bold font-mono {{ $totalSisa > 0 ? 'text-destructive' : 'text-muted-foreground' }} mt-1">
                Rp {{ number_format($totalSisa, 0, ',', '.') }}
            </p>
        </div>
    </div>

    <!-- Table Card -->
    <x-card title="Rekapitulasi Purchase Order & Liabilitas" subtitle="Daftar transaksi pengadaan bahan baku beserta status pembayaran termin">
        <div class="-mx-4 -my-4 overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-muted/70 text-muted-foreground uppercase text-[11px] font-semibold tracking-wider border-b border-border">
                    <tr>
                        <th class="px-4 py-3 w-40">No. PO</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3 text-center">Status PO</th>
                        <th class="px-4 py-3 text-right">Nilai Total</th>
                        <th class="px-4 py-3 text-right">Sudah Dibayar</th>
                        <th class="px-4 py-3 text-right">Sisa Tagihan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($pos as $po)
                    @php
                        $sisa = ($po->total_nilai ?? 0) - ($po->total_dibayar ?? 0);
                    @endphp
                    <tr class="hover:bg-muted/40 transition-colors">
                        <td class="px-4 py-2.5 font-mono font-medium text-foreground">
                            @can('purchasing.view')
                                <a href="{{ route('purchasing.show', $po) }}" class="hover:underline font-semibold">{{ $po->no_po }}</a>
                            @else
                                {{ $po->no_po }}
                            @endcan
                        </td>
                        <td class="px-4 py-2.5 font-medium text-foreground">
                            {{ $po->supplier->nama }}
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            @php
                                $badgeClass = match($po->status) {
                                    'draft' => 'bg-muted text-muted-foreground border-border',
                                    'menunggu_approval' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20',
                                    'approved' => 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20',
                                    'dikirim_ke_gudang' => 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20',
                                    'selesai' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
                                    'dibatalkan' => 'bg-destructive/10 text-destructive border-destructive/20',
                                    default => 'bg-muted text-muted-foreground border-border'
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $badgeClass }} uppercase tracking-wider">
                                {{ ucwords(str_replace('_', ' ', $po->status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono text-foreground font-medium">
                            Rp {{ number_format($po->total_nilai, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono text-emerald-600 font-medium">
                            Rp {{ number_format($po->total_dibayar ?? 0, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono font-semibold {{ $sisa > 0 ? 'text-destructive' : 'text-muted-foreground' }}">
                            Rp {{ number_format($sisa, 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-xs text-muted-foreground">
                            <svg class="mx-auto h-8 w-8 text-muted-foreground/50 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Belum ada riwayat purchase order.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

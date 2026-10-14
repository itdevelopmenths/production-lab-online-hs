<x-app-layout title="Laporan Produksi & Yield">
    <x-page-header
        title="Laporan Produksi & Yield"
        subtitle="Rekapitulasi batch manufaktur yang telah selesai, perbandingan output baik vs rusak, dan persentase yield"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Produksi' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('reports.index') }}" variant="outline" size="sm">
                &larr; Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalBatches = $batches->count();
        $totalBaik = $batches->sum('qty_baik');
        $totalRusak = $batches->sum('qty_rusak');
        $validYields = $batches->map(fn($b) => $b->yield())->filter(fn($y) => $y !== null);
        $avgYield = $validYields->isNotEmpty() ? $validYields->avg() : null;
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total Batch Selesai</p>
            <p class="text-xl font-bold font-mono text-foreground mt-1">{{ number_format($totalBatches) }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total Output Baik</p>
            <p class="text-xl font-bold font-mono text-emerald-600 mt-1">{{ number_format($totalBaik, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total Output Rusak</p>
            <p class="text-xl font-bold font-mono {{ $totalRusak > 0 ? 'text-destructive' : 'text-muted-foreground' }} mt-1">{{ number_format($totalRusak, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Rata-Rata Yield</p>
            <p class="text-xl font-bold font-mono text-foreground mt-1">
                {{ $avgYield !== null ? number_format($avgYield, 1) . '%' : '-' }}
            </p>
        </div>
    </div>

    <!-- Table Card -->
    <x-card title="Rekapitulasi Batch Produksi" subtitle="Menampilkan riwayat batch berstatus selesai">
        <div class="-mx-4 -my-4 overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-muted/70 text-muted-foreground uppercase text-[11px] font-semibold tracking-wider border-b border-border">
                    <tr>
                        <th class="px-4 py-3">No. Batch</th>
                        <th class="px-4 py-3">Produk Jadi</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3 text-right">Rencana</th>
                        <th class="px-4 py-3 text-right">Output Baik</th>
                        <th class="px-4 py-3 text-right">Output Rusak</th>
                        <th class="px-4 py-3 text-center">Capaian Yield</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($batches as $b)
                    @php
                        $yieldVal = $b->yield();
                    @endphp
                    <tr class="hover:bg-muted/40 transition-colors">
                        <td class="px-4 py-2.5 font-mono font-medium text-foreground">
                            @can('batch.view')
                                <a href="{{ route('batches.show', $b) }}" class="hover:underline font-semibold">{{ $b->no_batch }}</a>
                            @else
                                {{ $b->no_batch }}
                            @endcan
                        </td>
                        <td class="px-4 py-2.5 font-medium text-foreground">{{ $b->produk->nama }}</td>
                        <td class="px-4 py-2.5 text-muted-foreground">{{ $b->tanggal->format('d/m/Y') }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-foreground">{{ number_format($b->qty_rencana, 0, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-emerald-600 font-semibold">{{ number_format($b->qty_baik, 0, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right font-mono {{ $b->qty_rusak > 0 ? 'text-destructive font-semibold' : 'text-muted-foreground' }}">{{ number_format($b->qty_rusak, 0, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-center">
                            @if($yieldVal !== null)
                                @if($yieldVal >= 98)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                        {{ number_format($yieldVal, 1) }}%
                                    </span>
                                @elseif($yieldVal >= 90)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
                                        {{ number_format($yieldVal, 1) }}%
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-destructive/10 text-destructive border border-destructive/20">
                                        {{ number_format($yieldVal, 1) }}%
                                    </span>
                                @endif
                            @else
                                <span class="text-muted-foreground">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-xs text-muted-foreground">
                            <svg class="mx-auto h-8 w-8 text-muted-foreground/50 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Belum ada batch produksi yang berstatus selesai.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

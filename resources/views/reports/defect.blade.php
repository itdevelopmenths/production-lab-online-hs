<x-app-layout title="Laporan Defect & Kerusakan">
    <x-page-header
        title="Laporan Defect & Kerusakan Mutu"
        subtitle="Monitoring batch produksi yang mengalami unit rusak/cacat dan evaluasi tingkat defect rate"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Defect' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('reports.index') }}" variant="outline" size="sm">
                &larr; Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalBatches = $batches->count();
        $totalRusak = $batches->sum('qty_rusak');
        $validDefects = $batches->map(fn($b) => $b->defectRate())->filter(fn($d) => $d !== null);
        $avgDefect = $validDefects->isNotEmpty() ? $validDefects->avg() : null;
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Batch Mengalami Defect</p>
            <p class="text-xl font-bold font-mono text-foreground mt-1">{{ number_format($totalBatches) }} <span class="text-xs font-normal text-muted-foreground">Batch</span></p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total Unit Rusak / Reject</p>
            <p class="text-xl font-bold font-mono text-destructive mt-1">{{ number_format($totalRusak, 0, ',', '.') }} <span class="text-xs font-normal text-muted-foreground">Pcs</span></p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Rata-Rata Defect Rate</p>
            <p class="text-xl font-bold font-mono text-destructive mt-1">
                {{ $avgDefect !== null ? number_format($avgDefect, 1) . '%' : '0.0%' }}
            </p>
        </div>
    </div>

    <!-- Table Card -->
    <x-card title="Rincian Batch Produksi Terdampak Defect" subtitle="Daftar batch selesai yang mencatatkan kuantitas unit rusak lebih dari nol">
        <div class="-mx-4 -my-4 overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-muted/70 text-muted-foreground uppercase text-[11px] font-semibold tracking-wider border-b border-border">
                    <tr>
                        <th class="px-4 py-3 w-40">No. Batch</th>
                        <th class="px-4 py-3">Produk Jadi</th>
                        <th class="px-4 py-3 text-right">Output Baik</th>
                        <th class="px-4 py-3 text-right">Output Rusak</th>
                        <th class="px-4 py-3 text-center">Tingkat Defect</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($batches as $b)
                    @php
                        $defect = $b->defectRate();
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
                        <td class="px-4 py-2.5 text-right font-mono text-foreground">{{ number_format($b->qty_baik, 0, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-destructive font-semibold">{{ number_format($b->qty_rusak, 0, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-center">
                            @if($defect !== null)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-destructive/10 text-destructive border border-destructive/20">
                                    {{ number_format($defect, 1) }}%
                                </span>
                            @else
                                <span class="text-muted-foreground">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-xs text-muted-foreground">
                            <svg class="mx-auto h-8 w-8 text-emerald-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Kualitas sempurna! Tidak ditemukan batch produksi dengan unit rusak.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

<x-app-layout title="Variance Pemakaian (Stock Opname)">
    <x-page-header
        title="Variance Pemakaian (Stock Opname)"
        subtitle="Analisis deviasi perbandingan antara formula standar teoritis BOM vs realisasi aktual di lab"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Variance Opname' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('reports.index') }}" variant="outline" size="sm">
                &larr; Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalOpname = $opname->count();
        $borosCount = $opname->filter(fn($o) => $o->variance() > 0)->count();
        $hematCount = $opname->filter(fn($o) => $o->variance() <= 0)->count();
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total Pemeriksaan Opname</p>
            <p class="text-xl font-bold font-mono text-foreground mt-1">{{ number_format($totalOpname) }} <span class="text-xs font-normal text-muted-foreground">Record</span></p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Selisih Lebih (Pemborosan)</p>
            <p class="text-xl font-bold font-mono {{ $borosCount > 0 ? 'text-destructive' : 'text-muted-foreground' }} mt-1">
                {{ number_format($borosCount) }} <span class="text-xs font-normal text-muted-foreground">Batch</span>
            </p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Sesuai / Efisien (&le; 0)</p>
            <p class="text-xl font-bold font-mono text-emerald-600 mt-1">
                {{ number_format($hematCount) }} <span class="text-xs font-normal text-muted-foreground">Batch</span>
            </p>
        </div>
    </div>

    <!-- Table Card -->
    <x-card title="Data Deviasi Pemakaian Bahan Baku" subtitle="Daftar rekapitulasi audit opname bahan dari batch produksi">
        <div class="-mx-4 -my-4 overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-muted/70 text-muted-foreground uppercase text-[11px] font-semibold tracking-wider border-b border-border">
                    <tr>
                        <th class="px-4 py-3 w-40">No. Batch</th>
                        <th class="px-4 py-3">Nama Bahan / Material</th>
                        <th class="px-4 py-3 text-right">Teoritis (BOM)</th>
                        <th class="px-4 py-3 text-right">Realisasi Aktual</th>
                        <th class="px-4 py-3 text-right">Deviasi Variance</th>
                        <th class="px-4 py-3">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($opname as $o)
                    @php
                        $var = $o->variance();
                    @endphp
                    <tr class="hover:bg-muted/40 transition-colors">
                        <td class="px-4 py-2.5 font-mono font-medium text-foreground">
                            {{ $o->batch->no_batch ?? '-' }}
                        </td>
                        <td class="px-4 py-2.5 font-medium text-foreground">
                            {{ $o->bahan->nama ?? '-' }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono text-muted-foreground">
                            {{ number_format($o->pemakaian_teoritis, 2, ',', '.') }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono text-foreground font-medium">
                            {{ number_format($o->pemakaian_aktual, 2, ',', '.') }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono font-semibold">
                            @if($var > 0)
                                <span class="text-destructive font-semibold">+{{ number_format($var, 2, ',', '.') }}</span>
                            @elseif($var < 0)
                                <span class="text-emerald-600 font-semibold">{{ number_format($var, 2, ',', '.') }}</span>
                            @else
                                <span class="text-muted-foreground">0,00</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-muted-foreground text-xs">
                            {{ $o->keterangan ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-xs text-muted-foreground">
                            <svg class="mx-auto h-8 w-8 text-muted-foreground/50 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Belum ada catatan audit variance opname.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

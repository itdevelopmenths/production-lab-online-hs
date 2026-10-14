<x-app-layout title="Pergerakan Stok Fulfillment">
    <x-page-header
        title="Pergerakan Stok Fulfillment"
        subtitle="Log audit mutasi dan sirkulasi persediaan produk jadi pada fasilitas gudang fulfillment pusat dan cabang"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Pergerakan Fulfillment' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('reports.index') }}" variant="outline" size="sm">
                &larr; Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $totalLogs = $rows->count();
        $totalIn = $rows->where('tipe', 'in')->sum('qty');
        $totalOut = $rows->where('tipe', 'out')->sum('qty');
    @endphp

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total Transaksi Mutasi</p>
            <p class="text-xl font-bold font-mono text-foreground mt-1">{{ number_format($totalLogs) }} <span class="text-xs font-normal text-muted-foreground">Log</span></p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total Masuk (IN)</p>
            <p class="text-xl font-bold font-mono text-emerald-600 mt-1">+{{ number_format($totalIn, 0, ',', '.') }} <span class="text-xs font-normal text-muted-foreground">Pcs</span></p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4 shadow-xs">
            <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Total Keluar (OUT)</p>
            <p class="text-xl font-bold font-mono text-foreground mt-1">-{{ number_format($totalOut, 0, ',', '.') }} <span class="text-xs font-normal text-muted-foreground">Pcs</span></p>
        </div>
    </div>

    <!-- Table Card -->
    <x-card title="Riwayat Mutasi Stok Fulfillment" subtitle="Data pergerakan kartu stok pada gudang bertipe fulfillment">
        <div class="-mx-4 -my-4 overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-muted/70 text-muted-foreground uppercase text-[11px] font-semibold tracking-wider border-b border-border">
                    <tr>
                        <th class="px-4 py-3 w-32">Tanggal</th>
                        <th class="px-4 py-3">Lokasi Gudang</th>
                        <th class="px-4 py-3">Produk Jadi</th>
                        <th class="px-4 py-3 text-center w-24">Tipe</th>
                        <th class="px-4 py-3 text-right">Kuantitas</th>
                        <th class="px-4 py-3 text-right">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($rows as $r)
                    @php
                        $isMasuk = strtolower($r->tipe) === 'in';
                    @endphp
                    <tr class="hover:bg-muted/40 transition-colors">
                        <td class="px-4 py-2.5 font-mono text-muted-foreground">
                            {{ $r->tanggal->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-2.5 font-medium text-foreground">
                            {{ $r->gudang->nama ?? '-' }}
                        </td>
                        <td class="px-4 py-2.5 text-foreground">
                            {{ $r->produk->nama ?? '-' }}
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            @if($isMasuk)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                    MASUK
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
                                    KELUAR
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono font-medium {{ $isMasuk ? 'text-emerald-600' : 'text-amber-600' }}">
                            {{ $isMasuk ? '+' : '-' }}{{ number_format($r->qty, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono text-foreground font-semibold">
                            {{ number_format($r->saldo_setelah, 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-xs text-muted-foreground">
                            <svg class="mx-auto h-8 w-8 text-muted-foreground/50 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            Belum ada catatan mutasi stok fulfillment.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

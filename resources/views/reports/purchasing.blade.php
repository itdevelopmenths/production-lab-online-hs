<x-app-layout title="Purchasing & Hutang Dagang">
    <x-page-header
        title="Laporan Purchasing & Hutang Dagang"
        subtitle="Rekapitulasi komitmen nilai purchase order, realisasi pembayaran, dan outstanding liabilitas hutang supplier"
        :breadcrumbs="['Laporan' => route('reports.index'), 'Purchasing & Hutang' => null]"
        :backUrl="route('reports.index')"
    />

    @php
        $activePos = $pos->where('status', '!=', 'dibatalkan');
        $totalPoCount = $pos->count();
        $canceledPoCount = $pos->where('status', 'dibatalkan')->count();
        $totalNilai = $activePos->sum(fn($p) => $p->totalNilai());
        $totalDibayar = $activePos->sum(fn($p) => $p->totalDibayar());
        $totalSisa = $activePos->sum(fn($p) => $p->sisaTagihan());
    @endphp

    <!-- Financial KPI Summary Cards (Excluding Batal PO) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-box
            title="Total Pesanan (PO)"
            :value="number_format($totalPoCount)"
            :subtitle="$canceledPoCount > 0 ? 'PO (' . $canceledPoCount . ' dibatalkan)' : 'PO'"
            iconColor="gray"
        />
        <x-stat-box
            title="Total Nilai Komitmen"
            :value="$canSeePrice ? 'Rp ' . number_format($totalNilai, 0, ',', '.') : 'Rp ••••••'"
            subtitle="Kecuali PO Dibatalkan"
            iconColor="blue"
        />
        <x-stat-box
            title="Total Realisasi Bayar"
            :value="$canSeePrice ? 'Rp ' . number_format($totalDibayar, 0, ',', '.') : 'Rp ••••••'"
            subtitle="Realisasi Termin Kas"
            iconColor="emerald"
        />
        <x-stat-box
            title="Sisa Hutang Berjalan"
            :value="$canSeePrice ? 'Rp ' . number_format($totalSisa, 0, ',', '.') : 'Rp ••••••'"
            subtitle="Liabilitas Terbuka"
            :iconColor="$totalSisa > 0 ? 'rose' : 'gray'"
        />
    </div>

    <!-- Table Card -->
    <x-card class="tbl-modern" title="Rekapitulasi Purchase Order & Liabilitas" subtitle="Daftar transaksi pengadaan bahan baku beserta status tahapan alur 9-stage dan pembayaran termin" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Rekapitulasi Purchase Order dan Liabilitas Hutang" class="overflow-x-auto focus:outline-none focus:ring-1 focus:ring-[#111111]/20 dark:focus:ring-white/20">
            <table class="w-full text-sm text-left border-collapse min-w-[850px]">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[13px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-3 w-36">No. PO</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3 text-center">Status PO</th>
                        <th class="px-4 py-3 text-center">Stage Aktif</th>
                        <th class="px-4 py-3 text-right">Nilai Total</th>
                        <th class="px-4 py-3 text-right">Sudah Dibayar</th>
                        <th class="px-4 py-3 text-right">Sisa Tagihan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                    @forelse($pos as $po)
                    @php
                        $poNilai = $po->totalNilai();
                        $poDibayar = $po->totalDibayar();
                        $poSisa = $po->sisaTagihan();
                        $stageLabel = $po->stage_aktif ? (\App\Models\PoStage::STAGE_LABELS[$po->stage_aktif] ?? ucwords(str_replace('_', ' ', $po->stage_aktif))) : null;
                        $statusVariant = match($po->status) {
                            'draft' => 'neutral',
                            'menunggu_approval' => 'warning',
                            'approved' => 'info',
                            'dikirim_ke_gudang' => 'purple',
                            'selesai' => 'success',
                            'dibatalkan' => 'danger',
                            default => 'neutral',
                        };
                    @endphp
                    <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors {{ $po->status === 'dibatalkan' ? 'opacity-60 bg-gray-50 dark:bg-gray-900/40' : '' }}">
                        <td class="px-4 py-3 tabular-nums font-medium text-[#101828] dark:text-white">
                            @can('purchasing.view')
                                <a href="{{ route('purchasing.show', $po) }}" class="underline decoration-gray-400 hover:decoration-current">{{ $po->no_po }}</a>
                            @else
                                {{ $po->no_po }}
                            @endcan
                        </td>
                        <td class="px-4 py-3 font-medium text-[#101828] dark:text-white">
                            {{ $po->supplier?->nama ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <x-badge :variant="$statusVariant" size="xs" :pill="true">{{ ucwords(str_replace('_', ' ', $po->status)) }}</x-badge>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($stageLabel)
                                <x-badge variant="info" size="xs" :pill="true">{{ $stageLabel }}</x-badge>
                            @else
                                <span class="text-[#667085] dark:text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-[#101828] dark:text-white font-medium">
                            @if($canSeePrice)
                                Rp {{ number_format($poNilai, 0, ',', '.') }}
                            @else
                                <span class="text-[#667085] dark:text-gray-400 tabular-nums">Rp ••••••</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-foreground dark:text-foreground font-medium">
                            @if($canSeePrice)
                                Rp {{ number_format($poDibayar, 0, ',', '.') }}
                            @else
                                <span class="text-[#667085] dark:text-gray-400 tabular-nums">Rp ••••••</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium {{ $poSisa > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-[#667085] dark:text-gray-400' }}">
                            @if($canSeePrice)
                                Rp {{ number_format($poSisa, 0, ',', '.') }}
                            @else
                                <span class="text-[#667085] dark:text-gray-400 tabular-nums">Rp ••••••</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-xs text-[#667085] dark:text-gray-400">
                            <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Belum ada riwayat purchase order.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>

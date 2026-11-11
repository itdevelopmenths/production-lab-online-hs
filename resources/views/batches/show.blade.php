<x-app-layout title="Detail Batch {{ $batch->no_batch }}">
    <!-- Page Header & Action Tools -->
    <x-page-header
        :title="$batch->no_batch"
        :subtitle="($batch->produk ? $batch->produk->nama . ' (' . $batch->produk->sku . ')' : ($batch->outputs->count() > 0 ? $batch->outputs->count() . ' Varian Produk Jadi' : 'Multi-Produk')) . ' · Tanggal ' . $batch->tanggal->format('d M Y') . ($batch->creator ? ' · Oleh ' . $batch->creator->name : '')"
        :breadcrumbs="[
            'Produksi' => route('batches.index'),
            'Batch' => route('batches.index'),
            $batch->no_batch => null,
        ]"
        :backUrl="route('batches.index')"
        backSize="xs"
    >
        <x-slot:actions>
            @php
                $statusVariant = match($batch->status) {
                    'rencana' => 'warning',
                    'release' => 'info',
                    'selesai' => 'success',
                    'dibatalkan' => 'danger',
                    default => 'gray',
                };
            @endphp
            <x-badge :variant="$statusVariant" :dot="true" size="sm">
                {{ $batch->status === 'release' ? 'Release (Berjalan)' : ucfirst($batch->status) }}
            </x-badge>

            @can('batch.release')
            @if($batch->status === 'rencana')
            <form method="POST" action="{{ route('batches.release', $batch) }}" class="inline">
                @csrf
                <x-button type="submit" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </x-slot:icon>
                    Release & Issue
                </x-button>
            </form>
            @endif
            @endcan

            @if($batch->status === 'selesai')
                @if($batch->transferKirim)
                <x-button href="{{ route('transfers.show', $batch->transferKirim) }}" variant="secondary" size="xs">
                    <x-slot:icon>
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </x-slot:icon>
                    Transfer: {{ $batch->transferKirim->no_transaksi }}
                </x-button>
                @else
                    @can('transfer.produk_jadi.create')
                    <form method="POST" action="{{ route('batches.kirim', $batch) }}" class="inline">
                        @csrf
                        <x-button type="submit" variant="primary" size="xs">
                            <x-slot:icon>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3L9 8m-5 5h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293h3.172a1 1 0 00.707-.293l2.414-2.414a1 1 0 01.707-.293H20"/></svg>
                            </x-slot:icon>
                            Kirim ke Fulfillment
                        </x-button>
                    </form>
                    <a href="{{ route('transfers.create', ['jenis' => 'kirim_produk_jadi', 'batch_id' => $batch->id]) }}" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-300">
                        Buat Transfer Produk Jadi
                    </a>
                    @endcan
                @endif
            @endif
        </x-slot:actions>
    </x-page-header>

    <!-- AdminLTE 4 KPI Small Boxes Ribbon (4 Cards) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mb-5">
        <x-stat-box
            title="Target Rencana"
            :value="\App\Helpers\NumberHelper::formatQty($batch->qty_rencana)"
            :subtitle="($batch->produk?->satuan ?? 'Unit') . ' · Target kuantitas batch'"
            variant="primary"
        />

        <x-stat-box
            title="Gudang Operasional"
            :value="$batch->gudangOperasional?->nama ?? 'Belum Ditentukan'"
            subtitle="Sumber penarikan bahan"
            variant="default"
        />

        <x-stat-box
            title="Gudang Tujuan"
            :value="$batch->gudangTujuan?->nama ?? 'Fulfillment Pusat'"
            subtitle="Tujuan produk jadi"
            variant="default"
        />

        <x-stat-box
            title="Kesiapan Bahan"
            :value="$readinessSummary['value']"
            :subtitle="$readinessSummary['subtitle']"
            :variant="$readinessSummary['variant']"
        />
    </div>

    <!-- Sleek Inline Alert Bar (Jika bahan fisik kurang pada status rencana) -->
    @if($batch->status === 'rencana' && $hasDeficitFisik)
    <div class="mb-5">
        <x-alert type="danger" title="Peringatan Dini:">
            Saldo fisik bahan baku di {{ $batch->gudangOperasional?->nama ?? 'Gudang Operasional' }} belum mencukupi untuk batch ini. Silakan gunakan tombol <strong>Request Bahan yang Kurang</strong> pada panel Kontrol Batch.
        </x-alert>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Kolom Kiri: Tabel Alokasi Bahan & Modul Produksi -->
        <div class="lg:col-span-2 space-y-5">
            <!-- Tabel Produk Jadi Luaran (Multi-Output Batch) -->
            @if($batch->outputs->isNotEmpty())
            <x-card title="Produk Jadi Luaran Batch" subtitle="Daftar varian dan kuantitas produk jadi yang dihasilkan oleh batch ini" :noPadding="true">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr>
                                <th class="py-2.5 px-3.5">Produk Jadi</th>
                                <th class="py-2.5 px-3 text-right">Target Rencana</th>
                                <th class="py-2.5 px-3 text-right">Hasil Baik</th>
                                <th class="py-2.5 px-3 text-right">Hasil Rusak</th>
                                <th class="py-2.5 px-3 text-center">Yield QC</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($batch->outputs as $out)
                            <tr class="hover:bg-muted/40 transition">
                                <td class="py-2.5 px-3.5">
                                    <div class="font-bold text-foreground">{{ $out->produk?->nama }}</div>
                                    <div class="font-mono text-[11px] text-muted-foreground">{{ $out->produk?->sku }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold text-foreground">
                                    {{ \App\Helpers\NumberHelper::formatQty($out->qty_rencana) }} <span class="text-muted-foreground font-normal">{{ $out->produk?->satuan }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold text-emerald-700 dark:text-emerald-400">
                                    {{ $out->qty_baik !== null ? \App\Helpers\NumberHelper::formatQty($out->qty_baik) . ' ' . $out->produk?->satuan : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold text-rose-700 dark:text-rose-400">
                                    {{ $out->qty_rusak !== null ? \App\Helpers\NumberHelper::formatQty($out->qty_rusak) . ' ' . $out->produk?->satuan : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-mono font-bold">
                                    {{ $out->yield() !== null ? number_format($out->yield(), 1) . '%' : '-' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
            @endif

            <!-- Tabel Alokasi Bahan Baku (BOM Explode) -->
            @php
                $isBatchSelesai = $batch->status === 'selesai';
                $alokasiSubtitle = $isBatchSelesai
                    ? 'Realisasi bahan baku yang telah dilepas saat produksi & ringkasan hasil opname.'
                    : 'Formula resep × target kuantitas. Stok fisik dipotong saat Release & Issue.';
            @endphp
            <x-card title="Alokasi Bahan Baku (BOM Explode)" :subtitle="$alokasiSubtitle" :noPadding="true">
                <x-slot:tools>
                    <span class="text-xs text-muted-foreground font-mono font-medium">{{ $batch->alokasi->count() }} komponen resep</span>
                </x-slot:tools>

                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Tabel Alokasi Bahan Baku">
                    <table class="w-full text-left text-xs">
                        <thead>
                            @if($isBatchSelesai)
                            <tr>
                                <th scope="col" class="py-2.5 px-3.5">Bahan Baku</th>
                                <th scope="col" class="py-2.5 px-3 text-right whitespace-nowrap">Di-Issue (BOM)</th>
                                <th scope="col" class="py-2.5 px-3 text-right whitespace-nowrap">Saldo Pasca-Issue</th>
                                <th scope="col" class="py-2.5 px-3 whitespace-nowrap">Waktu Dilepas</th>
                                <th scope="col" class="py-2.5 px-3 text-right whitespace-nowrap">Aktual Opname</th>
                                <th scope="col" class="py-2.5 px-3 text-center">Status</th>
                                <th scope="col" class="py-2.5 px-3.5 text-center">Aksi</th>
                            </tr>
                            @else
                            <tr>
                                <th scope="col" class="py-2.5 px-3.5">Bahan Baku</th>
                                <th scope="col" class="py-2.5 px-3 text-right">Kebutuhan</th>
                                <th scope="col" class="py-2.5 px-3 text-right">Stok Fisik Ops</th>
                                <th scope="col" class="py-2.5 px-3 text-right">Kolom Rencana</th>
                                <th scope="col" class="py-2.5 px-3 text-right whitespace-nowrap" title="Stok fisik di {{ $gudangPusat?->nama ?? 'Gudang Bahan Baku Pusat' }}">Stok GD BB Pusat</th>
                                <th scope="col" class="py-2.5 px-3 text-center">Status</th>
                                <th scope="col" class="py-2.5 px-3.5 text-center">Aksi</th>
                            </tr>
                            @endif
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($batch->alokasi as $a)
                            @php
                                $w = $earlyWarnings[$a->id] ?? null;
                                $audit = $selesaiAudits[$a->id] ?? null;
                                $satuan = $a->bahan->satuan ?? '';
                            @endphp
                            <tr class="hover:bg-gray-50/70 transition">
                                <td class="py-2.5 px-3.5">
                                    <div class="font-bold text-gray-900">{{ $a->bahan->nama }}</div>
                                    <div class="font-mono text-[11px] text-gray-400">{{ $a->bahan->sku }}</div>
                                </td>
                                @if($isBatchSelesai)
                                <td class="py-2.5 px-3 text-right font-semibold text-gray-900 whitespace-nowrap font-mono tabular-nums">
                                    {{ \App\Helpers\NumberHelper::formatQty($audit['qty_issued'] ?? $a->qty_dialokasikan) }}
                                    <span class="text-gray-400 font-sans font-normal">{{ $satuan }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium text-gray-700 whitespace-nowrap font-mono tabular-nums">
                                    @if($audit && $audit['saldo_pasca_issue'] !== null)
                                        {{ \App\Helpers\NumberHelper::formatQty($audit['saldo_pasca_issue']) }}
                                        <span class="text-gray-400 font-sans font-normal">{{ $satuan }}</span>
                                    @else
                                        <span class="text-gray-400 font-sans">-</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-gray-600 whitespace-nowrap font-mono text-[11px]">
                                    {{ $audit && $audit['released_at'] ? $audit['released_at']->format('d/m/Y H:i') : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                    @if($audit && $audit['has_opname'])
                                        <div class="font-semibold text-gray-900 font-mono tabular-nums">
                                            {{ \App\Helpers\NumberHelper::formatQty($audit['opname_aktual']) }}
                                            <span class="text-gray-400 font-sans font-normal">{{ $satuan }}</span>
                                        </div>
                                        @php
                                            $variance = (float) ($audit['opname_variance'] ?? 0);
                                        @endphp
                                        @if(abs($variance) < 0.0001)
                                            <span class="text-[10px] font-semibold text-emerald-700">Sesuai BOM</span>
                                        @elseif($variance > 0)
                                            <span class="text-[10px] font-semibold text-amber-700 font-mono">+{{ \App\Helpers\NumberHelper::formatQty($variance) }} {{ $satuan }}</span>
                                        @else
                                            <span class="text-[10px] font-semibold text-sky-700 font-mono">-{{ \App\Helpers\NumberHelper::formatQty(abs($variance)) }} {{ $satuan }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted-foreground">Belum opname</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    @if($a->status === 'dilepas')
                                        <x-badge variant="success" size="sm">Dilepas · Selesai</x-badge>
                                    @else
                                        <x-badge variant="gray">{{ ucfirst($a->status) }}</x-badge>
                                    @endif
                                </td>
                                @else
                                <td class="py-2.5 px-3 text-right font-semibold text-gray-900">
                                    {{ \App\Helpers\NumberHelper::formatQty($a->qty_dialokasikan) }}
                                    <span class="text-gray-400 font-normal">{{ $satuan }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium text-gray-700">
                                    @if($w)
                                        {{ \App\Helpers\NumberHelper::formatQty($w['stok_fisik_op']) }}
                                        <span class="text-gray-400 font-normal">{{ $satuan }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium {{ ($w['stok_rencana'] ?? 0) < 0 ? 'text-rose-600 font-bold' : 'text-gray-700' }}">
                                    @if($w)
                                        {{ \App\Helpers\NumberHelper::formatQty($w['stok_rencana']) }}
                                        <span class="text-gray-400 font-normal">{{ $satuan }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right text-gray-500">
                                    @if($w)
                                        {{ \App\Helpers\NumberHelper::formatQty($w['stok_pusat']) }}
                                        <span class="text-gray-400 font-normal">{{ $satuan }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    @if($batch->status === 'rencana')
                                        @if($w && $w['is_cukup_fisik'])
                                            <x-badge variant="success" size="xs">Cukup</x-badge>
                                        @else
                                            <x-badge variant="danger" size="xs">
                                                Kurang {{ \App\Helpers\NumberHelper::formatQty($w['kurang_fisik'] ?? 0) }}
                                            </x-badge>
                                        @endif
                                    @else
                                        <x-badge variant="gray">
                                            {{ ucfirst($a->status) }}
                                        </x-badge>
                                    @endif
                                </td>
                                @endif
                                <td class="py-2.5 px-3.5 text-center whitespace-nowrap">
                                    <a href="{{ route('stok.ledger', $a->bahan_id) }}" class="text-foreground hover:underline text-xs font-semibold">
                                        Kartu Stok &rarr;
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-gray-400">
                                    Resep BOM untuk produk ini belum dikonfigurasi.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <!-- Rekap Pemenuhan Bahan per Batch (FR-PRD-05, TC-41) -->
            @if($showProgresPemenuhan ?? !empty($rekapBahan))
            <x-card title="Progres Pemenuhan Bahan Baku" subtitle="Perbandingan Kebutuhan BOM vs Total Diminta (Request) vs Total Diterima (Transfer) untuk batch ini." :noPadding="true">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-border">
                                <th class="py-2.5 px-3.5">Bahan Baku</th>
                                <th class="py-2.5 px-3 text-right">Kebutuhan BOM</th>
                                <th class="py-2.5 px-3 text-right">Total Diminta</th>
                                <th class="py-2.5 px-3 text-right">Total Diterima</th>
                                <th class="py-2.5 px-3 text-right">Sisa Kekurangan</th>
                                <th class="py-2.5 px-3 text-center">Status Pemenuhan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($rekapBahan as $rb)
                            @php
                                $sisa = max(0, $rb['kebutuhan'] - $rb['diterima']);
                                $persen = $rb['kebutuhan'] > 0 ? min(100, round(($rb['diterima'] / $rb['kebutuhan']) * 100)) : 100;
                            @endphp
                            <tr class="hover:bg-gray-50/70 transition">
                                <td class="py-2.5 px-3.5">
                                    <div class="font-bold text-gray-900">{{ $rb['nama'] }}</div>
                                    <div class="font-mono text-[11px] text-gray-400">{{ $rb['sku'] }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-right font-semibold text-gray-900">
                                    {{ \App\Helpers\NumberHelper::formatQty($rb['kebutuhan']) }} {{ $rb['satuan'] }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium text-blue-700">
                                    {{ \App\Helpers\NumberHelper::formatQty($rb['diminta']) }} {{ $rb['satuan'] }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-bold text-emerald-700">
                                    {{ \App\Helpers\NumberHelper::formatQty($rb['diterima']) }} {{ $rb['satuan'] }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium {{ $sisa > 0 ? 'text-amber-700' : 'text-gray-400' }}">
                                    {{ $sisa > 0 ? \App\Helpers\NumberHelper::formatQty($sisa) . ' ' . $rb['satuan'] : 'Terpenuhi' }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    @if($rb['diterima'] >= $rb['kebutuhan'])
                                        <x-badge variant="success" size="xs">Lengkap (100%)</x-badge>
                                    @elseif($rb['diminta'] >= $rb['kebutuhan'])
                                        <x-badge variant="primary" size="xs">Dalam Pengiriman ({{ $persen }}%)</x-badge>
                                    @else
                                        <x-badge variant="warning" size="xs">Kurang Permintaan ({{ $persen }}%)</x-badge>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
            @endif

            <!-- Card: Dokumen Request & Transfer Terkait -->
            @php
                $hasRelatedDocs = ($batch->stockRequests && $batch->stockRequests->isNotEmpty()) ||
                                  ($batch->modernTransfers && $batch->modernTransfers->isNotEmpty());
            @endphp
            @if($hasRelatedDocs)
            <x-card title="Dokumen Request & Transfer Terkait" subtitle="Daftar permintaan bahan dan mutasi pengiriman yang terhubung dengan batch ini" :noPadding="true">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-muted/70 border-b border-border text-muted-foreground font-semibold uppercase text-[11px] tracking-wider">
                                <th class="py-2.5 px-3.5">Jenis Dokumen</th>
                                <th class="py-2.5 px-3">Nomor Transaksi</th>
                                <th class="py-2.5 px-3">Gudang Asal</th>
                                <th class="py-2.5 px-3">Gudang Tujuan</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($batch->stockRequests as $sr)
                            <tr class="hover:bg-muted/30 transition">
                                <td class="py-2 px-3.5 font-medium text-amber-700">Request Permintaan</td>
                                <td class="py-2 px-3 font-mono font-bold text-foreground">{{ $sr->no_request }}</td>
                                <td class="py-2 px-3 text-muted-foreground">{{ $sr->gudangPemohon?->nama ?? '-' }}</td>
                                <td class="py-2 px-3 text-muted-foreground">{{ $sr->gudangTujuan?->nama ?? '-' }}</td>
                                <td class="py-2 px-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                        {{ ucfirst($sr->status) }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <a href="{{ route('requests.show', $sr) }}" class="text-primary hover:underline font-semibold text-xs">Detail &rarr;</a>
                                </td>
                            </tr>
                            @endforeach

                            @foreach($batch->modernTransfers as $trf)
                            <tr class="hover:bg-muted/30 transition">
                                <td class="py-2 px-3.5 font-medium text-blue-700">Transfer Modern</td>
                                <td class="py-2 px-3 font-mono font-bold text-foreground">{{ $trf->no_transaksi }}</td>
                                <td class="py-2 px-3 text-muted-foreground">{{ $trf->gudangAsal?->nama ?? '-' }}</td>
                                <td class="py-2 px-3 text-muted-foreground">{{ $trf->gudangTujuan?->nama ?? '-' }}</td>
                                <td class="py-2 px-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                                        {{ ucfirst($trf->status) }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <a href="{{ route('transfers.show', $trf) }}" class="text-primary hover:underline font-semibold text-xs">Detail &rarr;</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
            @endif

            <!-- Form Selesaikan Batch (Khusus status release) -->
            @can('batch.complete')
            @if($batch->status === 'release')
            <x-card title="Selesaikan Batch Produksi" subtitle="Input hasil QC produk jadi. Kuantitas baik otomatis masuk ke saldo stok gudang." variant="success" :noPadding="true">
                <form method="POST" action="{{ route('batches.complete', $batch) }}" class="p-3.5">
                    @csrf
                    <script>
                        function batchOutputSync(target, defaultBaik, defaultRusak) {
                            return {
                                target: parseInt(target, 10) || 0,
                                baik: parseInt(defaultBaik, 10) || 0,
                                rusak: parseInt(defaultRusak, 10) || 0,
                                syncFromRusak() {
                                    let r = parseInt(this.rusak, 10);
                                    if (isNaN(r) || r < 0) r = 0;
                                    if (r > this.target) r = this.target;
                                    this.rusak = r;
                                    this.baik = this.target - r;
                                },
                                syncFromBaik() {
                                    let b = parseInt(this.baik, 10);
                                    if (isNaN(b) || b < 0) b = 0;
                                    if (b > this.target) b = this.target;
                                    this.baik = b;
                                    this.rusak = this.target - b;
                                }
                            };
                        }
                    </script>
                    @if($batch->outputs->count() > 1)
                    <table class="w-full text-left text-xs mb-3">
                        <thead class="bg-gray-100 border-b border-gray-200 text-gray-700 font-semibold">
                            <tr>
                                <th class="py-2 px-3">Produk Jadi</th>
                                <th class="py-2 px-3 text-right">Target</th>
                                <th class="py-2 px-3 text-right w-36">Qty Baik <span class="text-rose-500">*</span></th>
                                <th class="py-2 px-3 text-right w-36">Qty Rusak (Defect)</th>
                                <th class="py-2 px-3 text-center w-28">Status Imbang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($batch->outputs as $index => $out)
                            @php
                                $targetOut = (int) round((float) $out->qty_rencana);
                                $defaultBaikOut = old("outputs.{$index}.qty_baik", $targetOut);
                                $defaultRusakOut = old("outputs.{$index}.qty_rusak", 0);
                            @endphp
                            <tr x-data="batchOutputSync({{ $targetOut }}, {{ (int) $defaultBaikOut }}, {{ (int) $defaultRusakOut }})">
                                <td class="py-2 px-3 font-medium text-gray-800">
                                    <input type="hidden" name="outputs[{{ $index }}][id]" value="{{ $out->id }}">
                                    <div class="font-bold">{{ $out->produk?->nama }}</div>
                                    <div class="font-mono text-[10px] text-gray-400">{{ $out->produk?->sku }}</div>
                                </td>
                                <td class="py-2 px-3 text-right font-mono text-gray-600 whitespace-nowrap">
                                    {{ \App\Helpers\NumberHelper::formatQty($out->qty_rencana) }} {{ $out->produk?->satuan }}
                                </td>
                                <td class="py-2 px-3 text-right">
                                    <input
                                        type="number"
                                        step="1"
                                        min="0"
                                        max="{{ $targetOut }}"
                                        name="outputs[{{ $index }}][qty_baik]"
                                        x-model.number="baik"
                                        @input="syncFromBaik()"
                                        onkeydown="if(event.key === '.' || event.key === ',') event.preventDefault()"
                                        class="w-full text-right rounded-sm border-gray-300 text-xs py-1 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono"
                                        required
                                    >
                                </td>
                                <td class="py-2 px-3 text-right">
                                    <input
                                        type="number"
                                        step="1"
                                        min="0"
                                        max="{{ $targetOut }}"
                                        name="outputs[{{ $index }}][qty_rusak]"
                                        x-model.number="rusak"
                                        @input="syncFromRusak()"
                                        onkeydown="if(event.key === '.' || event.key === ',') event.preventDefault()"
                                        class="w-full text-right rounded-sm border-gray-300 text-xs py-1 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono"
                                        required
                                    >
                                </td>
                                <td class="py-2 px-3 text-center whitespace-nowrap">
                                    <span x-show="(baik + rusak) === target" class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-xs border border-emerald-200 font-mono">
                                        &check; Pas (<span x-text="target"></span>)
                                    </span>
                                    <span x-show="(baik + rusak) !== target" class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-xs border border-rose-200 font-mono" x-cloak>
                                        &times; <span x-text="baik + rusak"></span>/<span x-text="target"></span>
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    @php
                        $targetRencana = (int) round((float) $batch->qty_rencana);
                        $defaultBaik = old('qty_baik', old('outputs.0.qty_baik', $targetRencana));
                        $defaultRusak = old('qty_rusak', old('outputs.0.qty_rusak', 0));
                    @endphp
                    <div x-data="batchOutputSync({{ $targetRencana }}, {{ (int) $defaultBaik }}, {{ (int) $defaultRusak }})">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end mb-1">
                            @if($batch->outputs->isNotEmpty())
                                <input type="hidden" name="outputs[0][id]" value="{{ $batch->outputs->first()->id }}">
                            @endif
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">
                                    Qty Baik ({{ $batch->produk?->satuan ?? 'Pcs' }}) <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    step="1"
                                    min="0"
                                    max="{{ $targetRencana }}"
                                    name="{{ $batch->outputs->isNotEmpty() ? 'outputs[0][qty_baik]' : 'qty_baik' }}"
                                    x-model.number="baik"
                                    @input="syncFromBaik()"
                                    onkeydown="if(event.key === '.' || event.key === ',') event.preventDefault()"
                                    class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono text-right"
                                    required
                                >
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">
                                    Qty Rusak ({{ $batch->produk?->satuan ?? 'Pcs' }})
                                </label>
                                <input
                                    type="number"
                                    step="1"
                                    min="0"
                                    max="{{ $targetRencana }}"
                                    name="{{ $batch->outputs->isNotEmpty() ? 'outputs[0][qty_rusak]' : 'qty_rusak' }}"
                                    x-model.number="rusak"
                                    @input="syncFromRusak()"
                                    onkeydown="if(event.key === '.' || event.key === ',') event.preventDefault()"
                                    class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono text-right"
                                    required
                                >
                            </div>
                            <div>
                                <x-button type="submit" variant="success" size="sm" class="w-full">
                                    Simpan Hasil Batch
                                </x-button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-gray-500 px-0.5 mt-1.5">
                            <span>Target Rencana: <strong class="font-mono text-gray-800">{{ $targetRencana }} {{ $batch->produk?->satuan ?? 'Pcs' }}</strong></span>
                            <span :class="(baik + rusak) === target ? 'text-emerald-700 font-semibold' : 'text-rose-600 font-bold'">
                                Total Input: <span class="font-mono" x-text="baik + rusak"></span> / <span class="font-mono" x-text="target"></span>
                                <span x-show="(baik + rusak) === target" class="text-emerald-600 ml-1">&check; Pas</span>
                                <span x-show="(baik + rusak) !== target" class="text-rose-600 ml-1">&times; Wajib sama dengan target</span>
                            </span>
                        </div>
                    </div>
                    @endif
                    @if($batch->outputs->count() > 1)
                    <div class="flex justify-end pt-2">
                        <x-button type="submit" variant="success" size="sm">
                            Simpan Hasil Batch & Selesaikan
                        </x-button>
                    </div>
                    @endif
                </form>
            </x-card>
            @endif
            @endcan

            <!-- Stock Opname Pemakaian Bahan (Jika release atau selesai) -->
            @if(in_array($batch->status, ['release', 'selesai']))
            <x-card title="Stock Opname Pemakaian Bahan" subtitle="Pemakaian teoritis (BOM) vs aktual di ruang produksi." :noPadding="true">
                @can('batch.opname')
                <form method="POST" action="{{ route('batches.opname', $batch) }}" class="p-3.5">
                    @csrf
                    <table class="w-full text-left text-xs mb-3">
                        <thead class="bg-gray-100 border-b border-gray-200 text-gray-700 font-semibold">
                            <tr>
                                <th class="py-2 px-3">Bahan</th>
                                <th class="py-2 px-3 text-right">Teoritis (BOM)</th>
                                <th class="py-2 px-3 text-right w-36">Pemakaian Aktual</th>
                                <th class="py-2 px-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($batch->alokasi as $index => $a)
                            @php
                                $op = $batch->opname->where('bahan_id', $a->bahan_id)->first();
                                $valAktual = $op ? $op->pemakaian_aktual : (float) $a->qty_dialokasikan;
                            @endphp
                            <tr>
                                <td class="py-2 px-3 font-medium text-gray-800">
                                    <input type="hidden" name="items[{{ $index }}][bahan_id]" value="{{ $a->bahan_id }}">
                                    {{ $a->bahan->nama }}
                                </td>
                                <td class="py-2 px-3 text-right font-mono text-gray-600">
                                    {{ rtrim(rtrim(number_format($a->qty_dialokasikan, 2, ',', '.'), '0'), ',') }} {{ $a->bahan->satuan }}
                                </td>
                                <td class="py-2 px-3 text-right">
                                    <input type="number" step="0.01" min="0" name="items[{{ $index }}][pemakaian_aktual]" value="{{ $valAktual }}" class="w-full text-right rounded-sm border-gray-300 text-xs py-1 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" required>
                                </td>
                                <td class="py-2 px-3">
                                    <input type="text" name="items[{{ $index }}][keterangan]" value="{{ $op?->keterangan }}" placeholder="Catatan opname" class="w-full rounded-sm border-gray-300 text-xs py-1 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="flex justify-end">
                        <x-button type="submit" variant="primary" size="sm">
                            Simpan Opname
                        </x-button>
                    </div>
                </form>
                @else
                <div class="p-3.5">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-100 border-b border-gray-200 text-gray-700 font-semibold">
                            <tr>
                                <th class="py-2 px-3">Bahan</th>
                                <th class="py-2 px-3 text-right">Teoritis</th>
                                <th class="py-2 px-3 text-right">Aktual</th>
                                <th class="py-2 px-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($batch->opname as $op)
                            <tr>
                                <td class="py-2 px-3 font-medium text-gray-800">{{ $op->bahan?->nama }}</td>
                                <td class="py-2 px-3 text-right font-mono">{{ rtrim(rtrim(number_format($op->pemakaian_teoritis, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="py-2 px-3 text-right font-mono font-semibold">{{ rtrim(rtrim(number_format($op->pemakaian_aktual, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="py-2 px-3 text-gray-500">{{ $op->keterangan ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="py-3 text-center text-gray-400">Belum ada data opname tercatat.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endcan
            </x-card>
            @endif
        </div>

        <!-- Kolom Kanan: Detail QC & Aksi Manajemen -->
        <div class="space-y-5">
            <!-- Hasil QC & Produksi (Jika selesai atau release) -->
            @if(in_array($batch->status, ['release', 'selesai']))
            <x-card title="Hasil QC & Produksi" variant="primary">
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="bg-gray-50 p-2.5 rounded-sm border border-gray-200">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase">Qty Baik</p>
                        <p class="text-xl font-bold text-emerald-700 mt-0.5">
                            {{ $batch->qty_baik !== null ? \App\Helpers\NumberHelper::formatQty($batch->qty_baik) : '-' }}
                        </p>
                    </div>
                    <div class="bg-gray-50 p-2.5 rounded-sm border border-gray-200">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase">Qty Rusak</p>
                        <p class="text-xl font-bold text-rose-700 mt-0.5">
                            {{ $batch->qty_rusak !== null ? \App\Helpers\NumberHelper::formatQty($batch->qty_rusak) : '-' }}
                        </p>
                    </div>
                </div>
                <div class="pt-2 mt-2 border-t border-gray-100 flex items-center justify-between text-xs">
                    <span class="text-gray-500">Yield QC</span>
                    <span class="font-bold text-gray-900 font-mono">
                        {{ $batch->yield() !== null ? number_format($batch->yield(), 1) . '%' : '-' }}
                    </span>
                </div>
                <div class="flex items-center justify-between text-xs pt-1">
                    <span class="text-gray-500">Defect Rate</span>
                    <span class="font-bold text-gray-900 font-mono">
                        {{ $batch->defectRate() !== null ? number_format($batch->defectRate(), 1) . '%' : '-' }}
                    </span>
                </div>
            </x-card>
            @endif

            <!-- Panel Aksi Utama -->
            <x-card title="Kontrol Batch" variant="default">
                @if($batch->status === 'rencana')
                    @can('batch.release')
                    <form method="POST" action="{{ route('batches.release', $batch) }}">
                        @csrf
                        <x-button type="submit" variant="primary" size="md" class="w-full">
                            <x-slot:icon>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </x-slot:icon>
                            Release & Issue Bahan
                        </x-button>
                    </form>
                    <p class="text-[11px] text-gray-500 text-center mt-1">Menarik stok fisik bahan baku dari gudang operasional.</p>
                    @endcan

                    @can('request.bahan.create')
                    <div class="mt-2.5">
                        @if($hasDeficitFisik)
                        <x-button href="{{ route('requests.create', ['batch_id' => $batch->id]) }}" variant="danger" size="sm" class="w-full">
                            <x-slot:icon>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </x-slot:icon>
                            Buat Request Bahan (Kurang Stok)
                        </x-button>
                        <p class="text-[10px] text-rose-600 text-center mt-1 font-medium">Auto-fill bahan & kuantitas yang kurang ke Request Permintaan.</p>
                        @else
                        <x-button href="{{ route('requests.create', ['batch_id' => $batch->id]) }}" variant="secondary" size="sm" class="w-full">
                            <x-slot:icon>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </x-slot:icon>
                            + Buat Request Bahan
                        </x-button>
                        <p class="text-[10px] text-gray-500 text-center mt-1">Buat permintaan mutasi bahan untuk batch ini.</p>
                        @endif
                    </div>
                    @endcan

                    @can('batch.cancel')
                    <form method="POST" action="{{ route('batches.cancel', $batch) }}" onsubmit="return confirm('Batalkan rencana batch ini? Alokasi bahan akan dilepas.');" class="pt-3 mt-3 border-t border-gray-100">
                        @csrf
                        <x-button type="submit" variant="link" size="xs" class="w-full text-rose-600 hover:text-rose-800">
                            Batalkan Batch
                        </x-button>
                    </form>
                    @endcan
                @endif

                @if($batch->status === 'release')
                    @php
                        $hasKekuranganRelease = $hasDeficitFisik || ($showProgresPemenuhan && collect($rekapBahan)->contains(fn ($rb) => ($rb['kebutuhan'] - $rb['diterima']) > 0.0001));
                    @endphp
                    @if($hasKekuranganRelease)
                    @can('request.bahan.create')
                    <div class="mt-2.5 mb-2.5">
                        <x-button href="{{ route('requests.create', ['batch_id' => $batch->id]) }}" variant="warning" size="sm" class="w-full">
                            <x-slot:icon>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </x-slot:icon>
                            Buat Request Bahan (Kekurangan Stok)
                        </x-button>
                        <p class="text-[10px] text-amber-600 text-center mt-1 font-medium">Bahan belum lengkap saat produksi berlangsung.</p>
                    </div>
                    @endcan
                    @endif
                @endif

                @if($batch->status === 'selesai')
                    @if($batch->transferKirim)
                    <div class="py-3 px-3.5 bg-emerald-50 border border-emerald-200 rounded-sm text-xs">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <span class="font-bold text-emerald-900 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Pengiriman Selesai
                            </span>
                            <x-badge variant="success" size="xs">
                                {{ ucfirst($batch->transferKirim->status) }}
                            </x-badge>
                        </div>
                        <p class="text-[11px] text-emerald-800 leading-relaxed">
                            Produk jadi telah dikirim ke fulfillment hub via dokumen <a href="{{ route('transfers.show', $batch->transferKirim) }}" class="font-mono font-bold underline hover:text-emerald-950">{{ $batch->transferKirim->no_transaksi }}</a>.
                        </p>
                        <div class="mt-2.5 pt-2 border-t border-emerald-200/60 flex items-center justify-between">
                            <span class="text-[10px] text-emerald-700 font-mono">Tujuan: {{ $batch->gudangTujuan?->nama ?? 'Fulfillment' }}</span>
                            <a href="{{ route('transfers.show', $batch->transferKirim) }}" class="text-xs font-bold text-emerald-800 hover:text-emerald-950 flex items-center gap-1">
                                Dokumen Transfer &rarr;
                            </a>
                        </div>
                    </div>
                    @else
                        @can('transfer.produk_jadi.create')
                        <form method="POST" action="{{ route('batches.kirim', $batch) }}">
                            @csrf
                            <x-button type="submit" variant="primary" size="md" class="w-full">
                                <x-slot:icon>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3L9 8m-5 5h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293h3.172a1 1 0 00.707-.293l2.414-2.414a1 1 0 01.707-.293H20"/></svg>
                                </x-slot:icon>
                                Kirim ke Fulfillment
                            </x-button>
                        </form>
                        <div class="mt-2 text-center">
                            <a href="{{ route('transfers.create', ['jenis' => 'kirim_produk_jadi', 'batch_id' => $batch->id]) }}" class="inline-flex items-center justify-center w-full px-3 py-1.5 text-xs font-semibold rounded-lg bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-300">
                                Buat Transfer Produk Jadi
                            </a>
                        </div>
                        <p class="text-[11px] text-gray-500 text-center mt-1">Buat dokumen transfer produk jadi ke fulfillment hub.</p>
                        @endcan
                    @endif
                @endif

                @if($batch->status === 'dibatalkan')
                    <div class="py-2 px-3 bg-gray-50 rounded-sm text-center text-xs text-gray-500 font-medium">
                        Batch ini telah dibatalkan.
                    </div>
                @endif
            </x-card>

            <!-- Ringkasan Info Sistem -->
            <x-card title="Informasi Sistem" variant="default">
                <div class="space-y-2 text-xs text-gray-600">
                    <div class="flex justify-between">
                        <span>No Batch</span>
                        <span class="font-mono text-gray-900 font-semibold">{{ $batch->no_batch }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tipe Produk</span>
                        <span class="capitalize text-gray-900 font-medium">{{ $batch->produk ? str_replace('_', ' ', $batch->produk->tipe) : 'Multi-Produk' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Satuan Unit</span>
                        <span class="text-gray-900 font-medium">{{ $batch->produk?->satuan ?? 'Unit' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Dibuat Pada</span>
                        <span class="text-gray-900 font-medium">{{ $batch->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    <!-- Log & Jejak Riwayat Audit Dokumen Batch -->
    @canany(['batch.audit', 'audit.view'])
    <div class="mt-6">
        <x-operational-audit-history :auditable="$batch" title="Jejak Riwayat Audit Dokumen Batch {{ $batch->no_batch }}" />
    </div>
    @endcanany
</x-app-layout>

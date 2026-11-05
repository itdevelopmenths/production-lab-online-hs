<x-app-layout title="Transfer Barang" subtitle="Mutasi internal antar gudang, pengiriman, dan pelacakan defect/barang rusak">
    <div class="space-y-6">

        {{-- ========================================================================= --}}
        {{-- DASHBOARD WIDGET: REKAPITULASI BARANG RUSAK / DEFECT PER BULAN PER GUDANG --}}
        {{-- ========================================================================= --}}
        <x-card class="bg-gradient-to-br from-card to-muted/20 border-border">
            <x-slot:header>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between w-full gap-3">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center border border-rose-200">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-foreground tracking-tight">Dashboard Barang Rusak & Defect Mutasi</h2>
                            <p class="text-xs text-muted-foreground">Kalkulasi akumulasi barang rusak per bulan per gudang dari penerimaan transfer</p>
                        </div>
                    </div>

                    {{-- Filter Form --}}
                    <form method="GET" action="{{ route('transfers.index') }}" class="flex flex-wrap items-center gap-2">
                        <select name="year" class="h-8 rounded-lg border border-border bg-card text-xs px-2.5 text-foreground">
                            @foreach(range(date('Y') - 2, date('Y') + 1) as $yr)
                                <option value="{{ $yr }}" {{ $year == $yr ? 'selected' : '' }}>Tahun {{ $yr }}</option>
                            @endforeach
                        </select>

                        <select name="month" class="h-8 rounded-lg border border-border bg-card text-xs px-2.5 text-foreground">
                            <option value="">Semua Bulan</option>
                            @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create(2026, $m, 1)->translatedFormat('F') }}
                                </option>
                            @endforeach
                        </select>

                        <select name="gudang_id" class="h-8 rounded-lg border border-border bg-card text-xs px-2.5 text-foreground">
                            <option value="">Semua Gudang</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ $gudangId == $wh->id ? 'selected' : '' }}>{{ $wh->nama }}</option>
                            @endforeach
                        </select>

                        <button type="submit" class="h-8 px-3 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition">
                            Filter
                        </button>

                        @if($month || $gudangId)
                            <a href="{{ route('transfers.index', ['year' => $year]) }}" class="h-8 px-2.5 rounded-lg border border-border text-xs text-muted-foreground hover:bg-muted flex items-center">
                                Reset
                            </a>
                        @endif
                    </form>
                </div>
            </x-slot:header>

            {{-- 4 Stat Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="p-4 rounded-xl bg-card border border-border/70 shadow-xs">
                    <span class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Total Transfer Selesai</span>
                    <div class="text-xl font-bold text-foreground mt-1">{{ number_format($defectAnalytics['summary']['total_transfers'] ?? 0) }}</div>
                    <span class="text-[10px] text-muted-foreground">Transaksi mutasi</span>
                </div>

                <div class="p-4 rounded-xl bg-card border border-border/70 shadow-xs">
                    <span class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Total Qty Dikirim</span>
                    <div class="text-xl font-bold text-blue-600 mt-1">{{ number_format($defectAnalytics['summary']['total_qty_dikirim'] ?? 0, 2) }}</div>
                    <span class="text-[10px] text-muted-foreground">Volume mutasi fisik</span>
                </div>

                <div class="p-4 rounded-xl bg-card border border-rose-200 bg-rose-50/40 shadow-xs">
                    <span class="text-[11px] font-bold text-rose-700 uppercase tracking-wider flex items-center gap-1">
                        <span class="size-1.5 rounded-full bg-rose-600"></span>
                        Total Barang Rusak
                    </span>
                    <div class="text-xl font-extrabold text-rose-600 mt-1">{{ number_format($defectAnalytics['summary']['total_qty_rusak'] ?? 0, 2) }}</div>
                    <span class="text-[10px] text-rose-700/80 font-medium">Tercatat pada penerimaan</span>
                </div>

                <div class="p-4 rounded-xl bg-card border border-border/70 shadow-xs">
                    <span class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Tingkat Kerusakan (Defect Rate)</span>
                    <div class="text-xl font-bold {{ ($defectAnalytics['summary']['defect_rate_percent'] ?? 0) > 0 ? 'text-amber-600' : 'text-emerald-600' }} mt-1">
                        {{ number_format($defectAnalytics['summary']['defect_rate_percent'] ?? 0, 2) }}%
                    </div>
                    <span class="text-[10px] text-muted-foreground">Rasio rusak vs kirim</span>
                </div>
            </div>

            {{-- Rekapitulasi Bulanan per Gudang --}}
            <div class="mt-4 border border-border rounded-xl overflow-hidden">
                <div class="px-4 py-2.5 bg-muted/60 border-b border-border flex items-center justify-between">
                    <span class="text-xs font-semibold text-foreground">Rekapitulasi Kerusakan Barang per Bulan (Tahun {{ $year }})</span>
                    <span class="text-[11px] text-muted-foreground">Akumulasi per gudang penerima</span>
                </div>
                <div class="overflow-x-auto max-h-72 overflow-y-auto">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-muted border-b border-border text-muted-foreground font-medium">
                            <tr>
                                <th class="px-4 py-2.5 text-left w-32">Bulan</th>
                                <th class="px-4 py-2.5 text-right w-24">Transfer</th>
                                <th class="px-4 py-2.5 text-right w-32">Qty Dikirim</th>
                                <th class="px-4 py-2.5 text-right w-32">Qty Baik</th>
                                <th class="px-4 py-2.5 text-right w-32 text-rose-700 font-bold">Qty Rusak</th>
                                <th class="px-4 py-2.5 text-right w-24">Defect %</th>
                                <th class="px-4 py-2.5 text-left">Rincian per Gudang Penerima</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-card">
                            @php
                                $matrix = $defectAnalytics['monthly_matrix'] ?? [];
                            @endphp
                            @forelse($matrix as $mRow)
                                @if(!$month || $mRow['month'] == $month)
                                <tr class="hover:bg-muted/30 {{ $mRow['qty_rusak'] > 0 ? 'bg-rose-50/20' : '' }}">
                                    <td class="px-4 py-2.5 font-medium text-foreground">{{ $mRow['month_name'] }}</td>
                                    <td class="px-4 py-2.5 text-right font-medium text-muted-foreground">{{ $mRow['transfers_count'] }}</td>
                                    <td class="px-4 py-2.5 text-right font-medium">{{ number_format($mRow['qty_dikirim'], 2) }}</td>
                                    <td class="px-4 py-2.5 text-right font-medium text-emerald-600">{{ number_format($mRow['qty_baik'], 2) }}</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-rose-600">
                                        {{ $mRow['qty_rusak'] > 0 ? number_format($mRow['qty_rusak'], 2) : '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-semibold {{ $mRow['defect_rate_percent'] > 0 ? 'text-rose-600' : 'text-muted-foreground' }}">
                                        {{ number_format($mRow['defect_rate_percent'], 2) }}%
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if(!empty($mRow['warehouses']))
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach($mRow['warehouses'] as $whBreakdown)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] {{ $whBreakdown['qty_rusak'] > 0 ? 'bg-rose-100 text-rose-800 font-semibold' : 'bg-muted text-muted-foreground' }}">
                                                        <span>{{ $whBreakdown['gudang_nama'] }}:</span>
                                                        <span>{{ number_format($whBreakdown['qty_rusak'], 2) }} rusak</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted-foreground">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-4 text-center text-muted-foreground">Tidak ada data transfer pada periode ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-card>

        {{-- ========================================================================= --}}
        {{-- DAFTAR TRANSAKSI TRANSFER ANTAR GUDANG --}}
        {{-- ========================================================================= --}}
        <x-card>
            <x-slot:header>
                <div>
                    <h2 class="text-lg font-semibold text-foreground tracking-tight leading-tight">Daftar Dokumen Transfer & Pengiriman</h2>
                    <p class="text-sm text-muted-foreground mt-0.5">Pengiriman stok fisik antar gudang, surat jalan, dan penerimaan dengan pencatatan defect</p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <select id="filterTransferStatus" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground outline-none cursor-pointer">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="dikirim">In Transit (Dikirim)</option>
                        <option value="selesai">Selesai Diterima</option>
                        <option value="dibatalkan">Dibatalkan</option>
                    </select>

                    @can('rt.create')
                    <x-button href="{{ route('transfers.create') }}" variant="primary">
                        <x-slot:icon>
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </x-slot:icon>
                        Buat Transfer Baru
                    </x-button>
                    @endcan
                </div>
            </x-slot:header>

            <div class="overflow-x-auto">
                <table id="tblTransfers" class="w-full text-sm">
                    <thead>
                        <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[13px] font-medium">
                            <th class="px-4 py-3 text-left">No Transfer</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-left">Jenis</th>
                            <th class="px-4 py-3 text-left">Gudang Asal</th>
                            <th class="px-4 py-3 text-left">Gudang Tujuan</th>
                            <th class="px-4 py-3 text-left">Ref Request</th>
                            <th class="px-4 py-3 text-right">Qty Kirim</th>
                            <th class="px-4 py-3 text-right">Qty Rusak</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </x-card>
    </div>

    @push('scripts')
    <script>
        $(document).ready(function() {
            const table = $('#tblTransfers').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("transfers.data") }}',
                    data: function(d) {
                        d.status = $('#filterTransferStatus').val();
                    }
                },
                columns: [
                    { data: 'no_transfer', name: 'no_transfer', className: 'font-mono text-xs font-semibold text-primary-600' },
                    { data: 'tanggal_transfer', name: 'tanggal_transfer' },
                    { data: 'jenis', name: 'jenis' },
                    { data: 'asal', name: 'gudangAsal.nama' },
                    { data: 'tujuan', name: 'gudangTujuan.nama' },
                    { data: 'request_no', name: 'stockRequest.no_request' },
                    { data: 'qty_dikirim', name: 'qty_dikirim', className: 'text-right font-medium' },
                    { data: 'qty_rusak', name: 'qty_rusak', className: 'text-right' },
                    { data: 'status', name: 'status' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' }
                ],
                order: [[0, 'desc']]
            });

            $('#filterTransferStatus').on('change', function() {
                table.draw();
            });
        });
    </script>
    @endpush
</x-app-layout>

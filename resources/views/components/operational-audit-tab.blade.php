@props([
    'module',
    'title' => 'Log Riwayat Audit & Aktivitas Operasional',
    'subtitle' => 'Rekaman otomatis aktivitas pembuatan dokumen, perubahan status, alokasi resep, persetujuan, penerimaan barang, dan pembayaran',
])

@php
    $moduleTitle = match($module) {
        'purchasing' => 'Purchasing',
        'produksi' => 'Produksi',
        'request' => 'Request Bahan',
        'transfer' => 'Transfer Antar Gudang',
        'request_transfer' => 'Request & Transfer',
        default => 'Operasional',
    };
    $cleanModule = preg_replace('/[^a-zA-Z0-9]/', '', $module);
    $tableId = 'tblOperationalAudit' . ucfirst($cleanModule);
    $filterId = 'filterModule' . ucfirst($cleanModule);
@endphp

<div class="space-y-4">
    <!-- Info Banner Audit Trail -->
    <div class="p-3.5 bg-muted/40 border border-border rounded-xl text-xs flex flex-wrap items-center justify-between gap-3 shadow-xs">
        <div class="flex items-center gap-2 text-foreground">
            <svg class="size-4 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span>Tabel audit ini merekam jejak operasional dokumen <strong>{{ $moduleTitle }}</strong> secara permanen.</span>
        </div>
        <button type="button" onclick="if (window.{{ $tableId }}) window.{{ $tableId }}.ajax.reload();" class="text-foreground hover:text-muted-foreground font-semibold inline-flex items-center gap-1 cursor-pointer text-xs transition-colors">
            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Segarkan Log
        </button>
    </div>

    @if($module === 'request_transfer')
    <!-- Filter Jenis Mutasi Khusus Request & Transfer -->
    <div class="flex items-center gap-3 bg-card p-3 rounded-xl border border-border text-xs shadow-xs">
        <label for="{{ $filterId }}" class="font-medium text-foreground">Filter Modul:</label>
        <select id="{{ $filterId }}" class="rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 outline-none">
            <option value="request_transfer">— Semua Mutasi (Request & Transfer) —</option>
            <option value="request">Khusus Request Bahan (req_bahan)</option>
            <option value="transfer">Khusus Transfer Antar Gudang</option>
        </select>
    </div>
    @endif

    <x-card :title="$title" :subtitle="$subtitle" :noPadding="true">
        <div class="overflow-x-auto">
            <table id="{{ $tableId }}" class="w-full text-sm">
                <thead>
                    <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[13px] font-medium">
                        <th class="px-4 py-3 text-left w-36">Waktu</th>
                        <th class="px-4 py-3 text-left w-44">No. Dokumen</th>
                        <th class="px-4 py-3 text-center w-28">Modul</th>
                        <th class="px-4 py-3 text-center w-24">Aksi / Status</th>
                        <th class="px-4 py-3 text-left">Rincian Aktivitas & Nilai Transaksi</th>
                        <th class="px-4 py-3 text-left w-36">Pelaksana</th>
                        <th class="px-4 py-3 text-left w-28">IP Address</th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>
</div>

@push('scripts')
<script>
$(function() {
    window.{{ $tableId }} = $('#{{ $tableId }}').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('operational-audit.data') }}",
            data: function(d) {
                @if($module === 'request_transfer')
                    d.module = $('#{{ $filterId }}').val();
                @else
                    d.module = "{{ $module }}";
                @endif
            }
        },
        columns: [
            { data: 'created_at', name: 'created_at', className: 'px-4 py-3 font-mono text-muted-foreground text-xs align-top whitespace-nowrap' },
            { data: 'document_no', name: 'document_no', className: 'px-4 py-3 font-semibold text-foreground text-xs align-top' },
            { data: 'module_badge', name: 'module', className: 'px-4 py-3 text-center align-top whitespace-nowrap' },
            { data: 'action_badge', name: 'action', className: 'px-4 py-3 text-center align-top whitespace-nowrap' },
            { data: 'details_formatted', name: 'details', className: 'px-4 py-3 text-xs text-foreground align-top' },
            { data: 'user_info', name: 'user_name', className: 'px-4 py-3 text-xs align-top' },
            { data: 'ip_address', name: 'ip_address', className: 'px-4 py-3 font-mono text-muted-foreground text-xs align-top whitespace-nowrap' }
        ],
        order: [[0, 'desc']]
    });

    @if($module === 'request_transfer')
    $('#{{ $filterId }}').on('change', function() {
        window.{{ $tableId }}.ajax.reload();
    });
    @endif
});
</script>
@endpush

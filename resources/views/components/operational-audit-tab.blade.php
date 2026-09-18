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
    <div class="p-3 bg-indigo-50/70 border border-indigo-200 rounded-sm text-xs flex flex-wrap items-center justify-between gap-3 shadow-2xs">
        <div class="flex items-center gap-2 text-indigo-900">
            <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span>Tabel audit ini merekam rekam jejak operasional dokumen <strong>{{ $moduleTitle }}</strong> secara permanen mencakup pengguna pelaksana, timestamp, rincian atribut, serta alamat IP.</span>
        </div>
        <button type="button" onclick="if (window.{{ $tableId }}) window.{{ $tableId }}.ajax.reload();" class="text-indigo-700 hover:text-indigo-900 font-semibold inline-flex items-center gap-1 cursor-pointer text-xs transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Segarkan Log
        </button>
    </div>

    @if($module === 'request_transfer')
    <!-- Filter Jenis Mutasi Khusus Request & Transfer -->
    <div class="flex items-center gap-3 bg-white p-2.5 rounded-sm border border-gray-200 text-xs shadow-2xs">
        <label for="{{ $filterId }}" class="font-semibold text-gray-700">Filter Modul:</label>
        <select id="{{ $filterId }}" class="rounded-sm border-gray-300 text-xs py-1 px-2.5 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            <option value="request_transfer">— Semua Mutasi (Request & Transfer) —</option>
            <option value="request">Khusus Request Bahan (req_bahan)</option>
            <option value="transfer">Khusus Transfer Antar Gudang</option>
        </select>
    </div>
    @endif

    <x-card :title="$title" :subtitle="$subtitle" :noPadding="true">
        <div class="overflow-x-auto p-2">
            <table id="{{ $tableId }}" class="w-full text-xs">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-[11px] uppercase tracking-wider">
                        <th class="px-3 py-2.5 text-left w-36">Waktu</th>
                        <th class="px-3 py-2.5 text-left w-44">No. Dokumen</th>
                        <th class="px-3 py-2.5 text-center w-28">Modul</th>
                        <th class="px-3 py-2.5 text-center w-24">Aksi / Status</th>
                        <th class="px-3 py-2.5 text-left">Rincian Aktivitas & Nilai Transaksi</th>
                        <th class="px-3 py-2.5 text-left w-36">Pelaksana</th>
                        <th class="px-3 py-2.5 text-left w-28">IP Address</th>
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
            url: '{{ route("operational-audit.data") }}',
            data: function(d) {
                @if($module === 'request_transfer')
                    d.module = $('#{{ $filterId }}').val() || '{{ $module }}';
                @else
                    d.module = '{{ $module }}';
                @endif
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'waktu', className: 'font-mono text-gray-700 whitespace-nowrap text-xs' },
            { data: 'nomor_referensi', className: 'whitespace-nowrap text-xs' },
            { data: 'module_badge', className: 'text-center whitespace-nowrap' },
            { data: 'event_badge', className: 'text-center whitespace-nowrap' },
            { data: 'rincian', className: 'text-xs text-gray-700 leading-relaxed font-sans' },
            { data: 'pelaksana', className: 'text-xs' },
            { data: 'ip_address', className: 'font-mono text-[11px] text-gray-500 whitespace-nowrap' }
        ]
    });

    @if($module === 'request_transfer')
    $('#{{ $filterId }}').on('change', function() {
        if (window.{{ $tableId }}) {
            window.{{ $tableId }}.ajax.reload();
        }
    });
    @endif
});
</script>
@endpush

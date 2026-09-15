@props([
    'entity',
    'title' => 'Log Riwayat Perubahan & Audit Trail',
    'subtitle' => 'Rekaman otomatis aktivitas penambahan, pengubahan, penghapusan, dan impor data untuk transparansi sistem',
])

@php
    $tableId = 'tblAudit' . ucfirst($entity);
@endphp

<div class="space-y-4">
    <div class="p-3 bg-blue-50/70 border border-blue-200 rounded-sm text-xs flex items-center justify-between gap-3 shadow-2xs">
        <div class="flex items-center gap-2 text-blue-900">
            <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Tabel ini mencatat riwayat perubahan data pada modul <strong>{{ ucfirst($entity) }}</strong> secara otomatis termasuk nilai sebelum vs sesudah dan pengguna yang melakukan perubahan.</span>
        </div>
        <button type="button" onclick="if (window.{{ $tableId }}) window.{{ $tableId }}.ajax.reload();" class="text-blue-700 hover:text-blue-900 font-semibold inline-flex items-center gap-1 cursor-pointer text-xs">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Segarkan Log
        </button>
    </div>

    <x-card :title="$title" :subtitle="$subtitle" :noPadding="true">
        <div class="overflow-x-auto p-2">
            <table id="{{ $tableId }}" class="w-full text-xs">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-[11px] uppercase tracking-wider">
                        <th class="px-3 py-2.5 text-left w-36">Waktu</th>
                        <th class="px-3 py-2.5 text-left w-52">Item / Objek</th>
                        <th class="px-3 py-2.5 text-center w-24">Aksi</th>
                        <th class="px-3 py-2.5 text-left">Rincian Perubahan (Sebelum &rarr; Sesudah)</th>
                        <th class="px-3 py-2.5 text-left w-36">Dilakukan Oleh</th>
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
            url: '{{ route("master-audit.data") }}',
            data: function(d) {
                d.entity = '{{ $entity }}';
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'waktu', className: 'font-mono text-gray-700 whitespace-nowrap text-xs' },
            { 
                data: 'item_name', 
                render: function(data) {
                    return `<span class="font-semibold text-gray-900 leading-tight">${data}</span>`;
                }
            },
            { data: 'event', className: 'text-center whitespace-nowrap' },
            { 
                data: 'perubahan', 
                className: 'text-xs text-gray-700 leading-relaxed font-sans'
            },
            { data: 'oleh', className: 'text-xs' },
            { data: 'ip_address', className: 'font-mono text-[11px] text-gray-500 whitespace-nowrap' }
        ]
    });
});
</script>
@endpush

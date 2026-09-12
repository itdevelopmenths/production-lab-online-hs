<x-app-layout title="Produksi">
    <x-page-header
        title="Batch Produksi"
        subtitle="Daftar rencana kerja, alokasi resep BOM, dan eksekusi produksi pabrik"
        :breadcrumbs="['Produksi' => null]"
    >
        <x-slot:actions>
            @can('batch.create')
            <x-button href="{{ route('batches.create') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Rencana Batch Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card title="Daftar Batch Produksi" :noPadding="true">
        <table id="tbl" class="w-full text-xs">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">No Batch</th>
                    <th class="px-4 py-3 text-left">Produk Luaran</th>
                    <th class="px-4 py-3 text-right">Rencana</th>
                    <th class="px-4 py-3 text-right">Baik</th>
                    <th class="px-4 py-3 text-right">Rusak</th>
                    <th class="px-4 py-3 text-center">Yield</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-left">Tanggal</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
        </table>
    </x-card>

    @push('scripts')
    <script>
    $(function(){
        $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("batches.data") }}',
            columns: [
                { data: 'no_batch' },
                { data: 'produk_nama', orderable: false },
                { data: 'qty_rencana', className: 'text-right font-mono' },
                { data: 'qty_baik', className: 'text-right font-mono font-semibold text-emerald-700', orderable: false, searchable: false },
                { data: 'qty_rusak', className: 'text-right font-mono font-semibold text-rose-700', orderable: false, searchable: false },
                { data: 'yield', orderable: false, searchable: false, className: 'text-center font-mono font-bold' },
                { data: 'status', className: 'text-center', orderable: false },
                { data: 'tanggal' },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

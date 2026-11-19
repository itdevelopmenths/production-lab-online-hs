<x-app-layout title="Produksi">
    <x-page-header
        title="Batch Produksi"
        :breadcrumbs="['Produksi' => null]"
    >
        <x-slot:actions>
            @can('batch.create')
            <x-button href="{{ route('batches.create') }}" variant="primary" size="sm">
                <x-slot:icon>
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Rencana Batch Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card title="Daftar Batch Produksi" subtitle="Monitoring rencana, rilis BOM, realisasi QC, dan yield produksi" :noPadding="true">
        <x-slot:icon>
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        </x-slot:icon>
        <div class="overflow-x-auto">
            <table id="tbl" class="w-full text-sm">
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
        </div>
    </x-card>

    @push('scripts')
    <script>
    $(function(){
        $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            order: [],
            ajax: '{{ route("batches.data") }}',
            columns: [
                { data: 'no_batch', className: 'px-4 py-3.5 tabular-nums text-xs font-medium text-[#101828] dark:text-white' },
                { data: 'produk_nama', orderable: false, className: 'px-4 py-3.5 font-medium text-[#101828] dark:text-white' },
                { data: 'qty_rencana', className: 'px-4 py-3.5 text-right  text-xs tabular-nums' },
                { data: 'qty_baik', className: 'px-4 py-3.5 text-right  text-xs font-medium text-foreground dark:text-foreground tabular-nums', orderable: false, searchable: false },
                { data: 'qty_rusak', className: 'px-4 py-3.5 text-right  text-xs font-medium text-red-600 dark:text-red-400 tabular-nums', orderable: false, searchable: false },
                { data: 'yield', orderable: false, searchable: false, className: 'px-4 py-3.5 text-center  text-xs font-medium tabular-nums' },
                { data: 'status', className: 'px-4 py-3.5 text-center', orderable: false },
                { data: 'tanggal', className: 'px-4 py-3.5 text-xs text-text-muted' },
                { data: 'action', orderable: false, searchable: false, className: 'px-4 py-3.5 text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

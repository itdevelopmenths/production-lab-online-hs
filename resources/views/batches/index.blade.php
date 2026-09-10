<x-app-layout title="Produksi">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-gray-900">Batch Produksi</h3>
        @can('batch.create')
        <a href="{{ route('batches.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-500 text-white text-sm font-medium rounded-xl hover:bg-primary-600 transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Rencana Produksi
        </a>
        @endcan
    </div>
    <div class="table-wrapper bg-white rounded-xl border border-gray-200 p-4">
        <table id="tbl" class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">No Batch</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Produk</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Rencana</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Yield</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tanggal</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Aksi</th>
            </tr></thead>
        </table>
    </div>
    @push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script>
    $(function(){ $('#tbl').DataTable({ processing:true, serverSide:true, ajax:'{{ route("batches.data") }}',
        columns:[{data:'no_batch'},{data:'produk_nama',orderable:false},{data:'qty_rencana',className:'text-right'},{data:'status'},{data:'yield',orderable:false,searchable:false},{data:'tanggal'},{data:'action',orderable:false,searchable:false}],
        language:{processing:'Memuat...',search:'Cari:',paginate:{previous:'Sebelumnya',next:'Berikutnya'},info:'_START_-_END_ dari _TOTAL_',zeroRecords:'Belum ada batch'} }); });
    </script>
    @endpush
</x-app-layout>

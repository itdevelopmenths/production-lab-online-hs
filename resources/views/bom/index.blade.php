<x-app-layout title="BOM">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-gray-900">Bill of Materials</h3>
        @can('bom.import')
        <form method="POST" action="{{ route('bom.import') }}" enctype="multipart/form-data" class="flex items-center gap-2">
            @csrf
            <input type="file" name="file" accept=".csv,.txt" class="text-xs" required>
            <button type="submit" class="px-3 py-2 bg-gray-700 text-white text-xs font-medium rounded-lg hover:bg-gray-800">Import CSV</button>
        </form>
        @endcan
    </div>
    <p class="text-xs text-gray-500 mb-4">Format CSV (pemisah <code>;</code>): <code>sku_produk_jadi;sku_bahan;qty_per_unit</code></p>
    <div class="table-wrapper bg-white rounded-xl border border-gray-200 p-4">
        <table id="tbl" class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">SKU</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Produk Jadi</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Jml Bahan</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Aksi</th>
            </tr></thead>
        </table>
    </div>
    @push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script>
    $(function(){ $('#tbl').DataTable({ processing:true, serverSide:true, ajax:'{{ route("bom.data") }}',
        columns:[{data:'sku'},{data:'nama'},{data:'bom_count',searchable:false,orderable:false},{data:'action',orderable:false,searchable:false}],
        language:{processing:'Memuat...',search:'Cari:',paginate:{previous:'Sebelumnya',next:'Berikutnya'},info:'_START_-_END_ dari _TOTAL_',zeroRecords:'Data tidak ditemukan'} }); });
    </script>
    @endpush
</x-app-layout>

<x-app-layout title="Kartu Stok">
    <a href="{{ route('stok.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>
    <div class="mt-4 mb-6">
        <h3 class="text-lg font-semibold text-gray-900">Kartu Stok — {{ $produk->nama }}</h3>
        <p class="text-xs text-gray-500">SKU {{ $produk->sku }}</p>
    </div>
    <div class="table-wrapper bg-white rounded-xl border border-gray-200 p-4">
        <table id="tbl" class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tanggal</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Gudang</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tipe</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Qty</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Saldo</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Referensi</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Catatan</th>
            </tr></thead>
        </table>
    </div>
    @push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script>
    $(function(){ $('#tbl').DataTable({ processing:true, serverSide:true, ajax:'{{ route("stok.ledger.data", $produk) }}', order:[],
        columns:[{data:'tanggal',orderable:false},{data:'gudang_nama',orderable:false},{data:'tipe',orderable:false},{data:'qty',className:'text-right'},{data:'saldo_setelah',className:'text-right'},{data:'referensi_tipe',orderable:false},{data:'catatan',orderable:false}],
        language:{processing:'Memuat...',search:'Cari:',paginate:{previous:'Sebelumnya',next:'Berikutnya'},info:'_START_-_END_ dari _TOTAL_',zeroRecords:'Belum ada mutasi'} }); });
    </script>
    @endpush
</x-app-layout>

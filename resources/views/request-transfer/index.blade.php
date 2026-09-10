<x-app-layout title="Request & Transfer">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <h3 class="text-lg font-semibold text-gray-900">Request &amp; Transfer</h3>
            <select id="jenisFilter" class="rounded-lg border-gray-300 text-sm">
                <option value="">Semua jenis</option>
                <option value="req_bahan">Request Bahan</option>
                <option value="retur_bahan">Retur Bahan</option>
                <option value="kirim_produk_jadi">Kirim Produk Jadi</option>
                <option value="antar_fulfillment">Antar Fulfillment</option>
                <option value="retur_produk_jadi">Retur Produk Jadi</option>
            </select>
        </div>
        @can('rt.create')
        <a href="{{ route('rt.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-500 text-white text-sm font-medium rounded-xl hover:bg-primary-600 transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Buat Baru
        </a>
        @endcan
    </div>
    <div class="table-wrapper bg-white rounded-xl border border-gray-200 p-4">
        <table id="tbl" class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">No</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Jenis</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Asal</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tujuan</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
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
    $(function(){ var t = $('#tbl').DataTable({ processing:true, serverSide:true,
        ajax:{ url:'{{ route("rt.data") }}', data:function(d){ d.jenis = $('#jenisFilter').val(); } },
        columns:[{data:'no_transaksi'},{data:'jenis'},{data:'asal',orderable:false,searchable:false},{data:'tujuan',orderable:false,searchable:false},{data:'status'},{data:'created_at'},{data:'action',orderable:false,searchable:false}],
        language:{processing:'Memuat...',search:'Cari:',paginate:{previous:'Sebelumnya',next:'Berikutnya'},info:'_START_-_END_ dari _TOTAL_',zeroRecords:'Belum ada dokumen'} });
        $('#jenisFilter').on('change', ()=>t.ajax.reload());
    });
    </script>
    @endpush
</x-app-layout>

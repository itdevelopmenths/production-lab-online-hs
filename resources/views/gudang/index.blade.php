<x-app-layout title="Gudang">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-gray-900">Master Gudang</h3>
        @can('gudang.create')
        <a href="{{ route('gudang.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-500 text-white text-sm font-medium rounded-xl hover:bg-primary-600 transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Gudang
        </a>
        @endcan
    </div>
    <div class="table-wrapper bg-white rounded-xl border border-gray-200 p-4">
        <table id="tbl" class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Kode</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Nama</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tipe</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Induk</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Aksi</th>
            </tr></thead>
        </table>
    </div>
    @push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script>
    function hapus(url){ Swal.fire({title:'Hapus data?',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc2626',confirmButtonText:'Hapus',cancelButtonText:'Batal'}).then(r=>{ if(r.isConfirmed){ fetch(url,{method:'DELETE',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}}).then(res=>res.json()).then(d=>{ Swal.fire('Terhapus',d.message,'success'); $('#tbl').DataTable().ajax.reload(); }); } }); }
    $(function(){ $('#tbl').DataTable({ processing:true, serverSide:true, ajax:'{{ route("gudang.data") }}',
        columns:[{data:'kode'},{data:'nama'},{data:'tipe'},{data:'parent',orderable:false,searchable:false},{data:'status'},{data:'action',orderable:false,searchable:false}],
        language:{processing:'Memuat...',search:'Cari:',paginate:{previous:'Sebelumnya',next:'Berikutnya'},info:'_START_-_END_ dari _TOTAL_',zeroRecords:'Data tidak ditemukan'} }); });
    </script>
    @endpush
</x-app-layout>

<x-app-layout title="Satuan (UOM)">
    <x-page-header
        title="Master Satuan Unit (UOM)"
        subtitle="Daftar unit pengukuran standar untuk bahan baku kimia, kemasan, dan produk jadi manufaktur"
        :breadcrumbs="['Master Data' => null, 'Satuan (UOM)' => null]"
    >
        <x-slot:actions>
            @can('uom.create')
            <x-button href="{{ route('uom.create') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Satuan Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card title="Daftar Master Satuan Unit (Unit of Measure)" :noPadding="true">
        <div class="overflow-x-auto">
            <table id="tbl" class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left w-32">Kode Satuan</th>
                        <th class="px-4 py-3 text-left">Nama Satuan</th>
                        <th class="px-4 py-3 text-left w-36">Kategori Dimensi</th>
                        <th class="px-4 py-3 text-left w-48">Konversi Acuan</th>
                        <th class="px-4 py-3 text-left w-36">Penggunaan Katalog</th>
                        <th class="px-4 py-3 text-center w-28">Status</th>
                        <th class="px-4 py-3 text-center w-28">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

    @push('scripts')
    <script>
    function hapus(url){
        Swal.fire({
            title: 'Hapus satuan UOM ini?',
            text: 'Data yang sedang digunakan oleh produk katalog tidak dapat dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(r => {
            if(r.isConfirmed){
                fetch(url, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                })
                .then(async res => {
                    const d = await res.json();
                    if (res.ok) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Terhapus',
                            text: d.message,
                            confirmButtonColor: '#0284c7'
                        });
                        $('#tbl').DataTable().ajax.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menghapus',
                            text: d.message || 'Terjadi kesalahan saat menghapus data.',
                            confirmButtonColor: '#dc2626'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Sistem',
                        text: 'Tidak dapat terhubung ke server.',
                        confirmButtonColor: '#dc2626'
                    });
                });
            }
        });
    }

    $(function(){
        $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("uom.data") }}',
            columns: [
                { data: 'kode' },
                { data: 'nama' },
                { data: 'kategori' },
                { data: 'konversi' },
                { data: 'produk_count', orderable: false, searchable: false },
                { data: 'is_active', className: 'text-center' },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

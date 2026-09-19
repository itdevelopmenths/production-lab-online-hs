<x-app-layout title="Kategori Produk">
    <x-page-header
        title="Master Kategori Produk"
        subtitle="Daftar kategori untuk klasifikasi produk manufaktur, kemasan, dan bahan baku"
        :breadcrumbs="['Master Data' => null, 'Kategori Produk' => null]"
    >
        <x-slot:actions>
            @can('produk.create')
            <x-button href="{{ route('kategori.create') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Kategori Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card title="Daftar Kategori Produk" :noPadding="true">
        <div class="overflow-x-auto">
            <table id="tbl-kategori" class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Nama Kategori</th>
                        <th class="px-4 py-3 text-left w-36">Jumlah Varian</th>
                        <th class="px-4 py-3 text-left w-36">Jumlah Produk</th>
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
            title: 'Hapus kategori ini?',
            text: 'Kategori yang masih memiliki produk atau varian terdaftar tidak dapat dihapus.',
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
                        $('#tbl-kategori').DataTable().ajax.reload();
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
        $('#tbl-kategori').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("kategori.data") }}',
            columns: [
                { data: 'nama' },
                { data: 'varians_count', orderable: false, searchable: false },
                { data: 'produk_count', orderable: false, searchable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

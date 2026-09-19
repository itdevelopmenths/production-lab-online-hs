<x-app-layout title="Varian Produk">
    <x-page-header
        title="Master Varian Produk"
        subtitle="Daftar variasi aroma, ukuran, atau warna yang terikat pada masing-masing kategori produk"
        :breadcrumbs="['Master Data' => null, 'Varian Produk' => null]"
    >
        <x-slot:actions>
            @can('produk.create')
            <x-button href="{{ route('varian.create') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Varian Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <!-- Filter Bar -->
    <div class="mb-4 bg-white p-3 rounded-md border border-gray-200 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2">
            <label for="filter-kategori" class="font-medium text-gray-700">Filter Kategori:</label>
            <select id="filter-kategori" class="rounded border-gray-300 text-xs py-1.5 px-2.5 focus:border-primary-500 focus:ring-primary-500">
                <option value="">Semua Kategori</option>
                @foreach($kategoriList as $k)
                    <option value="{{ $k->id }}">{{ $k->nama }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-card title="Daftar Master Varian Produk" :noPadding="true">
        <div class="overflow-x-auto">
            <table id="tbl-varian" class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left w-48">Kategori Induk</th>
                        <th class="px-4 py-3 text-left">Nama Varian</th>
                        <th class="px-4 py-3 text-left w-36">Penggunaan Katalog</th>
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
            title: 'Hapus varian ini?',
            text: 'Varian yang sedang digunakan oleh produk katalog tidak dapat dihapus.',
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
                        $('#tbl-varian').DataTable().ajax.reload();
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
        const table = $('#tbl-varian').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("varian.data") }}',
                data: function(d) {
                    d.kategori_id = $('#filter-kategori').val();
                }
            },
            columns: [
                { data: 'kategori_nama' },
                { data: 'nama' },
                { data: 'produk_count', orderable: false, searchable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });

        $('#filter-kategori').on('change', function() {
            table.ajax.reload();
        });
    });
    </script>
    @endpush
</x-app-layout>

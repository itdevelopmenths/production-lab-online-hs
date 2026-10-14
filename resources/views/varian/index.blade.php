<x-app-layout title="Varian Produk">
    <x-page-header
        title="Master Varian Produk"
        subtitle="Daftar variasi aroma, ukuran, atau warna yang terikat pada masing-masing kategori produk"
        :breadcrumbs="['Master Data' => null, 'Varian Produk' => null]"
    >
        <x-slot:actions>
            @can('produk.create')
            <x-button href="{{ route('varian.create') }}" variant="primary" size="sm">
                <x-slot:icon>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Varian
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <!-- Filter Bar -->
    <div class="mb-4 rounded-xl border border-border bg-card p-3.5 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2.5">
            <label for="filter-kategori" class="font-medium text-foreground">Filter Kategori:</label>
            <select id="filter-kategori" class="rounded-lg border border-border bg-background px-3 py-1.5 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 transition">
                <option value="">Semua Kategori</option>
                @foreach($kategoriList as $k)
                    <option value="{{ $k->id }}">{{ $k->nama }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-card title="Daftar Master Varian Produk" subtitle="Kelola variasi ukuran kemasan, aroma parfum, dan spesifikasi sub-kategori" :noPadding="true">
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
            confirmButtonColor: '#18181b',
            cancelButtonColor: '#71717a',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'rounded-xl border border-border shadow-lg',
                confirmButton: 'rounded-lg text-xs font-medium px-4 py-2',
                cancelButton: 'rounded-lg text-xs font-medium px-4 py-2'
            }
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
                            confirmButtonColor: '#18181b'
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
                {
                    data: 'kategori_nama',
                    render: function(data) {
                        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-muted text-muted-foreground border border-border">' + (data || '-') + '</span>';
                    }
                },
                {
                    data: 'nama',
                    render: function(data) {
                        return '<span class="font-medium text-foreground">' + (data || '-') + '</span>';
                    }
                },
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

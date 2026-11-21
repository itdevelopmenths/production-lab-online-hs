<x-app-layout title="Varian Produk">
    <div x-data="{ createOpen: {{ (($errors->any() && old('_modal') === 'create') || request()->boolean('tambah')) ? 'true' : 'false' }} }">
    <x-page-header
        title="Master Varian Produk"
        subtitle="Daftar variasi aroma, ukuran, atau warna yang terikat pada masing-masing kategori produk"
        :breadcrumbs="['Master Data' => null, 'Varian Produk' => null]"
    >
        <x-slot:actions>
            @can('varian.create')
            <x-button type="button" @click="createOpen = true" variant="primary" size="sm">
                <x-slot:icon>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Varian
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <!-- Filter Bar -->
    <div class="mb-4 rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2.5">
            <label for="filter-kategori" class="font-medium text-[#667085] dark:text-gray-400">Filter Kategori:</label>
            <select id="filter-kategori" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-1.5 text-xs text-[#101828] dark:text-white focus:border-[#111111] dark:focus:border-white outline-none transition cursor-pointer rounded-[6px]">
                <option value="">Semua Kategori</option>
                @foreach($kategoriList as $k)
                    <option value="{{ $k->id }}">{{ $k->nama }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-card class="tbl-modern" title="Daftar Master Varian Produk" subtitle="Kelola variasi ukuran kemasan, aroma parfum, dan spesifikasi sub-kategori" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Daftar Master Varian Produk" class="overflow-x-auto">
            <table id="tbl-varian" class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95">
                    <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-[13px] font-medium">
                        <th class="px-4 py-3 text-left w-48">Kategori Induk</th>
                        <th class="px-4 py-3 text-left">Nama Varian</th>
                        <th class="px-4 py-3 text-left w-36">Penggunaan Katalog</th>
                        <th class="px-4 py-3 text-center w-28">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

    @can('varian.create')
        @include('varian._create-modal')
        @endcan
    </div>

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
                        return data || '-';
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

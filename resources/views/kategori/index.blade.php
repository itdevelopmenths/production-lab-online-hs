<x-app-layout title="Kategori Produk">
    <div x-data="{ createOpen: {{ (($errors->any() && old('_modal') === 'create') || request()->boolean('tambah')) ? 'true' : 'false' }} }">
    <x-page-header
        title="Master Kategori Produk"
        subtitle="Daftar kategori untuk klasifikasi produk manufaktur, kemasan, dan bahan baku"
        :breadcrumbs="['Master Data' => null, 'Kategori Produk' => null]"
    >
        <x-slot:actions>
            @can('kategori.create')
            <x-button type="button" @click="createOpen = true" variant="primary" size="sm">
                <x-slot:icon>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Kategori
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card class="tbl-modern" title="Daftar Kategori Produk" subtitle="Kelola klasifikasi lini produk parfum, kemasan, dan bahan" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Daftar Kategori Produk" class="overflow-x-auto">
            <table id="tbl-kategori" class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95">
                    <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-[13px] font-medium">
                        <th class="px-4 py-3 text-left">Nama Kategori</th>
                        <th class="px-4 py-3 text-left w-36">Jumlah Varian</th>
                        <th class="px-4 py-3 text-left w-36">Jumlah Produk</th>
                        <th class="px-4 py-3 text-center w-28">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

    @can('kategori.create')
        @include('kategori._create-modal')
        @endcan
    </div>

    @push('scripts')
    <script>
    function hapus(url){
        Swal.fire({
            title: 'Hapus kategori ini?',
            text: 'Kategori yang masih memiliki produk atau varian terdaftar tidak dapat dihapus.',
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
                {
                    data: 'nama',
                    render: function(data) {
                        return '<span class="font-medium text-foreground">' + (data || '-') + '</span>';
                    }
                },
                { data: 'varians_count', orderable: false, searchable: false },
                { data: 'produk_count', orderable: false, searchable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

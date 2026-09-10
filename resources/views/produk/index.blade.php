<x-app-layout title="Produk">
    <x-page-header
        title="Master Produk"
        subtitle="Daftar produk jadi, bahan baku konsentrat/alkohol, dan komponen kemasan"
        :breadcrumbs="['Master Data' => null, 'Produk' => null]"
    >
        <x-slot:actions>
            @can('produk.create')
            <x-button href="{{ route('produk.create') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Produk
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card title="Katalog Produk & Bahan Baku" :noPadding="true">
        <table id="tbl" class="w-full text-xs">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">SKU</th>
                    <th class="px-4 py-3 text-left">Nama</th>
                    <th class="px-4 py-3 text-left">Tipe</th>
                    <th class="px-4 py-3 text-left">Satuan</th>
                    <th class="px-4 py-3 text-left">MOQ</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
        </table>
    </x-card>

    @push('scripts')
    <script>
    function hapus(url){
        Swal.fire({
            title: 'Hapus data produk ini?',
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
                .then(res => res.json())
                .then(d => {
                    Swal.fire('Terhapus', d.message, 'success');
                    $('#tbl').DataTable().ajax.reload();
                });
            }
        });
    }

    $(function(){
        $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("produk.data") }}',
            columns: [
                { data: 'sku' },
                { data: 'nama' },
                { data: 'tipe' },
                { data: 'satuan' },
                { data: 'satuan_order_moq', name: 'satuan_order_moq' },
                { data: 'is_active', name: 'is_active' },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

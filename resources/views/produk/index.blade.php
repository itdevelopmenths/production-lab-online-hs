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

    <div class="mb-4" x-data="{ currentTab: 'all' }">
        <div class="border-b border-gray-200 bg-white px-3 py-2 rounded-t-sm shadow-2xs">
            <nav class="flex space-x-2" aria-label="Tabs">
                <button type="button"
                    @click="currentTab = 'all'; filterTab('all')"
                    :class="currentTab === 'all' ? 'bg-primary-50 text-primary-700 border-primary-500 font-semibold' : 'text-gray-500 hover:text-gray-700 border-transparent'"
                    class="px-3 py-1.5 text-xs rounded-sm border-b-2 transition cursor-pointer">
                    Semua Produk
                </button>
                <button type="button"
                    @click="currentTab = 'bahan'; filterTab('bahan')"
                    :class="currentTab === 'bahan' ? 'bg-primary-50 text-primary-700 border-primary-500 font-semibold' : 'text-gray-500 hover:text-gray-700 border-transparent'"
                    class="px-3 py-1.5 text-xs rounded-sm border-b-2 transition cursor-pointer">
                    Bahan Baku
                </button>
                <button type="button"
                    @click="currentTab = 'kemas'; filterTab('kemas')"
                    :class="currentTab === 'kemas' ? 'bg-primary-50 text-primary-700 border-primary-500 font-semibold' : 'text-gray-500 hover:text-gray-700 border-transparent'"
                    class="px-3 py-1.5 text-xs rounded-sm border-b-2 transition cursor-pointer">
                    Komponen Kemasan
                </button>
                <button type="button"
                    @click="currentTab = 'produk_jadi'; filterTab('produk_jadi')"
                    :class="currentTab === 'produk_jadi' ? 'bg-primary-50 text-primary-700 border-primary-500 font-semibold' : 'text-gray-500 hover:text-gray-700 border-transparent'"
                    class="px-3 py-1.5 text-xs rounded-sm border-b-2 transition cursor-pointer">
                    Produk Jadi
                </button>
            </nav>
        </div>

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
    </div>

    @push('scripts')
    <script>
    let activeTipe = 'all';
    let table;

    function filterTab(tipe) {
        activeTipe = tipe;
        if (table) {
            table.ajax.reload();
        }
    }

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
        table = $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("produk.data") }}',
                data: function(d) {
                    d.tipe = activeTipe;
                }
            },
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

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

    <div x-data="{ currentTab: 'all' }">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-5 flex items-center justify-between">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                <button
                    type="button"
                    @click="currentTab = 'all'; filterTab('all')"
                    :class="currentTab === 'all' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                    Semua Produk
                </button>

                <button
                    type="button"
                    @click="currentTab = 'bahan'; filterTab('bahan')"
                    :class="currentTab === 'bahan' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                    Bahan Baku
                </button>

                <button
                    type="button"
                    @click="currentTab = 'kemas'; filterTab('kemas')"
                    :class="currentTab === 'kemas' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                    Komponen Kemasan
                </button>

                <button
                    type="button"
                    @click="currentTab = 'produk_jadi'; filterTab('produk_jadi')"
                    :class="currentTab === 'produk_jadi' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" /></svg>
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

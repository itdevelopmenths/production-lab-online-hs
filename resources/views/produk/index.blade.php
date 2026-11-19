<x-app-layout title="Produk">
    <div x-data="{ currentTab: 'all', createOpen: {{ (($errors->any() && old('_modal') === 'create') || request()->boolean('tambah')) ? 'true' : 'false' }} }" class="space-y-4">
        <x-page-header
            title="Master Produk"
            :breadcrumbs="['Master Data' => null, 'Produk' => null]"
        >
            <x-slot:actions>
                @can('produk.create')
                <x-button type="button" @click="createOpen = true" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Tambah Produk
                </x-button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-card class="tbl-modern" title="Katalog Produk & Material" subtitle="Kelola SKU bahan baku, kemasan, dan produk jadi beserta parameter pengadaan" :noPadding="true">
            <x-slot:icon>
                <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </x-slot:icon>

            {{-- Filter Bar & Segmented Tabs (Blueprint StatusTabs) --}}
            <div class="px-4 py-3 border-b border-[#E8E8E8] dark:border-gray-800 bg-[#fafafa] dark:bg-gray-900/60 flex flex-col md:flex-row md:items-center justify-between gap-3">
                {{-- Segmented Pill Tabs --}}
                <div class="inline-flex flex-wrap items-center gap-1 p-1 bg-[#f3f4f6] dark:bg-gray-950 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800">
                    <button type="button"
                            @click="currentTab = 'all'; filterTab('all')"
                            :class="currentTab === 'all' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white shadow-[0_1px_2px_rgba(0,0,0,0.06)] font-medium' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white'"
                            class="px-3 py-1.5 text-xs font-medium transition-all cursor-pointer select-none rounded-[6px]">
                        Semua Produk
                    </button>
                    <button type="button"
                            @click="currentTab = 'bahan'; filterTab('bahan')"
                            :class="currentTab === 'bahan' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white shadow-[0_1px_2px_rgba(0,0,0,0.06)] font-medium' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white'"
                            class="px-3 py-1.5 text-xs font-medium transition-all cursor-pointer select-none rounded-[6px]">
                        Bahan Baku
                    </button>
                    <button type="button"
                            @click="currentTab = 'kemas'; filterTab('kemas')"
                            :class="currentTab === 'kemas' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white shadow-[0_1px_2px_rgba(0,0,0,0.06)] font-medium' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white'"
                            class="px-3 py-1.5 text-xs font-medium transition-all cursor-pointer select-none rounded-[6px]">
                        Kemasan
                    </button>
                    <button type="button"
                            @click="currentTab = 'produk_jadi'; filterTab('produk_jadi')"
                            :class="currentTab === 'produk_jadi' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white shadow-[0_1px_2px_rgba(0,0,0,0.06)] font-medium' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white'"
                            class="px-3 py-1.5 text-xs font-medium transition-all cursor-pointer select-none rounded-[6px]">
                        Produk Jadi
                    </button>
                </div>

                {{-- Additional Filter --}}
                <div class="flex items-center gap-2">
                    <label for="filter-kategori" class="text-xs font-medium text-[#667085] dark:text-gray-400">Kategori:</label>
                    <select id="filter-kategori" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 px-3 text-xs text-[#101828] dark:text-white outline-none focus:border-[#111111] dark:focus:border-white cursor-pointer rounded-[6px]">
                        <option value="">Semua Kategori</option>
                        @foreach($kategoriList as $k)
                            <option value="{{ $k->id }}">{{ $k->nama }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Table --}}
            <div tabindex="0" role="region" aria-label="Tabel Katalog Produk" class="overflow-x-auto">
                <table id="tbl" class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95">
                        <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-[13px] font-medium">
                            <th class="px-4 py-3 text-left w-28">SKU</th>
                            <th class="px-4 py-3 text-left">Nama Produk</th>
                            <th class="px-4 py-3 text-left w-32">Kategori</th>
                            <th class="px-4 py-3 text-left w-32">Varian</th>
                            <th class="px-4 py-3 text-left w-28">Tipe</th>
                            <th class="px-4 py-3 text-center w-28">Supplier</th>
                            <th class="px-4 py-3 text-center w-20">Satuan</th>
                            <th class="px-4 py-3 text-center w-32">Analisa / ABC</th>
                            <th class="px-4 py-3 text-center w-24">Status</th>
                            <th class="px-4 py-3 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </x-card>

        @can('produk.create')
        @include('produk._create-modal')
        @endcan
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
            text: 'Data produk yang memiliki histori transaksi stok tidak dapat dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#18181b',
            cancelButtonColor: '#71717a',
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
                        Swal.fire({ icon: 'success', title: 'Terhapus', text: d.message, confirmButtonColor: '#18181b' });
                        $('#tbl').DataTable().ajax.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal Menghapus', text: d.message || 'Terjadi kesalahan.', confirmButtonColor: '#18181b' });
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Kesalahan Sistem', text: 'Tidak dapat terhubung ke server.', confirmButtonColor: '#18181b' });
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
                    d.kategori_id = $('#filter-kategori').val();
                }
            },
            columns: [
                { data: 'sku', name: 'sku', searchable: true, className: 'px-4 py-3.5 align-middle text-left tabular-nums text-xs' },
                { data: 'nama', name: 'nama', searchable: true, className: 'px-4 py-3.5 align-middle text-left font-medium' },
                { data: 'kategori_nama', name: 'kategori_nama', orderable: false, searchable: false, className: 'px-4 py-3.5 align-middle text-left' },
                { data: 'varian_nama', name: 'varian_nama', orderable: false, searchable: false, className: 'px-4 py-3.5 align-middle text-left' },
                { data: 'tipe', name: 'tipe', searchable: false, className: 'px-4 py-3.5 align-middle text-left' },
                { data: 'supplier_count', name: 'supplier_count', orderable: false, searchable: false, className: 'px-4 py-3.5 align-middle text-center' },
                { data: 'satuan', name: 'satuan', searchable: false, className: 'px-4 py-3.5 align-middle text-center tabular-nums text-xs' },
                { data: 'klasifikasi_abc_badge', name: 'klasifikasi_abc_id', orderable: false, searchable: false, className: 'px-4 py-3.5 align-middle text-center' },
                { data: 'is_active', name: 'is_active', searchable: false, className: 'px-4 py-3.5 align-middle text-center' },
                { data: 'action', orderable: false, searchable: false, className: 'px-4 py-3.5 align-middle text-center' }
            ]
        });

        $('#filter-kategori').on('change', function() {
            table.ajax.reload();
        });
    });
    </script>
    @endpush
</x-app-layout>

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

    <div x-data="{ currentTab: 'varian' }">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-5 flex items-center justify-between">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                <button
                    type="button"
                    @click="currentTab = 'varian'"
                    :class="currentTab === 'varian' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    Master Varian
                </button>

                <button
                    type="button"
                    @click="currentTab = 'audit'; $nextTick(() => { if (window.tblAuditVarian) window.tblAuditVarian.columns.adjust().draw(false); })"
                    :class="currentTab === 'audit' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Riwayat Audit
                </button>
            </nav>
        </div>

        <div x-show="currentTab === 'varian'">
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
        </div>

        <div x-show="currentTab === 'audit'" x-cloak>
            <x-master-audit-tab entity="varian" />
        </div>
    </div>

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

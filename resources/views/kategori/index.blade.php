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

    <div x-data="{ currentTab: 'kategori' }">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-5 flex items-center justify-between">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                <button
                    type="button"
                    @click="currentTab = 'kategori'"
                    :class="currentTab === 'kategori' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Master Kategori
                </button>

                @canany(['produk.audit', 'audit.view'])
                <button
                    type="button"
                    @click="currentTab = 'audit'; $nextTick(() => { if (window.tblAuditKategori) window.tblAuditKategori.columns.adjust().draw(false); })"
                    :class="currentTab === 'audit' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Riwayat Audit
                </button>
                @endcanany
            </nav>
        </div>

        <div x-show="currentTab === 'kategori'">
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
        </div>

        @canany(['produk.audit', 'audit.view'])
        <div x-show="currentTab === 'audit'" x-cloak>
            <x-master-audit-tab entity="kategori" />
        </div>
        @endcanany
    </div>

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

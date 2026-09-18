<x-app-layout title="Satuan (UOM)">
    <x-page-header
        title="Master Satuan Unit (UOM)"
        subtitle="Daftar unit pengukuran standar untuk bahan baku kimia, kemasan, dan produk jadi manufaktur"
        :breadcrumbs="['Master Data' => null, 'Satuan (UOM)' => null]"
    >
        <x-slot:actions>
            @can('uom.create')
            <x-button href="{{ route('uom.create') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Satuan Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div x-data="{ currentTab: 'uom' }">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-5 flex items-center justify-between">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                <button
                    type="button"
                    @click="currentTab = 'uom'"
                    :class="currentTab === 'uom' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                    Master Satuan UOM
                </button>

                @canany(['uom.audit', 'audit.view'])
                <button
                    type="button"
                    @click="currentTab = 'audit'; $nextTick(() => { if (window.tblAuditUom) window.tblAuditUom.columns.adjust().draw(false); })"
                    :class="currentTab === 'audit' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Riwayat Audit
                </button>
                @endcanany
            </nav>
        </div>

        <div x-show="currentTab === 'uom'">
            <x-card title="Daftar Master Satuan Unit (Unit of Measure)" :noPadding="true">
                <div class="overflow-x-auto">
                    <table id="tbl" class="w-full text-xs">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left w-32">Kode Satuan</th>
                                <th class="px-4 py-3 text-left">Nama Satuan</th>
                                <th class="px-4 py-3 text-left w-36">Kategori Dimensi</th>
                                <th class="px-4 py-3 text-left w-48">Konversi Acuan</th>
                                <th class="px-4 py-3 text-left w-36">Penggunaan Katalog</th>
                                <th class="px-4 py-3 text-center w-28">Status</th>
                                <th class="px-4 py-3 text-center w-28">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </x-card>
        </div>

        <!-- TAB 2: Riwayat Audit UOM -->
        @canany(['uom.audit', 'audit.view'])
        <div x-show="currentTab === 'audit'" x-cloak>
            <x-master-audit-tab entity="uom" />
        </div>
        @endcanany
    </div>

    @push('scripts')
    <script>
    function hapus(url){
        Swal.fire({
            title: 'Hapus satuan UOM ini?',
            text: 'Data yang sedang digunakan oleh produk katalog tidak dapat dihapus.',
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
                        $('#tbl').DataTable().ajax.reload();
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
        $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("uom.data") }}',
            columns: [
                { data: 'kode' },
                { data: 'nama' },
                { data: 'kategori' },
                { data: 'konversi' },
                { data: 'produk_count', orderable: false, searchable: false },
                { data: 'is_active', className: 'text-center' },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

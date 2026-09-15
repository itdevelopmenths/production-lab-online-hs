<x-app-layout title="BOM">
    <x-page-header
        title="Bill of Materials (BOM)"
        subtitle="Spesifikasi formula resep per unit produk jadi (konsentrasi minyak wangi, pelarut alkohol, botol, dan atomizer)"
        :breadcrumbs="['Master Data' => null, 'BOM' => null]"
    >
        <x-slot:actions>
            @can('bom.import')
            <x-button href="{{ route('bom.import-page') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </x-slot:icon>
                Impor Formula BOM
            </x-button>
            <x-button href="{{ route('bom.template') }}" variant="secondary" size="xs">
                Unduh Template CSV
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div x-data="{ currentTab: 'bom' }">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-5 flex items-center justify-between">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                <button
                    type="button"
                    @click="currentTab = 'bom'"
                    :class="currentTab === 'bom' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Daftar Formula BOM
                </button>

                <button
                    type="button"
                    @click="currentTab = 'audit'; $nextTick(() => { if (window.tblAuditBom) window.tblAuditBom.columns.adjust().draw(false); })"
                    :class="currentTab === 'audit' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Riwayat Audit
                </button>
            </nav>
        </div>

        <div x-show="currentTab === 'bom'">
            <div class="mb-4">
                <x-alert type="info" :icon="true">
                    <span>Format formula resep per unit produk jadi. Untuk memperbarui resep secara massal, gunakan menu <strong><a href="{{ route('bom.import-page') }}" class="underline font-bold">Impor Formula BOM</a></strong> atau unduh template CSV resmi.</span>
                </x-alert>
            </div>

            <x-card title="Daftar Resep Produk Jadi (BOM)" :noPadding="true">
                <table id="tbl" class="w-full text-xs">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">SKU Produk</th>
                            <th class="px-4 py-3 text-left">Nama Produk Jadi</th>
                            <th class="px-4 py-3 text-right">Jumlah Komponen Bahan</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </x-card>
        </div>

        <div x-show="currentTab === 'audit'" x-cloak>
            <x-master-audit-tab entity="bom" />
        </div>
    </div>

    @push('scripts')
    <script>
    $(function(){
        $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("bom.data") }}',
            columns: [
                { data: 'sku' },
                { data: 'nama' },
                { data: 'bom_count', className: 'text-right font-mono font-semibold', searchable: false, orderable: false },
                { data: 'action', className: 'text-center', orderable: false, searchable: false }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

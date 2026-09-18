<x-app-layout title="Produksi">
    <x-page-header
        title="Batch Produksi"
        subtitle="Daftar rencana kerja, alokasi resep BOM, dan eksekusi produksi pabrik"
        :breadcrumbs="['Produksi' => null]"
    >
        <x-slot:actions>
            @can('batch.create')
            <x-button href="{{ route('batches.create') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Rencana Batch Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div x-data="{ currentTab: 'batch' }">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-5 flex items-center justify-between">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                <button
                    type="button"
                    @click="currentTab = 'batch'"
                    :class="currentTab === 'batch' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Daftar Batch Produksi
                </button>

                @canany(['batch.audit', 'audit.view'])
                <button
                    type="button"
                    @click="currentTab = 'audit'; $nextTick(() => { if (window.tblOperationalAuditProduksi) window.tblOperationalAuditProduksi.columns.adjust().draw(false); })"
                    :class="currentTab === 'audit' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Riwayat Audit
                </button>
                @endcanany
            </nav>
        </div>

        <!-- TAB 1: Daftar Batch Produksi -->
        <div x-show="currentTab === 'batch'">
            <x-card title="Daftar Batch Produksi" :noPadding="true">
                <table id="tbl" class="w-full text-xs">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">No Batch</th>
                            <th class="px-4 py-3 text-left">Produk Luaran</th>
                            <th class="px-4 py-3 text-right">Rencana</th>
                            <th class="px-4 py-3 text-right">Baik</th>
                            <th class="px-4 py-3 text-right">Rusak</th>
                            <th class="px-4 py-3 text-center">Yield</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </x-card>
        </div>

        <!-- TAB 2: Riwayat Audit Produksi -->
        @canany(['batch.audit', 'audit.view'])
        <div x-show="currentTab === 'audit'" x-cloak>
            <x-operational-audit-tab module="produksi" title="Log Riwayat Audit Modul Produksi" subtitle="Rekaman otomatis pembuatan rencana batch, pelepasan alokasi resep BOM (issue), opname, dan penyelesaian luaran" />
        </div>
        @endcanany
    </div>

    @push('scripts')
    <script>
    $(function(){
        $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("batches.data") }}',
            columns: [
                { data: 'no_batch' },
                { data: 'produk_nama', orderable: false },
                { data: 'qty_rencana', className: 'text-right font-mono' },
                { data: 'qty_baik', className: 'text-right font-mono font-semibold text-emerald-700', orderable: false, searchable: false },
                { data: 'qty_rusak', className: 'text-right font-mono font-semibold text-rose-700', orderable: false, searchable: false },
                { data: 'yield', orderable: false, searchable: false, className: 'text-center font-mono font-bold' },
                { data: 'status', className: 'text-center', orderable: false },
                { data: 'tanggal' },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

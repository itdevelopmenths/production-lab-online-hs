<x-app-layout title="Request & Transfer">
    <x-page-header
        title="Request & Transfer"
        subtitle="Logistik mutasi internal, permintaan bahan baku, dan pengiriman produk jadi"
        :breadcrumbs="['Request & Transfer' => null]"
    >
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <select id="jenisFilter" class="rounded-sm border-gray-300 text-xs py-1 px-2.5 bg-white shadow-xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua Jenis Mutasi</option>
                    <option value="req_bahan">Request Bahan</option>
                    <option value="retur_bahan">Retur Bahan</option>
                    <option value="kirim_produk_jadi">Kirim Produk Jadi</option>
                    <option value="antar_fulfillment">Antar Fulfillment</option>
                    <option value="retur_produk_jadi">Retur Produk Jadi</option>
                </select>

                @can('rt.create')
                <x-button href="{{ route('rt.create') }}" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Buat Transfer Baru
                </x-button>
                @endcan
            </div>
        </x-slot:actions>
    </x-page-header>

    <div x-data="{ currentTab: 'dokumen' }">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-5 flex items-center justify-between">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                <button
                    type="button"
                    @click="currentTab = 'dokumen'"
                    :class="currentTab === 'dokumen' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    Daftar Dokumen Mutasi
                </button>

                @canany(['rt.audit', 'audit.view'])
                <button
                    type="button"
                    @click="currentTab = 'audit'; $nextTick(() => { if (window.tblOperationalAuditRequesttransfer) window.tblOperationalAuditRequesttransfer.columns.adjust().draw(false); })"
                    :class="currentTab === 'audit' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Riwayat Audit
                </button>
                @endcanany
            </nav>
        </div>

        <!-- TAB 1: Daftar Dokumen Mutasi & Transfer -->
        <div x-show="currentTab === 'dokumen'">
            <x-card title="Daftar Dokumen Mutasi & Transfer" :noPadding="true">
                <table id="tbl" class="w-full text-xs">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">No Transaksi</th>
                            <th class="px-4 py-3 text-left">Jenis</th>
                            <th class="px-4 py-3 text-left">Gudang Asal</th>
                            <th class="px-4 py-3 text-left">Gudang Tujuan</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </x-card>
        </div>

        <!-- TAB 2: Riwayat Audit Mutasi -->
        @canany(['rt.audit', 'audit.view'])
        <div x-show="currentTab === 'audit'" x-cloak>
            <x-operational-audit-tab module="request_transfer" title="Log Riwayat Audit Request & Transfer" subtitle="Rekaman otomatis permintaan bahan baku pabrik, alur persetujuan, surat jalan pengiriman, dan serah terima gudang" />
        </div>
        @endcanany
    </div>

    @push('scripts')
    <script>
    $(function(){
        var t = $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("rt.data") }}',
                data: function(d) {
                    d.jenis = $('#jenisFilter').val();
                }
            },
            columns: [
                { data: 'no_transaksi' },
                { data: 'jenis' },
                { data: 'asal', orderable: false, searchable: false },
                { data: 'tujuan', orderable: false, searchable: false },
                { data: 'status', className: 'text-center' },
                { data: 'created_at' },
                { data: 'action', orderable: false, searchable: false, className: 'text-center whitespace-nowrap' }
            ]
        });
        $('#jenisFilter').on('change', () => t.ajax.reload());
    });
    </script>
    @endpush
</x-app-layout>

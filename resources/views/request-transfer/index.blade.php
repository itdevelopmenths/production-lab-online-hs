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
                { data: 'status' },
                { data: 'created_at' },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });
        $('#jenisFilter').on('change', () => t.ajax.reload());
    });
    </script>
    @endpush
</x-app-layout>

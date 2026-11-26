<x-app-layout title="Transfer & Mutasi Barang">
    <div>
        <x-page-header
            title="Transfer & Mutasi Barang"
            :breadcrumbs="['Transfer' => null]"
        >
            <x-slot:actions>
                @can('transfer.create')
                <x-button href="{{ route('transfers.create') }}" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="size-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Buat Transfer
                </x-button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        {{-- Filter Bar --}}
        <div class="mb-3.5 flex flex-wrap items-center gap-2.5 bg-white dark:bg-gray-900 px-4 py-3 rounded-[12px] border border-[#E8E8E8] dark:border-border shadow-theme-xs">
            <div class="flex flex-wrap items-center gap-2.5">
                <label for="filterTransferStatus" class="sr-only">Filter Status Transfer</label>
                <select id="filterTransferStatus" class="h-8 border border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 text-xs py-1 px-2.5 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white outline-none cursor-pointer rounded-[6px]">
                    <option value="">Semua Status</option>
                    <option value="draft">Draft</option>
                    <option value="dikirim">In Transit (Dikirim)</option>
                    <option value="selesai">Selesai Diterima</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>

                <label for="filterTransferJenis" class="sr-only">Filter Jenis Transfer</label>
                <select id="filterTransferJenis" class="h-8 border border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 text-xs py-1 px-2.5 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white outline-none cursor-pointer rounded-[6px]">
                    <option value="">Semua Jenis Transfer</option>
                    @foreach(\App\Models\Transfer::JENIS_LABELS as $jKey => $jLbl)
                        <option value="{{ $jKey }}">{{ $jLbl }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Daftar Transaksi Transfer Antar Gudang --}}
        <x-card class="tbl-modern" title="Daftar Dokumen Transfer" subtitle="Riwayat mutasi stok, penerimaan, dan tracking defect antar fasilitas" :noPadding="true">
            <x-slot:icon>
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            </x-slot:icon>
            <div class="overflow-x-auto">
                <table id="tblTransfers" class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">No Transfer</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-left">Jenis</th>
                            <th class="px-4 py-3 text-left">Gudang Asal</th>
                            <th class="px-4 py-3 text-left">Gudang Tujuan</th>
                            <th class="px-4 py-3 text-left">Ref Request</th>
                            <th class="px-4 py-3 text-right">Qty Kirim</th>
                            <th class="px-4 py-3 text-right">Qty Rusak</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center w-44">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </x-card>
    </div>

    @push('scripts')
    <script>
        $(document).ready(function() {
            const table = $('#tblTransfers').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("transfers.data") }}',
                    data: function(d) {
                        d.status = $('#filterTransferStatus').val();
                        d.jenis = $('#filterTransferJenis').val();
                    }
                },
                columns: [
                    { data: 'no_transfer', name: 'no_transaksi', className: 'tabular-nums text-xs font-medium text-primary-600' },
                    { data: 'tanggal_transfer', name: 'created_at' },
                    { data: 'jenis', name: 'jenis' },
                    { data: 'asal', name: 'gudangAsal.nama' },
                    { data: 'tujuan', name: 'gudangTujuan.nama' },
                    { data: 'request_no', name: 'stockRequest.no_request' },
                    { data: 'qty_dikirim', name: 'qty_dikirim', orderable: false, searchable: false, className: 'text-right font-medium' },
                    { data: 'qty_rusak', name: 'qty_rusak', orderable: false, searchable: false, className: 'text-right' },
                    { data: 'status', name: 'status' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center whitespace-nowrap' }
                ],
                order: [[0, 'desc']]
            });

            $('#filterTransferStatus, #filterTransferJenis').on('change', function() {
                table.draw();
            });
        });
    </script>
    @endpush
</x-app-layout>


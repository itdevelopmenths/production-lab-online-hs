<x-app-layout title="Log Pergerakan Stok">
    <x-page-header
        title="Log Pergerakan Stok"
        subtitle="Jejak audit menyeluruh atas mutasi masuk (IN) dan keluar (OUT) seluruh barang di gudang"
        :breadcrumbs="['Stok & Mutasi' => route('stok.index'), 'Log Pergerakan' => null]"
        :backUrl="route('stok.index')"
        backSize="xs"
    />

    {{-- Filter Toolbar --}}
    <div class="mb-4 bg-white dark:bg-gray-900 p-4 rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)] flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="filter-gudang" class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Lokasi Gudang</label>
                <select id="filter-gudang" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-40 max-w-full outline-none cursor-pointer rounded-[6px]">
                    <option value="">— Semua Gudang —</option>
                    @foreach($gudang as $g)
                        <option value="{{ $g->id }}">{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-tipe" class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Tipe Mutasi</label>
                <select id="filter-tipe" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-36 max-w-full outline-none cursor-pointer rounded-[6px]">
                    <option value="">— Semua Tipe —</option>
                    <option value="in">Masuk (IN)</option>
                    <option value="out">Keluar (OUT)</option>
                </select>
            </div>

            <div>
                <label for="filter-tgl-mulai" class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Dari Tanggal</label>
                <input type="date" id="filter-tgl-mulai" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white outline-none rounded-[6px]">
            </div>

            <div>
                <label for="filter-tgl-selesai" class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Sampai Tanggal</label>
                <input type="date" id="filter-tgl-selesai" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white outline-none rounded-[6px]">
            </div>
        </div>

        <div>
            <x-button type="button" id="btn-reset" variant="secondary" size="xs">
                Reset Filter
            </x-button>
        </div>
    </div>

    {{-- Main DataTable Card --}}
    <x-card title="Buku Audit Mutasi Fisik Barang" subtitle="Histori perubahan kuantitas, saldo sebelum dan sesudah mutasi, nomor referensi, serta penanggung jawab transaksi" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Buku Audit Mutasi Fisik Barang" class="overflow-x-auto">
            <table id="tblLog" class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95">
                    <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-[13px] font-medium">
                        <th class="px-4 py-3 text-center w-28">Tipe</th>
                        <th class="px-4 py-3 text-left">Item</th>
                        <th class="px-4 py-3 text-left">Lokasi Gudang</th>
                        <th class="px-4 py-3 text-left">Ref</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-left">Sebelum &rarr; Sesudah</th>
                        <th class="px-4 py-3 text-left">Tanggal</th>
                        <th class="px-4 py-3 text-left">Oleh</th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

    @push('scripts')
    <script>
    let dtLogTable = null;

    $(function() {
        dtLogTable = $('#tblLog').DataTable({
            processing: true,
            serverSide: true,
            order: [[6, 'desc']], // Urutkan tanggal transaksi terbaru secara default
            ajax: {
                url: '{{ route("stok.log-pergerakan.data") }}',
                data: function(d) {
                    d.gudang_id = $('#filter-gudang').val();
                    d.tipe = $('#filter-tipe').val();
                    d.tanggal_mulai = $('#filter-tgl-mulai').val();
                    d.tanggal_selesai = $('#filter-tgl-selesai').val();
                }
            },
            columns: [
                { data: 'tipe', className: 'text-center', orderable: false, searchable: false },
                { data: 'item', orderable: false, searchable: false },
                { data: 'lokasi_gudang', orderable: false, searchable: false },
                { data: 'ref', orderable: false, searchable: false },
                { data: 'qty', className: 'text-right', orderable: false, searchable: false },
                { data: 'sebelum_sesudah', orderable: false, searchable: false },
                { data: 'tanggal' },
                { data: 'oleh', orderable: false, searchable: false }
            ]
        });

        $('#filter-gudang, #filter-tipe, #filter-tgl-mulai, #filter-tgl-selesai').on('change', function() {
            if (dtLogTable) dtLogTable.ajax.reload();
        });

        $('#btn-reset').on('click', function() {
            $('#filter-gudang').val('');
            $('#filter-tipe').val('');
            $('#filter-tgl-mulai').val('');
            $('#filter-tgl-selesai').val('');
            if (dtLogTable) dtLogTable.ajax.reload();
        });
    });
    </script>
    @endpush
</x-app-layout>

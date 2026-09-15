<x-app-layout title="Log Pergerakan Stok">
    <x-page-header
        title="Log Pergerakan Stok"
        subtitle="Jejak audit menyeluruh atas mutasi masuk (IN) dan keluar (OUT) seluruh barang di gudang"
        :breadcrumbs="['Stok & Mutasi' => route('stok.index'), 'Log Pergerakan' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('stok.index') }}" variant="secondary" size="xs">
                &larr; Lihat Saldo Stok
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Filter Toolbar --}}
    <div class="mb-4 bg-white p-3 rounded-sm border border-gray-200 shadow-xs flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="filter-gudang" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Lokasi Gudang</label>
                <select id="filter-gudang" class="rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 min-w-[170px]">
                    <option value="">— Semua Gudang —</option>
                    @foreach($gudang as $g)
                        <option value="{{ $g->id }}">{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-tipe" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Tipe Mutasi</label>
                <select id="filter-tipe" class="rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 min-w-[140px]">
                    <option value="">— Semua Tipe —</option>
                    <option value="in">🟢 Masuk (IN)</option>
                    <option value="out">🔴 Keluar (OUT)</option>
                </select>
            </div>

            <div>
                <label for="filter-tgl-mulai" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Dari Tanggal</label>
                <input type="date" id="filter-tgl-mulai" class="rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
            </div>

            <div>
                <label for="filter-tgl-selesai" class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Sampai Tanggal</label>
                <input type="date" id="filter-tgl-selesai" class="rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
            </div>
        </div>

        <div>
            <button type="button" id="btn-reset" class="text-xs text-gray-500 hover:text-gray-800 underline font-medium cursor-pointer">
                Reset Filter
            </button>
        </div>
    </div>

    {{-- Main DataTable Card --}}
    <x-card title="Buku Audit Mutasi Fisik Barang" subtitle="Histori perubahan kuantitas, saldo sebelum dan sesudah mutasi, nomor referensi, serta penanggung jawab transaksi" :noPadding="true">
        <div class="overflow-x-auto p-2">
            <table id="tblLog" class="w-full text-xs">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-[11px] uppercase">
                        <th class="px-3 py-2.5 text-center w-28">Tipe</th>
                        <th class="px-3 py-2.5 text-left">Item</th>
                        <th class="px-3 py-2.5 text-left">Lokasi Gudang</th>
                        <th class="px-3 py-2.5 text-left">Ref</th>
                        <th class="px-3 py-2.5 text-right">Qty</th>
                        <th class="px-3 py-2.5 text-left">Sebelum &rarr; Sesudah</th>
                        <th class="px-3 py-2.5 text-left">Tanggal</th>
                        <th class="px-3 py-2.5 text-left">Oleh</th>
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
                { data: 'tipe', className: 'text-center' },
                { data: 'item' },
                { data: 'lokasi_gudang' },
                { data: 'ref' },
                { data: 'qty', className: 'text-right' },
                { data: 'sebelum_sesudah' },
                { data: 'tanggal' },
                { data: 'oleh' }
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

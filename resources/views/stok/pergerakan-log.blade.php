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
    <div class="mb-4 bg-card p-4 rounded-xl border border-border shadow-xs flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label for="filter-gudang" class="block text-xs font-medium text-muted-foreground mb-1">Lokasi Gudang</label>
                <select id="filter-gudang" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground min-w-[180px] outline-none cursor-pointer">
                    <option value="">— Semua Gudang —</option>
                    @foreach($gudang as $g)
                        <option value="{{ $g->id }}">{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-tipe" class="block text-xs font-medium text-muted-foreground mb-1">Tipe Mutasi</label>
                <select id="filter-tipe" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground min-w-[150px] outline-none cursor-pointer">
                    <option value="">— Semua Tipe —</option>
                    <option value="in">🟢 Masuk (IN)</option>
                    <option value="out">🔴 Keluar (OUT)</option>
                </select>
            </div>

            <div>
                <label for="filter-tgl-mulai" class="block text-xs font-medium text-muted-foreground mb-1">Dari Tanggal</label>
                <input type="date" id="filter-tgl-mulai" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground outline-none">
            </div>

            <div>
                <label for="filter-tgl-selesai" class="block text-xs font-medium text-muted-foreground mb-1">Sampai Tanggal</label>
                <input type="date" id="filter-tgl-selesai" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground outline-none">
            </div>
        </div>

        <div>
            <button type="button" id="btn-reset" class="text-xs text-muted-foreground hover:text-foreground font-medium cursor-pointer transition-colors">
                Reset Filter
            </button>
        </div>
    </div>

    {{-- Main DataTable Card --}}
    <x-card title="Buku Audit Mutasi Fisik Barang" subtitle="Histori perubahan kuantitas, saldo sebelum dan sesudah mutasi, nomor referensi, serta penanggung jawab transaksi" :noPadding="true">
        <div class="overflow-x-auto">
            <table id="tblLog" class="w-full text-sm">
                <thead>
                    <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[13px] font-medium">
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

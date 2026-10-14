<x-app-layout title="Kartu Stok">
    <x-page-header
        title="Kartu Stok — {{ $produk->nama }}"
        subtitle="Riwayat audit saldo mutasi fisik produk SKU {{ $produk->sku }}"
        :breadcrumbs="['Stok & Mutasi' => route('stok.index'), 'Kartu Stok' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('stok.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Stok
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-card p-4 rounded-xl border border-border shadow-xs">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">Lokasi Gudang</label>
                <select id="filter-gudang" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground min-w-[200px] outline-none cursor-pointer">
                    <option value="">— Semua Gudang —</option>
                    @foreach($gudangs as $g)
                        <option value="{{ $g->id }}" @selected($selectedGudangId == $g->id)>{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>
            @if($selectedGudang)
                <div class="pt-4 sm:pt-0 self-end sm:self-center">
                    <x-badge variant="info">
                        Lokasi Terpilih: {{ $selectedGudang->nama }}
                    </x-badge>
                </div>
            @endif
        </div>
        <div class="text-right">
            <span class="text-xs text-muted-foreground font-medium">Buku Besar Pergerakan Saldo (Ledger)</span>
        </div>
    </div>

    <x-card title="Buku Besar Pergerakan Saldo (Ledger)" subtitle="Riwayat audit saldo mutasi fisik produk SKU {{ $produk->sku }}" :noPadding="true">
        <div class="overflow-x-auto">
            <table id="tbl" class="w-full text-sm">
                <thead>
                    <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[13px] font-medium">
                        <th class="px-4 py-3 text-left">Tanggal</th>
                        <th class="px-4 py-3 text-left">Gudang</th>
                        <th class="px-4 py-3 text-left">Tipe</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Saldo</th>
                        <th class="px-4 py-3 text-left">Referensi</th>
                        <th class="px-4 py-3 text-left">Catatan</th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

    @push('scripts')
    <script>
    $(function(){
        const tbl = $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("stok.ledger.data", $produk) }}',
                data: function(d) {
                    d.gudang_id = $('#filter-gudang').val();
                }
            },
            order: [],
            columns: [
                { data: 'tanggal', orderable: false },
                { data: 'gudang_nama', orderable: false },
                { data: 'tipe', orderable: false },
                { data: 'qty', className: 'text-right font-mono' },
                { data: 'saldo_setelah', className: 'text-right font-mono font-semibold' },
                { data: 'referensi_tipe', orderable: false },
                { data: 'catatan', orderable: false }
            ]
        });

        $('#filter-gudang').on('change', function(){
            tbl.ajax.reload();
        });
    });
    </script>
    @endpush
</x-app-layout>

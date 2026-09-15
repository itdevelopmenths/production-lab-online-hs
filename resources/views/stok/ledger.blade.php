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

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3 rounded-sm border border-gray-200">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Filter Lokasi Gudang</label>
                <select id="filter-gudang" class="rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 min-w-[200px]">
                    <option value="">— Semua Gudang —</option>
                    @foreach($gudangs as $g)
                        <option value="{{ $g->id }}" @selected($selectedGudangId == $g->id)>{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>
            @if($selectedGudang)
                <div class="pt-4 sm:pt-0 self-end sm:self-center">
                    <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                        <svg class="w-3.5 h-3.5 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Lokasi Terpilih: {{ $selectedGudang->nama }}
                    </span>
                </div>
            @endif
        </div>
        <div class="text-right">
            <span class="text-xs text-gray-400 font-medium">Buku Besar Pergerakan Saldo (Ledger)</span>
        </div>
    </div>

    <x-card title="Buku Besar Pergerakan Saldo (Ledger)" :noPadding="true">
        <table id="tbl" class="w-full text-xs">
            <thead>
                <tr>
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

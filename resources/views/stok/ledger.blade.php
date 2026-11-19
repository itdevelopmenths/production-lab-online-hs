<x-app-layout title="Kartu Stok">
    <x-page-header
        title="Kartu Stok — {{ $produk->nama }}"
        subtitle="Riwayat audit saldo mutasi fisik produk SKU {{ $produk->sku }}"
        :breadcrumbs="['Stok & Mutasi' => route('stok.index'), 'Kartu Stok' => null]"
        :backUrl="route('stok.index')"
        backSize="xs"
    />

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-gray-900 p-4 rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label class="block text-xs font-medium text-[#667085] dark:text-gray-400 mb-1">Lokasi Gudang</label>
                <select id="filter-gudang" class="h-9 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-xs py-1.5 px-3 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white w-full sm:w-auto min-w-40 max-w-full outline-none cursor-pointer rounded-[6px]">
                    <option value="">— Semua Gudang —</option>
                    @foreach($gudangs as $g)
                        <option value="{{ $g->id }}" @selected($selectedGudangId == $g->id)>{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>
            @if($selectedGudang)
                <div class="pt-4 sm:pt-0 self-end sm:self-center">
                    <x-badge variant="info" :pill="true">
                        Lokasi Terpilih: {{ $selectedGudang->nama }}
                    </x-badge>
                </div>
            @endif
        </div>
        <div class="text-right">
            <span class="text-xs text-[#667085] dark:text-gray-400 font-medium">Buku Besar Pergerakan Saldo (Ledger)</span>
        </div>
    </div>

    <x-card class="tbl-modern" title="Buku Besar Pergerakan Saldo (Ledger)" subtitle="Riwayat audit saldo mutasi fisik produk SKU {{ $produk->sku }}" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Buku Besar Pergerakan Saldo" class="overflow-x-auto">
            <table id="tbl" class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95">
                    <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-[13px] font-medium">
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
                { data: 'qty', className: 'text-right tabular-nums' },
                { data: 'saldo_setelah', className: 'text-right tabular-nums font-medium' },
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

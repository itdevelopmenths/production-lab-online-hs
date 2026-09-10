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
        $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("stok.ledger.data", $produk) }}',
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
    });
    </script>
    @endpush
</x-app-layout>

<x-app-layout title="Purchasing">
    <x-page-header
        title="Purchase Order"
        subtitle="Daftar pesanan pengadaan bahan baku, kemasan, dan pembayaran ke supplier"
        :breadcrumbs="['Purchasing' => null]"
    >
        <x-slot:actions>
            @can('purchasing.create')
            <x-button href="{{ route('purchasing.create') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Buat PO Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card title="Daftar Purchase Order" :noPadding="true">
        <table id="tbl" class="w-full text-xs">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">No PO</th>
                    <th class="px-4 py-3 text-left">Supplier</th>
                    <th class="px-4 py-3 text-left">Tanggal</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Nilai Tagihan</th>
                    <th class="px-4 py-3 text-right">Sisa Tagihan</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
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
            ajax: '{{ route("purchasing.data") }}',
            columns: [
                { data: 'no_po' },
                { data: 'supplier_nama', orderable: false },
                { data: 'tanggal' },
                { data: 'status' },
                { data: 'total_nilai', className: 'text-right font-mono', searchable: false, orderable: false },
                { data: 'sisa', className: 'text-right font-mono', searchable: false, orderable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

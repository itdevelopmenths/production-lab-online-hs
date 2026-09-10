<x-app-layout title="BOM">
    <x-page-header
        title="Bill of Materials (BOM)"
        subtitle="Spesifikasi formula resep per unit produk jadi (konsentrasi minyak wangi, pelarut alkohol, botol, dan atomizer)"
        :breadcrumbs="['Master Data' => null, 'BOM' => null]"
    >
        <x-slot:actions>
            @can('bom.import')
            <form method="POST" action="{{ route('bom.import') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                @csrf
                <input type="file" name="file" accept=".csv,.txt" class="text-xs file:mr-2 file:py-1 file:px-2.5 file:rounded-sm file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer" required>
                <x-button type="submit" variant="secondary" size="xs">
                    Impor CSV Resep
                </x-button>
            </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4">
        <x-alert type="info" :icon="true">
            <span>Format file CSV resep (pemisah <code>;</code>): <code>sku_produk_jadi;sku_bahan;qty_per_unit</code> atau gunakan seeder <code>ProductVarianBomSeeder</code>.</span>
        </x-alert>
    </div>

    <x-card title="Daftar Resep Produk Jadi (BOM)" :noPadding="true">
        <table id="tbl" class="w-full text-xs">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">SKU Produk</th>
                    <th class="px-4 py-3 text-left">Nama Produk Jadi</th>
                    <th class="px-4 py-3 text-right">Jumlah Komponen Bahan</th>
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
            ajax: '{{ route("bom.data") }}',
            columns: [
                { data: 'sku' },
                { data: 'nama' },
                { data: 'bom_count', className: 'text-right font-mono font-semibold', searchable: false, orderable: false },
                { data: 'action', className: 'text-center', orderable: false, searchable: false }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

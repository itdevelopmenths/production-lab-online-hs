<x-app-layout title="BOM">
    <x-page-header
        title="Bill of Materials (BOM)"
        subtitle="Spesifikasi formula resep per unit produk jadi (konsentrasi minyak wangi, pelarut alkohol, botol, dan atomizer)"
        :breadcrumbs="['Master Data' => null, 'BOM' => null]"
    >
        <x-slot:actions>
            @can('bom.import')
            <x-button href="{{ route('bom.import-page') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </x-slot:icon>
                Impor Formula BOM
            </x-button>
            <x-button href="{{ route('bom.template') }}" variant="secondary" size="xs">
                Unduh Template CSV
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4">
        <x-alert type="info" :icon="true">
            <span>Format formula resep per unit produk jadi. Untuk memperbarui resep secara massal, gunakan menu <strong><a href="{{ route('bom.import-page') }}" class="underline font-bold">Impor Formula BOM</a></strong> atau unduh template CSV resmi.</span>
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

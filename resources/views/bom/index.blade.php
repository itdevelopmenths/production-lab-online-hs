<x-app-layout title="BOM">
    <x-page-header
        title="Bill of Materials (BOM)"
        subtitle="Spesifikasi formula resep per unit produk jadi (konsentrasi minyak wangi, pelarut alkohol, botol, dan atomizer)"
        :breadcrumbs="['Master Data' => null, 'BOM' => null]"
    >
        <x-slot:actions>
            @can('bom.import')
            <x-button href="{{ route('bom.import-page') }}" variant="primary" size="sm">
                <x-slot:icon>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </x-slot:icon>
                Impor Formula BOM
            </x-button>
            <x-button href="{{ route('bom.template') }}" variant="outline" size="sm">
                Unduh Template CSV
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4">
        <x-alert type="info" :icon="true">
            <span>Format formula resep per unit produk jadi. Untuk memperbarui resep secara massal, gunakan menu <strong><a href="{{ route('bom.import-page') }}" class="underline font-medium text-foreground">Impor Formula BOM</a></strong> atau unduh template CSV resmi.</span>
        </x-alert>
    </div>

    <x-card class="tbl-modern" title="Daftar Resep Produk Jadi (BOM)" subtitle="Komposisi bahan kimia dan botol per unit output produksi" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Daftar Resep Produk Jadi" class="overflow-x-auto">
            <table id="tbl" class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95">
                    <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-[13px] font-medium">
                        <th class="px-4 py-3 text-left w-36">SKU Produk</th>
                        <th class="px-4 py-3 text-left">Nama Produk Jadi</th>
                        <th class="px-4 py-3 text-right w-52">Jumlah Komponen Bahan</th>
                        <th class="px-4 py-3 text-center w-28">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

    @push('scripts')
    <script>
    $(function(){
        $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("bom.data") }}',
            columns: [
                {
                    data: 'sku',
                    render: function(data) {
                        return '<span class="tabular-nums font-medium text-foreground bg-muted/60 px-2 py-0.5 rounded-md border border-border text-[11px]">' + (data || '-') + '</span>';
                    }
                },
                {
                    data: 'nama',
                    render: function(data) {
                        return '<span class="font-medium text-foreground">' + (data || '-') + '</span>';
                    }
                },
                {
                    data: 'bom_count',
                    className: 'text-right tabular-nums font-medium',
                    searchable: false,
                    orderable: false,
                    render: function(data) {
                        const count = parseInt(data) || 0;
                        const cls = count > 0 ? 'bg-primary/10 text-primary border-primary/20' : 'bg-destructive/10 text-destructive border-destructive/20';
                        return count + ' Komponen';
                    }
                },
                { data: 'action', className: 'text-center', orderable: false, searchable: false }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

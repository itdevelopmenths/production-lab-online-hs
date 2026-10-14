<x-app-layout title="Produksi" subtitle="Daftar rencana kerja, alokasi resep BOM, dan eksekusi produksi pabrik">
    <x-card>
        <x-slot:header>
            <div>
                <h2 class="text-lg font-semibold text-foreground tracking-tight leading-tight">Daftar Batch Produksi</h2>
                <p class="text-sm text-muted-foreground mt-0.5">Rencana kerja produksi, alokasi resep formula, dan tracking yield luaran</p>
            </div>
            @can('batch.create')
            <x-button href="{{ route('batches.create') }}" variant="primary">
                <x-slot:icon>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Rencana Batch Baru
            </x-button>
            @endcan
        </x-slot:header>

        <div class="overflow-x-auto">
            <table id="tbl" class="w-full text-sm">
                <thead>
                    <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[13px] font-medium">
                        <th class="px-4 py-3 text-left">No Batch</th>
                        <th class="px-4 py-3 text-left">Produk Luaran</th>
                        <th class="px-4 py-3 text-right">Rencana</th>
                        <th class="px-4 py-3 text-right">Baik</th>
                        <th class="px-4 py-3 text-right">Rusak</th>
                        <th class="px-4 py-3 text-center">Yield</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-left">Tanggal</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
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
            order: [],
            ajax: '{{ route("batches.data") }}',
            columns: [
                { data: 'no_batch', className: 'px-4 py-3.5 font-mono text-xs font-semibold text-foreground' },
                { data: 'produk_nama', orderable: false, className: 'px-4 py-3.5 font-medium text-foreground' },
                { data: 'qty_rencana', className: 'px-4 py-3.5 text-right font-mono text-xs' },
                { data: 'qty_baik', className: 'px-4 py-3.5 text-right font-mono text-xs font-semibold text-emerald-700', orderable: false, searchable: false },
                { data: 'qty_rusak', className: 'px-4 py-3.5 text-right font-mono text-xs font-semibold text-destructive', orderable: false, searchable: false },
                { data: 'yield', orderable: false, searchable: false, className: 'px-4 py-3.5 text-center font-mono text-xs font-bold' },
                { data: 'status', className: 'px-4 py-3.5 text-center', orderable: false },
                { data: 'tanggal', className: 'px-4 py-3.5 text-xs text-muted-foreground' },
                { data: 'action', orderable: false, searchable: false, className: 'px-4 py-3.5 text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

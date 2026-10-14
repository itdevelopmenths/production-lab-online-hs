<x-app-layout title="Request & Transfer" subtitle="Logistik mutasi internal, permintaan bahan baku, dan pengiriman produk jadi">
    <x-card>
        <x-slot:header>
            <div>
                <h2 class="text-lg font-semibold text-foreground tracking-tight leading-tight">Daftar Dokumen Mutasi & Transfer</h2>
                <p class="text-sm text-muted-foreground mt-0.5">Logistik mutasi internal, permintaan bahan baku, dan distribusi antar gudang</p>
            </div>
            <div class="flex items-center gap-2.5">
                <select id="jenisFilter" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground outline-none cursor-pointer">
                    <option value="">Semua Jenis Mutasi</option>
                    <option value="req_bahan">Request Bahan</option>
                    <option value="retur_bahan">Retur Bahan</option>
                    <option value="kirim_produk_jadi">Kirim Produk Jadi</option>
                    <option value="antar_fulfillment">Antar Fulfillment</option>
                    <option value="retur_produk_jadi">Retur Produk Jadi</option>
                </select>

                @can('rt.create')
                <x-button href="{{ route('rt.create') }}" variant="primary">
                    <x-slot:icon>
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Buat Transfer Baru
                </x-button>
                @endcan
            </div>
        </x-slot:header>

        <div class="overflow-x-auto">
            <table id="tbl" class="w-full text-sm">
                <thead>
                    <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[13px] font-medium">
                        <th class="px-4 py-3 text-left">No Transaksi</th>
                        <th class="px-4 py-3 text-left">Jenis</th>
                        <th class="px-4 py-3 text-left">Gudang Asal</th>
                        <th class="px-4 py-3 text-left">Gudang Tujuan</th>
                        <th class="px-4 py-3 text-left">Status</th>
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
        var t = $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            order: [[5, 'desc']],
            ajax: {
                url: '{{ route("rt.data") }}',
                data: function(d) {
                    d.jenis = $('#jenisFilter').val();
                }
            },
            columns: [
                { data: 'no_transaksi', className: 'px-4 py-3.5 font-mono text-xs font-semibold text-foreground' },
                { data: 'jenis', className: 'px-4 py-3.5 font-medium text-foreground' },
                { data: 'asal', orderable: false, searchable: false, className: 'px-4 py-3.5 text-xs text-muted-foreground' },
                { data: 'tujuan', orderable: false, searchable: false, className: 'px-4 py-3.5 text-xs text-muted-foreground' },
                { data: 'status', className: 'px-4 py-3.5 text-center' },
                { data: 'created_at', className: 'px-4 py-3.5 text-xs text-muted-foreground' },
                { data: 'action', orderable: false, searchable: false, className: 'px-4 py-3.5 text-center whitespace-nowrap' }
            ]
        });
        $('#jenisFilter').on('change', () => t.ajax.reload());
    });
    </script>
    @endpush
</x-app-layout>

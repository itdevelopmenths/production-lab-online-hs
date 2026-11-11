<x-app-layout title="Transfer Barang">
    <div class="space-y-4">
        <x-page-header
            title="Transfer & Mutasi Barang"
            :breadcrumbs="['Transfer' => null]"
        >
            <x-slot:actions>
                @can('rt.create')
                <x-button href="{{ route('transfers.create') }}" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Buat Transfer
                </x-button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        {{-- Rekapitulasi Barang Rusak / Defect Mutasi --}}
        @can('defect.view')
            <div x-data="{ openDefect: {{ (request()->hasAny(['year', 'month', 'gudang_id']) || request()->has('defect')) ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between mb-2">
                    <button type="button" @click="openDefect = !openDefect" class="text-xs font-semibold text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 transition-colors cursor-pointer">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span x-text="openDefect ? 'Sembunyikan Rekapitulasi Defect' : 'Lihat Rekapitulasi Defect Mutasi'"></span>
                        <svg class="size-3 transition-transform" :class="openDefect ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <a href="{{ route('dashboard') }}" class="text-xs text-muted-foreground hover:text-foreground inline-flex items-center gap-1 font-medium">
                        Buka di Dashboard Beranda &rarr;
                    </a>
                </div>
                <div x-show="openDefect" class="transition-all mb-4">
                    @include('partials.defect-dashboard', ['formAction' => route('transfers.index')])
                </div>
            </div>
        @endcan

        {{-- ========================================================================= --}}
        {{-- DAFTAR TRANSAKSI TRANSFER ANTAR GUDANG --}}
        {{-- ========================================================================= --}}
        <x-card>
            <x-slot:header>
                <div>
                    <h2 class="text-lg font-semibold text-foreground tracking-tight leading-tight">Daftar Dokumen Transfer & Pengiriman</h2>
                    <p class="text-sm text-muted-foreground mt-0.5">Pengiriman stok fisik antar gudang, surat jalan, dan penerimaan dengan pencatatan defect</p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <select id="filterTransferStatus" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground outline-none cursor-pointer">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="dikirim">In Transit (Dikirim)</option>
                        <option value="selesai">Selesai Diterima</option>
                        <option value="dibatalkan">Dibatalkan</option>
                    </select>

                    <select id="filterTransferJenis" class="h-9 rounded-lg border border-border bg-card text-xs py-1.5 px-3 focus:ring-2 focus:ring-primary/20 text-foreground outline-none cursor-pointer">
                        <option value="">Semua Jenis Transfer</option>
                        @foreach(\App\Models\Transfer::JENIS_LABELS as $jKey => $jLbl)
                            <option value="{{ $jKey }}">{{ $jLbl }}</option>
                        @endforeach
                    </select>

                    @can('rt.create')
                    <x-button href="{{ route('transfers.create') }}" variant="primary">
                        <x-slot:icon>
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </x-slot:icon>
                        Buat Transfer Baru
                    </x-button>
                    @endcan
                </div>
            </x-slot:header>

            <div class="overflow-x-auto">
                <table id="tblTransfers" class="w-full text-sm">
                    <thead>
                        <tr class="bg-muted/70 border-b border-border text-muted-foreground text-[13px] font-medium">
                            <th class="px-4 py-3 text-left">No Transfer</th>
                            <th class="px-4 py-3 text-left">Tanggal</th>
                            <th class="px-4 py-3 text-left">Jenis</th>
                            <th class="px-4 py-3 text-left">Gudang Asal</th>
                            <th class="px-4 py-3 text-left">Gudang Tujuan</th>
                            <th class="px-4 py-3 text-left">Ref Request</th>
                            <th class="px-4 py-3 text-right">Qty Kirim</th>
                            <th class="px-4 py-3 text-right">Qty Rusak</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center w-44">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </x-card>
    </div>

    @push('scripts')
    <script>
        $(document).ready(function() {
            const table = $('#tblTransfers').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("transfers.data") }}',
                    data: function(d) {
                        d.status = $('#filterTransferStatus').val();
                        d.jenis = $('#filterTransferJenis').val();
                    }
                },
                columns: [
                    { data: 'no_transfer', name: 'no_transaksi', className: 'font-mono text-xs font-semibold text-primary-600' },
                    { data: 'tanggal_transfer', name: 'created_at' },
                    { data: 'jenis', name: 'jenis' },
                    { data: 'asal', name: 'gudangAsal.nama' },
                    { data: 'tujuan', name: 'gudangTujuan.nama' },
                    { data: 'request_no', name: 'stockRequest.no_request' },
                    { data: 'qty_dikirim', name: 'qty_dikirim', orderable: false, searchable: false, className: 'text-right font-medium' },
                    { data: 'qty_rusak', name: 'qty_rusak', orderable: false, searchable: false, className: 'text-right' },
                    { data: 'status', name: 'status' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center whitespace-nowrap' }
                ],
                order: [[0, 'desc']]
            });

            $('#filterTransferStatus, #filterTransferJenis').on('change', function() {
                table.draw();
            });
        });
    </script>
    @endpush
</x-app-layout>


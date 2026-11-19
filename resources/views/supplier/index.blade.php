<x-app-layout title="Supplier">
    <div x-data="{ createOpen: {{ (($errors->any() && old('_modal') === 'create') || request()->boolean('tambah')) ? 'true' : 'false' }} }">
    <x-page-header
        title="Master Supplier"
        subtitle="Daftar rekanan pemasok bahan baku lokal, importir concentrate oil, dan kemasan botol"
        :breadcrumbs="['Master Data' => null, 'Supplier' => null]"
    >
        <x-slot:actions>
            @can('supplier.create')
            <x-button type="button" @click="createOpen = true" variant="primary" size="sm">
                <x-slot:icon>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Supplier
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card class="tbl-modern" title="Daftar Rekanan Pemasok / Supplier" subtitle="Kelola seluruh data kontak, klasifikasi vendor, dan termin pembayaran" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Daftar Rekanan Pemasok" class="overflow-x-auto">
            <table id="tbl" class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95">
                    <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-[13px] font-medium">
                        <th class="px-4 py-3 text-left">Nama Supplier</th>
                        <th class="px-4 py-3 text-left">Kategori</th>
                        <th class="px-4 py-3 text-left">Kontak</th>
                        <th class="px-4 py-3 text-left">Termin Default</th>
                        <th class="px-4 py-3 text-center w-32">Produk Dipasok</th>
                        <th class="px-4 py-3 text-center w-28">Status</th>
                        <th class="px-4 py-3 text-center w-28">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

    @can('supplier.create')
        @include('supplier._create-modal')
        @endcan
    </div>

    @push('scripts')
    <script>
    function hapus(url){
        Swal.fire({
            title: 'Hapus data supplier ini?',
            text: 'Data vendor yang terhapus tidak dapat dipulihkan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#18181b',
            cancelButtonColor: '#71717a',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'rounded-xl border border-border shadow-lg',
                confirmButton: 'rounded-lg text-xs font-medium px-4 py-2',
                cancelButton: 'rounded-lg text-xs font-medium px-4 py-2'
            }
        }).then(r => {
            if(r.isConfirmed){
                fetch(url, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(d => {
                    Swal.fire({
                        title: 'Terhapus',
                        text: d.message,
                        icon: 'success',
                        confirmButtonColor: '#18181b'
                    });
                    $('#tbl').DataTable().ajax.reload();
                });
            }
        });
    }

    $(function(){
        $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("supplier.data") }}',
            columns: [
                {
                    data: 'nama',
                    render: function(data, type, row) {
                        return '<span class="font-medium text-foreground">' + (data || '-') + '</span>';
                    }
                },
                {
                    data: 'kategori',
                    render: function(data) {
                        if (!data) return '-';
                        const isImpor = String(data).toLowerCase().includes('impor');
                        const cls = isImpor 
                            ? 'bg-card text-foreground dark:text-foreground border-border' 
                            : 'bg-card text-foreground dark:text-foreground border-border';
                        return data;
                    }
                },
                { data: 'kontak' },
                {
                    data: 'termin_default',
                    render: function(data) {
                        return '<span class="tabular-nums text-xs text-muted-foreground">' + (data || '-') + '</span>';
                    }
                },
                {
                    data: 'produk_count',
                    className: 'text-center',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'is_active',
                    className: 'text-center',
                    render: function(data) {
                        const isAktif = /(^|>)\s*aktif\s*(<|$)/i.test(String(data));  // data berupa HTML statusPill dari server
                        const cls = isAktif
                            ? 'bg-card text-foreground dark:text-foreground border-border'
                            : 'bg-muted text-muted-foreground border-border';
                        return '<span class="tbl-status"><i class="tbl-dot ' + (isAktif ? 'tbl-dot--ok' : 'tbl-dot--bad') + '" aria-hidden="true"></i>' + (isAktif ? 'Aktif' : 'Nonaktif') + '</span>';
                    }
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false,
                    className: 'text-center'
                }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

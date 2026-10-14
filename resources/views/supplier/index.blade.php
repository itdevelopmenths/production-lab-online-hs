<x-app-layout title="Supplier">
    <x-page-header
        title="Master Supplier"
        subtitle="Daftar rekanan pemasok bahan baku lokal, importir concentrate oil, dan kemasan botol"
        :breadcrumbs="['Master Data' => null, 'Supplier' => null]"
    >
        <x-slot:actions>
            @can('supplier.create')
            <x-button href="{{ route('supplier.create') }}" variant="primary" size="sm">
                <x-slot:icon>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Supplier
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card title="Daftar Rekanan Pemasok / Supplier" subtitle="Kelola seluruh data kontak, klasifikasi vendor, dan termin pembayaran" :noPadding="true">
        <div class="overflow-x-auto">
            <table id="tbl" class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Nama Supplier</th>
                        <th class="px-4 py-3 text-left">Kategori</th>
                        <th class="px-4 py-3 text-left">Kontak</th>
                        <th class="px-4 py-3 text-left">Termin Default</th>
                        <th class="px-4 py-3 text-center w-28">Status</th>
                        <th class="px-4 py-3 text-center w-28">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

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
                            ? 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20' 
                            : 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20';
                        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border ' + cls + '">' + data + '</span>';
                    }
                },
                { data: 'kontak' },
                {
                    data: 'termin_default',
                    render: function(data) {
                        return '<span class="font-mono text-xs text-muted-foreground">' + (data || '-') + '</span>';
                    }
                },
                {
                    data: 'is_active',
                    className: 'text-center',
                    render: function(data) {
                        const isAktif = String(data).toLowerCase() === 'aktif';
                        const cls = isAktif
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20'
                            : 'bg-muted text-muted-foreground border-border';
                        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border ' + cls + '">' + (isAktif ? 'Aktif' : 'Nonaktif') + '</span>';
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

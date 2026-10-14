<x-app-layout title="Satuan (UOM)">
    <x-page-header
        title="Master Satuan Unit (UOM)"
        subtitle="Daftar unit pengukuran standar untuk bahan baku kimia, kemasan, dan produk jadi manufaktur"
        :breadcrumbs="['Master Data' => null, 'Satuan (UOM)' => null]"
    >
        <x-slot:actions>
            @can('uom.create')
            <x-button href="{{ route('uom.create') }}" variant="primary" size="sm">
                <x-slot:icon>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Satuan Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card title="Daftar Master Satuan Unit (Unit of Measure)" subtitle="Standar pengukuran unit untuk resep BOM, kartu stok, dan pemesanan" :noPadding="true">
        <div class="overflow-x-auto">
            <table id="tbl" class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left w-32">Kode Satuan</th>
                        <th class="px-4 py-3 text-left">Nama Satuan</th>
                        <th class="px-4 py-3 text-left w-36">Kategori Dimensi</th>
                        <th class="px-4 py-3 text-left w-48">Konversi Acuan</th>
                        <th class="px-4 py-3 text-left w-36">Penggunaan Katalog</th>
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
            title: 'Hapus satuan UOM ini?',
            text: 'Data yang sedang digunakan oleh produk katalog tidak dapat dihapus.',
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
                .then(async res => {
                    const d = await res.json();
                    if (res.ok) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Terhapus',
                            text: d.message,
                            confirmButtonColor: '#18181b'
                        });
                        $('#tbl').DataTable().ajax.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menghapus',
                            text: d.message || 'Terjadi kesalahan saat menghapus data.',
                            confirmButtonColor: '#dc2626'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Sistem',
                        text: 'Tidak dapat terhubung ke server.',
                        confirmButtonColor: '#dc2626'
                    });
                });
            }
        });
    }

    $(function(){
        $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("uom.data") }}',
            columns: [
                {
                    data: 'kode',
                    render: function(data) {
                        return '<span class="font-mono font-bold text-foreground bg-muted/60 px-2 py-0.5 rounded-md border border-border text-[11px]">' + (data || '-') + '</span>';
                    }
                },
                {
                    data: 'nama',
                    render: function(data) {
                        return '<span class="font-medium text-foreground">' + (data || '-') + '</span>';
                    }
                },
                {
                    data: 'kategori',
                    render: function(data) {
                        return data ? '<span class="text-muted-foreground">' + data + '</span>' : '-';
                    }
                },
                {
                    data: 'konversi',
                    render: function(data) {
                        return '<span class="font-mono text-muted-foreground text-[11px]">' + (data || '-') + '</span>';
                    }
                },
                { data: 'produk_count', orderable: false, searchable: false },
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
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

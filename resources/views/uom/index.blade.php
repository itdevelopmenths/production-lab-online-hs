<x-app-layout title="Satuan (UOM)">
    <div x-data="{ createOpen: {{ (($errors->any() && old('_modal') === 'create') || request()->boolean('tambah')) ? 'true' : 'false' }} }">
    <x-page-header
        title="Master Satuan Unit (UOM)"
        subtitle="Daftar unit pengukuran standar untuk bahan baku kimia, kemasan, dan produk jadi manufaktur"
        :breadcrumbs="['Master Data' => null, 'Satuan (UOM)' => null]"
    >
        <x-slot:actions>
            @can('uom.create')
            <x-button type="button" @click="createOpen = true" variant="primary" size="sm">
                <x-slot:icon>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Satuan Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card class="tbl-modern" title="Daftar Master Satuan Unit (Unit of Measure)" subtitle="Standar pengukuran unit untuk resep BOM, kartu stok, dan pemesanan" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Daftar Master Satuan Unit" class="overflow-x-auto">
            <table id="tbl" class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95">
                    <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-[13px] font-medium">
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

    @can('uom.create')
        @include('uom._create-modal')
        @endcan
    </div>

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
                    data: 'kategori',
                    render: function(data) {
                        return (data && data !== '-') ? '<span class="text-muted-foreground">' + data + '</span>' : '<span class="text-muted-foreground/60">—</span>';
                    }
                },
                {
                    data: 'konversi',
                    render: function(data) {
                        return '<span class="tabular-nums text-muted-foreground text-[11px]">' + (data || '-') + '</span>';
                    }
                },
                {
                    data: 'produk_count',
                    orderable: false,
                    searchable: false,
                    render: function(data) {
                        return '<span class="tabular-nums text-xs text-muted-foreground">' + (data || '0 SKU') + '</span>';
                    }
                },
                {
                    data: 'is_active',
                    className: 'text-center',
                    render: function(data) {
                        const val = String(data || '').toLowerCase().trim();
                        const isAktif = (data === true || data === 1 || data === '1' || val === 'aktif' || val === 'active' || val.includes('>aktif<'));
                        const cls = isAktif
                            ? 'bg-card text-foreground dark:text-foreground border-border'
                            : 'bg-zinc-100 dark:bg-gray-800 text-text-muted border-[#E8E8E8] dark:border-gray-700';
                        return '<span class="tbl-status"><i class="tbl-dot ' + (isAktif ? 'tbl-dot--ok' : 'tbl-dot--bad') + '" aria-hidden="true"></i>' + (isAktif ? 'Aktif' : 'Nonaktif') + '</span>';
                    }
                },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

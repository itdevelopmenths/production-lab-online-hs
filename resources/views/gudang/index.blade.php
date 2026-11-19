<x-app-layout title="Gudang">
    <x-page-header
        title="Master Gudang"
        subtitle="Daftar lokasi penyimpanan bahan baku, gudang operasional, dan fulfillment hub"
        :breadcrumbs="['Master Data' => null, 'Gudang' => null]"
    >
        <x-slot:actions>
            @can('gudang.create')
            <x-button href="{{ route('gudang.create') }}" variant="primary" size="sm">
                <x-slot:icon>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Gudang
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card title="Daftar Lokasi Gudang" subtitle="Kelola seluruh fasilitas gudang fisik dan hierarki cabang" :noPadding="true">
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
        </x-slot:icon>
        <div tabindex="0" role="region" aria-label="Daftar Lokasi Gudang" class="overflow-x-auto">
            <table id="tbl" class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95">
                    <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-[13px] font-medium">
                        <th class="px-4 py-3 text-left w-28">Kode</th>
                        <th class="px-4 py-3 text-left">Nama Gudang</th>
                        <th class="px-4 py-3 text-left w-44">Tipe Fasilitas</th>
                        <th class="px-4 py-3 text-left w-48">Induk Gudang</th>
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
            title: 'Hapus data gudang ini?',
            text: 'Gudang yang masih memiliki saldo stok tidak dapat dihapus.',
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
            ajax: '{{ route("gudang.data") }}',
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
                    data: 'tipe',
                    render: function(data) {
                        if (!data) return '-';
                        return '<span class="capitalize text-muted-foreground">' + data.replace(/_/g, ' ') + '</span>';
                    }
                },
                {
                    data: 'parent',
                    orderable: false,
                    searchable: false,
                    render: function(data) {
                        return data ? '<span class="text-muted-foreground">' + data + '</span>' : '<span class="text-muted-foreground/60 italic">— Pusat (Induk) —</span>';
                    }
                },
                {
                    data: 'status',
                    className: 'text-center',
                    render: function(data) {
                        const isAktif = String(data).toLowerCase() === 'aktif';
                        const cls = isAktif
                            ? 'bg-card text-foreground dark:text-foreground border-border'
                            : 'bg-muted text-muted-foreground border-border';
                        return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border ' + cls + '">' + (isAktif ? 'Aktif' : 'Nonaktif') + '</span>';
                    }
                },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ]
        });
    });
    </script>
    @endpush
</x-app-layout>

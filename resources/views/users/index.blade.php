<x-app-layout title="Pengguna">
    <div class="space-y-5">
        <x-page-header
            title="Daftar Pengguna"
            subtitle="Manajemen akun user, departemen divisi, hak akses gudang fisik, dan wewenang laboratorium"
            :breadcrumbs="['Pengaturan' => null, 'Pengguna' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('users.create') }}" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Tambah User
                </x-button>
            </x-slot:actions>
        </x-page-header>

        {{-- Filter Bar --}}
        <div class="bg-card border border-border rounded-xl p-3.5 shadow-xs">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <svg class="size-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span class="text-xs font-semibold text-foreground">Filter Data Pengguna:</span>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    {{-- Filter Divisi --}}
                    <div class="min-w-[190px]">
                        <select id="filterDivisi" class="w-full text-xs rounded-lg border border-border bg-background text-foreground px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <option value="">-- Semua Divisi --</option>
                            @if(isset($divisis) && $divisis->isNotEmpty())
                                @foreach($divisis as $d)
                                    <option value="{{ $d->kode }}">{{ $d->nama }}</option>
                                @endforeach
                            @else
                                @foreach($divisiList as $divKey => $divLabel)
                                    <option value="{{ $divKey }}">{{ $divLabel }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    {{-- Filter Role --}}
                    <div class="min-w-[180px]">
                        <select id="filterRole" class="w-full text-xs rounded-lg border border-border bg-background text-foreground px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <option value="">-- Semua Peran / Role --</option>
                            @foreach($roles as $r)
                                <option value="{{ $r->name }}">{{ $r->display_name ?: $r->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="button" id="btnResetFilter" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-background hover:bg-muted text-foreground border border-border transition cursor-pointer">
                        Reset Filter
                    </button>
                </div>
            </div>
        </div>

        <x-card title="Daftar Pengguna Sistem" subtitle="Kelola identitas akun, akses gudang fisik, dan wewenang pengguna" :noPadding="true">
            <div class="overflow-x-auto">
                <table id="usersTable" class="w-full text-xs">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Nama Lengkap</th>
                            <th class="px-4 py-3 text-left">Email Akun</th>
                            <th class="px-4 py-3 text-left">Divisi / Dept</th>
                            <th class="px-4 py-3 text-left">Peran (Role)</th>
                            <th class="px-4 py-3 text-left">Akses Gudang (LBAC)</th>
                            <th class="px-4 py-3 text-left">Terdaftar</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </x-card>
    </div>

    @push('scripts')
    <script>
    $(document).ready(function() {
        var table = $('#usersTable').DataTable({
            processing: true, 
            serverSide: true,
            ajax: {
                url: '{{ route("users.data") }}',
                data: function(d) {
                    d.divisi = $('#filterDivisi').val();
                    d.role = $('#filterRole').val();
                }
            },
            columns: [
                {
                    data: 'name', 
                    name: 'name', 
                    render: function(data, type, row) {
                        var initial = data ? data.charAt(0).toUpperCase() : '?';
                        return '<div class="flex items-center gap-2.5">' +
                                    '<div class="size-7 rounded-lg bg-primary text-primary-foreground flex items-center justify-center text-xs font-semibold shrink-0">' + initial + '</div>' +
                                    '<div class="min-w-0">' +
                                        '<div class="font-medium text-foreground leading-tight">' + data + '</div>' +
                                    '</div>' +
                               '</div>';
                    }
                },
                { data: 'email', name: 'email', render: function(data) {
                    return '<span class="font-mono text-muted-foreground text-xs">' + data + '</span>';
                }},
                { data: 'divisi_badge', name: 'divisi', orderable: true, searchable: false },
                { data: 'roles_label', name: 'roles_label', orderable: false, searchable: false },
                { data: 'gudang_access_badge', name: 'gudang_access_badge', orderable: false, searchable: false },
                { data: 'created_at', name: 'created_at', className: 'text-muted-foreground whitespace-nowrap' },
                { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-right whitespace-nowrap' }
            ],
            order: [[5, 'desc']]
        });

        $('#filterDivisi, #filterRole').on('change', function() {
            table.ajax.reload();
        });

        $('#btnResetFilter').on('click', function() {
            $('#filterDivisi').val('');
            $('#filterRole').val('');
            table.ajax.reload();
        });
    });

    function deleteUser(id, userName) {
        Swal.fire({
            title: 'Konfirmasi Hapus Pengguna',
            html: 'Yakin ingin menghapus akun pengguna <strong>' + userName + '</strong>?<br><span class="text-xs text-red-600">Aksi ini tidak dapat dibatalkan.</span>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Hapus Pengguna',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('{{ url("users") }}/' + id, { 
                    method: 'DELETE', 
                    headers: { 
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    } 
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        $('#usersTable').DataTable().ajax.reload(); 
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: data.message || 'Gagal menghapus pengguna.'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Sistem',
                        text: 'Terjadi kesalahan saat memproses permintaan.'
                    });
                });
            }
        });
    }
    </script>
    @endpush
</x-app-layout>

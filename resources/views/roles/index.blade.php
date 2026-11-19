<x-app-layout title="Peran & Wewenang">
    <div>
        <x-page-header
            title="Peran & Wewenang"
            subtitle="Kelola peran pengguna dan konfigurasi matriks wewenang akses modul laboratorium"
            :breadcrumbs="['Sistem' => null, 'Peran & Wewenang' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('roles.create') }}" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </x-slot:icon>
                    Tambah Peran Baru
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card class="tbl-modern" title="Daftar Peran & Hak Akses" subtitle="Struktur peran sistem dan matriks wewenang laboratorium" :noPadding="true">
            <x-slot:icon>
                <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
            </x-slot:icon>
            <div tabindex="0" role="region" aria-label="Daftar Peran & Hak Akses" class="overflow-x-auto">
                <table id="rolesTable" class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95">
                        <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-[13px] font-medium">
                            <th class="px-4 py-3 text-left">Nama Peran & Identifier</th>
                            <th class="px-4 py-3 text-left">Tipe</th>
                            <th class="px-4 py-3 text-left">Cakupan Izin</th>
                            <th class="px-4 py-3 text-left">Pengguna</th>
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
        $('#rolesTable').DataTable({
            processing: true, 
            serverSide: true,
            ajax: '{{ route("roles.data") }}',
            columns: [
                { data: 'role_badge', name: 'display_name' },
                { data: 'system_badge', name: 'is_system', className: 'text-left' },
                { data: 'permissions_summary', name: 'permissions_summary', orderable: false, searchable: false },
                { data: 'users_count_badge', name: 'users_count', searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-right' }
            ],
            language: {
                search: "Cari Peran:",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ peran",
                paginate: {
                    first: "Awal",
                    last: "Akhir",
                    next: "Lanjut",
                    previous: "Kembali"
                },
                zeroRecords: "Belum ada data peran yang cocok.",
                emptyTable: "Tidak ada peran yang terdaftar."
            }
        });
    });

    function deleteRole(id, name) {
        Swal.fire({
            title: 'Konfirmasi Hapus Peran',
            html: 'Yakin ingin menghapus peran <strong>' + name + '</strong>?<br><span class="text-xs text-gray-500">Aksi ini akan mencabut peran dari wewenang terkait.</span>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Hapus Peran',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('{{ url("roles") }}/' + id, { 
                    method: 'DELETE', 
                    headers: { 
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json'
                    } 
                })
                .then(response => response.json().then(data => ({ status: response.status, body: data })))
                .then(({ status, body }) => {
                    if (status === 200 && body.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: body.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        $('#rolesTable').DataTable().ajax.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menghapus',
                            text: body.message || 'Terjadi kesalahan sistem.'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Jaringan',
                        text: 'Tidak dapat menghubungi server.'
                    });
                });
            }
        });
    }
    </script>
    @endpush
</x-app-layout>

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

        <x-card title="Daftar Peran & Hak Akses" :noPadding="true">
            <div class="overflow-x-auto">
                <table id="rolesTable" class="w-full text-xs">
                    <thead>
                        <tr class="bg-gray-50/75 border-b border-gray-200 text-gray-600 font-semibold uppercase tracking-wider text-[11px]">
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

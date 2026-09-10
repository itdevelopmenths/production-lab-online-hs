<x-app-layout title="Pengguna">
    <div x-data="{ 
        confirmDelete(id) {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Yakin ingin menghapus pengguna ini? Aksi ini tidak dapat dibatalkan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yakin, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('/production/users/' + id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } })
                        .then(() => { 
                            $('#usersTable').DataTable().ajax.reload(); 
                        });
                }
            });
        }
    }" @delete-user.window="confirmDelete($event.detail)">
        <x-page-header
            title="Daftar Pengguna"
            subtitle="Manajemen akun user, hak akses role, dan wewenang modul laboratorium"
            :breadcrumbs="['Pengguna' => null]"
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

        <x-card title="Daftar Pengguna Sistem" :noPadding="true">
            <table id="usersTable" class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Nama</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Role</th>
                        <th class="px-4 py-3 text-left">Terdaftar</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
            </table>
        </x-card>
    </div>

    @push('scripts')
    <script>
    $(document).ready(function() {
        $('#usersTable').DataTable({
            processing: true, 
            serverSide: true,
            ajax: '{{ route("users.data") }}',
            columns: [
                {data: 'name', name: 'name', render: function(data) {
                    var initial = data.charAt(0).toUpperCase();
                    return '<div class="flex items-center gap-2.5"><div class="w-7 h-7 rounded-sm bg-primary-600 flex items-center justify-center text-white text-xs font-bold">' + initial + '</div><span class="font-medium text-gray-900">' + data + '</span></div>';
                }},
                {data: 'email', name: 'email'},
                {data: 'roles_label', name: 'roles_label', orderable: false, searchable: false},
                {data: 'created_at', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center'}
            ]
        });
    });

    function deleteUser(id) {
        window.dispatchEvent(new CustomEvent('delete-user', { detail: id }));
    }
    </script>
    @endpush
</x-app-layout>

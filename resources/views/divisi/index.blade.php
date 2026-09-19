<x-app-layout title="Master Divisi & Departemen">
    <x-page-header
        title="Master Divisi & Departemen"
        subtitle="Kelola struktur departemen organisasi, penamaan divisi, dan aksen warna badge identifikasi pengguna"
        :breadcrumbs="['Master Data' => null, 'Divisi' => null]"
    >
        <x-slot:actions>
            @can('divisi.create')
            <x-button href="{{ route('divisi.create') }}" variant="primary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </x-slot:icon>
                Tambah Divisi Baru
            </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card title="Daftar Master Divisi / Departemen Kerja" subtitle="Divisi yang aktif akan otomatis muncul sebagai opsi pemilihan saat membuat atau mengedit akun staf pengguna" :noPadding="true">
        <div class="overflow-x-auto">
            <table id="tbl" class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left w-28">Kode Unik</th>
                        <th class="px-4 py-3 text-left">Nama Divisi & Deskripsi</th>
                        <th class="px-4 py-3 text-left w-48">Tampilan Badge</th>
                        <th class="px-4 py-3 text-left w-36">Staf Terdaftar</th>
                        <th class="px-4 py-3 text-center w-28">Status</th>
                        <th class="px-4 py-3 text-center w-28">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </x-card>

    @push('scripts')
    <script>
    function hapus(url, nama){
        Swal.fire({
            title: 'Hapus divisi ini?',
            text: 'Divisi "' + nama + '" yang masih digunakan oleh staf aktif tidak dapat dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(r => {
            if(r.isConfirmed){
                fetch(url, {
                    method: 'DELETE',
                    headers: { 
                        'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(async res => {
                    const d = await res.json();
                    if (res.ok && d.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Terhapus',
                            text: d.message,
                            confirmButtonColor: '#0284c7'
                        });
                        $('#tbl').DataTable().ajax.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menghapus',
                            text: d.message || 'Terjadi kesalahan saat menghapus data divisi.',
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
            ajax: '{{ route("divisi.data") }}',
            columns: [
                { data: 'kode_badge', name: 'kode' },
                { data: 'nama_section', name: 'nama' },
                { data: 'badge_preview', orderable: false, searchable: false },
                { data: 'users_count_badge', name: 'users_count', searchable: false },
                { data: 'status_badge', name: 'is_active', className: 'text-center' },
                { data: 'action', orderable: false, searchable: false, className: 'text-center' }
            ],
            order: [[1, 'asc']]
        });
    });
    </script>
    @endpush
</x-app-layout>

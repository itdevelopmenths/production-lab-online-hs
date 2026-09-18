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

    <div x-data="{ currentTab: 'divisi' }">
        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-5 flex items-center justify-between">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                <button
                    type="button"
                    @click="currentTab = 'divisi'"
                    :class="currentTab === 'divisi' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    Master Divisi
                </button>

                @canany(['divisi.audit', 'audit.view'])
                <button
                    type="button"
                    @click="currentTab = 'audit'; $nextTick(() => { if (window.tblAuditDivisi) window.tblAuditDivisi.columns.adjust().draw(false); })"
                    :class="currentTab === 'audit' ? 'border-primary-600 text-primary-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium'"
                    class="py-2.5 px-1 border-b-2 text-xs transition cursor-pointer flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Riwayat Audit
                </button>
                @endcanany
            </nav>
        </div>

        <div x-show="currentTab === 'divisi'">
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
        </div>

        @canany(['divisi.audit', 'audit.view'])
        <div x-show="currentTab === 'audit'" x-cloak>
            <x-master-audit-tab entity="divisi" />
        </div>
        @endcanany
    </div>

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

<x-app-layout title="Tambah Divisi Baru">
    <div class="flex justify-center w-full">
        <div class="w-full max-w-4xl space-y-4">
            <x-page-header
                title="Tambah Divisi Baru"
                subtitle="Daftarkan unit kerja departemen baru untuk struktur organisasi dan klasifikasi staf pengguna"
                :breadcrumbs="['Master Data' => null, 'Divisi' => route('divisi.index'), 'Tambah' => null]"
            >
                <x-slot:actions>
                    <x-button href="{{ route('divisi.index') }}" variant="secondary" size="xs">
                        &larr; Kembali ke Daftar
                    </x-button>
                </x-slot:actions>
            </x-page-header>

            <form method="POST" action="{{ route('divisi.store') }}">
                @csrf
                <x-card title="Informasi Divisi / Departemen" subtitle="Nama resmi, kode slug sistem, aksen warna badge, dan deskripsi tanggung jawab" variant="primary">
                    @include('divisi._form', ['divisi' => $divisi])

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2.5">
                            <x-button href="{{ route('divisi.index') }}" variant="secondary" size="sm">
                                Batal
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm">
                                <x-slot:icon>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </x-slot:icon>
                                Simpan Divisi
                            </x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
</x-app-layout>

<x-app-layout title="Tambah Divisi Baru">
    <div class="w-full space-y-4">
        <x-page-header
            title="Tambah Divisi Baru"
            subtitle="Daftarkan unit kerja departemen baru untuk struktur organisasi dan klasifikasi staf pengguna"
            :breadcrumbs="['Master Data' => null, 'Divisi' => route('divisi.index'), 'Tambah' => null]"
            :backUrl="route('divisi.index')"
        />

        <form method="POST" action="{{ route('divisi.store') }}">
            @csrf
            <x-card title="Informasi Divisi / Departemen" subtitle="Nama resmi, kode slug sistem, aksen warna badge, dan deskripsi tanggung jawab" :noPadding="true">
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </x-slot:icon>
                <div class="bg-[#F5F5F5] dark:bg-gray-950 p-4 sm:p-5">
                    @include('divisi._form', ['divisi' => $divisi])
                </div>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('divisi.index') }}" variant="secondary" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Simpan Divisi
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

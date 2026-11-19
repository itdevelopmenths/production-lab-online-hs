<x-app-layout title="Edit Divisi">
    <div class="w-full space-y-4">
        <x-page-header
            title="Edit Divisi"
            subtitle="Perbarui data unit kerja, kode slug sistem, atau aksen warna identifikasi badge"
            :breadcrumbs="['Master Data' => null, 'Divisi' => route('divisi.index'), $divisi->nama => null, 'Edit' => null]"
            :backUrl="route('divisi.index')"
        />

        <form method="POST" action="{{ route('divisi.update', $divisi) }}">
            @csrf
            @method('PUT')
            <x-card title="Perbarui Informasi Divisi" subtitle="Mengubah rincian departemen: {{ $divisi->nama }} ({{ $divisi->kode }})" :noPadding="true">
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </x-slot:icon>
                <div class="bg-gray-50 dark:bg-gray-950 p-3 sm:p-4">
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
                            Perbarui Divisi
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

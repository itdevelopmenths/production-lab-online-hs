<x-app-layout title="Edit Divisi" :backUrl="route('divisi.index')">
    <div class="mx-auto max-w-3xl space-y-5">
        <x-page-header
            title="Edit Divisi"
            subtitle="Perbarui data unit kerja, kode slug sistem, atau aksen warna identifikasi badge"
            :breadcrumbs="['Master Data' => null, 'Divisi' => route('divisi.index'), $divisi->nama => null, 'Edit' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('divisi.index') }}" variant="outline" size="sm">
                    &larr; Kembali
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('divisi.update', $divisi) }}">
            @csrf
            @method('PUT')
            <x-card title="Perbarui Informasi Divisi" subtitle="Mengubah rincian departemen: {{ $divisi->nama }} ({{ $divisi->kode }})">
                @include('divisi._form', ['divisi' => $divisi])

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('divisi.index') }}" variant="outline" size="sm">
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

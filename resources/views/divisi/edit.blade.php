<x-app-layout title="Edit Divisi: {{ $divisi->nama }}">
    <div class="max-w-4xl mx-auto">
        <x-page-header
            title="Edit Divisi / Departemen"
            subtitle="Perbarui nama departemen, aksen warna badge, deskripsi, atau status operasional divisi"
            :breadcrumbs="['Master Data' => null, 'Divisi' => route('divisi.index'), $divisi->nama => null, 'Edit' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('divisi.index') }}" variant="secondary" size="xs">
                    &larr; Kembali ke Daftar
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('divisi.update', $divisi) }}">
            @csrf
            @method('PUT')
            <x-card title="Ubah Data Divisi" subtitle="Perubahan nama dan warna divisi akan langsung terfleksi pada seluruh staf yang terdaftar" variant="primary">
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
                            Simpan Perubahan
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

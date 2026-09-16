<x-app-layout title="Tambah Kategori Produk">
    <div class="max-w-2xl mx-auto">
        <x-page-header
            title="Tambah Kategori Produk"
            subtitle="Daftarkan kelompok kategori utama untuk produk manufaktur, kemasan, atau bahan baku"
            :breadcrumbs="['Master Data' => null, 'Kategori' => route('kategori.index'), 'Tambah' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('kategori.index') }}" variant="secondary" size="xs">
                    &larr; Kembali ke Daftar
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('kategori.store') }}">
            @csrf
            <x-card title="Data Kategori" subtitle="Nama kategori unik yang memayungi varian dan produk" variant="primary">
                @include('kategori._form')

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2.5">
                        <x-button href="{{ route('kategori.index') }}" variant="secondary" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Simpan Kategori
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

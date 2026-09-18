<x-app-layout title="Tambah Varian Produk">
    <div class="flex justify-center w-full">
        <div class="w-full max-w-3xl space-y-4">
            <x-page-header
                title="Tambah Varian Baru"
                subtitle="Daftarkan varian spesifik di bawah kategori produk tertentu"
                :breadcrumbs="['Master Data' => null, 'Varian Produk' => route('varian.index'), 'Tambah' => null]"
            >
                <x-slot:actions>
                    <x-button href="{{ route('varian.index') }}" variant="secondary" size="xs">
                        &larr; Kembali ke Daftar
                    </x-button>
                </x-slot:actions>
            </x-page-header>

            <form method="POST" action="{{ route('varian.store') }}">
                @csrf
                <x-card title="Data Varian Produk" subtitle="Spesifikasi kategori induk dan nama varian" variant="primary">
                    @include('varian._form')

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2.5">
                            <x-button href="{{ route('varian.index') }}" variant="secondary" size="sm">
                                Batal
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm">
                                <x-slot:icon>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </x-slot:icon>
                                Simpan Varian
                            </x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
</x-app-layout>

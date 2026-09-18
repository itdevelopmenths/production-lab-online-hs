<x-app-layout title="Tambah Produk">
    <div class="flex justify-center w-full">
        <div class="w-full max-w-4xl space-y-4">
            <x-page-header
                title="Tambah Produk Baru"
                subtitle="Daftarkan master produk jadi, bahan baku kimia, atau komponen kemasan"
                :breadcrumbs="['Master Data' => null, 'Produk' => route('produk.index'), 'Tambah' => null]"
            >
                <x-slot:actions>
                    <x-button href="{{ route('produk.index') }}" variant="secondary" size="xs">
                        &larr; Kembali ke Katalog
                    </x-button>
                </x-slot:actions>
            </x-page-header>

            <form method="POST" action="{{ route('produk.store') }}">
                @csrf
                <x-card title="Informasi Master Produk" subtitle="Spesifikasi SKU, klasifikasi tipe, dan parameter pemesanan" variant="primary">
                    @include('produk._form')

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2.5">
                            <x-button href="{{ route('produk.index') }}" variant="secondary" size="sm">
                                Batal
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm">
                                <x-slot:icon>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </x-slot:icon>
                                Simpan Produk
                            </x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
</x-app-layout>

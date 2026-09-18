<x-app-layout title="Edit Produk">
    <div class="flex justify-center w-full">
        <div class="w-full max-w-4xl space-y-4">
            <x-page-header
                title="Edit Produk — {{ $produk->nama }}"
                subtitle="Perbarui spesifikasi SKU, klasifikasi tipe, dan parameter pemesanan"
                :breadcrumbs="['Master Data' => null, 'Produk' => route('produk.index'), 'Edit' => null]"
            >
                <x-slot:actions>
                    <x-button href="{{ route('produk.index') }}" variant="secondary" size="xs">
                        &larr; Kembali ke Katalog
                    </x-button>
                </x-slot:actions>
            </x-page-header>

            <form method="POST" action="{{ route('produk.update', $produk) }}">
                @csrf
                @method('PUT')
                <x-card title="Informasi Master Produk" subtitle="Spesifikasi SKU: {{ $produk->sku }}" variant="primary">
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
                                Perbarui Produk
                            </x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
</x-app-layout>

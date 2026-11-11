<x-app-layout title="Tambah Produk">
    <div class="w-full space-y-4">
        <x-page-header
            title="Tambah Produk Baru"
            :breadcrumbs="['Master Data' => null, 'Produk' => route('produk.index'), 'Tambah' => null]"
            :backUrl="route('produk.index')"
        />

        <form method="POST" action="{{ route('produk.store') }}">
            @csrf
            <x-card title="Informasi Master Produk" subtitle="Spesifikasi SKU, klasifikasi tipe, dan parameter pemesanan">
                @include('produk._form')

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('produk.index') }}" variant="outline" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Simpan Produk
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

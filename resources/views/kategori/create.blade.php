<x-app-layout title="Tambah Kategori Produk">
    <div class="w-full space-y-4">
        <x-page-header
            title="Tambah Kategori Produk"
            :breadcrumbs="['Master Data' => null, 'Kategori' => route('kategori.index'), 'Tambah' => null]"
            :backUrl="route('kategori.index')"
        />

        <form method="POST" action="{{ route('kategori.store') }}">
            @csrf
            <x-card title="Data Kategori" subtitle="Nama grup atau kategori produk">
                @include('kategori._form', ['kategori' => null])

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('kategori.index') }}" variant="outline" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Simpan Kategori
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

<x-app-layout title="Edit Kategori Produk" :backUrl="route('kategori.index')">
    <div class="mx-auto max-w-2xl space-y-5">
        <x-page-header
            title="Edit Kategori Produk"
            subtitle="Perbarui penamaan klasifikasi kategori produk"
            :breadcrumbs="['Master Data' => null, 'Kategori' => route('kategori.index'), $kategori->nama => null, 'Edit' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('kategori.index') }}" variant="outline" size="sm">
                    &larr; Kembali
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('kategori.update', $kategori) }}">
            @csrf
            @method('PUT')
            <x-card title="Perbarui Data Kategori" subtitle="Mengubah nama kategori: {{ $kategori->nama }}">
                @include('kategori._form', ['kategori' => $kategori])

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('kategori.index') }}" variant="outline" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Perbarui Kategori
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

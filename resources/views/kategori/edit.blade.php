<x-app-layout title="Edit Kategori Produk">
    <div class="w-full space-y-4">
        <x-page-header
            title="Edit Kategori Produk"
            :breadcrumbs="['Master Data' => null, 'Kategori' => route('kategori.index'), $kategori->nama => null, 'Edit' => null]"
            :backUrl="route('kategori.index')"
        />

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

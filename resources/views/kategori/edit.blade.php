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
            <x-card title="Perbarui Data Kategori" subtitle="Mengubah nama kategori: {{ $kategori->nama }}" :noPadding="true">
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </x-slot:icon>
                <div class="bg-gray-50 dark:bg-gray-950 p-3 sm:p-4">
                    @include('kategori._form', ['kategori' => $kategori])
                </div>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('kategori.index') }}" variant="secondary" size="sm">
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

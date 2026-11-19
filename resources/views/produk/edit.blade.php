<x-app-layout title="Edit Produk">
    <div class="w-full space-y-4">
        <x-page-header
            title="Edit Produk — {{ $produk->nama }}"
            :breadcrumbs="['Master Data' => null, 'Produk' => route('produk.index'), 'Edit' => null]"
            :backUrl="route('produk.index')"
        />

        <form method="POST" action="{{ route('produk.update', $produk) }}">
            @csrf
            @method('PUT')
            <x-card title="Informasi Master Produk" subtitle="Spesifikasi SKU: {{ $produk->sku }}" :noPadding="true">
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </x-slot:icon>
                <div class="bg-gray-50 dark:bg-gray-950 p-3 sm:p-4">
                    @include('produk._form')
                </div>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('produk.index') }}" variant="secondary" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Perbarui Produk
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

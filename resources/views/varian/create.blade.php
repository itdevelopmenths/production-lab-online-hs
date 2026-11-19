<x-app-layout title="Tambah Varian Produk">
    <div class="w-full space-y-4">
        <x-page-header
            title="Tambah Varian Baru"
            :breadcrumbs="['Master Data' => null, 'Varian Produk' => route('varian.index'), 'Tambah' => null]"
            :backUrl="route('varian.index')"
        />

        <form method="POST" action="{{ route('varian.store') }}">
            @csrf
            <x-card title="Data Varian Produk" subtitle="Spesifikasi kategori induk dan nama varian" :noPadding="true">
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </x-slot:icon>
                <div class="bg-gray-50 dark:bg-gray-950 p-3 sm:p-4">
                    @include('varian._form')
                </div>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('varian.index') }}" variant="secondary" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Simpan Varian
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

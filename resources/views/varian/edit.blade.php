<x-app-layout title="Edit Varian Produk">
    <div class="flex justify-center w-full">
        <div class="w-full max-w-3xl space-y-4">
            <x-page-header
                title="Edit Varian Produk"
                subtitle="Perbarui kategori induk dan nama varian"
                :breadcrumbs="['Master Data' => null, 'Varian Produk' => route('varian.index'), 'Edit' => null]"
            >
                <x-slot:actions>
                    <x-button href="{{ route('varian.index') }}" variant="secondary" size="xs">
                        &larr; Kembali ke Daftar
                    </x-button>
                </x-slot:actions>
            </x-page-header>

            <form method="POST" action="{{ route('varian.update', $varian) }}">
                @csrf
                @method('PUT')
                <x-card title="Data Varian Produk" subtitle="Spesifikasi kategori induk dan nama varian" variant="primary">
                    @include('varian._form', ['varian' => $varian])

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2.5">
                            <x-button href="{{ route('varian.index') }}" variant="secondary" size="sm">
                                Batal
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm">
                                <x-slot:icon>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </x-slot:icon>
                                Simpan Perubahan
                            </x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
</x-app-layout>

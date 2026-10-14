<x-app-layout title="Edit Varian Produk" :backUrl="route('varian.index')">
    <div class="mx-auto max-w-3xl space-y-5">
        <x-page-header
            title="Edit Varian Produk"
            subtitle="Perbarui data kategori atau nama varian produk"
            :breadcrumbs="['Master Data' => null, 'Varian Produk' => route('varian.index'), $varian->nama => null, 'Edit' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('varian.index') }}" variant="outline" size="sm">
                    &larr; Kembali
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('varian.update', $varian) }}">
            @csrf
            @method('PUT')
            <x-card title="Perbarui Data Varian" subtitle="Mengubah rincian varian: {{ $varian->nama }}">
                @include('varian._form')

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('varian.index') }}" variant="outline" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Perbarui Varian
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

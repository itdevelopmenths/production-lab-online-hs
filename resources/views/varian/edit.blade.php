<x-app-layout title="Edit Varian Produk">
    <div class="w-full space-y-4">
        <x-page-header
            title="Edit Varian Produk"
            :breadcrumbs="['Master Data' => null, 'Varian Produk' => route('varian.index'), $varian->nama => null, 'Edit' => null]"
            :backUrl="route('varian.index')"
        />

        <form method="POST" action="{{ route('varian.update', $varian) }}">
            @csrf
            @method('PUT')
            <x-card title="Perbarui Data Varian" subtitle="Mengubah rincian varian: {{ $varian->nama }}" :noPadding="true">
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </x-slot:icon>
                <div class="bg-[#F5F5F5] dark:bg-gray-950 p-4 sm:p-5">
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
                            Perbarui Varian
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

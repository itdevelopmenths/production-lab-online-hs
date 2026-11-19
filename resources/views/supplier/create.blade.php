<x-app-layout title="Tambah Supplier">
    <div class="w-full space-y-4">
        <x-page-header
            title="Tambah Supplier Baru"
            subtitle="Daftarkan rekanan vendor penyedia bahan baku kimia atau kemasan"
            :breadcrumbs="['Master Data' => null, 'Supplier' => route('supplier.index'), 'Tambah' => null]"
            :backUrl="route('supplier.index')"
        />

        <form method="POST" action="{{ route('supplier.store') }}">
            @csrf
            <x-card title="Data Profil Supplier" subtitle="Rincian identitas, kategori, dan kontak representatif vendor" :noPadding="true">
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </x-slot:icon>
                <div class="bg-[#F5F5F5] dark:bg-gray-950 p-4 sm:p-5">
                    @include('supplier._form')
                </div>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('supplier.index') }}" variant="secondary" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Simpan Supplier
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

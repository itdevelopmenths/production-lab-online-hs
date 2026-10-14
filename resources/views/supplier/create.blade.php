<x-app-layout title="Tambah Supplier" :backUrl="route('supplier.index')">
    <div class="mx-auto max-w-3xl space-y-5">
        <x-page-header
            title="Tambah Supplier Baru"
            subtitle="Daftarkan rekanan vendor penyedia bahan baku kimia atau kemasan"
            :breadcrumbs="['Master Data' => null, 'Supplier' => route('supplier.index'), 'Tambah' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('supplier.index') }}" variant="outline" size="sm">
                    &larr; Kembali
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <form method="POST" action="{{ route('supplier.store') }}">
            @csrf
            <x-card title="Data Profil Supplier" subtitle="Rincian identitas, kategori, dan kontak representatif vendor">
                @include('supplier._form')

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('supplier.index') }}" variant="outline" size="sm">
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

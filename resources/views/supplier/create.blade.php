<x-app-layout title="Tambah Supplier">
    <div class="flex justify-center w-full">
        <div class="w-full max-w-4xl space-y-4">
            <x-page-header
                title="Tambah Supplier Baru"
                subtitle="Daftarkan rekanan vendor penyedia bahan baku kimia atau kemasan"
                :breadcrumbs="['Master Data' => null, 'Supplier' => route('supplier.index'), 'Tambah' => null]"
            >
                <x-slot:actions>
                    <x-button href="{{ route('supplier.index') }}" variant="secondary" size="xs">
                        &larr; Kembali ke Daftar
                    </x-button>
                </x-slot:actions>
            </x-page-header>

            <form method="POST" action="{{ route('supplier.store') }}">
                @csrf
                <x-card title="Data Profil Supplier" subtitle="Rincian identitas, kategori, dan kontak representatif vendor" variant="primary">
                    @include('supplier._form')

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2.5">
                            <x-button href="{{ route('supplier.index') }}" variant="secondary" size="sm">
                                Batal
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm">
                                <x-slot:icon>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </x-slot:icon>
                                Simpan Supplier
                            </x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
</x-app-layout>

<x-app-layout title="Edit Supplier">
    <div class="w-full space-y-4">
        <x-page-header
            title="Edit Supplier"
            subtitle="Perbarui data kontak, kategori, atau termin pembayaran rekanan"
            :breadcrumbs="['Master Data' => null, 'Supplier' => route('supplier.index'), $supplier->nama => null, 'Edit' => null]"
            :backUrl="route('supplier.index')"
        />

        <form method="POST" action="{{ route('supplier.update', $supplier) }}">
            @csrf
            @method('PUT')
            <x-card title="Perbarui Data Supplier" subtitle="Mengubah rincian profil: {{ $supplier->nama }}" :noPadding="true">
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </x-slot:icon>
                <div class="bg-gray-50 dark:bg-gray-950 p-3 sm:p-4">
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
                            Perbarui Supplier
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

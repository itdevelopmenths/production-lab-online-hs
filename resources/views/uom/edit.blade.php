<x-app-layout title="Edit Satuan (UOM)">
    <div class="w-full space-y-4">
        <x-page-header
            title="Edit Satuan Unit"
            subtitle="Perbarui nama, kategori dimensi, atau faktor konversi acuan unit pengukuran"
            :breadcrumbs="['Master Data' => null, 'Satuan (UOM)' => route('uom.index'), $uom->nama => null, 'Edit' => null]"
            :backUrl="route('uom.index')"
        />

        <form method="POST" action="{{ route('uom.update', $uom) }}">
            @csrf
            @method('PUT')
            <x-card title="Perbarui Data Satuan" subtitle="Mengubah rincian unit pengukuran: {{ $uom->nama }} ({{ $uom->kode }})" :noPadding="true">
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                </x-slot:icon>
                <div class="bg-gray-50 dark:bg-gray-950 p-3 sm:p-4">
                    @include('uom._form')
                </div>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('uom.index') }}" variant="secondary" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Perbarui Satuan
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

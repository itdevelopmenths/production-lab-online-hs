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
            <x-card title="Perbarui Data Satuan" subtitle="Mengubah rincian unit pengukuran: {{ $uom->nama }} ({{ $uom->kode }})">
                @include('uom._form')

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('uom.index') }}" variant="outline" size="sm">
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

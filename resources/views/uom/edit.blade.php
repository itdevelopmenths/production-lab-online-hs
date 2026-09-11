<x-app-layout title="Edit Satuan (UOM)">
    <x-page-header
        title="Edit Satuan Unit"
        subtitle="Perbarui data simbol, nama, atau kategori dimensi satuan unit"
        :breadcrumbs="['Master Data' => null, 'Satuan (UOM)' => route('uom.index'), $uom->nama => null, 'Edit' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('uom.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Daftar
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="max-w-4xl">
        <form method="POST" action="{{ route('uom.update', $uom) }}">
            @csrf
            @method('PUT')
            <x-card title="Perbarui Data Satuan Unit" subtitle="Mengubah definisi satuan: {{ $uom->nama }} ({{ $uom->kode }})" variant="primary">
                @include('uom._form')

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2.5">
                        <x-button href="{{ route('uom.index') }}" variant="secondary" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Perbarui Satuan
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

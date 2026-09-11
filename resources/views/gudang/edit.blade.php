<x-app-layout title="Edit Gudang">
    <x-page-header
        title="Edit Gudang — {{ $gudang->nama }}"
        subtitle="Perbarui konfigurasi fasilitas, klasifikasi tipe, dan relasi hirarki induk"
        :breadcrumbs="['Master Data' => null, 'Gudang' => route('gudang.index'), 'Edit' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('gudang.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Gudang
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="max-w-4xl">
        <form method="POST" action="{{ route('gudang.update', $gudang) }}">
            @csrf
            @method('PUT')
            <x-card title="Informasi Lokasi Gudang" subtitle="Kode Gudang: {{ $gudang->kode }}" variant="primary">
                @include('gudang._form')

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2.5">
                        <x-button href="{{ route('gudang.index') }}" variant="secondary" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Perbarui Gudang
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

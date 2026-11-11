<x-app-layout title="Edit Gudang">
    <div class="w-full space-y-4">
        <x-page-header
            title="Edit Gudang"
            :breadcrumbs="['Master Data' => null, 'Gudang' => route('gudang.index'), $gudang->nama => null, 'Edit' => null]"
            :backUrl="route('gudang.index')"
        />

        <form method="POST" action="{{ route('gudang.update', $gudang) }}">
            @csrf
            @method('PUT')
            <x-card title="Perbarui Informasi Gudang" subtitle="Mengubah rincian fasilitas: {{ $gudang->nama }}">
                @include('gudang._form')

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('gudang.index') }}" variant="outline" size="sm">
                            Batal
                        </x-button>
                        <x-button type="submit" variant="primary" size="sm">
                            <x-slot:icon>
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Perbarui Gudang
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

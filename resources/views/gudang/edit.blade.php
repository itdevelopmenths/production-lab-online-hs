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
            <x-card title="Perbarui Informasi Gudang" subtitle="Mengubah rincian fasilitas: {{ $gudang->nama }}" :noPadding="true">
                <x-slot:icon>
                    <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                </x-slot:icon>
                <div class="bg-gray-50 dark:bg-gray-950 p-3 sm:p-4">
                    @include('gudang._form')
                </div>

                <x-slot:footer>
                    <div class="flex items-center justify-end gap-2">
                        <x-button href="{{ route('gudang.index') }}" variant="secondary" size="sm">
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

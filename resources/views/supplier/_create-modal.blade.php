{{-- Modal Tambah Supplier — form sama dengan halaman create, dibuka dari index. --}}
<form method="POST" action="{{ route('supplier.store') }}">
    @csrf
    <input type="hidden" name="_modal" value="create">
    <x-modal name="createOpen" title="Tambah Supplier Baru" subtitle="Identitas, kategori, dan kontak representatif vendor" maxWidth="4xl">
        @include('supplier._form', ['produks' => $produks])

        <x-slot:footer>
            <x-button type="button" variant="secondary" size="sm" @click="createOpen = false">Batal</x-button>
            <x-button type="submit" variant="primary" size="sm">
                <x-slot:icon>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </x-slot:icon>
                Simpan Supplier
            </x-button>
        </x-slot:footer>
    </x-modal>
</form>

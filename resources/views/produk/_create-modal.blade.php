{{-- Modal Tambah Produk — form sama dengan halaman create, dibuka dari index. --}}
<form method="POST" action="{{ route('produk.store') }}">
    @csrf
    <input type="hidden" name="_modal" value="create">
    <x-modal name="createOpen" title="Tambah Produk Baru" subtitle="Spesifikasi SKU, klasifikasi tipe, dan parameter pemesanan" maxWidth="5xl">
        @include('produk._form', ['kategoriList' => $createKategoriList, 'uomList' => $uomList, 'suppliers' => $suppliers, 'klasifikasiAbcList' => $klasifikasiAbcList, 'tipeOptions' => $tipeOptions ?? null])

        <x-slot:footer>
            <x-button type="button" variant="secondary" size="sm" @click="createOpen = false">Batal</x-button>
            <x-button type="submit" variant="primary" size="sm">
                <x-slot:icon>
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </x-slot:icon>
                Simpan Produk
            </x-button>
        </x-slot:footer>
    </x-modal>
</form>

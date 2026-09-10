<x-app-layout title="Stok & Mutasi">
    <div x-data="{ openMutasi: false }">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Stok &amp; Mutasi</h3>
                <p class="text-xs text-gray-500">Kolom Stok = fisik. Kolom Rencana = fisik − alokasi bahan aktif.</p>
            </div>
            <div class="flex gap-2">
                @can('stok.opname')
                <a href="{{ route('stok.opname') }}" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">Opname</a>
                @endcan
                @can('stok.mutasi')
                <button @click="openMutasi = true" class="px-4 py-2 bg-primary-500 text-white text-sm font-medium rounded-xl hover:bg-primary-600">Mutasi Manual</button>
                @endcan
            </div>
        </div>

        <div class="table-wrapper bg-white rounded-xl border border-gray-200 p-4">
            <table id="tbl" class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200"><tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">SKU</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Produk</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Gudang</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600">Kolom Stok</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600">Kolom Rencana</th>
                </tr></thead>
            </table>
        </div>

        {{-- Modal mutasi --}}
        @can('stok.mutasi')
        <div x-show="openMutasi" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div @click.away="openMutasi = false" class="bg-white rounded-xl w-full max-w-lg p-6">
                <h4 class="text-lg font-semibold mb-4">Mutasi Stok Manual</h4>
                <form method="POST" action="{{ route('stok.mutasi') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Produk</label>
                        <select name="produk_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                            @foreach($produk as $p)<option value="{{ $p->id }}">{{ $p->sku }} — {{ $p->nama }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gudang</label>
                        <select name="gudang_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                            @foreach($gudang as $g)<option value="{{ $g->id }}">{{ $g->nama }}</option>@endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
                            <select name="tipe" class="w-full rounded-lg border-gray-300 text-sm"><option value="in">Masuk</option><option value="out">Keluar</option></select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Qty</label>
                            <input type="number" step="0.01" name="qty" class="w-full rounded-lg border-gray-300 text-sm" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <input type="text" name="catatan" class="w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="openMutasi = false" class="px-4 py-2 text-sm text-gray-600">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-primary-500 text-white text-sm font-medium rounded-xl hover:bg-primary-600">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
        @endcan
    </div>

    @push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script>
    $(function(){ $('#tbl').DataTable({ processing:true, serverSide:true, ajax:'{{ route("stok.data") }}',
        columns:[{data:'sku',orderable:false},{data:'nama',orderable:false},{data:'gudang_nama',orderable:false},{data:'kolom_stok',className:'text-right',searchable:false},{data:'kolom_rencana',className:'text-right',searchable:false}],
        language:{processing:'Memuat...',search:'Cari:',paginate:{previous:'Sebelumnya',next:'Berikutnya'},info:'_START_-_END_ dari _TOTAL_',zeroRecords:'Data tidak ditemukan'} }); });
    </script>
    @endpush
</x-app-layout>

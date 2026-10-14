<x-app-layout title="Produk" subtitle="Daftar produk jadi, bahan baku konsentrat/alkohol, dan komponen kemasan">
    <div x-data="{ currentTab: 'all' }" class="space-y-6">
        <x-card>
            {{-- Card Header matching DevScore --}}
            <x-slot:header>
                <div>
                    <h2 class="text-lg font-semibold text-foreground tracking-tight leading-tight">Daftar Produk</h2>
                    <p class="text-sm text-muted-foreground mt-0.5">Seluruh produk jadi, bahan baku, dan kemasan laboratorium</p>
                </div>
                @can('produk.create')
                <x-button href="{{ route('produk.create') }}" variant="primary">
                    <x-slot:icon>
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Tambah Produk
                </x-button>
                @endcan
            </x-slot:header>

            {{-- Filter Bar & Segmented Tabs --}}
            <div class="px-5 pt-4 pb-2 border-b border-border/60 flex flex-col md:flex-row md:items-center justify-between gap-4">
                {{-- Segmented Pill Tabs matching DevScore --}}
                <div class="inline-flex items-center gap-1.5 p-1 bg-muted/60 rounded-xl border border-border/40">
                    <button type="button"
                            @click="currentTab = 'all'; filterTab('all')"
                            :class="currentTab === 'all' ? 'bg-primary text-primary-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground'"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors cursor-pointer select-none">
                        Semua Produk
                    </button>
                    <button type="button"
                            @click="currentTab = 'bahan'; filterTab('bahan')"
                            :class="currentTab === 'bahan' ? 'bg-primary text-primary-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground'"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors cursor-pointer select-none">
                        Bahan Baku
                    </button>
                    <button type="button"
                            @click="currentTab = 'kemas'; filterTab('kemas')"
                            :class="currentTab === 'kemas' ? 'bg-primary text-primary-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground'"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors cursor-pointer select-none">
                        Kemasan
                    </button>
                    <button type="button"
                            @click="currentTab = 'produk_jadi'; filterTab('produk_jadi')"
                            :class="currentTab === 'produk_jadi' ? 'bg-primary text-primary-foreground shadow-xs font-semibold' : 'text-muted-foreground hover:text-foreground'"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors cursor-pointer select-none">
                        Produk Jadi
                    </button>
                </div>

                {{-- Additional Filter --}}
                <div class="flex items-center gap-2.5">
                    <label for="filter-kategori" class="text-xs font-medium text-muted-foreground">Kategori:</label>
                    <select id="filter-kategori" class="h-9 rounded-lg border border-border bg-card px-3 text-xs text-foreground outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer">
                        <option value="">Semua Kategori</option>
                        @foreach($kategoriList as $k)
                            <option value="{{ $k->id }}">{{ $k->nama }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table id="tbl" class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left w-28">SKU</th>
                            <th class="px-4 py-3 text-left">Nama Produk</th>
                            <th class="px-4 py-3 text-left w-32">Kategori</th>
                            <th class="px-4 py-3 text-left w-32">Varian</th>
                            <th class="px-4 py-3 text-left w-28">Tipe</th>
                            <th class="px-4 py-3 text-center w-20">Satuan</th>
                            <th class="px-4 py-3 text-center w-24">Status</th>
                            <th class="px-4 py-3 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </x-card>
    </div>

    @push('scripts')
    <script>
    let activeTipe = 'all';
    let table;

    function filterTab(tipe) {
        activeTipe = tipe;
        if (table) {
            table.ajax.reload();
        }
    }

    function hapus(url){
        Swal.fire({
            title: 'Hapus data produk ini?',
            text: 'Data produk yang memiliki histori transaksi stok tidak dapat dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#18181b',
            cancelButtonColor: '#71717a',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(r => {
            if(r.isConfirmed){
                fetch(url, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                })
                .then(async res => {
                    const d = await res.json();
                    if (res.ok) {
                        Swal.fire({ icon: 'success', title: 'Terhapus', text: d.message, confirmButtonColor: '#18181b' });
                        $('#tbl').DataTable().ajax.reload();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal Menghapus', text: d.message || 'Terjadi kesalahan.', confirmButtonColor: '#18181b' });
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Kesalahan Sistem', text: 'Tidak dapat terhubung ke server.', confirmButtonColor: '#18181b' });
                });
            }
        });
    }

    $(function(){
        table = $('#tbl').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("produk.data") }}',
                data: function(d) {
                    d.tipe = activeTipe;
                    d.kategori_id = $('#filter-kategori').val();
                }
            },
            columns: [
                { data: 'sku', name: 'sku', searchable: true, className: 'px-4 py-3.5 align-middle text-left font-mono text-xs' },
                { data: 'nama', name: 'nama', searchable: true, className: 'px-4 py-3.5 align-middle text-left font-medium' },
                { data: 'kategori_nama', name: 'kategori_nama', orderable: false, searchable: false, className: 'px-4 py-3.5 align-middle text-left' },
                { data: 'varian_nama', name: 'varian_nama', orderable: false, searchable: false, className: 'px-4 py-3.5 align-middle text-left' },
                { data: 'tipe', name: 'tipe', searchable: false, className: 'px-4 py-3.5 align-middle text-left' },
                { data: 'satuan', name: 'satuan', searchable: false, className: 'px-4 py-3.5 align-middle text-center font-mono text-xs' },
                { data: 'is_active', name: 'is_active', searchable: false, className: 'px-4 py-3.5 align-middle text-center' },
                { data: 'action', orderable: false, searchable: false, className: 'px-4 py-3.5 align-middle text-center' }
            ]
        });

        $('#filter-kategori').on('change', function() {
            table.ajax.reload();
        });
    });
    </script>
    @endpush
</x-app-layout>

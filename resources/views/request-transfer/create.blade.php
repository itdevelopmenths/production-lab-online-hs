<x-app-layout title="Buat Request/Transfer">
    <div class="max-w-5xl mx-auto space-y-6">
        <x-page-header
            title="Buat Request & Transfer"
            subtitle="Formulir mutasi stok internal antar gudang atau permintaan bahan baku produksi"
            :breadcrumbs="['Request & Transfer' => route('rt.index'), 'Buat Dokumen' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('rt.index') }}" variant="outline" size="sm">
                    &larr; Kembali
                </x-button>
            </x-slot:actions>
        </x-page-header>

        @if(isset($batch))
        <div>
            <x-alert type="info" title="Referensi Batch Produksi:">
                Otomatis mengisi kekurangan bahan baku untuk produksi batch <strong>{{ $batch->no_batch }}</strong> ({{ $batch->produk?->nama }}).
                Gudang tujuan otomatis diset ke <strong>{{ $batch->gudangOperasional?->nama ?? 'Gudang Operasional' }}</strong> sesuai pengaturan batch.
            </x-alert>
        </div>
        @endif

        <div x-data="rtForm({{ Js::from([
            'jenis' => old('jenis', $defaultJenis ?? 'req_bahan'),
            'gudangAsalId' => (string) old('gudang_asal_id', $defaultGudangAsalId ?? ''),
            'gudangTujuanId' => (string) old('gudang_tujuan_id', $defaultGudangTujuanId ?? ''),
            'rows' => $prefilledRows ?? [],
        ]) }})">
            <form method="POST" action="{{ route('rt.store') }}" x-data="{ submitting: false }" @submit="if(submitting) return false; submitting = true">
            @csrf
            <div class="space-y-6">
                {{-- Card 1: Parameter Transfer & Lokasi --}}
                <x-card title="Parameter Dokumen Mutasi" subtitle="Tentukan jenis transfer logistik dan alur gudang asal ke gudang tujuan">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <x-form-group name="jenis" label="Jenis Mutasi" :required="true">
                            <x-select name="jenis" x-model="jenis" :required="true">
                                <option value="req_bahan">Request Bahan (Approval 5-Tahap)</option>
                                <option value="retur_bahan">Retur Bahan ke Gudang Asal</option>
                                <option value="kirim_produk_jadi">Kirim Produk Jadi ke FF</option>
                                <option value="antar_fulfillment">Antar Fulfillment</option>
                                <option value="retur_produk_jadi">Retur Produk Jadi</option>
                            </x-select>
                            <x-slot:help>
                                <span class="inline-flex items-center gap-1 font-mono text-[10px] text-foreground bg-muted px-2 py-0.5 rounded-md border border-border mt-1"
                                      x-text="jenis === 'req_bahan' ? '⚡ Alur 5 Tahap: Draft ➔ Disetujui ➔ Diproses ➔ Dikirim ➔ Selesai' : '⚡ Alur 3 Tahap Langsung: Draft ➔ Dikirim ➔ Selesai'"></span>
                            </x-slot:help>
                        </x-form-group>

                        <x-form-group name="gudang_asal_id" label="Gudang Asal" help="Gudang pengirim / sumber stok">
                            <x-select name="gudang_asal_id" x-model="gudangAsalId" placeholder="— Pilih Gudang Asal —">
                                @foreach($allGudang as $g)
                                    <option value="{{ $g->id }}" @selected((string)old('gudang_asal_id', $defaultGudangAsalId ?? '') === (string)$g->id)>{{ $g->nama }} ({{ ucfirst($g->tipe) }})</option>
                                @endforeach
                            </x-select>
                        </x-form-group>

                        <x-form-group name="gudang_tujuan_id" label="Gudang Tujuan" help="Gudang penerima alokasi fisik (gudang operasional batch)">
                            <x-select name="gudang_tujuan_id" x-model="gudangTujuanId" placeholder="— Pilih Gudang Tujuan —">
                                @foreach($allGudang as $g)
                                    <option value="{{ $g->id }}" @selected((string)old('gudang_tujuan_id', $defaultGudangTujuanId ?? '') === (string)$g->id)>{{ $g->nama }} ({{ ucfirst($g->tipe) }})</option>
                                @endforeach
                            </x-select>
                        </x-form-group>
                    </div>

                    <div class="mt-5 pt-5 border-t border-border">
                        <x-form-group name="catatan" label="Catatan / Referensi Dokumen" help="Informasi tambahan untuk tim logistik / gudang operasional">
                            <x-textarea name="catatan" rows="2" placeholder="Tuliskan catatan khusus atau nomor referensi kerja jika ada..." :value="old('catatan', $defaultCatatan ?? '')" />
                        </x-form-group>
                    </div>
                </x-card>

                {{-- Card 2: Rincian Item Mutasi --}}
                <x-card title="Daftar Barang & Kuantitas Diminta" subtitle="Tentukan SKU produk/bahan dan kuantitas unit yang akan ditransfer" :noPadding="true">
                    <div class="overflow-visible">
                        <table class="w-full text-xs">
                            <thead class="bg-muted/70 border-b border-border text-muted-foreground font-semibold uppercase text-[11px] tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left w-[62%]">Produk / Bahan Baku <span class="text-destructive">*</span></th>
                                    <th class="px-4 py-3 text-right w-52">Qty Diminta <span class="text-destructive">*</span></th>
                                    <th class="px-4 py-3 text-center w-14">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border bg-card">
                                <template x-for="(row, i) in rows" :key="i">
                                    <tr
                                        class="hover:bg-muted/40 transition"
                                        :style="'position: relative; z-index: ' + (activeRow === i ? 200 : (100 - i))"
                                        @hs-dropdown-opened.window="if ($event.detail === `items[${i}][produk_id]`) activeRow = i"
                                        @hs-dropdown-closed.window="if ($event.detail === `items[${i}][produk_id]` && activeRow === i) activeRow = null"
                                        @product-selected="onProductSelected(i, $event.detail)"
                                    >
                                        <td class="px-4 py-3">
                                            <div
                                                x-data="productSearchSelect({
                                                    name: `items[${i}][produk_id]`,
                                                    selectedId: row.produk_id,
                                                    selectedItem: row.selectedItem,
                                                    placeholder: '— Cari & Pilih Produk / Bahan (Paginasi 10) —',
                                                    url: '{{ route('produk.select-data', [], false) }}'
                                                })"
                                                x-init="init()"
                                                class="relative w-full"
                                                :class="open ? 'z-[100]' : 'z-10'"
                                                style="position: relative;"
                                                @click.outside="open = false; if (activeRow === i) activeRow = null"
                                                @keydown.escape.window="open = false; if (activeRow === i) activeRow = null"
                                                @hs-dropdown-opened.window="if ($event.detail && $event.detail !== `items[${i}][produk_id]`) open = false"
                                            >
                                                <input type="hidden" :name="`items[${i}][produk_id]`" :value="selectedId" required>

                                                <div
                                                    @click="toggle()"
                                                    role="button"
                                                    tabindex="0"
                                                    @keydown.enter.prevent="toggle()"
                                                    @keydown.space.prevent="toggle()"
                                                    class="w-full flex items-center justify-between text-left rounded-lg border border-input bg-background px-3 py-2 text-xs shadow-xs hover:border-foreground/30 focus:outline-none focus:ring-1 focus:ring-ring transition cursor-pointer select-none"
                                                >
                                                    <div class="flex items-center gap-1.5 truncate flex-1 min-w-0 pointer-events-none">
                                                        <template x-if="selectedItem">
                                                            <div class="flex items-center gap-1.5 truncate">
                                                                <span class="font-mono text-foreground font-semibold bg-muted px-1.5 py-0.5 rounded-md border border-border text-[11px]" x-text="selectedItem.sku"></span>
                                                                <span class="font-medium text-foreground truncate" x-text="selectedItem.nama"></span>
                                                                <span class="text-[10px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="selectedItem.satuan"></span>
                                                            </div>
                                                        </template>
                                                        <template x-if="!selectedItem">
                                                            <span class="text-muted-foreground font-normal" x-text="placeholder"></span>
                                                        </template>
                                                    </div>
                                                    <div class="flex items-center gap-1 ml-2 shrink-0 pointer-events-none">
                                                        <svg class="w-3.5 h-3.5 text-muted-foreground transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                                    </div>
                                                </div>

                                                <div
                                                    x-show="open"
                                                    x-cloak
                                                    style="display: none; position: absolute; left: 0; top: 100%; z-index: 9999; width: 100%; min-width: 320px; box-sizing: border-box;"
                                                    class="absolute left-0 top-full z-[100] mt-1.5 w-full min-w-[320px] bg-card rounded-xl border border-border shadow-xl text-xs overflow-hidden"
                                                >
                                                    <div class="p-2.5 border-b border-border bg-muted/50 flex items-center gap-2">
                                                        <svg class="w-3.5 h-3.5 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                        <input type="text" x-model="search" @input="onSearch($event)" @keydown.enter.prevent="fetchItems(1, false)" x-ref="searchInput" placeholder="Ketik SKU atau nama..." class="w-full bg-background border border-input rounded-lg px-2.5 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring" />
                                                    </div>

                                                    <div class="max-h-52 overflow-y-auto divide-y divide-border">
                                                        <template x-for="item in items" :key="item.id">
                                                            <div @click="select(item)" :class="selectedId == item.id ? 'bg-muted text-foreground font-semibold' : 'text-foreground hover:bg-muted/50'" class="px-3.5 py-2.5 cursor-pointer transition flex items-center justify-between gap-2 select-none">
                                                                <div class="flex items-center gap-2 truncate pointer-events-none">
                                                                    <span class="font-mono text-foreground font-medium bg-muted px-1.5 py-0.5 rounded-md border border-border text-[10px]" x-text="item.sku"></span>
                                                                    <span class="truncate font-medium text-foreground" x-text="item.nama"></span>
                                                                </div>
                                                                <div class="flex items-center gap-1 shrink-0 pointer-events-none">
                                                                    <span class="text-[10px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="item.satuan"></span>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        <div x-show="!loading && items.length === 0" class="py-4 text-center text-muted-foreground">Tidak ada produk yang cocok.</div>
                                                    </div>

                                                    <div class="p-2 border-t border-border bg-muted/40 flex items-center justify-between text-[11px] text-muted-foreground">
                                                        <span x-text="`Data: ${items.length}/${total}`"></span>
                                                        <button type="button" x-show="hasMore" @click="loadMore()" :disabled="loading" class="px-2.5 py-1 rounded-md bg-background border border-border hover:bg-muted text-foreground font-medium transition disabled:opacity-50 cursor-pointer shadow-xs">
                                                            <span x-text="loading ? '...' : '+10 Lagi'"></span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Indikator Qty Tersedia dari Gudang Asal -->
                                            <div class="mt-1.5 flex items-center justify-between text-[11px] min-h-[20px]">
                                                <div>
                                                    <template x-if="gudangAsalId && row.produk_id">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="text-muted-foreground font-medium">Qty Tersedia:</span>
                                                            <span
                                                                class="font-mono font-bold px-2 py-0.5 rounded-full border text-[11px]"
                                                                :class="(stokMap[row.produk_id] !== undefined && stokMap[row.produk_id] > 0) ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/20' : 'text-destructive bg-destructive/10 border-destructive/20'"
                                                                x-text="(stokMap[row.produk_id] !== undefined ? Number(stokMap[row.produk_id]).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 4}) : '0') + ' ' + (row.satuan || '')"
                                                            ></span>
                                                        </div>
                                                    </template>
                                                    <template x-if="!gudangAsalId && row.produk_id">
                                                        <span class="text-amber-600 text-[10px] italic">Pilih Gudang Asal untuk cek ketersediaan stok</span>
                                                    </template>
                                                </div>

                                                <!-- Peringatan jika Qty Diminta melebihi Qty Tersedia -->
                                                <template x-if="gudangAsalId && row.produk_id && row.qty && stokMap[row.produk_id] !== undefined && Number(row.qty) > Number(stokMap[row.produk_id])">
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-destructive bg-destructive/10 border border-destructive/20 px-2 py-0.5 rounded-md">
                                                        <svg class="w-3 h-3 text-destructive" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                        Melebihi stok gudang asal!
                                                    </span>
                                                </template>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="relative flex items-center">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    :name="`items[${i}][qty_diminta]`"
                                                    x-model="row.qty"
                                                    placeholder="0.00"
                                                    required
                                                    class="w-full rounded-lg border-input bg-background text-xs py-2 pr-16 font-mono text-right focus:border-ring focus:ring-1 focus:ring-ring shadow-xs"
                                                />
                                                <span class="absolute right-2.5 px-2 py-0.5 text-[10px] font-mono font-semibold text-muted-foreground bg-muted border border-border rounded-md uppercase tracking-wider pointer-events-none" x-text="row.satuan || 'Unit'"></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button type="button" @click="if(rows.length > 1) rows.splice(i,1)" :disabled="rows.length <= 1"
                                                    class="p-1.5 text-muted-foreground hover:text-destructive hover:bg-destructive/10 disabled:opacity-30 disabled:cursor-not-allowed transition rounded-lg"
                                                    title="Hapus baris">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 bg-muted/30 border-t border-border flex items-center justify-between">
                        <x-button type="button" @click="rows.push({produk_id:'',qty:'',satuan:'',selectedItem:null})" variant="outline" size="sm">
                            <x-slot:icon>
                                <svg class="w-3.5 h-3.5 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </x-slot:icon>
                            Tambah Baris Barang
                        </x-button>

                        <div class="text-xs text-muted-foreground font-mono">
                            Total Item: <span class="font-bold text-foreground" x-text="rows.length"></span>
                        </div>
                    </div>

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2.5">
                            <x-button href="{{ route('rt.index') }}" variant="outline" size="sm">
                                Batal
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm" ::disabled="submitting">
                                <x-slot:icon>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </x-slot:icon>
                                <span x-text="submitting ? 'Menyimpan...' : 'Simpan Draft Dokumen'"></span>
                            </x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </div>
        </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function rtForm(initial = {}) {
            return {
                jenis: initial.jenis || '{{ old("jenis", "req_bahan") }}',
                gudangAsalId: initial.gudangAsalId !== undefined ? String(initial.gudangAsalId) : '{{ old("gudang_asal_id", $defaultGudangAsalId ?? "") }}',
                gudangTujuanId: initial.gudangTujuanId !== undefined ? String(initial.gudangTujuanId) : '{{ old("gudang_tujuan_id", $defaultGudangTujuanId ?? "") }}',
                rows: (initial.rows && initial.rows.length > 0) ? initial.rows : [
                    { produk_id: '', qty: '', satuan: '', selectedItem: null }
                ],
                activeRow: null,
                stokMap: initial.stokMap || {},
                loadingStok: false,

                init() {
                    this.$watch('gudangAsalId', (newVal) => {
                        this.refreshAllStok();
                    });
                    if (this.gudangAsalId) {
                        this.refreshAllStok();
                    }
                },

                refreshAllStok() {
                    if (!this.gudangAsalId) {
                        this.stokMap = {};
                        return;
                    }
                    const productIds = this.rows.map(r => r.produk_id).filter(id => Boolean(id));
                    if (productIds.length === 0) return;

                    this.fetchStokForProducts(productIds);
                },

                fetchStokForProducts(productIds) {
                    if (!this.gudangAsalId || !productIds || productIds.length === 0) return;
                    this.loadingStok = true;
                    fetch(`{{ route('rt.stok-tersedia') }}?gudang_id=${this.gudangAsalId}&produk_ids=${productIds.join(',')}`)
                        .then(res => res.json())
                        .then(data => {
                            this.loadingStok = false;
                            if (data && data.stok) {
                                this.stokMap = { ...this.stokMap, ...data.stok };
                            }
                        })
                        .catch(() => {
                            this.loadingStok = false;
                        });
                },

                onProductSelected(i, item) {
                    const row = this.rows[i];
                    if (item) {
                        row.produk_id = item.id;
                        row.satuan = item.satuan;
                        row.selectedItem = item;
                        if (this.gudangAsalId) {
                            this.fetchStokForProducts([item.id]);
                        }
                    } else {
                        row.produk_id = '';
                        row.satuan = '';
                        row.selectedItem = null;
                    }
                }
            };
        }
    </script>
    @endpush
</x-app-layout>

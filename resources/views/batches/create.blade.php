<x-app-layout title="Rencana Batch Baru">
    <div class="max-w-4xl mx-auto space-y-6">
        <x-page-header
            title="Rencana Batch Baru"
            subtitle="Alokasi bahan baku otomatis dihitung dari gabungan resep (BOM) seluruh produk jadi luaran"
            :breadcrumbs="['Produksi' => route('batches.index'), 'Rencana Baru' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('batches.index') }}" variant="outline" size="sm">
                    &larr; Kembali
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <div x-data="batchCreateForm()">
            <form method="POST" action="{{ route('batches.store') }}">
                @csrf
                <div class="space-y-6">
                    {{-- Card 1: Parameter & Lokasi Pabrik --}}
                    <x-card title="Parameter Fasilitas & Jadwal" subtitle="Pilih fasilitas gudang operasional penarik bahan dan gudang tujuan produk jadi">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <x-form-group name="gudang_operasional_id" label="Gudang Operasional (Sumber Bahan)" help="Gudang asal pengambilan stok bahan baku saat batch dirilis">
                                <x-select name="gudang_operasional_id" placeholder="— Pilih Gudang Bahan —">
                                    @foreach($gudangOp as $g)
                                        <option value="{{ $g->id }}" @selected(old('gudang_operasional_id') == $g->id)>{{ $g->nama }}</option>
                                    @endforeach
                                </x-select>
                            </x-form-group>

                            <x-form-group name="gudang_tujuan_rencana_id" label="Gudang Tujuan (Produk Jadi)" help="Gudang penyimpanan setelah produk selesai diproduksi">
                                <x-select name="gudang_tujuan_rencana_id" placeholder="— Pilih Gudang Output —">
                                    @foreach($gudangFf as $g)
                                        <option value="{{ $g->id }}" @selected(old('gudang_tujuan_rencana_id') == $g->id)>{{ $g->nama }}</option>
                                    @endforeach
                                </x-select>
                            </x-form-group>

                            <x-form-group name="tanggal" label="Tanggal Rencana" :required="true">
                                <x-input type="date" name="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" :required="true" />
                            </x-form-group>
                        </div>
                    </x-card>

                    {{-- Card 2: Multi-Output Produk Jadi Luaran --}}
                    <x-card title="Produk Jadi Luaran (Multi-Output)" subtitle="Dalam 1 batch dapat menghasilkan satu atau beberapa varian/ukuran produk jadi sekaligus" :noPadding="true">
                        <div class="overflow-visible">
                            <table class="w-full text-xs">
                                <thead class="bg-muted/70 border-b border-border text-muted-foreground font-semibold uppercase text-[11px] tracking-wider">
                                    <tr>
                                        <th class="px-4 py-3 text-left w-[65%]">Produk Jadi (BOM) <span class="text-destructive">*</span></th>
                                        <th class="px-4 py-3 text-right w-52">Target Rencana <span class="text-destructive">*</span></th>
                                        <th class="px-4 py-3 text-center w-14">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    <template x-for="(row, i) in rows" :key="i">
                                        <tr
                                            class="hover:bg-muted/40 transition"
                                            :style="'position: relative; z-index: ' + (activeRow === i ? 200 : (100 - i))"
                                            @hs-dropdown-opened.window="if ($event.detail === `outputs[${i}][produk_id]`) activeRow = i"
                                            @hs-dropdown-closed.window="if ($event.detail === `outputs[${i}][produk_id]` && activeRow === i) activeRow = null"
                                            @product-selected="if($event.detail) { row.produk_id = $event.detail.id; row.satuan = $event.detail.satuan; row.selectedItem = $event.detail; } else { row.produk_id = ''; row.satuan = ''; row.selectedItem = null; }"
                                        >
                                            <td class="px-4 py-3">
                                                <div
                                                    x-data="productSearchSelect({
                                                        name: `outputs[${i}][produk_id]`,
                                                        selectedId: row.produk_id,
                                                        selectedItem: row.selectedItem,
                                                        filterTipe: 'produk_jadi',
                                                        placeholder: '— Cari & Pilih Produk Jadi (BOM) —',
                                                        url: '{{ route('produk.select-data', [], false) }}'
                                                    })"
                                                    x-init="init()"
                                                    class="relative w-full"
                                                    :class="open ? 'z-[100]' : 'z-10'"
                                                    style="position: relative;"
                                                    @click.outside="open = false; if (activeRow === i) activeRow = null"
                                                    @keydown.escape.window="open = false; if (activeRow === i) activeRow = null"
                                                    @hs-dropdown-opened.window="if ($event.detail && $event.detail !== `outputs[${i}][produk_id]`) open = false"
                                                >
                                                    <input type="hidden" :name="`outputs[${i}][produk_id]`" :value="selectedId" required>

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
                                                            <input type="text" x-model="search" @input="onSearch($event)" @keydown.enter.prevent="fetchItems(1, false)" x-ref="searchInput" placeholder="Ketik SKU atau nama produk jadi..." class="w-full bg-background border border-input rounded-lg px-2.5 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring" />
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
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="relative flex items-center">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        :name="`outputs[${i}][qty_rencana]`"
                                                        x-model="row.qty_rencana"
                                                        placeholder="misal: 100"
                                                        required
                                                        class="w-full rounded-lg border-input bg-background text-xs py-2 pr-16 font-mono text-right focus:border-ring focus:ring-1 focus:ring-ring shadow-xs"
                                                    />
                                                    <span class="absolute right-2.5 px-2 py-0.5 text-[10px] font-mono font-semibold text-muted-foreground bg-muted border border-border rounded-md uppercase tracking-wider pointer-events-none" x-text="row.satuan || 'Pcs'"></span>
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
                            <x-button type="button" @click="rows.push({produk_id:'',qty_rencana:'',satuan:'',selectedItem:null})" variant="outline" size="sm">
                                <x-slot:icon>
                                    <svg class="w-3.5 h-3.5 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </x-slot:icon>
                                + Tambah Produk Jadi Luaran
                            </x-button>

                            <div class="text-xs text-muted-foreground font-mono">
                                Total Varian: <span class="font-bold text-foreground" x-text="rows.length"></span>
                            </div>
                        </div>

                        <x-slot:footer>
                            <div class="flex items-center justify-end gap-2.5">
                                <x-button href="{{ route('batches.index') }}" variant="outline" size="sm">
                                    Batal
                                </x-button>
                                <x-button type="submit" variant="primary" size="sm">
                                    <x-slot:icon>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    </x-slot:icon>
                                    Simpan & Hitung Alokasi
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
    function batchCreateForm() {
        return {
            activeRow: null,
            rows: [
                {
                    produk_id: '{{ old('produk_id', '') }}',
                    qty_rencana: '{{ old('qty_rencana', '') }}',
                    satuan: '',
                    selectedItem: null
                }
            ]
        }
    }
    </script>
    @endpush
</x-app-layout>

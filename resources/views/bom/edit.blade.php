<x-app-layout title="Kelola BOM" :backUrl="route('bom.index')">
    <div class="mx-auto max-w-4xl space-y-5">
        <x-page-header
            title="Kelola Formula Resep (BOM)"
            subtitle="Definisi kebutuhan komposisi bahan baku & kemasan per 1 unit produk jadi"
            :breadcrumbs="['Master Data' => null, 'BOM' => route('bom.index'), $produk->nama => null, 'Kelola Resep' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('bom.index') }}" variant="outline" size="sm">
                    &larr; Kembali ke Daftar BOM
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <div x-data="bomForm()">
            <form method="POST" action="{{ route('bom.update', $produk) }}">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    <!-- Info Header Card -->
                    <div class="bg-card rounded-xl border border-border p-4 shadow-xs flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-foreground font-semibold bg-muted px-2 py-0.5 rounded-md border border-border text-xs">{{ $produk->sku }}</span>
                                <h3 class="text-sm font-semibold text-foreground">{{ $produk->nama }}</h3>
                            </div>
                            <p class="text-xs text-muted-foreground mt-1">Kalkulasi alokasi bahan otomatis: <span class="font-mono font-medium text-foreground">Kebutuhan Bahan = Formula &times; Qty Rencana Batch</span></p>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Satuan Output</span>
                            <p class="font-mono font-bold text-sm text-foreground">{{ $produk->satuan }}</p>
                        </div>
                    </div>

                    <!-- Table Card -->
                    <x-card title="Komposisi Bahan Baku & Kemasan" subtitle="Gunakan dropdown search untuk memilih bahan dan tentukan kuantitas" :noPadding="true">
                        <div class="overflow-visible">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left w-[62%]">Bahan Baku / Kemasan (Searchable) <span class="text-destructive">*</span></th>
                                        <th class="px-4 py-3 text-right w-52">Qty per Unit <span class="text-destructive">*</span></th>
                                        <th class="px-4 py-3 text-center w-14">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <template x-for="(row, i) in rows" :key="i">
                                        <tr
                                            class="hover:bg-muted/40 transition"
                                            :style="'position: relative; z-index: ' + (activeRow === i ? 200 : (100 - i))"
                                            @hs-dropdown-opened.window="if ($event.detail === `items[${i}][bahan_id]`) activeRow = i"
                                            @hs-dropdown-closed.window="if ($event.detail === `items[${i}][bahan_id]` && activeRow === i) activeRow = null"
                                            @product-selected="if($event.detail) { row.bahan_id = $event.detail.id; row.satuan = $event.detail.satuan; row.selectedItem = $event.detail; } else { row.bahan_id = ''; row.satuan = ''; row.selectedItem = null; }"
                                        >
                                            <td class="px-4 py-2.5">
                                                <div
                                                    x-data="productSearchSelect({
                                                        name: `items[${i}][bahan_id]`,
                                                        selectedId: row.bahan_id,
                                                        selectedItem: row.selectedItem,
                                                        filterTipe: ['bahan', 'kemas'],
                                                        placeholder: '— Cari & Pilih Bahan Baku / Kemasan —',
                                                        url: '{{ route('produk.select-data', [], false) }}'
                                                    })"
                                                    x-init="init()"
                                                    class="relative w-full"
                                                    :class="open ? 'z-[100]' : 'z-10'"
                                                    style="position: relative;"
                                                    @click.outside="open = false; if (activeRow === i) activeRow = null"
                                                    @keydown.escape.window="open = false; if (activeRow === i) activeRow = null"
                                                    @hs-dropdown-opened.window="if ($event.detail && $event.detail !== `items[${i}][bahan_id]`) open = false"
                                                >
                                                    <input type="hidden" :name="`items[${i}][bahan_id]`" :value="selectedId" required>

                                                    <div
                                                        @click="toggle()"
                                                        role="button"
                                                        tabindex="0"
                                                        @keydown.enter.prevent="toggle()"
                                                        @keydown.space.prevent="toggle()"
                                                        class="w-full flex items-center justify-between text-left rounded-lg border border-border bg-background px-3 py-2 text-xs shadow-xs hover:border-foreground/30 focus:outline-none focus:ring-2 focus:ring-primary/20 transition cursor-pointer select-none"
                                                    >
                                                        <div class="flex items-center gap-1.5 truncate flex-1 min-w-0 pointer-events-none">
                                                            <template x-if="selectedItem">
                                                                <div class="flex items-center gap-1.5 truncate">
                                                                    <span class="font-mono text-foreground font-semibold bg-muted px-1.5 py-0.5 rounded border border-border text-[11px]" x-text="selectedItem.sku"></span>
                                                                    <span class="font-medium text-foreground truncate" x-text="selectedItem.nama"></span>
                                                                    <span class="text-[10px] font-mono text-muted-foreground bg-muted px-1 py-0.5 rounded border border-border" x-text="selectedItem.satuan"></span>
                                                                </div>
                                                            </template>
                                                            <template x-if="!selectedItem">
                                                                <span class="text-muted-foreground font-normal" x-text="placeholder"></span>
                                                            </template>
                                                        </div>
                                                        <div class="flex items-center gap-1 ml-2 shrink-0 pointer-events-none">
                                                            <svg class="size-3.5 text-muted-foreground transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                                        </div>
                                                    </div>

                                                    <div
                                                        x-show="open"
                                                        x-cloak
                                                        style="display: none; position: absolute; left: 0; top: 100%; z-index: 9999; width: 100%; min-width: 320px; box-sizing: border-box;"
                                                        class="absolute left-0 top-full z-[100] mt-1 w-full min-w-[320px] bg-card rounded-xl border border-border shadow-xl text-xs overflow-hidden"
                                                    >
                                                        <div class="p-2 border-b border-border bg-muted/40 flex items-center gap-1.5">
                                                            <svg class="size-3.5 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                            <input type="text" x-model="search" @input="onSearch($event)" @keydown.enter.prevent="fetchItems(1, false)" x-ref="searchInput" placeholder="Ketik SKU atau nama bahan..." class="w-full bg-background border border-border rounded-md px-2 py-1 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20" />
                                                        </div>

                                                        <div class="max-h-52 overflow-y-auto divide-y divide-border">
                                                            <template x-for="item in items" :key="item.id">
                                                                <div @click="select(item)" :class="selectedId == item.id ? 'bg-accent text-accent-foreground font-semibold' : 'text-foreground hover:bg-muted/50'" class="px-3 py-2 cursor-pointer transition flex items-center justify-between gap-2 select-none">
                                                                    <div class="flex items-center gap-1.5 truncate pointer-events-none">
                                                                        <span class="font-mono text-muted-foreground font-medium bg-muted px-1.5 py-0.5 rounded border border-border text-[10px]" x-text="item.sku"></span>
                                                                        <span class="truncate font-medium text-foreground" x-text="item.nama"></span>
                                                                    </div>
                                                                    <span class="text-[10px] font-mono text-muted-foreground bg-muted px-1 py-0.5 rounded border border-border" x-text="item.satuan"></span>
                                                                </div>
                                                            </template>
                                                            <div x-show="!loading && items.length === 0" class="py-4 text-center text-muted-foreground">Tidak ada bahan yang cocok.</div>
                                                        </div>

                                                        <div class="p-2 border-t border-border bg-muted/40 flex items-center justify-between text-[11px] text-muted-foreground">
                                                            <span x-text="`Data: ${items.length}/${total}`"></span>
                                                            <button type="button" x-show="hasMore" @click="loadMore()" :disabled="loading" class="px-2 py-0.5 rounded-md bg-card border border-border hover:bg-muted text-foreground font-medium transition disabled:opacity-50 cursor-pointer">
                                                                <span x-text="loading ? '...' : '+10 Lagi'"></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-2.5">
                                                <div class="relative flex items-center">
                                                    <input
                                                        type="number"
                                                        step="0.0001"
                                                        min="0.0001"
                                                        :name="`items[${i}][qty_per_unit]`"
                                                        x-model="row.qty"
                                                        placeholder="0.0000"
                                                        class="w-full rounded-lg border border-border bg-background text-xs py-1.5 pr-16 font-mono text-right text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 shadow-xs"
                                                        required
                                                    >
                                                    <span class="absolute right-2 px-1.5 py-0.5 text-[10px] font-mono font-bold text-foreground bg-muted border border-border rounded uppercase tracking-wider pointer-events-none" x-text="row.satuan || 'Unit'"></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-2.5 text-center">
                                                <button type="button" @click="if(rows.length > 1) rows.splice(i,1)" :disabled="rows.length <= 1" class="p-1.5 text-muted-foreground hover:text-destructive disabled:opacity-30 disabled:cursor-not-allowed transition rounded cursor-pointer" title="Hapus baris">
                                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="px-4 py-3 bg-muted/30 border-t border-border flex items-center justify-between">
                            <x-button type="button" @click="rows.push({bahan_id:'',qty:'',satuan:'',selectedItem:null})" variant="outline" size="sm">
                                <x-slot:icon>
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </x-slot:icon>
                                Tambah Baris Bahan
                            </x-button>

                            <div class="text-xs text-muted-foreground font-mono">
                                Total Komponen: <span class="font-bold text-foreground" x-text="rows.length"></span> Bahan
                            </div>
                        </div>

                        <x-slot:footer>
                            <div class="flex items-center justify-end gap-2">
                                <x-button href="{{ route('bom.index') }}" variant="outline" size="sm">
                                    Batal
                                </x-button>
                                <x-button type="submit" variant="primary" size="sm">
                                    <x-slot:icon>
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    </x-slot:icon>
                                    Simpan Formula Resep (BOM)
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
    function bomForm(){
        @php
            $initialRows = $items->map(function($i) {
                return [
                    'bahan_id' => (string) $i->bahan_id,
                    'qty' => (float) $i->qty_per_unit,
                    'satuan' => $i->bahan?->satuan ?? '',
                    'selectedItem' => $i->bahan ? [
                        'id' => $i->bahan->id,
                        'sku' => $i->bahan->sku,
                        'nama' => $i->bahan->nama,
                        'satuan' => $i->bahan->satuan,
                        'tipe' => $i->bahan->tipe,
                    ] : null,
                ];
            })->values()->all();
        @endphp
        const init = @json($initialRows);

        return {
            rows: init.length ? init : [{ bahan_id: '', qty: '', satuan: '', selectedItem: null }],
            activeRow: null
        };
    }
    </script>
    @endpush
</x-app-layout>

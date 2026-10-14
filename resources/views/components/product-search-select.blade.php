@props([
    'name' => 'produk_id',
    'selectedId' => null,
    'selectedItem' => null,
    'placeholder' => '— Cari & Pilih Produk / Bahan —',
    'filterTipe' => null, // 'bahan', 'kemas', 'produk_jadi', or array ['bahan', 'kemas']
    'required' => false,
    'disabled' => false,
    'size' => 'sm',
])

@php
    $selectedIdVal = $selectedId ?? old($name);
    // Jika ada item awal terpilih dari database, siapkan datanya
    if ($selectedIdVal && !$selectedItem) {
        $found = \App\Models\Produk::find($selectedIdVal);
        if ($found) {
            $selectedItem = [
                'id' => $found->id,
                'sku' => $found->sku,
                'nama' => $found->nama,
                'satuan' => $found->satuan,
                'tipe' => $found->tipe,
            ];
        }
    }
@endphp

<div
    x-data="productSearchSelect({
        name: '{{ $name }}',
        selectedId: '{{ $selectedIdVal }}',
        selectedItem: @js($selectedItem),
        filterTipe: @js($filterTipe),
        placeholder: '{{ $placeholder }}',
        url: '{{ route('produk.select-data', [], false) }}'
    })"
    x-init="init()"
    class="relative w-full"
    :class="open ? 'z-[100]' : 'z-10'"
    style="position: relative;"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    @hs-dropdown-opened.window="if ($event.detail && $event.detail !== '{{ $name }}') open = false"
>
    <!-- Hidden input for standard Laravel form submit -->
    <input type="hidden" name="{{ $name }}" :value="selectedId" @if($required) required @endif>

    <!-- Trigger Box -->
    <div
        @click="!{{ $disabled ? 'true' : 'false' }} && toggle()"
        role="button"
        tabindex="0"
        @keydown.enter.prevent="!{{ $disabled ? 'true' : 'false' }} && toggle()"
        @keydown.space.prevent="!{{ $disabled ? 'true' : 'false' }} && toggle()"
        class="w-full flex items-center justify-between text-left rounded-lg border border-border bg-card px-3.5 py-2 text-sm shadow-xs hover:border-border/80 focus:outline-none focus:ring-2 focus:ring-primary/20 transition select-none cursor-pointer @if($disabled) bg-muted text-muted-foreground cursor-not-allowed @endif"
    >
        <div class="flex items-center gap-2 truncate flex-1 min-w-0 pointer-events-none">
            <template x-if="selectedItem">
                <div class="flex items-center gap-2 truncate">
                    <span class="font-mono text-foreground font-semibold bg-muted px-1.5 py-0.5 rounded-md border border-border text-xs" x-text="selectedItem.sku"></span>
                    <span class="font-medium text-foreground truncate" x-text="selectedItem.nama"></span>
                    <span class="text-[10px] font-mono text-muted-foreground bg-muted/60 px-1.5 py-0.5 rounded-md border border-border" x-text="selectedItem.satuan"></span>
                </div>
            </template>
            <template x-if="!selectedItem">
                <span class="text-muted-foreground font-normal text-xs" x-text="placeholder"></span>
            </template>
        </div>

        <div class="flex items-center gap-1.5 ml-2 shrink-0">
            <button
                type="button"
                x-show="selectedItem && !{{ $disabled ? 'true' : 'false' }}"
                @click.stop="clear()"
                class="p-1 text-muted-foreground hover:text-destructive rounded-md transition cursor-pointer"
                title="Hapus pilihan"
            >
                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <svg class="size-4 text-muted-foreground transition-transform duration-150 pointer-events-none" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </div>

    <!-- Dropdown Panel -->
    <div
        x-show="open"
        x-cloak
        style="display: none; position: absolute; left: 0; top: 100%; z-index: 9999; width: 100%; min-width: 100%; box-sizing: border-box;"
        class="absolute left-0 top-full z-[100] mt-1.5 w-full bg-card rounded-xl border border-border shadow-xl text-xs overflow-hidden"
    >
        <!-- Search Input Bar -->
        <div class="p-2.5 border-b border-border bg-muted/30 flex items-center gap-2">
            <svg class="size-4 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
                type="text"
                x-model="search"
                @input="onSearch($event)"
                @keydown.enter.prevent="fetchItems(1, false)"
                x-ref="searchInput"
                placeholder="Ketik SKU atau nama bahan/produk..."
                class="w-full bg-card border border-border rounded-lg px-2.5 py-1.5 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/20"
            />
            <button
                type="button"
                x-show="search.length > 0"
                @click="search = ''; fetchItems(1, false); $refs.searchInput.focus()"
                class="text-muted-foreground hover:text-foreground p-1 cursor-pointer"
                title="Hapus pencarian"
            >
                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Options List (Paginated 10 items) -->
        <div class="max-h-56 overflow-y-auto divide-y divide-border">
            <!-- Loading Indicator -->
            <div x-show="loading && items.length === 0" class="py-4 text-center text-muted-foreground">
                <span class="text-xs">Memuat data produk...</span>
            </div>

            <!-- Items -->
            <template x-for="item in items" :key="item.id">
                <div
                    @click="select(item)"
                    :class="selectedId == item.id ? 'bg-muted text-foreground font-semibold' : 'text-foreground hover:bg-muted/50'"
                    class="px-3.5 py-2.5 cursor-pointer transition flex items-center justify-between gap-2 select-none"
                >
                    <div class="flex items-center gap-2 truncate pointer-events-none">
                        <span class="font-mono text-foreground font-medium bg-muted px-1.5 py-0.5 rounded-md border border-border text-[10px]" x-text="item.sku"></span>
                        <span class="truncate font-medium text-foreground" x-text="item.nama"></span>
                    </div>
                    <div class="flex items-center gap-1 shrink-0 pointer-events-none">
                        <span class="text-[10px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="item.satuan"></span>
                    </div>
                </div>
            </template>

            <!-- Empty State -->
            <div x-show="!loading && items.length === 0" class="py-4 text-center text-muted-foreground">
                <p class="text-xs">Tidak ada komoditas yang cocok.</p>
                <template x-if="search">
                    <p class="text-[10px] text-muted-foreground mt-1">Kata kunci: "<span class="font-mono text-foreground" x-text="search"></span>"</p>
                </template>
            </div>
        </div>

        <!-- Pagination Footer (10 items per page) -->
        <div class="p-2.5 border-t border-border bg-muted/30 flex items-center justify-between text-xs text-muted-foreground">
            <span x-text="`Menampilkan ${items.length} dari ${total} data`"></span>
            <button
                type="button"
                x-show="hasMore"
                @click="loadMore()"
                :disabled="loading"
                class="px-2.5 py-1 rounded-lg bg-card border border-border hover:bg-muted text-foreground font-medium transition flex items-center gap-1 disabled:opacity-50 cursor-pointer shadow-xs"
            >
                <span x-text="loading ? 'Memuat...' : '+10 Berikutnya'"></span>
            </button>
        </div>
    </div>
</div>

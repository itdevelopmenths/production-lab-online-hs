@props([
    'name' => 'produk_id',
    'selectedId' => null,
    'selectedItem' => null,
    'placeholder' => '— Cari & Pilih Produk / Bahan —',
    'filterTipe' => null, // 'bahan', 'kemas', 'produk_jadi', or array ['bahan', 'kemas']
    'required' => false,
    'disabled' => false,
    'size' => 'sm',
    'variant' => 'boxed', // boxed | seamless
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

    $triggerVariantClass = $variant === 'seamless'
        ? 'w-full flex items-center justify-between text-left bg-transparent border-0 p-0 shadow-none text-[14px] font-medium focus:outline-none select-none cursor-pointer'
        : 'w-full flex items-center justify-between text-left rounded-[8px] border border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 px-3 py-2 text-[13px] shadow-theme-xs hover:border-[#DEDEDE] dark:hover:border-border-2 focus:outline-none focus:border-[#111111] dark:focus:border-white transition select-none cursor-pointer';
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
    @click.window="handleOutsideClick($event)"
    @keydown.escape.window="close()"
    @hs-dropdown-opened.window="if ($event.detail && $event.detail !== '{{ $name }}') close()"
>
    <!-- Hidden input for standard Laravel form submit -->
    <input type="hidden" name="{{ $name }}" :value="selectedId" @if($required) required @endif>

    <!-- Trigger Box -->
    <div
        x-ref="trigger"
        @click="!{{ $disabled ? 'true' : 'false' }} && toggle()"
        role="button"
        tabindex="0"
        @keydown.enter.prevent="!{{ $disabled ? 'true' : 'false' }} && toggle()"
        @keydown.space.prevent="!{{ $disabled ? 'true' : 'false' }} && toggle()"
        class="{{ $triggerVariantClass }} @if($disabled) opacity-60 bg-gray-50 dark:bg-gray-800/40 text-text-muted cursor-not-allowed @endif"
    >
        <div class="flex items-center gap-2 truncate flex-1 min-w-0 pointer-events-none">
            <template x-if="selectedItem">
                <div class="flex items-center gap-2 truncate">
                    <span class="font-mono text-[#111111] dark:text-white font-semibold bg-[#F5F5F5] dark:bg-gray-800 px-1.5 py-0.5 rounded-[5px] border border-[#E8E8E8] dark:border-border text-[11px]" x-text="selectedItem.sku"></span>
                    <span class="font-medium text-text-active truncate" x-text="selectedItem.nama"></span>
                    <span class="text-[10px] font-mono text-text-muted bg-[#FBFBFB] dark:bg-gray-800/80 px-1.5 py-0.5 rounded-[5px] border border-[#E8E8E8] dark:border-border" x-text="selectedItem.satuan"></span>
                </div>
            </template>
            <template x-if="!selectedItem">
                <span class="text-text-placeholder font-normal text-[13px]" x-text="placeholder"></span>
            </template>
        </div>

        <div class="flex items-center gap-1.5 ml-2 shrink-0">
            <button
                type="button"
                x-show="selectedItem && !{{ $disabled ? 'true' : 'false' }}"
                @click.stop="clear()"
                class="p-1 text-text-muted hover:text-red-600 dark:hover:text-red-400 rounded-md transition cursor-pointer"
                title="Hapus pilihan"
            >
                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <svg class="size-4 text-text-muted transition-transform duration-150 pointer-events-none" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </div>

    <!-- Teleported Dropdown Panel (Never Clipped by Overflow-Hidden Modals or Table Wrappers) -->
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-ref="panel"
            style="display: none; position: fixed; z-index: 9999;"
            :style="panelStyle"
            class="rounded-xl border border-gray-200 bg-white shadow-2xl overflow-hidden text-xs dark:border-gray-700 dark:bg-gray-900"
        >
            <!-- Search Input Bar -->
            <div class="p-2.5 border-b border-[#E8E8E8] dark:border-gray-800 bg-[#FBFBFB] dark:bg-gray-950 flex items-center gap-2">
                <svg class="size-4 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    type="text"
                    x-model="search"
                    @input="onSearch($event)"
                    @keydown.enter.prevent="fetchItems(1, false)"
                    x-ref="searchInput"
                    placeholder="Ketik SKU atau nama bahan/produk..."
                    class="w-full bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-gray-700 rounded-[8px] px-2.5 py-1.5 text-xs text-text-active placeholder:text-text-placeholder focus:outline-none focus:border-[#111111] dark:focus:border-white"
                />
                <button
                    type="button"
                    x-show="search.length > 0"
                    @click="search = ''; fetchItems(1, false); $refs.searchInput.focus()"
                    class="text-text-muted hover:text-text-active p-1 cursor-pointer"
                    title="Hapus pencarian"
                >
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Options List (Paginated 10 items) -->
            <div class="max-h-56 overflow-y-auto custom-scrollbar divide-y divide-[#E8E8E8]/70 dark:divide-gray-800">
                <!-- Loading Indicator -->
                <div x-show="loading && items.length === 0" class="py-4 text-center text-text-muted">
                    <span class="text-xs">Memuat data produk...</span>
                </div>

                <!-- Items -->
                <template x-for="item in items" :key="item.id">
                    <div
                        @click="select(item)"
                        :class="selectedId == item.id ? 'bg-brand-50 dark:bg-brand-500/10 text-[#111111] dark:text-white font-bold' : 'text-text-active hover:bg-gray-50 dark:hover:bg-gray-800/60'"
                        class="px-3.5 py-2.5 cursor-pointer transition flex items-center justify-between gap-2 select-none"
                    >
                        <div class="flex items-center gap-2 truncate pointer-events-none">
                            <span class="font-mono text-text-active font-medium bg-[#F5F5F5] dark:bg-gray-800 px-1.5 py-0.5 rounded-[5px] border border-[#E8E8E8] dark:border-gray-700 text-[10px]" x-text="item.sku"></span>
                            <span class="truncate font-medium" x-text="item.nama"></span>
                        </div>
                        <div class="flex items-center gap-1 shrink-0 pointer-events-none">
                            <span class="text-[10px] font-mono text-text-muted bg-[#F5F5F5] dark:bg-gray-800 px-1.5 py-0.5 rounded-[5px] border border-[#E8E8E8] dark:border-gray-700" x-text="item.satuan"></span>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div x-show="!loading && items.length === 0" class="py-4 text-center text-text-muted">
                    <p class="text-xs">Tidak ada komoditas yang cocok.</p>
                    <template x-if="search">
                        <p class="text-[10px] text-text-muted mt-1">Kata kunci: "<span class="font-mono text-text-active" x-text="search"></span>"</p>
                    </template>
                </div>
            </div>

            <!-- Pagination Footer (10 items per page) -->
            <div class="p-2.5 border-t border-[#E8E8E8] dark:border-gray-800 bg-[#FBFBFB] dark:bg-gray-950 flex items-center justify-between text-xs text-text-muted">
                <span x-text="`Menampilkan ${items.length} dari ${total} data`"></span>
                <button
                    type="button"
                    x-show="hasMore"
                    @click="loadMore()"
                    :disabled="loading"
                    class="px-2.5 py-1 rounded-[6px] bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 text-text-active font-medium transition flex items-center gap-1 disabled:opacity-50 cursor-pointer shadow-theme-xs"
                >
                    <span x-text="loading ? 'Memuat...' : '+10 Berikutnya'"></span>
                </button>
            </div>
        </div>
    </template>
</div>

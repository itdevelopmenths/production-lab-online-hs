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

    <!-- Trigger Box (Valid HTML element, avoiding nested button issues) -->
    <div
        @click="!{{ $disabled ? 'true' : 'false' }} && toggle()"
        role="button"
        tabindex="0"
        @keydown.enter.prevent="!{{ $disabled ? 'true' : 'false' }} && toggle()"
        @keydown.space.prevent="!{{ $disabled ? 'true' : 'false' }} && toggle()"
        class="w-full flex items-center justify-between text-left rounded-sm border border-gray-300 bg-white px-3 py-1.5 text-xs shadow-xs hover:border-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition select-none cursor-pointer @if($disabled) bg-gray-100 text-gray-400 cursor-not-allowed @endif"
    >
        <div class="flex items-center gap-1.5 truncate flex-1 min-w-0 pointer-events-none">
            <template x-if="selectedItem">
                <div class="flex items-center gap-1.5 truncate">
                    <span class="font-mono text-primary-700 font-semibold bg-primary-50 px-1 py-0.5 rounded-xs border border-primary-100 text-[11px]" x-text="selectedItem.sku"></span>
                    <span class="font-medium text-gray-900 truncate" x-text="selectedItem.nama"></span>
                    <span class="text-[10px] font-mono text-gray-500 bg-gray-100 px-1 py-0.5 rounded-xs border border-gray-200" x-text="selectedItem.satuan"></span>
                </div>
            </template>
            <template x-if="!selectedItem">
                <span class="text-gray-400 font-normal" x-text="placeholder"></span>
            </template>
        </div>

        <div class="flex items-center gap-1 ml-2 shrink-0">
            <button
                type="button"
                x-show="selectedItem && !{{ $disabled ? 'true' : 'false' }}"
                @click.stop="clear()"
                class="p-0.5 text-gray-400 hover:text-rose-600 rounded-xs transition cursor-pointer"
                title="Hapus pilihan"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <svg class="w-4 h-4 text-gray-400 transition-transform duration-150 pointer-events-none" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </div>

    <!-- Dropdown Panel (Overlay that covers content underneath, hides on select or click outside) -->
    <div
        x-show="open"
        x-cloak
        style="display: none; position: absolute; left: 0; top: 100%; z-index: 9999; width: 100%; min-width: 100%; box-sizing: border-box;"
        class="absolute left-0 top-full z-[100] mt-1 w-full bg-white rounded-sm border border-gray-200 shadow-2xl text-xs overflow-hidden"
    >
        <!-- Search Input Bar -->
        <div class="p-2 border-b border-gray-100 bg-gray-50 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
                type="text"
                x-model="search"
                @input="onSearch()"
                x-ref="searchInput"
                placeholder="Ketik SKU atau nama bahan/produk..."
                class="w-full bg-white border border-gray-200 rounded-xs px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
            />
        </div>

        <!-- Options List (Paginated 10 items) -->
        <div class="max-h-56 overflow-y-auto divide-y divide-gray-50">
            <!-- Loading Indicator -->
            <div x-show="loading && items.length === 0" class="py-4 text-center text-gray-500">
                <span class="text-[11px]">Memuat 10 item...</span>
            </div>

            <!-- Items -->
            <template x-for="item in items" :key="item.id">
                <div
                    @click="select(item)"
                    :class="selectedId == item.id ? 'bg-primary-50 text-primary-900 font-semibold' : 'text-gray-700 hover:bg-gray-50'"
                    class="px-3 py-2 cursor-pointer transition flex items-center justify-between gap-2 select-none"
                >
                    <div class="flex items-center gap-1.5 truncate pointer-events-none">
                        <span class="font-mono text-primary-700 font-medium bg-primary-50 px-1 py-0.5 rounded-xs border border-primary-100 text-[10px]" x-text="item.sku"></span>
                        <span class="truncate font-medium text-gray-900" x-text="item.nama"></span>
                    </div>
                    <div class="flex items-center gap-1 shrink-0 pointer-events-none">
                        <span class="text-[10px] font-mono text-gray-500 bg-gray-100 px-1 py-0.5 rounded-xs border border-gray-200" x-text="item.satuan"></span>
                    </div>
                </div>
            </template>

            <!-- Empty State -->
            <div x-show="!loading && items.length === 0" class="py-4 text-center text-gray-400">
                <p class="text-xs">Tidak ada komoditas yang cocok.</p>
            </div>
        </div>

        <!-- Pagination Footer (10 items per page) -->
        <div class="p-2 border-t border-gray-100 bg-gray-50 flex items-center justify-between text-[11px] text-gray-500">
            <span x-text="`Menampilkan ${items.length} dari ${total} data`"></span>
            <button
                type="button"
                x-show="hasMore"
                @click="loadMore()"
                :disabled="loading"
                class="px-2 py-0.5 rounded-xs bg-white border border-gray-200 hover:bg-gray-100 text-primary-700 font-medium transition flex items-center gap-1 disabled:opacity-50 cursor-pointer"
            >
                <span x-text="loading ? 'Memuat...' : '+10 Berikutnya'"></span>
            </button>
        </div>
    </div>
</div>

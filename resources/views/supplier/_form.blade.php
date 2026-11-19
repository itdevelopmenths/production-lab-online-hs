@php
    $s = $supplier ?? null;
    $availableProduks = $produks ?? \App\Models\Produk::suppliable()->orderBy('nama')->get(['id', 'nama', 'sku', 'tipe']);
    $availableProdukIds = $availableProduks->pluck('id')->map(fn ($id) => (int) $id)->all();
    $oldInput = session()->getOldInput();
    $hasOldProdukIds = is_array($oldInput) && array_key_exists('produk_ids', $oldInput);

    if ($hasOldProdukIds) {
        $rawOld = $oldInput['produk_ids'];
        $rawArray = is_array($rawOld)
            ? $rawOld
            : (is_string($rawOld) ? explode(',', $rawOld) : []);
        $assignedIds = array_values(array_intersect(
            array_unique(array_filter(array_map('intval', $rawArray), fn ($id) => $id > 0)),
            $availableProdukIds
        ));
    } elseif ($s) {
        $rawAssigned = $s->relationLoaded('activeProduks')
            ? $s->activeProduks->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $s->activeProduks()->pluck('produk.id')->map(fn ($id) => (int) $id)->all();
        $assignedIds = array_values(array_intersect(array_unique($rawAssigned), $availableProdukIds));
    } else {
        $assignedIds = [];
    }
@endphp

<script>
function supplierProdukChecklist(allProducts, initialSelectedIds) {
    return {
        search: '',
        filterMode: 'all',
        allProducts: allProducts || [],
        selectedIds: Array.from(new Set((initialSelectedIds || []).map(Number).filter(function(n) {
            return Number.isInteger(n) && n > 0;
        }))),

        get parsedClauses() {
            var raw = (this.search || '').toLowerCase().trim();
            if (!raw) {
                return [];
            }
            return raw.split(/[,;|]+/)
                .map(function(clause) {
                    return clause.trim().split(/\s+/).filter(Boolean);
                })
                .filter(function(tokens) {
                    return tokens.length > 0;
                });
        },

        matchesProduct(p, clauses) {
            if (!clauses.length) {
                return true;
            }
            var haystack = ((p.sku || '') + ' ' + (p.nama || '')).toLowerCase();
            return clauses.some(function(tokens) {
                return tokens.every(function(token) {
                    return haystack.indexOf(token) !== -1;
                });
            });
        },

        get filteredProducts() {
            var self = this;
            var clauses = this.parsedClauses;
            return this.allProducts.filter(function(p) {
                if (self.filterMode === 'selected' && !self.isSelected(p.id)) {
                    return false;
                }
                return self.matchesProduct(p, clauses);
            });
        },

        get filteredSelectedCount() {
            var self = this;
            return this.filteredProducts.filter(function(p) {
                return self.isSelected(p.id);
            }).length;
        },

        get allFilteredSelected() {
            var list = this.filteredProducts;
            if (!list.length) {
                return false;
            }
            var self = this;
            return list.every(function(p) {
                return self.isSelected(p.id);
            });
        },

        isSelected(id) {
            return this.selectedIds.indexOf(Number(id)) !== -1;
        },

        setSelected(id, checked) {
            var nId = Number(id);
            var idx = this.selectedIds.indexOf(nId);
            if (checked && idx === -1) {
                this.selectedIds.push(nId);
            } else if (!checked && idx !== -1) {
                this.selectedIds.splice(idx, 1);
            }
        },

        toggle(id) {
            this.setSelected(id, !this.isSelected(id));
        },

        selectAllFiltered() {
            var self = this;
            this.filteredProducts.forEach(function(p) {
                var nId = Number(p.id);
                if (self.selectedIds.indexOf(nId) === -1) {
                    self.selectedIds.push(nId);
                }
            });
        },

        deselectAllFiltered() {
            var visibleIds = {};
            this.filteredProducts.forEach(function(p) {
                visibleIds[Number(p.id)] = true;
            });
            this.selectedIds = this.selectedIds.filter(function(id) {
                return !visibleIds[Number(id)];
            });
        },

        clearSearch() {
            this.search = '';
            if (this.$refs && this.$refs.searchInput) {
                this.$refs.searchInput.focus();
            }
        }
    };
}
</script>

<div class="space-y-3">
    <x-form-section card title="Identitas & Kontak Vendor" subtitle="Nama badan usaha, kategori asal, skema termin, serta informasi kontak">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3">
            <x-form-field name="nama" label="Nama Supplier" :required="true" help="Nama resmi badan usaha atau penyedia">
                <x-input name="nama" variant="seamless" value="{{ old('nama', $s?->nama) }}" placeholder="misal: PT Sumber Kimia Abadi" :required="true" />
            </x-form-field>

            <x-form-field name="kategori" label="Kategori Supplier" :required="true" help="Klasifikasi asal vendor">
                <x-select name="kategori" variant="seamless" :required="true">
                    <option value="lokal" @selected(old('kategori', $s?->kategori) === 'lokal')>Lokal</option>
                    <option value="impor" @selected(old('kategori', $s?->kategori) === 'impor')>Impor</option>
                </x-select>
            </x-form-field>

            <x-form-field name="termin_default" label="Termin Pembayaran Default" help="Skema standar jatuh tempo pemesanan">
                <x-select name="termin_default" variant="seamless" placeholder="— Pilih Termin (Opsional) —">
                    @foreach(['tempo'=>'Tempo','termin'=>'Termin','pelunasan'=>'Pelunasan'] as $v=>$l)
                        <option value="{{ $v }}" @selected(old('termin_default', $s?->termin_default) === $v)>{{ $l }}</option>
                    @endforeach
                </x-select>
            </x-form-field>

            <x-form-field name="kontak" label="Kontak / PIC" help="No. telp, surel, atau nama representatif vendor">
                <x-input name="kontak" variant="seamless" value="{{ old('kontak', $s?->kontak) }}" placeholder="misal: 0812-3456-7890 (Bpk. Anton)" />
            </x-form-field>

            <div class="md:col-span-2">
                <x-form-field name="alamat" label="Alamat Kantor / Gudang" help="Alamat operasional atau pengiriman barang">
                    <x-textarea name="alamat" rows="2" variant="seamless" placeholder="Alamat lengkap supplier..." :value="old('alamat', $s?->alamat)" />
                </x-form-field>
            </div>
        </div>
    </x-form-section>

    {{-- Panel Produk yang Dipasok --}}
    <div x-data="supplierProdukChecklist({{ Js::from($availableProduks) }}, {{ Js::from($assignedIds) }})" class="border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 rounded-[12px] p-4 sm:p-5 space-y-3.5 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#E8E8E8] dark:border-gray-800 pb-3">
            <div>
                <h3 class="text-sm font-medium text-[#101828] dark:text-white flex items-center gap-2">
                    <svg class="size-4 text-[#111111] dark:text-white" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Produk yang Dipasok (<span class="tabular-nums text-[#111111] dark:text-white" x-text="selectedIds.length"></span> dipilih)
                </h3>
                <p class="text-[11px] text-[#667085] dark:text-gray-400 mt-0.5">Pilih bahan baku atau kemasan yang dapat dipesan ke supplier ini. Pilihan tetap terjaga meski kata kunci diubah.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button"
                        @click="selectAllFiltered()"
                        :disabled="filteredProducts.length === 0 || allFilteredSelected"
                        class="text-[11px] font-medium text-[#111111] dark:text-white hover:underline cursor-pointer rounded focus-visible:outline-none disabled:opacity-40 disabled:no-underline disabled:cursor-not-allowed">
                    <span x-text="search.trim() ? ('Pilih Semua Hasil (' + filteredProducts.length + ')') : 'Pilih Semua'"></span>
                </button>
                <span class="text-[#667085]/60 text-xs" aria-hidden="true">&bull;</span>
                <button type="button"
                        @click="deselectAllFiltered()"
                        :disabled="filteredSelectedCount === 0"
                        class="text-[11px] font-medium text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white cursor-pointer rounded focus-visible:outline-none disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-text="(search.trim() || filterMode === 'selected') ? 'Batalkan Hasil Ini' : 'Batalkan Semua'"></span>
                </button>
            </div>
        </div>

        {{-- Search & View Filter Toolbar --}}
        <div class="space-y-2">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <search class="block relative flex-1">
                    <label for="supplier-produk-search" class="sr-only">Cari nama atau SKU produk bahan atau kemasan</label>
                    <svg class="pointer-events-none absolute left-3 top-2.5 size-4 text-[#667085] dark:text-gray-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input id="supplier-produk-search"
                           x-ref="searchInput"
                           type="search"
                           x-model="search"
                           @keydown.enter.prevent
                           @keydown.escape.prevent="if (search) clearSearch()"
                           autocomplete="off"
                           autocorrect="off"
                           autocapitalize="none"
                           spellcheck="false"
                           placeholder="Cari nama atau SKU (pisahkan koma untuk multi-keyword, mis: alkohol, botol)..."
                           class="w-full text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 pl-9 pr-8 py-2 text-[#101828] dark:text-white focus:border-[#111111] dark:focus:border-white outline-none transition [&::-webkit-search-cancel-button]:hidden">
                    <button type="button"
                            x-show="search.length > 0"
                            x-cloak
                            @click="clearSearch()"
                            aria-label="Hapus kata kunci pencarian"
                            class="absolute right-2 top-1.5 p-1 rounded-md text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                        <svg class="size-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </search>

                <div class="inline-flex items-center rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 bg-[#f3f4f6] dark:bg-gray-950 p-1 shrink-0 self-start sm:self-auto" role="group" aria-label="Filter tampilan daftar produk">
                    <button type="button"
                            @click="filterMode = 'all'"
                            :aria-pressed="filterMode === 'all'"
                            :class="filterMode === 'all' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white shadow-[0_1px_2px_rgba(0,0,0,0.06)] font-medium' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white'"
                            class="px-2.5 py-1 text-[11px] font-medium rounded-[7px] transition cursor-pointer">
                        Semua (<span class="tabular-nums" x-text="allProducts.length"></span>)
                    </button>
                    <button type="button"
                            @click="filterMode = 'selected'"
                            :aria-pressed="filterMode === 'selected'"
                            :class="filterMode === 'selected' ? 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white shadow-[0_1px_2px_rgba(0,0,0,0.06)] font-medium' : 'text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white'"
                            class="px-2.5 py-1 text-[11px] font-medium rounded-[7px] transition cursor-pointer">
                        Terpilih (<span class="tabular-nums" x-text="selectedIds.length"></span>)
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-[#667085] dark:text-gray-400 px-0.5">
                <span>
                    Menampilkan <span class="tabular-nums font-medium text-[#101828] dark:text-white" x-text="filteredProducts.length"></span> produk<span x-show="search.trim()"> untuk "<span class="font-medium text-[#101828] dark:text-white" x-text="search.trim()"></span>"</span>
                </span>
                <span x-show="selectedIds.length > filteredSelectedCount" x-cloak class="font-medium text-foreground dark:text-foreground">
                    +<span class="tabular-nums" x-text="selectedIds.length - filteredSelectedCount"></span> produk terpilih di luar filter tetap tersimpan
                </span>
                <span x-show="selectedIds.length === filteredSelectedCount" class="text-[#667085]/80 dark:text-gray-500">
                    Tips: pisahkan koma untuk cari 2+ keyword sekaligus
                </span>
            </div>
        </div>

        {{-- Canonical submission source: independent of search filter DOM culling --}}
        <input type="hidden" name="produk_ids" value="" :disabled="selectedIds.length > 0" @if(!empty($assignedIds)) disabled @endif>
        <template x-for="id in selectedIds" :key="'selected-produk-' + id">
            <input type="hidden" name="produk_ids[]" :value="id">
        </template>

        {{-- Scrollable checklist (visual & toggle only) --}}
        <div class="max-h-64 overflow-y-auto rounded-lg border border-[#E8E8E8] dark:border-gray-800 divide-y divide-[#E8E8E8]/60 dark:divide-gray-800 bg-[#F5F5F5]/60 dark:bg-gray-950/60 p-1 custom-scrollbar">
            <template x-for="prod in filteredProducts" :key="prod.id">
                <label :for="'supplier-prod-' + prod.id"
                       class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-md cursor-pointer transition-colors"
                       :class="isSelected(prod.id) ? 'bg-white dark:bg-gray-800/80 shadow-2xs' : 'hover:bg-white/70 dark:hover:bg-gray-900/60'">
                    <div class="flex items-center gap-3 min-w-0">
                        <input type="checkbox"
                               :id="'supplier-prod-' + prod.id"
                               :value="prod.id"
                               :checked="isSelected(prod.id)"
                               @change="setSelected(prod.id, $event.target.checked)"
                               class="rounded border-[#E8E8E8] dark:border-gray-700 accent-[#111111] dark:accent-white text-[#111111] dark:text-white focus:outline-none size-4 shrink-0 cursor-pointer">
                        <span class="tabular-nums text-xs font-medium text-[#101828] dark:text-white shrink-0" x-text="prod.sku"></span>
                        <span class="text-xs text-[#101828] dark:text-gray-200 font-medium truncate" x-text="prod.nama"></span>
                    </div>
                    <div class="shrink-0">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium uppercase border"
                              :class="['kemas', 'kemasan'].includes(prod.tipe) ? 'bg-card dark:bg-card text-foreground dark:text-foreground border-border dark:border-border' : 'bg-card dark:bg-card text-foreground dark:text-foreground border-border dark:border-border'"
                              x-text="['kemas', 'kemasan'].includes(prod.tipe) ? 'Kemasan' : 'Bahan Baku'">
                        </span>
                    </div>
                </label>
            </template>
            <div x-show="filteredProducts.length === 0" class="py-6 text-center text-xs text-[#667085] dark:text-gray-400 italic">
                <span x-text="filterMode === 'selected' && selectedIds.length === 0 ? 'Belum ada produk yang dipilih.' : 'Tidak ada produk bahan/kemasan yang cocok dengan kata kunci pencarian.'"></span>
            </div>
        </div>
    </div>

    <x-form-section card title="Status Aktivasi">
        <x-checkbox name="is_active" label="Status Aktif" :checked="old('is_active', $s?->is_active ?? true)" help="Supplier aktif dapat dipilih pada penerimaan dan purchase order" />
    </x-form-section>
</div>

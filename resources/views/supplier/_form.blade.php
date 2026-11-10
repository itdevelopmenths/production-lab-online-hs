@php
    $s = $supplier ?? null;
    $availableProduks = $produks ?? \App\Models\Produk::whereIn('tipe', ['bahan', 'kemas', 'bahan_baku', 'kemasan'])->active()->orderBy('nama')->get(['id', 'nama', 'sku', 'tipe']);
    $oldInput = session()->getOldInput();
    $hasOldProdukIds = is_array($oldInput) && array_key_exists('produk_ids', $oldInput);

    if ($hasOldProdukIds) {
        $rawOld = $oldInput['produk_ids'];
        $rawArray = is_array($rawOld)
            ? $rawOld
            : (is_string($rawOld) ? explode(',', $rawOld) : []);
        $assignedIds = array_values(array_unique(array_filter(
            array_map('intval', $rawArray),
            fn ($id) => $id > 0
        )));
    } elseif ($s) {
        $assignedIds = $s->relationLoaded('produks')
            ? $s->produks->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : $s->produks()->pluck('produk.id')->map(fn ($id) => (int) $id)->values()->all();
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

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <x-form-group name="nama" label="Nama Supplier" :required="true" help="Nama resmi badan usaha atau penyedia">
        <x-input name="nama" value="{{ old('nama', $s?->nama) }}" placeholder="misal: PT Sumber Kimia Abadi" :required="true" />
    </x-form-group>

    <x-form-group name="kategori" label="Kategori Supplier" :required="true" help="Klasifikasi asal vendor">
        <x-select name="kategori" :required="true">
            <option value="lokal" @selected(old('kategori', $s?->kategori) === 'lokal')>Lokal</option>
            <option value="impor" @selected(old('kategori', $s?->kategori) === 'impor')>Impor</option>
        </x-select>
    </x-form-group>

    <x-form-group name="termin_default" label="Termin Pembayaran Default" help="Skema standar jatuh tempo pemesanan">
        <x-select name="termin_default" placeholder="— Pilih Termin (Opsional) —">
            @foreach(['tempo'=>'Tempo','termin'=>'Termin','pelunasan'=>'Pelunasan'] as $v=>$l)
                <option value="{{ $v }}" @selected(old('termin_default', $s?->termin_default) === $v)>{{ $l }}</option>
            @endforeach
        </x-select>
    </x-form-group>

    <x-form-group name="kontak" label="Kontak / PIC" help="No. telp, surel, atau nama representatif vendor">
        <x-input name="kontak" value="{{ old('kontak', $s?->kontak) }}" placeholder="misal: 0812-3456-7890 (Bpk. Anton)" />
    </x-form-group>

    <div class="md:col-span-2">
        <x-form-group name="alamat" label="Alamat Kantor / Gudang" help="Alamat operasional atau pengiriman barang">
            <x-textarea name="alamat" rows="2" placeholder="Alamat lengkap supplier...">{{ old('alamat', $s?->alamat) }}</x-textarea>
        </x-form-group>
    </div>

    {{-- Panel Produk yang Dipasok --}}
    <div x-data="supplierProdukChecklist({{ Js::from($availableProduks) }}, {{ Js::from($assignedIds) }})" class="md:col-span-2 border border-border/80 bg-card rounded-xl p-4 space-y-3.5 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border/60 pb-3">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-2">
                    <svg class="size-4 text-primary" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Produk yang Dipasok (<span class="font-mono text-primary" x-text="selectedIds.length"></span> dipilih)
                </h3>
                <p class="text-[11px] text-muted-foreground mt-0.5">Pilih bahan baku atau kemasan yang dapat dipesan ke supplier ini. Pilihan tetap terjaga meski kata kunci diubah.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button"
                        @click="selectAllFiltered()"
                        :disabled="filteredProducts.length === 0 || allFilteredSelected"
                        class="text-[11px] font-medium text-primary hover:underline cursor-pointer rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 disabled:opacity-40 disabled:no-underline disabled:cursor-not-allowed">
                    <span x-text="search.trim() ? ('Pilih Semua Hasil (' + filteredProducts.length + ')') : 'Pilih Semua'"></span>
                </button>
                <span class="text-muted-foreground/60 text-xs" aria-hidden="true">&bull;</span>
                <button type="button"
                        @click="deselectAllFiltered()"
                        :disabled="filteredSelectedCount === 0"
                        class="text-[11px] font-medium text-muted-foreground hover:text-foreground cursor-pointer rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-text="(search.trim() || filterMode === 'selected') ? 'Batalkan Hasil Ini' : 'Batalkan Semua'"></span>
                </button>
            </div>
        </div>

        {{-- Search & View Filter Toolbar --}}
        <div class="space-y-2">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <search class="block relative flex-1">
                    <label for="supplier-produk-search" class="sr-only">Cari nama atau SKU produk bahan atau kemasan</label>
                    <svg class="pointer-events-none absolute left-3 top-2.5 size-4 text-muted-foreground" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
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
                           class="w-full text-xs rounded-lg border border-border bg-background pl-9 pr-8 py-2 text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 shadow-xs transition [&::-webkit-search-cancel-button]:hidden">
                    <button type="button"
                            x-show="search.length > 0"
                            x-cloak
                            @click="clearSearch()"
                            aria-label="Hapus kata kunci pencarian"
                            class="absolute right-2 top-1.5 p-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 cursor-pointer">
                        <svg class="size-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </search>

                <div class="inline-flex items-center rounded-lg border border-border bg-muted/40 p-0.5 shrink-0 self-start sm:self-auto" role="group" aria-label="Filter tampilan daftar produk">
                    <button type="button"
                            @click="filterMode = 'all'"
                            :aria-pressed="filterMode === 'all'"
                            :class="filterMode === 'all' ? 'bg-card text-foreground shadow-2xs border-border' : 'text-muted-foreground hover:text-foreground border-transparent'"
                            class="px-2.5 py-1.5 text-[11px] font-medium rounded-md border transition cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30">
                        Semua (<span class="font-mono" x-text="allProducts.length"></span>)
                    </button>
                    <button type="button"
                            @click="filterMode = 'selected'"
                            :aria-pressed="filterMode === 'selected'"
                            :class="filterMode === 'selected' ? 'bg-card text-foreground shadow-2xs border-border' : 'text-muted-foreground hover:text-foreground border-transparent'"
                            class="px-2.5 py-1.5 text-[11px] font-medium rounded-md border transition cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30">
                        Terpilih (<span class="font-mono" x-text="selectedIds.length"></span>)
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted-foreground px-0.5">
                <span>
                    Menampilkan <span class="font-mono font-semibold text-foreground" x-text="filteredProducts.length"></span> produk<span x-show="search.trim()"> untuk "<span class="font-medium text-foreground" x-text="search.trim()"></span>"</span>
                </span>
                <span x-show="selectedIds.length > filteredSelectedCount" x-cloak class="font-medium text-emerald-700 dark:text-emerald-400">
                    +<span class="font-mono" x-text="selectedIds.length - filteredSelectedCount"></span> produk terpilih di luar filter tetap tersimpan
                </span>
                <span x-show="selectedIds.length === filteredSelectedCount" class="text-muted-foreground/80">
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
        <div class="max-h-64 overflow-y-auto rounded-lg border border-border/60 divide-y divide-border/40 bg-muted/10 p-1">
            <template x-for="prod in filteredProducts" :key="prod.id">
                <label :for="'supplier-prod-' + prod.id"
                       class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-md cursor-pointer transition-colors"
                       :class="isSelected(prod.id) ? 'bg-primary/5 hover:bg-primary/10' : 'hover:bg-muted/30'">
                    <div class="flex items-center gap-3 min-w-0">
                        <input type="checkbox"
                               :id="'supplier-prod-' + prod.id"
                               :value="prod.id"
                               :checked="isSelected(prod.id)"
                               @change="setSelected(prod.id, $event.target.checked)"
                               class="rounded border-border accent-primary text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 size-4 shrink-0 cursor-pointer">
                        <span class="font-mono text-xs font-semibold text-foreground shrink-0" x-text="prod.sku"></span>
                        <span class="text-xs text-foreground font-medium truncate" x-text="prod.nama"></span>
                    </div>
                    <div class="shrink-0">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold uppercase border"
                              :class="['kemas', 'kemasan'].includes(prod.tipe) ? 'bg-sky-50 text-sky-700 border-sky-200/60' : 'bg-amber-50 text-amber-700 border-amber-200/60'"
                              x-text="['kemas', 'kemasan'].includes(prod.tipe) ? 'Kemasan' : 'Bahan Baku'">
                        </span>
                    </div>
                </label>
            </template>
            <div x-show="filteredProducts.length === 0" class="py-6 text-center text-xs text-muted-foreground italic">
                <span x-text="filterMode === 'selected' && selectedIds.length === 0 ? 'Belum ada produk yang dipilih.' : 'Tidak ada produk bahan/kemasan yang cocok dengan kata kunci pencarian.'"></span>
            </div>
        </div>
    </div>

    <div class="md:col-span-2 pt-1">
        <x-checkbox name="is_active" label="Status Aktif" :checked="old('is_active', $s?->is_active ?? true)" help="Supplier aktif dapat dipilih pada penerimaan dan purchase order" />
    </div>
</div>

@php
    $s = $supplier ?? null;
    $availableProduks = $produks ?? \App\Models\Produk::whereIn('tipe', ['bahan', 'kemas', 'bahan_baku', 'kemasan'])->active()->orderBy('nama')->get(['id', 'nama', 'sku', 'tipe']);
    $assignedIds = old('produk_ids');
    if ($assignedIds !== null) {
        $assignedIds = array_map('intval', (array) $assignedIds);
    } elseif ($s) {
        $assignedIds = $s->relationLoaded('produks') ? $s->produks->pluck('id')->map(fn($id) => (int)$id)->all() : $s->produks()->pluck('produk.id')->map(fn($id) => (int)$id)->all();
    } else {
        $assignedIds = [];
    }
@endphp

<script>
function supplierProdukChecklist(allProducts, initialSelectedIds) {
    return {
        search: '',
        allProducts: allProducts || [],
        selectedIds: (initialSelectedIds || []).map(Number),

        get filteredProducts() {
            if (!this.search.trim()) {
                return this.allProducts;
            }
            var q = this.search.toLowerCase().trim();
            return this.allProducts.filter(function(p) {
                var nama = (p.nama || '').toLowerCase();
                var sku = (p.sku || '').toLowerCase();
                return nama.includes(q) || sku.includes(q);
            });
        },

        isSelected(id) {
            return this.selectedIds.indexOf(Number(id)) !== -1;
        },

        toggle(id) {
            var nId = Number(id);
            var idx = this.selectedIds.indexOf(nId);
            if (idx === -1) {
                this.selectedIds.push(nId);
            } else {
                this.selectedIds.splice(idx, 1);
            }
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
            var self = this;
            this.filteredProducts.forEach(function(p) {
                var nId = Number(p.id);
                var idx = self.selectedIds.indexOf(nId);
                if (idx !== -1) {
                    self.selectedIds.splice(idx, 1);
                }
            });
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
    <div x-data="supplierProdukChecklist({{ Js::from($availableProduks) }}, {{ Js::from($assignedIds) }})" class="md:col-span-2 border border-border/80 bg-card rounded-xl p-4 space-y-4 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border/60 pb-3">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-foreground flex items-center gap-2">
                    <svg class="size-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Produk yang Dipasok (<span class="font-mono text-primary" x-text="selectedIds.length"></span> dipilih)
                </h3>
                <p class="text-[11px] text-muted-foreground mt-0.5">Pilih bahan baku atau kemasan yang dapat dipesan ke supplier ini.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="selectAllFiltered()" class="text-[11px] font-medium text-primary hover:underline cursor-pointer">Pilih Semua</button>
                <span class="text-muted-foreground/60 text-xs">•</span>
                <button type="button" @click="deselectAllFiltered()" class="text-[11px] font-medium text-muted-foreground hover:text-foreground cursor-pointer">Batalkan Pilihan</button>
            </div>
        </div>

        {{-- Search input --}}
        <div class="relative">
            <svg class="absolute left-3 top-2.5 size-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" placeholder="Cari nama atau SKU bahan/kemasan..." class="w-full text-xs rounded-lg border border-border bg-background pl-9 pr-3 py-2 text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 shadow-xs transition">
        </div>

        {{-- Hidden default input so unchecking all submits empty array --}}
        <input type="hidden" name="produk_ids" value="">

        {{-- Scrollable checklist --}}
        <div class="max-h-64 overflow-y-auto rounded-lg border border-border/60 divide-y divide-border/40 bg-muted/10 p-1">
            <template x-for="prod in filteredProducts" :key="prod.id">
                <label class="flex items-center justify-between gap-3 px-3 py-2 hover:bg-muted/30 rounded-md cursor-pointer transition-colors">
                    <div class="flex items-center gap-3">
                        <input type="checkbox"
                               name="produk_ids[]"
                               :value="prod.id"
                               :checked="isSelected(prod.id)"
                               @change="toggle(prod.id)"
                               class="rounded border-border text-primary focus:ring-primary/20 size-4">
                        <span class="font-mono text-xs font-semibold text-foreground" x-text="prod.sku"></span>
                        <span class="text-xs text-foreground font-medium" x-text="prod.nama"></span>
                    </div>
                    <div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold uppercase border"
                              :class="prod.tipe === 'kemas' ? 'bg-sky-50 text-sky-700 border-sky-200/60' : 'bg-amber-50 text-amber-700 border-amber-200/60'"
                              x-text="prod.tipe === 'kemas' ? 'Kemasan' : 'Bahan Baku'">
                        </span>
                    </div>
                </label>
            </template>
            <div x-show="filteredProducts.length === 0" class="py-6 text-center text-xs text-muted-foreground italic">
                Tidak ada produk bahan/kemasan yang cocok dengan kata kunci pencarian.
            </div>
        </div>
    </div>

    <div class="md:col-span-2 pt-1">
        <x-checkbox name="is_active" label="Status Aktif" :checked="old('is_active', $s?->is_active ?? true)" help="Supplier aktif dapat dipilih pada penerimaan dan purchase order" />
    </div>
</div>

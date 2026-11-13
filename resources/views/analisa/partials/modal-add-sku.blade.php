{{-- Modal Tambah / Pindah SKU ke Analisa Stok (Lokal atau Impor + Klasifikasi ABC) --}}
<x-modal name="showAddSkuModal" maxWidth="2xl">
    <x-slot:header>
        <div class="px-6 py-4 border-b border-border bg-card flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-foreground tracking-tight">Tambah SKU ke Analisa Stok</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Pilih SKU Bahan Baku / Kemasan, tentukan masuk ke Analisa Impor atau Lokal, serta Klasifikasi ABC-nya.</p>
                </div>
            </div>
            <button type="button" @click="showAddSkuModal = false" class="text-muted-foreground hover:text-foreground transition cursor-pointer p-1.5 rounded-lg hover:bg-accent">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <form @submit.prevent="submitAddSku()" class="p-6 space-y-5 max-h-[78vh] overflow-y-auto text-xs">
        {{-- 1. Pilih SKU --}}
        <div class="space-y-2">
            <div class="flex items-center justify-between gap-2">
                <label class="font-semibold text-foreground">1. Pilih SKU Bahan Baku / Kemasan <span class="text-destructive">*</span></label>
                <input
                    type="text"
                    x-model.debounce.250ms="addSkuSearch"
                    @input="fetchAvailableAnalisaSkus()"
                    placeholder="Cari kode SKU atau nama..."
                    class="px-3 py-1.5 text-xs rounded-lg border border-input bg-background text-foreground w-56 focus:border-ring focus:ring-1 focus:ring-ring"
                >
            </div>

            <div class="max-h-44 overflow-y-auto border border-border rounded-xl bg-background divide-y divide-border/60">
                <template x-if="loadingAvailableAnalisaSkus">
                    <div class="py-6 text-center text-muted-foreground">Memuat daftar SKU...</div>
                </template>
                <template x-if="!loadingAvailableAnalisaSkus && availableAnalisaSkus.length === 0">
                    <div class="py-6 text-center text-muted-foreground">Tidak ada SKU bahan/kemas aktif yang ditemukan.</div>
                </template>
                <template x-for="item in availableAnalisaSkus" :key="item.id">
                    <div
                        @click="selectSkuToAdd(item)"
                        :class="String(addSkuForm.produk_id) === String(item.id) ? 'bg-primary/10 border-l-2 border-l-primary' : 'hover:bg-muted/40'"
                        class="flex items-center justify-between gap-3 px-3 py-2 cursor-pointer transition"
                    >
                        <div class="min-w-0">
                            <div class="font-semibold text-foreground truncate" x-text="item.nama"></div>
                            <div class="font-mono text-[10px] text-muted-foreground" x-text="item.sku + ' · ' + item.satuan + ' · ' + item.tipe"></div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border"
                                  :class="item.is_impor ? 'bg-purple-50 text-purple-700 border-purple-200' : (item.profil_analisa === 'lokal' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-zinc-100 text-zinc-600 border-zinc-200')"
                                  x-text="item.is_impor ? 'Profil: Impor' : (item.profil_analisa === 'lokal' ? 'Profil: Lokal' : 'Belum Diset')"></span>
                            <template x-if="item.klasifikasi_abc_kode">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-muted text-foreground border border-border" x-text="'ABC: ' + item.klasifikasi_abc_kode"></span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
            <template x-if="addSkuForm.produk_id">
                <div class="p-3 rounded-lg bg-muted/40 border border-border flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <span class="text-muted-foreground">SKU Terpilih:</span>
                        <strong class="text-foreground ml-1" x-text="addSkuForm.selectedNama"></strong>
                        <span class="font-mono text-muted-foreground ml-1" x-text="'(' + addSkuForm.selectedSku + ')'"></span>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] font-mono text-muted-foreground">
                        <span x-show="addSkuForm.current_stok_aktual !== null && addSkuForm.current_stok_aktual !== undefined">
                            Stok: <strong class="text-foreground" x-text="formatNumber(addSkuForm.current_stok_aktual) + ' ' + (addSkuForm.selectedSatuan || '')"></strong>
                        </span>
                        <span x-show="addSkuForm.current_hpp !== null && addSkuForm.current_hpp !== undefined">
                            HPP: <strong class="text-foreground" x-text="formatRupiah(addSkuForm.current_hpp)"></strong>
                        </span>
                    </div>
                </div>
            </template>
        </div>

        {{-- 2. Pilih Masuk ke Analisa Impor atau Lokal --}}
        <div class="space-y-2">
            <label class="font-semibold text-foreground block">2. Masuk ke Profil Analisa <span class="text-destructive">*</span></label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label
                    :class="addSkuForm.target_profil === 'impor' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border bg-card hover:bg-muted/30'"
                    class="flex items-start gap-2.5 p-3.5 rounded-xl border cursor-pointer transition"
                >
                    <input type="radio" name="add_sku_target_profil" value="impor" x-model="addSkuForm.target_profil" class="mt-0.5 text-primary focus:ring-primary">
                    <div>
                        <div class="font-semibold text-foreground">Bahan Baku Impor</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Menggunakan Klasifikasi ABC (+4/+2/+0 hari buffer), ADU ETA, dan Lead Time Impor.</p>
                    </div>
                </label>
                <label
                    :class="addSkuForm.target_profil === 'lokal' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border bg-card hover:bg-muted/30'"
                    class="flex items-start gap-2.5 p-3.5 rounded-xl border cursor-pointer transition"
                >
                    <input type="radio" name="add_sku_target_profil" value="lokal" x-model="addSkuForm.target_profil" class="mt-0.5 text-primary focus:ring-primary">
                    <div>
                        <div class="font-semibold text-foreground">Bahan Baku Lokal</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Menggunakan 9 tahap Lead Time Lokal, ADU 4 bulan, dan buffer manual tanpa Klasifikasi ABC.</p>
                    </div>
                </label>
            </div>

            <template x-if="addSkuForm.produk_id && addSkuForm.currentIsOpposite()">
                <div class="p-2.5 rounded-lg border border-amber-300 bg-amber-50 text-amber-800 text-[11px]">
                    Perhatian: SKU ini saat ini berada pada profil berbeda dan akan dipindahkan ke <strong x-text="addSkuForm.target_profil === 'impor' ? 'Analisa Impor' : 'Analisa Lokal'"></strong> (data working table profil lama akan dibersihkan agar tidak ganda).
                </div>
            </template>
        </div>

        {{-- 3a. Parameter Analisa Impor & Klasifikasi ABC --}}
        <div x-show="addSkuForm.target_profil === 'impor'" class="space-y-3 p-4 rounded-xl bg-muted/30 border border-border">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="font-semibold text-foreground block mb-1">Klasifikasi ABC <span class="text-destructive">*</span></label>
                    <select x-model="addSkuForm.klasifikasi_abc_id" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background text-foreground font-semibold focus:border-ring focus:ring-1 focus:ring-ring">
                        <option value="">— Pilih Klasifikasi ABC —</option>
                        <template x-for="abc in klasifikasiAbcList" :key="abc.id">
                            <option :value="abc.id" x-text="abc.nama + ' (+' + abc.tambahan_buffer_hari + ' Hari)'"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="font-semibold text-foreground block mb-1">Total OUT (Opsional)</label>
                    <input type="number" step="0.01" min="0" x-model.number="addSkuForm.out" placeholder="0" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background font-mono text-foreground">
                </div>
                <div>
                    <label class="font-semibold text-foreground block mb-1">Review Period (Hari)</label>
                    <input type="number" min="1" x-model.number="addSkuForm.review_period_impor" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background font-mono text-foreground">
                </div>
                <div>
                    <label class="font-semibold text-foreground block mb-1">Lead Time Avg (Hari, Opsional)</label>
                    <input type="number" step="0.1" min="0" x-model.number="addSkuForm.lead_time_average" placeholder="Otomatis / 45" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background font-mono text-foreground">
                </div>
                <div>
                    <label class="font-semibold text-foreground block mb-1">Lead Time Max (Hari, Opsional)</label>
                    <input type="number" step="0.1" min="0" x-model.number="addSkuForm.lead_time_max" placeholder="Otomatis / 60" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background font-mono text-foreground">
                </div>
                <div class="sm:col-span-2">
                    <label class="font-semibold text-foreground block mb-1">Harga per Satuan (Rp, Opsional)</label>
                    <input type="number" step="0.01" min="0" x-model.number="addSkuForm.harga_per_satuan" placeholder="Ikut harga HPP produk" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background font-mono text-foreground">
                    <div class="mt-1.5 flex items-center justify-between text-xs text-muted-foreground" x-show="addSkuForm.current_hpp !== null && addSkuForm.current_hpp !== undefined">
                        <span class="inline-flex items-center gap-1 font-mono text-[11px]">
                            <span class="text-muted-foreground/80">HPP Sistem:</span>
                            <span class="font-medium text-foreground" x-text="formatRupiah(addSkuForm.current_hpp)"></span>
                        </span>
                        <button type="button"
                                @click="addSkuForm.harga_per_satuan = addSkuForm.current_hpp"
                                class="text-primary hover:text-primary/80 hover:underline font-medium text-[11px] transition-colors focus:outline-none">
                            Gunakan Nilai Sistem
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3b. Parameter Analisa Lokal --}}
        <div x-show="addSkuForm.target_profil === 'lokal'" class="space-y-3 p-4 rounded-xl bg-muted/30 border border-border">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="font-semibold text-foreground block mb-1">Terjual 4 Bulan (Opsional)</label>
                    <input type="number" step="0.01" min="0" x-model.number="addSkuForm.terjual_rata_rata_4bulan" placeholder="0" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background font-mono text-foreground">
                </div>
                <div>
                    <label class="font-semibold text-foreground block mb-1">Review Period (Hari)</label>
                    <input type="number" min="1" x-model.number="addSkuForm.review_period_lokal" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background font-mono text-foreground">
                </div>
                <div>
                    <label class="font-semibold text-foreground block mb-1">Tambahan Buffer (Hari, Opsional)</label>
                    <input type="number" step="0.1" min="0" x-model.number="addSkuForm.tambahan_buffer_hari" placeholder="0" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background font-mono text-foreground">
                </div>
                <div>
                    <label class="font-semibold text-foreground block mb-1">Harga Satuan (Rp, Opsional)</label>
                    <input type="number" step="0.01" min="0" x-model.number="addSkuForm.harga_per_satuan" placeholder="Ikut harga HPP produk" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background font-mono text-foreground">
                    <div class="mt-1.5 flex items-center justify-between text-xs text-muted-foreground" x-show="addSkuForm.current_hpp !== null && addSkuForm.current_hpp !== undefined">
                        <span class="inline-flex items-center gap-1 font-mono text-[11px]">
                            <span class="text-muted-foreground/80">HPP Sistem:</span>
                            <span class="font-medium text-foreground" x-text="formatRupiah(addSkuForm.current_hpp)"></span>
                        </span>
                        <button type="button"
                                @click="addSkuForm.harga_per_satuan = addSkuForm.current_hpp"
                                class="text-primary hover:text-primary/80 hover:underline font-medium text-[11px] transition-colors focus:outline-none">
                            Gunakan Nilai Sistem
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-border flex justify-end gap-2.5">
            <x-button type="button" @click="showAddSkuModal = false" variant="outline" size="sm">
                Batal
            </x-button>
            <x-button type="submit" ::disabled="!addSkuForm.produk_id || savingAddSku || (addSkuForm.target_profil === 'impor' && !addSkuForm.klasifikasi_abc_id)" variant="primary" size="sm">
                <span x-text="savingAddSku ? 'Menyimpan...' : 'Simpan ke Analisa'"></span>
            </x-button>
        </div>
    </form>
</x-modal>


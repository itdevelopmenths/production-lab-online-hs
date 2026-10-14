{{-- Modal 2: Form Input Bahan Baku Lokal (ANALISA_LOKAL, REKOMENDASI_ORDER_LOKAL, LEAD_TIME_LOKAL) --}}
<x-modal name="showManualLokalModal" maxWidth="2xl">
    <x-slot:header>
        <div class="px-6 py-4 border-b border-border bg-card flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-foreground tracking-tight">Form Input Bahan Baku Lokal</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        <span class="font-medium text-foreground" x-text="manualLokalForm.nama"></span>
                        <span class="font-mono text-muted-foreground" x-text="' (' + manualLokalForm.sku + ')'"></span>
                        <template x-if="manualLokalForm.supplier_nama && manualLokalForm.supplier_nama !== '-'">
                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium bg-muted text-foreground border border-border" x-text="manualLokalForm.supplier_nama"></span>
                        </template>
                    </p>
                </div>
            </div>
            <button type="button" @click="showManualLokalModal = false" class="text-muted-foreground hover:text-foreground transition cursor-pointer p-1.5 rounded-lg hover:bg-accent">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <form @submit.prevent="saveManualLokal()" class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <!-- 1. Terjual 4 Bulan -->
            <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                <label class="font-semibold text-foreground block mb-1">
                    Terjual 4 Bulan Terakhir
                </label>
                <p class="text-[11px] text-muted-foreground mb-2.5">Rata-rata penjualan 4 bulan untuk kalkulasi ADU = Terjual / 30 hari.</p>
                <div class="relative flex items-center">
                    <input type="number" step="0.01" min="0" x-model.number="manualLokalForm.terjual_rata_rata_4bulan" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground pr-14 shadow-xs">
                    <span class="absolute right-3 text-[11px] text-muted-foreground font-medium pointer-events-none" x-text="manualLokalForm.satuan"></span>
                </div>
            </div>

            <!-- 2. Review Period (Hari) -->
            <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                <label class="font-semibold text-foreground block mb-1">
                    Review Period (Hari)
                </label>
                <p class="text-[11px] text-muted-foreground mb-2.5">Interval siklus peninjauan pesanan kembali (default 15 hari).</p>
                <div class="relative flex items-center">
                    <input type="number" min="1" x-model.number="manualLokalForm.review_period" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground pr-12 shadow-xs">
                    <span class="absolute right-3 text-[11px] text-muted-foreground font-medium pointer-events-none">Hari</span>
                </div>
            </div>

            <!-- 3. Tambahan Buffer Lead Time -->
            <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                <label class="font-semibold text-foreground block mb-1">
                    Tambahan Buffer Lead Time
                </label>
                <p class="text-[11px] text-muted-foreground mb-2.5">Buffer hari pengaman tak terduga: Safety Stock = (Max - Avg) + Buffer.</p>
                <div class="relative flex items-center">
                    <input type="number" min="0" x-model.number="manualLokalForm.tambahan_buffer_hari" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground pr-12 shadow-xs">
                    <span class="absolute right-3 text-[11px] text-muted-foreground font-medium pointer-events-none">Hari</span>
                </div>
            </div>

            <!-- 4. Harga Satuan Pakai (Rp) -->
            <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                <label class="font-semibold text-foreground block mb-1">
                    Harga Satuan Pakai (Rp)
                </label>
                <p class="text-[11px] text-muted-foreground mb-2.5">Harga per satuan dasar (misal Rp/ml) untuk kalkulasi total nominal.</p>
                <div class="relative flex items-center">
                    <span class="absolute left-3 text-[11px] text-muted-foreground font-medium pointer-events-none">Rp</span>
                    <input type="number" step="0.01" min="0" x-model.number="manualLokalForm.harga_ml_pcs" class="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground shadow-xs">
                </div>
            </div>

            <!-- 5. Stok Fisik Saat Ini (Satuan Pakai) -->
            <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs sm:col-span-2">
                <label class="font-semibold text-foreground block mb-1">
                    Stok Fisik Saat Ini (Satuan Pakai)
                </label>
                <p class="text-[11px] text-muted-foreground mb-2.5">Gunakan input ini jika ingin menyesuaikan saldo stok fisik untuk analisa ini.</p>
                <div class="relative flex items-center">
                    <input type="number" step="0.01" min="0" x-model.number="manualLokalForm.stok_saat_ini" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground pr-14 shadow-xs">
                    <span class="absolute right-3 text-[11px] text-muted-foreground font-medium pointer-events-none" x-text="manualLokalForm.satuan"></span>
                </div>
            </div>
        </div>

        {{-- Live Calculation Preview Card --}}
        <div class="bg-card border border-border rounded-xl p-4 shadow-xs space-y-3">
            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-2">
                <svg class="size-4 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span>Simulasi Live Hasil Analisa:</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                <div class="p-3 bg-muted/40 rounded-lg border border-border text-center shadow-xs">
                    <div class="text-[10px] text-muted-foreground font-semibold uppercase tracking-wider">ADU Harian</div>
                    <div class="font-mono font-bold text-foreground text-xs mt-1" x-text="simulasiLokalAdu()"></div>
                </div>
                <div class="p-3 bg-muted/40 rounded-lg border border-border text-center shadow-xs">
                    <div class="text-[10px] text-muted-foreground font-semibold uppercase tracking-wider">Batas Min (ROP)</div>
                    <div class="font-mono font-bold text-foreground text-xs mt-1" x-text="simulasiLokalBatasMin()"></div>
                </div>
                <div class="p-3 bg-muted/40 rounded-lg border border-border text-center shadow-xs">
                    <div class="text-[10px] text-muted-foreground font-semibold uppercase tracking-wider">Target Stock</div>
                    <div class="font-mono font-bold text-foreground text-xs mt-1" x-text="simulasiLokalTarget()"></div>
                </div>
                <div class="p-3 bg-muted/40 rounded-lg border border-border text-center shadow-xs">
                    <div class="text-[10px] text-muted-foreground font-semibold uppercase tracking-wider">Estimasi Order</div>
                    <div class="font-mono font-bold text-foreground text-xs mt-1" x-text="simulasiLokalOrder()"></div>
                </div>
            </div>
        </div>

        {{-- Action Buttons Footer --}}
        <div class="pt-4 border-t border-border flex justify-end gap-2.5">
            <x-button type="button" @click="showManualLokalModal = false" variant="outline" size="sm">
                Batal
            </x-button>
            <x-button type="submit" :disabled="savingManualLokal" variant="primary" size="sm">
                <template x-if="savingManualLokal">
                    <svg class="animate-spin -ml-1 mr-1.5 size-3.5 text-current" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </template>
                <span x-text="savingManualLokal ? 'Menyimpan & Menghitung...' : 'Simpan & Hitung Ulang'"></span>
            </x-button>
        </div>
    </form>
</x-modal>

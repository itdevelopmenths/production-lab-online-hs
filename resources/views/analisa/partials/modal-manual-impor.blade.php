{{-- Modal 3: Form Input Bahan Baku Impor (LEAD_TIME_IMPOR & ANALISA_IMPOR) --}}
<x-modal name="showManualImporModal" maxWidth="3xl">
    <x-slot:header>
        <div class="px-6 py-4 border-b border-border bg-card flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-foreground tracking-tight">Form Input Bahan Baku Impor</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        <span class="font-medium text-foreground" x-text="manualImporForm.nama"></span>
                        <span class="font-mono text-muted-foreground" x-text="' (' + manualImporForm.sku + ')'"></span>
                        <template x-if="manualImporForm.supplier_nama && manualImporForm.supplier_nama !== '-'">
                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium bg-muted text-foreground border border-border" x-text="manualImporForm.supplier_nama"></span>
                        </template>
                    </p>
                </div>
            </div>
            <button type="button" @click="showManualImporModal = false" class="text-muted-foreground hover:text-foreground transition cursor-pointer p-1.5 rounded-lg hover:bg-accent">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <form @submit.prevent="saveManualImpor()" class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
        <!-- Group 1: Lead Time Impor -->
        <div class="space-y-3">
            <h4 class="text-xs font-semibold text-foreground uppercase tracking-wider pb-1.5 border-b border-border flex items-center gap-1.5">
                <span>1. Lead Time Impor</span>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                    <label class="font-semibold text-foreground block mb-1">
                        Lead Time Average (Hari)
                    </label>
                    <p class="text-[11px] text-muted-foreground mb-2.5">Rata-rata waktu kedatangan pesanan impor.</p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.01" min="0" x-model.number="manualImporForm.lead_time_average" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground pr-12 shadow-xs">
                        <span class="absolute right-3 text-[11px] text-muted-foreground font-medium pointer-events-none">Hari</span>
                    </div>
                </div>
                <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                    <label class="font-semibold text-foreground block mb-1">
                        Lead Time Max (Hari)
                    </label>
                    <p class="text-[11px] text-muted-foreground mb-2.5">Durasi terlama (Buffer Days = Max - Average).</p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.01" min="0" x-model.number="manualImporForm.lead_time_max" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground pr-12 shadow-xs">
                        <span class="absolute right-3 text-[11px] text-muted-foreground font-medium pointer-events-none">Hari</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Group 2: Analisa & Parameter Pemakaian Impor -->
        <div class="space-y-3">
            <h4 class="text-xs font-semibold text-foreground uppercase tracking-wider pb-1.5 border-b border-border flex items-center gap-1.5">
                <span>2. Parameter Analisa Pemakaian</span>
            </h4>

            {{-- Rentang Tanggal Kalender Riil --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                    <label class="font-semibold text-foreground block mb-1">
                        Periode Mulai OUT <span class="text-destructive">*</span>
                    </label>
                    <p class="text-[11px] text-muted-foreground mb-2.5">Tanggal awal rekaman data pengeluaran (OUT).</p>
                    <input type="date" x-model="manualImporForm.periode_mulai" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground shadow-xs" required>
                </div>
                <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                    <label class="font-semibold text-foreground block mb-1">
                        Periode Akhir OUT <span class="text-destructive">*</span>
                    </label>
                    <p class="text-[11px] text-muted-foreground mb-2.5">
                        Tanggal akhir rekaman (Total: <span class="font-bold text-foreground font-mono" x-text="simulasiImporJumlahHari() + ' Hari'"></span>).
                    </p>
                    <input type="date" x-model="manualImporForm.periode_akhir" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground shadow-xs" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                    <label class="font-semibold text-foreground block mb-1">
                        Total OUT (Pengeluaran)
                    </label>
                    <p class="text-[11px] text-muted-foreground mb-2.5">
                        ADU Base = OUT / <span class="font-bold font-mono text-foreground" x-text="simulasiImporJumlahHari()"></span> Hari
                    </p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.01" min="0" x-model.number="manualImporForm.out" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground pr-12 shadow-xs">
                        <span class="absolute right-3 text-[11px] text-muted-foreground font-medium pointer-events-none" x-text="manualImporForm.satuan"></span>
                    </div>
                </div>
                <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                    <label class="font-semibold text-foreground block mb-1">
                        Review Period (Hari)
                    </label>
                    <p class="text-[11px] text-muted-foreground mb-2.5">Interval siklus order impor (default 30 hari).</p>
                    <div class="relative flex items-center">
                        <input type="number" min="1" x-model.number="manualImporForm.review_period" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground pr-12 shadow-xs">
                        <span class="absolute right-3 text-[11px] text-muted-foreground font-medium pointer-events-none">Hari</span>
                    </div>
                </div>
                <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                    <label class="font-semibold text-foreground block mb-1">
                        Klasifikasi ABC
                    </label>
                    <p class="text-[11px] text-muted-foreground mb-2.5">
                        Buffer: <span class="font-bold font-mono text-foreground" x-text="'+' + simulasiImporTambahanBuffer() + ' Hari'"></span>
                    </p>
                    <select x-model="manualImporForm.klasifikasi_abc_id" @change="onAbcChange()" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-semibold text-foreground shadow-xs">
                        <template x-for="abc in klasifikasiAbcList" :key="abc.id">
                            <option :value="abc.id" :selected="manualImporForm.klasifikasi_abc_id == abc.id" x-text="abc.nama + ' (+' + abc.tambahan_buffer_hari + ' Hari)'"></option>
                        </template>
                    </select>
                </div>
            </div>
        </div>

        <!-- Group 3: Stok Fisik, Inbound & Harga -->
        <div class="space-y-3">
            <h4 class="text-xs font-semibold text-foreground uppercase tracking-wider pb-1.5 border-b border-border flex items-center gap-1.5">
                <span>3. Stok, Inbound & Finansial</span>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                    <label class="font-semibold text-foreground block mb-1">
                        Stok Fisik Saat Ini
                    </label>
                    <p class="text-[11px] text-muted-foreground mb-2.5">Stok riil di gudang impor.</p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.01" min="0" x-model.number="manualImporForm.stok_saat_ini" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground pr-12 shadow-xs">
                        <span class="absolute right-3 text-[11px] text-muted-foreground font-medium pointer-events-none" x-text="manualImporForm.satuan"></span>
                    </div>
                </div>
                <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                    <label class="font-semibold text-foreground block mb-1">
                        Inbound Sebelum ETA
                    </label>
                    <p class="text-[11px] text-muted-foreground mb-2.5">PO dalam perjalanan sebelum tiba.</p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.01" min="0" x-model.number="manualImporForm.inbound_before_eta" class="w-full px-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground pr-12 shadow-xs">
                        <span class="absolute right-3 text-[11px] text-muted-foreground font-medium pointer-events-none" x-text="manualImporForm.satuan"></span>
                    </div>
                </div>
                <div class="p-4 bg-muted/40 rounded-xl border border-border shadow-xs">
                    <label class="font-semibold text-foreground block mb-1">
                        Harga per Satuan (Rp)
                    </label>
                    <p class="text-[11px] text-muted-foreground mb-2.5">Harga per satuan beli impor.</p>
                    <div class="relative flex items-center">
                        <span class="absolute left-3 text-[11px] text-muted-foreground font-medium pointer-events-none">Rp</span>
                        <input type="number" step="0.01" min="0" x-model.number="manualImporForm.harga_per_satuan" class="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-mono font-bold text-foreground shadow-xs">
                    </div>
                </div>
            </div>
        </div>

        {{-- Live Calculation Preview Card --}}
        <div class="bg-card border border-border rounded-xl p-4 shadow-xs space-y-3">
            <div class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="size-4 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>Simulasi Hasil Analisa Impor:</span>
                </div>
                <div class="text-[11px] font-normal text-muted-foreground">
                    MOQ: <span class="font-mono font-bold text-foreground" x-text="formatNumber(manualImporForm.satuan_order_moq || 1) + ' ' + (manualImporForm.satuan || '')"></span>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-6 gap-2.5 text-xs">
                <div class="p-2.5 bg-muted/40 rounded-lg border border-border text-center shadow-xs">
                    <div class="text-[9px] text-muted-foreground font-semibold uppercase tracking-wider">ADU Base</div>
                    <div class="font-mono font-bold text-foreground text-xs mt-1" x-text="simulasiImporAdu()"></div>
                </div>
                <div class="p-2.5 bg-muted/40 rounded-lg border border-border text-center shadow-xs">
                    <div class="text-[9px] text-muted-foreground font-semibold uppercase tracking-wider">ADU ETA</div>
                    <div class="font-mono font-bold text-foreground text-xs mt-1" x-text="simulasiImporAduEta()"></div>
                </div>
                <div class="p-2.5 bg-muted/40 rounded-lg border border-border text-center shadow-xs">
                    <div class="text-[9px] text-muted-foreground font-semibold uppercase tracking-wider">Safety Stock</div>
                    <div class="font-mono font-bold text-foreground text-xs mt-1" x-text="simulasiImporSafetyStock()"></div>
                </div>
                <div class="p-2.5 bg-muted/40 rounded-lg border border-border text-center shadow-xs">
                    <div class="text-[9px] text-muted-foreground font-semibold uppercase tracking-wider">Target Stock</div>
                    <div class="font-mono font-bold text-foreground text-xs mt-1" x-text="simulasiImporTarget()"></div>
                </div>
                <div class="p-2.5 bg-muted/40 rounded-lg border border-border text-center shadow-xs">
                    <div class="text-[9px] text-muted-foreground font-semibold uppercase tracking-wider">Proyeksi</div>
                    <div class="font-mono font-bold text-xs mt-1" :class="simulasiImporProyeksiRaw() < 0 ? 'text-destructive' : 'text-foreground'" x-text="simulasiImporProyeksi()"></div>
                </div>
                <div class="p-2.5 bg-muted/40 rounded-lg border border-border text-center shadow-xs">
                    <div class="text-[9px] text-foreground font-semibold uppercase tracking-wider">Order (MOQ)</div>
                    <div class="font-mono font-bold text-foreground text-xs mt-1" x-text="simulasiImporOrder()"></div>
                </div>
            </div>
        </div>

        {{-- Action Buttons Footer --}}
        <div class="pt-4 border-t border-border flex justify-end gap-2.5">
            <x-button type="button" @click="showManualImporModal = false" variant="outline" size="sm">
                Batal
            </x-button>
            <x-button type="submit" ::disabled="savingManualImpor" variant="primary" size="sm">
                <template x-if="savingManualImpor">
                    <svg class="animate-spin -ml-1 mr-1.5 size-3.5 text-current" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </template>
                <span x-text="savingManualImpor ? 'Menyimpan & Menghitung...' : 'Simpan & Hitung Ulang'"></span>
            </x-button>
        </div>
    </form>
</x-modal>

{{-- Modal 3: Form Input Bahan Baku Impor (LEAD_TIME_IMPOR & ANALISA_IMPOR) --}}
{{-- Follows AdminLTE Modal Standard & SOLID Principles --}}
<x-modal name="showManualImporModal" maxWidth="3xl" variant="primary">
    <x-slot:header>
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-primary-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-gray-800 tracking-tight">Form Input Bahan Baku Impor</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <span class="font-semibold text-gray-800" x-text="manualImporForm.nama"></span>
                        <span class="font-mono text-gray-400" x-text="' (' + manualImporForm.sku + ')'"></span>
                        <template x-if="manualImporForm.supplier_nama && manualImporForm.supplier_nama !== '-'">
                            <span class="ml-1 inline-flex items-center px-1.5 py-0.2 rounded-xs text-[10px] font-medium bg-gray-100 text-gray-700 border border-gray-300" x-text="manualImporForm.supplier_nama"></span>
                        </template>
                    </p>
                </div>
            </div>
            <button type="button" @click="showManualImporModal = false" class="text-gray-400 hover:text-gray-600 transition cursor-pointer p-1 rounded-sm hover:bg-gray-200/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <form @submit.prevent="saveManualImpor()" class="space-y-4 max-h-[75vh] overflow-y-auto">
        <!-- Group 1: Lead Time Impor (3.1) -->
        <div>
            <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider mb-2.5 pb-1 border-b border-gray-200 flex items-center gap-1.5">
                <span>1. Lead Time Impor (lead_time_impor)</span>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="p-3 bg-gray-50 rounded-sm border border-gray-200 shadow-2xs">
                    <label class="font-semibold text-gray-800 block mb-1">
                        Lead Time Average (Hari)
                    </label>
                    <p class="text-[11px] text-gray-500 mb-2">Rata-rata waktu kedatangan pesanan impor.</p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.01" min="0" x-model.number="manualImporForm.lead_time_average" class="w-full px-3 py-1.5 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-bold text-gray-900 pr-12 shadow-xs">
                        <span class="absolute right-2.5 text-[11px] text-gray-500 font-medium pointer-events-none">Hari</span>
                    </div>
                </div>
                <div class="p-3 bg-gray-50 rounded-sm border border-gray-200 shadow-2xs">
                    <label class="font-semibold text-gray-800 block mb-1">
                        Lead Time Max (Hari)
                    </label>
                    <p class="text-[11px] text-gray-500 mb-2">Durasi terlama (Buffer Days = Max - Average).</p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.01" min="0" x-model.number="manualImporForm.lead_time_max" class="w-full px-3 py-1.5 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-bold text-gray-900 pr-12 shadow-xs">
                        <span class="absolute right-2.5 text-[11px] text-gray-500 font-medium pointer-events-none">Hari</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Group 2: Analisa & Parameter Pemakaian Impor (3.2) -->
        <div>
            <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider mb-2.5 pb-1 border-b border-gray-200 flex items-center gap-1.5">
                <span>2. Parameter Analisa Pemakaian (analisa_impor)</span>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                <div class="p-3 bg-gray-50 rounded-sm border border-gray-200 shadow-2xs">
                    <label class="font-semibold text-gray-800 block mb-1">
                        OUT (4 Bulan)
                    </label>
                    <p class="text-[11px] text-gray-500 mb-2">Total pemakaian 4 bulan (ADU = OUT / 120).</p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.01" min="0" x-model.number="manualImporForm.out" class="w-full px-3 py-1.5 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-bold text-gray-900 pr-12 shadow-xs">
                        <span class="absolute right-2.5 text-[11px] text-gray-500 font-medium pointer-events-none" x-text="manualImporForm.satuan"></span>
                    </div>
                </div>
                <div class="p-3 bg-gray-50 rounded-sm border border-gray-200 shadow-2xs">
                    <label class="font-semibold text-gray-800 block mb-1">
                        Review Period
                    </label>
                    <p class="text-[11px] text-gray-500 mb-2">Interval siklus order impor (default 30 hari).</p>
                    <div class="relative flex items-center">
                        <input type="number" min="1" x-model.number="manualImporForm.review_period" class="w-full px-3 py-1.5 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-bold text-gray-900 pr-12 shadow-xs">
                        <span class="absolute right-2.5 text-[11px] text-gray-500 font-medium pointer-events-none">Hari</span>
                    </div>
                </div>
                <div class="p-3 bg-gray-50 rounded-sm border border-gray-200 shadow-2xs">
                    <label class="font-semibold text-gray-800 block mb-1">
                        Klasifikasi ABC
                    </label>
                    <p class="text-[11px] text-gray-500 mb-2">Kategori kritikalitas bahan impor.</p>
                    <select x-model="manualImporForm.klasifikasi_abc" class="w-full px-3 py-1.5 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-semibold text-gray-800 uppercase shadow-xs">
                        <option value="wajib_a">Wajib A (Kritis Utama)</option>
                        <option value="a">Kelas A (Tinggi)</option>
                        <option value="b">Kelas B (Menengah)</option>
                        <option value="c">Kelas C (Rendah)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Group 3: Stok Fisik, Inbound & Harga (3.2) -->
        <div>
            <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider mb-2.5 pb-1 border-b border-gray-200 flex items-center gap-1.5">
                <span>3. Stok, Inbound & Finansial</span>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                <div class="p-3 bg-gray-50 rounded-sm border border-gray-200 shadow-2xs">
                    <label class="font-semibold text-gray-800 block mb-1">
                        Stok Fisik Saat Ini
                    </label>
                    <p class="text-[11px] text-gray-500 mb-2">Stok riil di gudang impor.</p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.01" min="0" x-model.number="manualImporForm.stok_saat_ini" class="w-full px-3 py-1.5 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-bold text-gray-900 pr-12 shadow-xs">
                        <span class="absolute right-2.5 text-[11px] text-gray-500 font-medium pointer-events-none" x-text="manualImporForm.satuan"></span>
                    </div>
                </div>
                <div class="p-3 bg-gray-50 rounded-sm border border-gray-200 shadow-2xs">
                    <label class="font-semibold text-gray-800 block mb-1">
                        Inbound Sebelum ETA
                    </label>
                    <p class="text-[11px] text-gray-500 mb-2">PO dalam perjalanan sebelum perkiraan tiba.</p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.01" min="0" x-model.number="manualImporForm.inbound_before_eta" class="w-full px-3 py-1.5 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-bold text-gray-900 pr-12 shadow-xs">
                        <span class="absolute right-2.5 text-[11px] text-gray-500 font-medium pointer-events-none" x-text="manualImporForm.satuan"></span>
                    </div>
                </div>
                <div class="p-3 bg-gray-50 rounded-sm border border-gray-200 shadow-2xs">
                    <label class="font-semibold text-gray-800 block mb-1">
                        Harga per Satuan (Rp)
                    </label>
                    <p class="text-[11px] text-gray-500 mb-2">Harga per satuan beli impor.</p>
                    <div class="relative flex items-center">
                        <span class="absolute left-2.5 text-[11px] text-gray-500 font-medium pointer-events-none">Rp</span>
                        <input type="number" step="0.01" min="0" x-model.number="manualImporForm.harga_per_satuan" class="w-full pl-8 pr-3 py-1.5 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-bold text-gray-900 shadow-xs">
                    </div>
                </div>
            </div>
        </div>

        {{-- Live Calculation Preview Callout (AdminLTE Callout) --}}
        <div class="bg-gray-50 border border-gray-200 border-l-4 border-l-primary-600 rounded-r-sm p-3 shadow-2xs space-y-2">
            <div class="text-[10px] font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span>Simulasi Live Hasil Analisa Impor:</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                <div class="p-2.5 bg-white rounded-sm border border-gray-200 text-center shadow-2xs">
                    <div class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider">ADU (OUT / 120)</div>
                    <div class="font-mono font-bold text-gray-900 text-xs mt-0.5" x-text="simulasiImporAdu()"></div>
                </div>
                <div class="p-2.5 bg-white rounded-sm border border-gray-200 text-center shadow-2xs">
                    <div class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider">Safety Stock</div>
                    <div class="font-mono font-bold text-gray-900 text-xs mt-0.5" x-text="simulasiImporSafetyStock()"></div>
                </div>
                <div class="p-2.5 bg-white rounded-sm border border-gray-200 text-center shadow-2xs">
                    <div class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider">Target Stock</div>
                    <div class="font-mono font-bold text-gray-900 text-xs mt-0.5" x-text="simulasiImporTarget()"></div>
                </div>
                <div class="p-2.5 bg-white rounded-sm border border-gray-200 text-center shadow-2xs">
                    <div class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider">Rekomendasi Order</div>
                    <div class="font-mono font-bold text-primary-700 text-xs mt-0.5" x-text="simulasiImporOrder()"></div>
                </div>
            </div>
        </div>

        {{-- Action Buttons Footer --}}
        <div class="pt-3 border-t border-gray-200 flex justify-end gap-2">
            <button type="button" @click="showManualImporModal = false" class="px-3.5 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-sm hover:bg-gray-50 shadow-xs cursor-pointer">
                Batal
            </button>
            <button type="submit" :disabled="savingManualImpor" class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold text-white bg-primary-600 hover:bg-primary-700 rounded-sm shadow-xs transition disabled:opacity-50 cursor-pointer">
                <template x-if="savingManualImpor">
                    <svg class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </template>
                <span x-text="savingManualImpor ? 'Menyimpan & Menghitung...' : 'Simpan & Hitung Ulang'"></span>
            </button>
        </div>
    </form>
</x-modal>

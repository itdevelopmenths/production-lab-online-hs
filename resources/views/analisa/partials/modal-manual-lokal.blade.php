{{-- Modal 2: Form Input Bahan Baku Lokal (ANALISA_LOKAL, REKOMENDASI_ORDER_LOKAL, LEAD_TIME_LOKAL) --}}
<x-modal name="showManualLokalModal" maxWidth="2xl">
    <x-slot:header>
        <div class="px-6 py-4 border-b border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="size-9 rounded-[10px] border border-[#E8E8E8] dark:border-gray-700 bg-[#fafafa] dark:bg-gray-800 text-[#111111] dark:text-white flex items-center justify-center shrink-0">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-[#101828] dark:text-white tracking-tight">Form Input Bahan Baku Lokal</h3>
                    <p class="text-xs text-[#667085] dark:text-gray-400 mt-0.5">
                        <span class="font-medium text-[#101828] dark:text-gray-200" x-text="manualLokalForm.nama"></span>
                        <span class="font-mono text-[#667085] dark:text-gray-400" x-text="' (' + manualLokalForm.sku + ')'"></span>
                        <template x-if="manualLokalForm.supplier_nama && manualLokalForm.supplier_nama !== '-'">
                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium bg-gray-100 dark:bg-gray-800 text-[#101828] dark:text-gray-200 border border-[#E8E8E8] dark:border-gray-700" x-text="manualLokalForm.supplier_nama"></span>
                        </template>
                    </p>
                </div>
            </div>
            <button type="button" @click="showManualLokalModal = false" class="text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white transition cursor-pointer p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <form @submit.prevent="saveManualLokal()" class="bg-[#F5F5F5] dark:bg-gray-950">
        <div class="p-5 sm:p-6 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs">
                <!-- 1. Terjual 4 Bulan -->
                <div class="p-4 bg-white dark:bg-gray-900 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                    <label class="font-semibold text-[#101828] dark:text-white block mb-1">
                        Terjual 4 Bulan Terakhir
                    </label>
                    <p class="text-[11px] text-[#667085] dark:text-gray-400 mb-2.5">Rata-rata penjualan 4 bulan untuk kalkulasi ADU = Terjual / 30 hari.</p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.01" min="0" x-model.number="manualLokalForm.terjual_rata_rata_4bulan" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-bold text-[#101828] dark:text-white pr-14 outline-none">
                        <span class="absolute right-3 text-[11px] text-[#667085] dark:text-gray-400 font-medium pointer-events-none" x-text="manualLokalForm.satuan"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs text-[#667085] dark:text-gray-400" x-show="manualLokalForm.current_terjual_sistem !== null && manualLokalForm.current_terjual_sistem !== undefined">
                        <span class="inline-flex items-center gap-1 font-mono text-[11px]">
                            <span>Sistem:</span>
                            <span class="font-medium text-[#101828] dark:text-white" x-text="formatNumber(manualLokalForm.current_terjual_sistem) + ' ' + (manualLokalForm.satuan || '')"></span>
                        </span>
                        <button type="button"
                                @click="manualLokalForm.terjual_rata_rata_4bulan = manualLokalForm.current_terjual_sistem"
                                class="text-[#111111] dark:text-white underline font-medium text-[11px] transition-colors focus:outline-none cursor-pointer">
                            Gunakan Nilai Sistem
                        </button>
                    </div>
                </div>

                <!-- 2. Review Period (Hari) -->
                <div class="p-4 bg-white dark:bg-gray-900 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                    <label class="font-semibold text-[#101828] dark:text-white block mb-1">
                        Review Period (Hari)
                    </label>
                    <p class="text-[11px] text-[#667085] dark:text-gray-400 mb-2.5">Interval siklus peninjauan pesanan kembali (default 15 hari).</p>
                    <div class="relative flex items-center">
                        <input type="number" min="1" x-model.number="manualLokalForm.review_period" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-bold text-[#101828] dark:text-white pr-12 outline-none">
                        <span class="absolute right-3 text-[11px] text-[#667085] dark:text-gray-400 font-medium pointer-events-none">Hari</span>
                    </div>
                </div>

                <!-- 3. Tambahan Buffer Lead Time -->
                <div class="p-4 bg-white dark:bg-gray-900 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                    <label class="font-semibold text-[#101828] dark:text-white block mb-1">
                        Tambahan Buffer Lead Time
                    </label>
                    <p class="text-[11px] text-[#667085] dark:text-gray-400 mb-2.5">Buffer hari pengaman tak terduga: Safety Stock = (Max - Avg) + Buffer.</p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.1" min="0" x-model.number="manualLokalForm.tambahan_buffer_hari" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-bold text-[#101828] dark:text-white pr-12 outline-none">
                        <span class="absolute right-3 text-[11px] text-[#667085] dark:text-gray-400 font-medium pointer-events-none">Hari</span>
                    </div>
                </div>

                <!-- 4. Harga Satuan Pakai (Rp) -->
                <div class="p-4 bg-white dark:bg-gray-900 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                    <label class="font-semibold text-[#101828] dark:text-white block mb-1">
                        Harga Satuan Pakai (Rp)
                    </label>
                    <p class="text-[11px] text-[#667085] dark:text-gray-400 mb-2.5">Harga per satuan dasar (misal Rp/ml) untuk kalkulasi total nominal.</p>
                    <div class="relative flex items-center">
                        <span class="absolute left-3 text-[11px] text-[#667085] dark:text-gray-400 font-medium pointer-events-none">Rp</span>
                        <input type="number" step="0.01" min="0" x-model.number="manualLokalForm.harga_ml_pcs" class="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-bold text-[#101828] dark:text-white outline-none">
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs text-[#667085] dark:text-gray-400" x-show="manualLokalForm.current_hpp !== null && manualLokalForm.current_hpp !== undefined">
                        <span class="inline-flex items-center gap-1 font-mono text-[11px]">
                            <span>HPP Sistem:</span>
                            <span class="font-medium text-[#101828] dark:text-white" x-text="formatRupiah(manualLokalForm.current_hpp)"></span>
                        </span>
                        <button type="button"
                                @click="manualLokalForm.harga_ml_pcs = manualLokalForm.current_hpp"
                                class="text-[#111111] dark:text-white underline font-medium text-[11px] transition-colors focus:outline-none cursor-pointer">
                            Gunakan Nilai Sistem
                        </button>
                    </div>
                </div>

                <!-- 5. Stok Fisik Saat Ini (Satuan Pakai) -->
                <div class="p-4 bg-white dark:bg-gray-900 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 shadow-[0_1px_2px_rgba(0,0,0,0.04)] sm:col-span-2">
                    <label class="font-semibold text-[#101828] dark:text-white block mb-1">
                        Stok Fisik Saat Ini (Satuan Pakai)
                    </label>
                    <p class="text-[11px] text-[#667085] dark:text-gray-400 mb-2.5">Gunakan input ini jika ingin menyesuaikan saldo stok fisik untuk analisa ini.</p>
                    <div class="relative flex items-center">
                        <input type="number" step="0.01" min="0" x-model.number="manualLokalForm.stok_saat_ini" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-bold text-[#101828] dark:text-white pr-14 outline-none">
                        <span class="absolute right-3 text-[11px] text-[#667085] dark:text-gray-400 font-medium pointer-events-none" x-text="manualLokalForm.satuan"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs text-[#667085] dark:text-gray-400" x-show="manualLokalForm.current_stok_aktual !== null && manualLokalForm.current_stok_aktual !== undefined">
                        <span class="inline-flex items-center gap-1 font-mono text-[11px]">
                            <span>Stok Aktual Sistem:</span>
                            <span class="font-medium text-[#101828] dark:text-white" x-text="formatNumber(manualLokalForm.current_stok_aktual) + ' ' + (manualLokalForm.satuan || '')"></span>
                        </span>
                        <button type="button"
                                @click="manualLokalForm.stok_saat_ini = manualLokalForm.current_stok_aktual"
                                class="text-[#111111] dark:text-white underline font-medium text-[11px] transition-colors focus:outline-none cursor-pointer">
                            Gunakan Nilai Sistem
                        </button>
                    </div>
                </div>
            </div>

            {{-- Live Calculation Preview Card --}}
            <div class="bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-gray-800 rounded-[10px] p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] space-y-3">
                <div class="text-[10px] font-bold text-[#667085] dark:text-gray-400 uppercase tracking-wider flex items-center gap-2">
                    <svg class="size-4 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <span>Simulasi Live Hasil Analisa:</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                    <div class="p-3 bg-[#F5F5F5] dark:bg-gray-950 rounded-lg border border-[#E8E8E8] dark:border-gray-800 text-center">
                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-semibold uppercase tracking-wider">ADU Harian</div>
                        <div class="font-mono font-bold text-[#101828] dark:text-white text-xs mt-1" x-text="simulasiLokalAdu()"></div>
                    </div>
                    <div class="p-3 bg-[#F5F5F5] dark:bg-gray-950 rounded-lg border border-[#E8E8E8] dark:border-gray-800 text-center">
                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-semibold uppercase tracking-wider">Batas Min (ROP)</div>
                        <div class="font-mono font-bold text-[#101828] dark:text-white text-xs mt-1" x-text="simulasiLokalBatasMin()"></div>
                    </div>
                    <div class="p-3 bg-[#F5F5F5] dark:bg-gray-950 rounded-lg border border-[#E8E8E8] dark:border-gray-800 text-center">
                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-semibold uppercase tracking-wider">Target Stock</div>
                        <div class="font-mono font-bold text-[#101828] dark:text-white text-xs mt-1" x-text="simulasiLokalTarget()"></div>
                    </div>
                    <div class="p-3 bg-[#F5F5F5] dark:bg-gray-950 rounded-lg border border-[#E8E8E8] dark:border-gray-800 text-center">
                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-semibold uppercase tracking-wider">Estimasi Order</div>
                        <div class="font-mono font-bold text-[#101828] dark:text-white text-xs mt-1" x-text="simulasiLokalOrder()"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action Buttons Footer --}}
        <div class="px-6 py-4 bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-gray-800 flex justify-end gap-2.5">
            <x-button type="button" @click="showManualLokalModal = false" variant="secondary" size="sm">
                Batal
            </x-button>
            <x-button type="submit" ::disabled="savingManualLokal" variant="primary" size="sm">
                <template x-if="savingManualLokal">
                    <svg class="animate-spin -ml-1 mr-1.5 size-3.5 text-current" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </template>
                <span x-text="savingManualLokal ? 'Menyimpan & Menghitung...' : 'Simpan & Hitung Ulang'"></span>
            </x-button>
        </div>
    </form>
</x-modal>

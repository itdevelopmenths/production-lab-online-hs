{{-- Modal 1: Form Input Tahap Lead Time & Buffer (LEAD_TIME_LOKAL_STAGE & LEAD_TIME_LOKAL) --}}
<x-modal name="showStageModal" maxWidth="4xl">
    <x-slot:header>
        <div class="px-6 py-4 border-b border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="size-9 rounded-[10px] border border-[#E8E8E8] dark:border-gray-700 bg-[#fafafa] dark:bg-gray-800 text-[#111111] dark:text-white flex items-center justify-center shrink-0">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-[#101828] dark:text-white tracking-tight">Form Input Tahap Lead Time & Buffer</h3>
                    <p class="text-xs text-[#667085] dark:text-gray-400 mt-0.5">
                        <span class="font-medium text-[#101828] dark:text-gray-200" x-text="stageForm.nama"></span>
                        <span class="font-mono text-[#667085] dark:text-gray-400" x-text="' (' + stageForm.sku + ')'"></span>
                        <template x-if="stageForm.supplier_nama && stageForm.supplier_nama !== '-'">
                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium bg-gray-100 dark:bg-gray-800 text-[#101828] dark:text-gray-200 border border-[#E8E8E8] dark:border-gray-700" x-text="stageForm.supplier_nama"></span>
                        </template>
                    </p>
                </div>
            </div>
            <button type="button" @click="showStageModal = false" class="text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white transition cursor-pointer p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <form @submit.prevent="saveStages()" class="bg-[#F5F5F5] dark:bg-gray-950">
        <div class="p-5 sm:p-6 space-y-5 max-h-[70vh] overflow-y-auto custom-scrollbar">
            <template x-if="stageForm.sumber_lt === 'otomatis'">
                <div class="p-3.5 rounded-[10px] border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50 dark:bg-emerald-950/30 text-xs text-emerald-900 dark:text-emerald-200 leading-relaxed flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <strong class="font-semibold">Sumber Lead Time Otomatis (<span x-text="stageForm.jumlah_transaksi"></span> PO):</strong>
                        Rincian 9 tahap aktif saat ini dihitung otomatis dari histori PO Purchasing. Nilai 9 tahap di bawah digunakan sebagai <em>fallback manual</em> jika supplier berganti ke vendor tanpa histori PO.
                    </div>
                    <template x-if="stageForm.auto_average">
                        <button type="button" @click="copyAutoStages()" class="shrink-0 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs shadow-xs transition cursor-pointer">
                            Gunakan Nilai Sistem (PO Otomatis)
                        </button>
                    </template>
                </div>
            </template>

            {{-- Tabel Matriks Input Tahapan Lead Time (9 Tahapan) --}}
            <div class="overflow-x-auto border border-[#E8E8E8] dark:border-gray-800 rounded-[12px] bg-white dark:bg-gray-900">
                <table class="w-full text-xs text-left border-collapse">
                    <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[12px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                        <tr>
                            <th class="py-3 px-4">Tahapan Lead Time (9 Tahap)</th>
                            <th class="py-3 px-4 text-center w-36 font-semibold">Skenario Average (Hari)</th>
                            <th class="py-3 px-4 text-center w-36 font-semibold">Skenario Max (Hari)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 font-mono bg-white dark:bg-gray-900">
                        <!-- 1. Perencanaan Kebutuhan -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">1. Perencanaan Kebutuhan</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Analisa kebutuhan & perencanaan internal</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.perencanaan" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.perencanaan" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                        </tr>
                        <!-- 2. Approval / Persetujuan -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">2. Approval / Persetujuan</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Waktu persetujuan approval order/PR internal</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.approval" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.approval" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                        </tr>
                        <!-- 3. Supplier Confirm -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">3. Supplier Confirm</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Konfirmasi kesanggupan & ketersediaan supplier</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.supplier_confirm" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.supplier_confirm" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                        </tr>
                        <!-- 4. Payment / Pembayaran -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">4. Payment / Pembayaran</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Proses DP / termin pembayaran supplier</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.payment" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.payment" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                        </tr>
                        <!-- 5. PO (Terbit PO Resmi) -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">5. PO (Terbit PO Resmi)</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Tanggal PO resmi terbit ke vendor</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.po" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.po" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                        </tr>
                        <!-- 6. Pengemasan / Produksi Vendor -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">6. Pengemasan / Produksi Vendor</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Durasi pabrikasi & packaging vendor</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.pengemasan" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.pengemasan" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                        </tr>
                        <!-- 7. Pengiriman / Ekspedisi -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">7. Pengiriman / Ekspedisi</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Waktu tempuh transit & ekspedisi pengiriman</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.pengiriman" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.pengiriman" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                        </tr>
                        <!-- 8. Unloading & Bongkar Muat -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">8. Unloading & Bongkar Muat</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Bongkar muat fisik dari armada pengangkut</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.unloading" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.unloading" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                        </tr>
                        <!-- 9. Input Sistem / QC Receiving -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">9. Input Sistem / QC Receiving</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Pengecekan QC fisik & input penerimaan ke sistem</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.input" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.input" class="w-24 text-center px-2.5 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-medium text-[#101828] dark:text-white outline-none">
                            </td>
                        </tr>
                        <!-- Subtotal Baris -->
                        <tr class="bg-[#fafafa] dark:bg-gray-800/60 font-bold border-t-2 border-[#E8E8E8] dark:border-gray-700 text-[#101828] dark:text-white">
                            <td class="py-3 px-4 font-sans">TOTAL LEAD TIME TAHAP:</td>
                            <td class="py-3 px-4 text-center font-mono" x-text="formatLt(stageSum('average')) + ' Hari'"></td>
                            <td class="py-3 px-4 text-center font-mono" x-text="formatLt(stageSum('max')) + ' Hari'"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Section Tambahan Buffer Hari (LEAD_TIME_LOKAL 2.2) --}}
            <div class="p-4 bg-white dark:bg-gray-900 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <label class="text-xs font-semibold text-[#101828] dark:text-white">
                            Tambahan Buffer Lead Time (Hari)
                        </label>
                        <p class="text-[11px] text-[#667085] dark:text-gray-400 mt-0.5">Buffer hari tambahan untuk mengantisipasi ketidakpastian pengiriman atau vendor peak season.</p>
                    </div>
                    <div class="w-32 shrink-0">
                        <input type="number" step="0.1" min="0" x-model.number="stageForm.tambahan_buffer_hari" class="w-full text-right px-3 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white font-mono font-bold text-[#101828] dark:text-white outline-none">
                    </div>
                </div>

                {{-- Live Calculation Preview Card --}}
                <div class="grid grid-cols-3 gap-3 pt-3 border-t border-[#E8E8E8] dark:border-gray-800 text-xs">
                    <div class="p-3 bg-[#F5F5F5] dark:bg-gray-950 rounded-lg border border-[#E8E8E8] dark:border-gray-800 text-center">
                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-semibold uppercase tracking-wider">Total Avg Lead Time</div>
                        <div class="text-base font-bold font-mono text-[#101828] dark:text-white mt-0.5" x-text="formatLt(stageSum('average')) + ' Hari'"></div>
                    </div>
                    <div class="p-3 bg-[#F5F5F5] dark:bg-gray-950 rounded-lg border border-[#E8E8E8] dark:border-gray-800 text-center">
                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-semibold uppercase tracking-wider">Total Max Lead Time</div>
                        <div class="text-base font-bold font-mono text-[#101828] dark:text-white mt-0.5" x-text="formatLt(stageSum('max')) + ' Hari'"></div>
                    </div>
                    <div class="p-3 bg-[#F5F5F5] dark:bg-gray-950 rounded-lg border border-[#E8E8E8] dark:border-gray-800 text-center">
                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-semibold uppercase tracking-wider">Safety Stock (Hari)</div>
                        <div class="text-base font-bold font-mono text-[#101828] dark:text-white mt-0.5" x-text="formatLt(stageSafetyStock()) + ' Hari'"></div>
                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-sans mt-0.5">(Max - Avg) + Buffer</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action Buttons Footer --}}
        <div class="px-6 py-4 bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-gray-800 flex justify-end gap-2.5">
            <x-button type="button" @click="showStageModal = false" variant="secondary" size="sm">
                Batal
            </x-button>
            <x-button type="submit" ::disabled="savingStage" variant="primary" size="sm">
                <template x-if="savingStage">
                    <svg class="animate-spin -ml-1 mr-1.5 size-3.5 text-current" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </template>
                <span x-text="savingStage ? 'Menyimpan & Menghitung...' : 'Simpan & Hitung Ulang'"></span>
            </x-button>
        </div>
    </form>
</x-modal>

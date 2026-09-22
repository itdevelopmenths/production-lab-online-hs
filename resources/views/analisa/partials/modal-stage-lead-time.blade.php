{{-- Modal 1: Form Input Tahap Lead Time & Buffer (LEAD_TIME_LOKAL_STAGE & LEAD_TIME_LOKAL) --}}
{{-- Follows AdminLTE Modal Standard & SOLID Principles --}}
<x-modal name="showStageModal" maxWidth="4xl" variant="primary">
    <x-slot:header>
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-primary-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-gray-800 tracking-tight">Form Input Tahap Lead Time & Buffer</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <span class="font-semibold text-gray-800" x-text="stageForm.nama"></span>
                        <span class="font-mono text-gray-400" x-text="' (' + stageForm.sku + ')'"></span>
                        <template x-if="stageForm.supplier_nama && stageForm.supplier_nama !== '-'">
                            <span class="ml-1 inline-flex items-center px-1.5 py-0.2 rounded-xs text-[10px] font-medium bg-gray-100 text-gray-700 border border-gray-300" x-text="stageForm.supplier_nama"></span>
                        </template>
                    </p>
                </div>
            </div>
            <button type="button" @click="showStageModal = false" class="text-gray-400 hover:text-gray-600 transition cursor-pointer p-1 rounded-sm hover:bg-gray-200/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <form @submit.prevent="saveStages()" class="space-y-4 max-h-[75vh] overflow-y-auto">
        {{-- Tabel Matriks Input Tahapan Lead Time (9 Tahapan) --}}
        <div class="overflow-x-auto border border-gray-200 rounded-sm shadow-2xs">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-gray-100 text-gray-700 text-[10px] uppercase font-semibold border-b border-gray-200">
                    <tr>
                        <th class="py-2.5 px-3">Tahapan Lead Time (9 Tahap)</th>
                        <th class="py-2.5 px-3 text-center w-36 font-semibold">Skenario Average (Hari)</th>
                        <th class="py-2.5 px-3 text-center w-36 font-semibold">Skenario Max (Hari)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 font-mono bg-white">
                    <!-- 1. Perencanaan Kebutuhan -->
                    <tr class="hover:bg-gray-50/80 transition">
                        <td class="py-2.5 px-3 font-sans">
                            <div class="font-semibold text-gray-900">1. Perencanaan Kebutuhan</div>
                            <div class="text-[10px] text-gray-500 font-normal">Analisa kebutuhan & perencanaan internal</div>
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.average.perencanaan" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.max.perencanaan" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                    </tr>
                    <!-- 2. Approval / Persetujuan -->
                    <tr class="hover:bg-gray-50/80 transition">
                        <td class="py-2.5 px-3 font-sans">
                            <div class="font-semibold text-gray-900">2. Approval / Persetujuan</div>
                            <div class="text-[10px] text-gray-500 font-normal">Waktu persetujuan approval order/PR internal</div>
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.average.approval" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.max.approval" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                    </tr>
                    <!-- 3. Supplier Confirm -->
                    <tr class="hover:bg-gray-50/80 transition">
                        <td class="py-2.5 px-3 font-sans">
                            <div class="font-semibold text-gray-900">3. Supplier Confirm</div>
                            <div class="text-[10px] text-gray-500 font-normal">Konfirmasi kesanggupan & ketersediaan supplier</div>
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.average.supplier_confirm" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.max.supplier_confirm" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                    </tr>
                    <!-- 4. Payment / Pembayaran -->
                    <tr class="hover:bg-gray-50/80 transition">
                        <td class="py-2.5 px-3 font-sans">
                            <div class="font-semibold text-gray-900">4. Payment / Pembayaran</div>
                            <div class="text-[10px] text-gray-500 font-normal">Proses DP / termin pembayaran supplier</div>
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.average.payment" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.max.payment" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                    </tr>
                    <!-- 5. PO (Terbit PO Resmi) -->
                    <tr class="hover:bg-gray-50/80 transition">
                        <td class="py-2.5 px-3 font-sans">
                            <div class="font-semibold text-gray-900">5. PO (Terbit PO Resmi)</div>
                            <div class="text-[10px] text-gray-500 font-normal">Tanggal PO resmi terbit ke vendor</div>
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.average.po" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.max.po" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                    </tr>
                    <!-- 6. Pengemasan / Produksi Vendor -->
                    <tr class="hover:bg-gray-50/80 transition">
                        <td class="py-2.5 px-3 font-sans">
                            <div class="font-semibold text-gray-900">6. Pengemasan / Produksi Vendor</div>
                            <div class="text-[10px] text-gray-500 font-normal">Durasi pabrikasi & packaging vendor</div>
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.average.pengemasan" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.max.pengemasan" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                    </tr>
                    <!-- 7. Pengiriman / Ekspedisi -->
                    <tr class="hover:bg-gray-50/80 transition">
                        <td class="py-2.5 px-3 font-sans">
                            <div class="font-semibold text-gray-900">7. Pengiriman / Ekspedisi</div>
                            <div class="text-[10px] text-gray-500 font-normal">Waktu tempuh transit & ekspedisi pengiriman</div>
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.average.pengiriman" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.max.pengiriman" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                    </tr>
                    <!-- 8. Unloading & Bongkar Muat -->
                    <tr class="hover:bg-gray-50/80 transition">
                        <td class="py-2.5 px-3 font-sans">
                            <div class="font-semibold text-gray-900">8. Unloading & Bongkar Muat</div>
                            <div class="text-[10px] text-gray-500 font-normal">Bongkar muat fisik dari armada pengangkut</div>
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.average.unloading" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.max.unloading" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                    </tr>
                    <!-- 9. Input Sistem / QC Receiving -->
                    <tr class="hover:bg-gray-50/80 transition">
                        <td class="py-2.5 px-3 font-sans">
                            <div class="font-semibold text-gray-900">9. Input Sistem / QC Receiving</div>
                            <div class="text-[10px] text-gray-500 font-normal">Pengecekan QC fisik & input penerimaan ke sistem</div>
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.average.input" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                        <td class="py-2.5 px-3 text-center">
                            <input type="number" min="0" x-model.number="stageForm.max.input" class="w-24 text-center px-2 py-1 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-semibold shadow-xs">
                        </td>
                    </tr>
                    <!-- Subtotal Baris -->
                    <tr class="bg-gray-100 font-bold border-t-2 border-gray-300 text-gray-900">
                        <td class="py-2.5 px-3 font-sans">TOTAL LEAD TIME TAHAP:</td>
                        <td class="py-2.5 px-3 text-center text-gray-900" x-text="stageSum('average') + ' Hari'"></td>
                        <td class="py-2.5 px-3 text-center text-gray-900" x-text="stageSum('max') + ' Hari'"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Section Tambahan Buffer Hari (LEAD_TIME_LOKAL 2.2) --}}
        <div class="p-3.5 bg-gray-50 rounded-sm border border-gray-200 space-y-3">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <label class="text-xs font-semibold text-gray-800">
                        Tambahan Buffer Lead Time (Hari)
                    </label>
                    <p class="text-[11px] text-gray-500 mt-0.5">Buffer hari tambahan untuk mengantisipasi ketidakpastian pengiriman atau vendor peak season.</p>
                </div>
                <div class="w-32 shrink-0">
                    <input type="number" min="0" x-model.number="stageForm.tambahan_buffer_hari" class="w-full text-right px-3 py-1.5 text-xs rounded-sm border border-gray-300 bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono font-bold text-gray-900 shadow-xs">
                </div>
            </div>

            {{-- Live Calculation Preview Card (AdminLTE KPI Grid) --}}
            <div class="grid grid-cols-3 gap-2 pt-2.5 border-t border-gray-200 text-xs">
                <div class="p-2.5 bg-white rounded-sm border border-gray-200 shadow-2xs text-center">
                    <div class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider">Total Avg Lead Time</div>
                    <div class="text-base font-bold font-mono text-gray-900 mt-0.5" x-text="stageSum('average') + ' Hari'"></div>
                </div>
                <div class="p-2.5 bg-white rounded-sm border border-gray-200 shadow-2xs text-center">
                    <div class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider">Total Max Lead Time</div>
                    <div class="text-base font-bold font-mono text-gray-900 mt-0.5" x-text="stageSum('max') + ' Hari'"></div>
                </div>
                <div class="p-2.5 bg-white rounded-sm border border-gray-200 shadow-2xs text-center">
                    <div class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider">Safety Stock (Hari)</div>
                    <div class="text-base font-bold font-mono text-primary-700 mt-0.5" x-text="stageSafetyStock() + ' Hari'"></div>
                    <div class="text-[10px] text-gray-400 font-sans mt-0.5">(Max - Avg) + Buffer</div>
                </div>
            </div>
        </div>

        {{-- Action Buttons Footer --}}
        <div class="pt-3 border-t border-gray-200 flex justify-end gap-2">
            <button type="button" @click="showStageModal = false" class="px-3.5 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-sm hover:bg-gray-50 shadow-xs cursor-pointer">
                Batal
            </button>
            <button type="submit" :disabled="savingStage" class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-semibold text-white bg-primary-600 hover:bg-primary-700 rounded-sm shadow-xs transition disabled:opacity-50 cursor-pointer">
                <template x-if="savingStage">
                    <svg class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </template>
                <span x-text="savingStage ? 'Menyimpan & Menghitung...' : 'Simpan & Hitung Ulang'"></span>
            </button>
        </div>
    </form>
</x-modal>

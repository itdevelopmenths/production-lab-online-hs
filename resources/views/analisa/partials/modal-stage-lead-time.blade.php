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
                        <span class="tabular-nums text-[#667085] dark:text-gray-400" x-text="' (' + stageForm.sku + ')'"></span>
                        <template x-if="stageForm.supplier_nama && stageForm.supplier_nama !== '-'">
                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium bg-gray-100 dark:bg-gray-800 text-[#101828] dark:text-gray-200 border border-[#E8E8E8] dark:border-gray-700" x-text="stageForm.supplier_nama"></span>
                        </template>
                    </p>
                </div>
            </div>
            <button type="button" @click="showStageModal = false" class="text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white transition cursor-pointer p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-[6px]">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <form @submit.prevent="saveStages()" class="bg-[#F5F5F5] dark:bg-gray-950">
        <div class="p-5 sm:p-6 space-y-5 max-h-[70vh] overflow-y-auto custom-scrollbar">
            <p x-show="stageForm.tanpa_rincian" class="p-3 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 text-xs text-[#667085] dark:text-gray-400">
                Lead time saat ini (<span class="font-medium tabular-nums text-[#101828] dark:text-white" x-text="formatLt(stageSum('average')) + ' hari'"></span>) belum dirinci per tahap, jadi sementara diisi di <span class="font-medium text-[#101828] dark:text-white">Pengiriman &amp; Logistik</span>. Pecah ke tahap yang sesuai lalu simpan.
            </p>
            <template x-if="stageForm.sumber_lt === 'otomatis'">
                <div role="alert" class="p-4 rounded-[10px] border border-amber-300 border-l-4 border-l-amber-500 dark:border-amber-500/40 dark:border-l-amber-500 bg-amber-50 dark:bg-amber-500/10 text-xs text-amber-900 dark:text-amber-100 leading-relaxed flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-start gap-2.5">
                        <svg class="size-4 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        <div class="space-y-1">
                            <p class="text-[13px] font-semibold">Angka di bawah belum dipakai: bahan ini sudah punya <span x-text="stageForm.jumlah_transaksi"></span> PO</p>
                            <p>Lead time, safety stock, dan rekomendasi order saat ini dihitung <strong class="font-semibold">otomatis dari histori PO</strong>, bukan dari angka yang Anda isi di sini. Perubahan 8 tahap hanya disimpan sebagai <strong class="font-semibold">cadangan manual</strong> dan baru berlaku jika supplier diganti ke vendor tanpa histori PO.</p>
                            <p><strong class="font-semibold">Tambahan Buffer (hari)</strong> tetap berlaku dan langsung memengaruhi safety stock.</p>
                        </div>
                    </div>
                    <template x-if="stageForm.auto_average">
                        <button type="button" @click="copyAutoStages()" class="shrink-0 px-3 py-1.5 font-medium text-xs transition cursor-pointer btn-glossy rounded-[6px]">
                            Gunakan Nilai Sistem (PO Otomatis)
                        </button>
                    </template>
                </div>
            </template>

            {{-- Tabel Matriks Input Tahapan Lead Time (8 Tahapan) --}}
            <div class="overflow-x-auto border border-[#E8E8E8] dark:border-gray-800 rounded-[12px] bg-white dark:bg-gray-900">
                <table class="w-full text-xs text-left border-collapse">
                    <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[12px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                        <tr>
                            <th class="py-3 px-4">Tahapan Lead Time (8 Tahap)</th>
                            <th class="py-3 px-4 text-center w-36 font-medium">Skenario Average (Hari)</th>
                            <th class="py-3 px-4 text-center w-36 font-medium">Skenario Max (Hari)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 tabular-nums bg-white dark:bg-gray-900">
                        <!-- 1. Perencanaan & Pengajuan PO -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">1. Perencanaan &amp; Pengajuan PO</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Perencanaan kebutuhan & pengajuan PO ke manager</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.perencanaan" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.perencanaan" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                        </tr>
                        <!-- 2. Konfirmasi Supplier -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">2. Konfirmasi Supplier</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Konfirmasi kesanggupan & ketersediaan supplier</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.supplier_confirm" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.supplier_confirm" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                        </tr>
                        <!-- 3. Persetujuan Manager -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">3. Persetujuan Manager</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Waktu persetujuan PO oleh manager</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.approval" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.approval" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                        </tr>
                        <!-- 4. Pembayaran / DP -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">4. Pembayaran / DP</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Proses DP / termin pembayaran supplier</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.payment" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.payment" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                        </tr>
                        <!-- 5. Penerbitan PO Resmi -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">5. Penerbitan PO Resmi</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Tanggal PO resmi terbit ke vendor</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.po" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.po" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                        </tr>
                        <!-- 6. Pengiriman &amp; Logistik -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">6. Pengiriman &amp; Logistik</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Pengemasan vendor, ekspedisi & transit pengiriman</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.pengiriman" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.pengiriman" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                        </tr>
                        <!-- 7. Unloading di Gudang -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">7. Unloading di Gudang</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Bongkar muat fisik dari armada pengangkut</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.unloading" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.unloading" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                        </tr>
                        <!-- 8. Input Sistem &amp; Posting Stok -->
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-medium text-[#101828] dark:text-white">8. Input Sistem &amp; Posting Stok</div>
                                <div class="text-[10px] text-[#667085] dark:text-gray-400 font-normal">Verifikasi penerimaan & posting stok ke sistem</div>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.average.input" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <input type="number" step="0.1" min="0" x-model.number="stageForm.max.input" class="w-24 text-center px-2.5 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                            </td>
                        </tr>
                        <!-- Subtotal Baris -->
                        <tr class="bg-[#fafafa] dark:bg-gray-800/60 font-medium border-t-2 border-[#E8E8E8] dark:border-gray-700 text-[#101828] dark:text-white">
                            <td class="py-3 px-4 font-sans">TOTAL LEAD TIME TAHAP:</td>
                            <td class="py-3 px-4 text-center tabular-nums" x-text="formatLt(stageSum('average')) + ' Hari'"></td>
                            <td class="py-3 px-4 text-center tabular-nums" x-text="formatLt(stageSum('max')) + ' Hari'"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Section Tambahan Buffer Hari (LEAD_TIME_LOKAL 2.2) --}}
            <div class="p-4 bg-white dark:bg-gray-900 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 space-y-4">
                <p x-show="stageForm.tipe === 'impor'" class="text-xs text-[#667085] dark:text-gray-400">
                    Buffer Days impor mengikuti Klasifikasi ABC (<span class="font-medium tabular-nums text-[#101828] dark:text-white" x-text="'+' + formatLt(stageForm.tambahan_buffer_hari) + ' hari'"></span>); ubah kelas ABC di tab Analisa.
                </p>
                <div x-show="stageForm.tipe !== 'impor'" class="flex items-center justify-between gap-4">
                    <div>
                        <label class="text-xs font-medium text-[#101828] dark:text-white">
                            Tambahan Buffer Lead Time (Hari)
                        </label>
                        <p class="text-[11px] text-[#667085] dark:text-gray-400 mt-0.5">Buffer hari tambahan untuk mengantisipasi ketidakpastian pengiriman atau vendor peak season.</p>
                    </div>
                    <div class="w-32 shrink-0">
                        <input type="number" step="0.1" min="0" x-model.number="stageForm.tambahan_buffer_hari" class="w-full text-right px-3 py-1.5 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white tabular-nums font-medium text-[#101828] dark:text-white outline-none rounded-[6px]">
                    </div>
                </div>

                {{-- Live Calculation Preview Card --}}
                <div class="grid grid-cols-3 gap-3 pt-3 border-t border-[#E8E8E8] dark:border-gray-800 text-xs">
                    <div class="p-3 bg-[#F5F5F5] dark:bg-gray-950 rounded-lg border border-[#E8E8E8] dark:border-gray-800 text-center">
                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-medium uppercase tracking-wider">Total Avg Lead Time</div>
                        <div class="text-base font-medium tabular-nums text-[#101828] dark:text-white mt-0.5" x-text="formatLt(stageSum('average')) + ' Hari'"></div>
                    </div>
                    <div class="p-3 bg-[#F5F5F5] dark:bg-gray-950 rounded-lg border border-[#E8E8E8] dark:border-gray-800 text-center">
                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-medium uppercase tracking-wider">Total Max Lead Time</div>
                        <div class="text-base font-medium tabular-nums text-[#101828] dark:text-white mt-0.5" x-text="formatLt(stageSum('max')) + ' Hari'"></div>
                    </div>
                    <div class="p-3 bg-[#F5F5F5] dark:bg-gray-950 rounded-lg border border-[#E8E8E8] dark:border-gray-800 text-center">
                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-medium uppercase tracking-wider">Safety Stock (Hari)</div>
                        <div class="text-base font-medium tabular-nums text-[#101828] dark:text-white mt-0.5" x-text="formatLt(stageSafetyStock()) + ' Hari'"></div>
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

{{-- Modal Tambah / Pindah SKU ke Analisa Stok (Lokal atau Impor + Klasifikasi ABC) --}}
<x-modal name="showAddSkuModal" maxWidth="2xl">
    <x-slot:header>
        <div class="px-6 py-4 border-b border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="size-9 rounded-[10px] border border-[#E8E8E8] dark:border-gray-700 bg-[#fafafa] dark:bg-gray-800 text-[#111111] dark:text-white flex items-center justify-center shrink-0">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-[#101828] dark:text-white tracking-tight">Tambah SKU ke Analisa Stok</h3>
                    <p class="text-xs text-[#667085] dark:text-gray-400 mt-0.5">Pilih SKU Bahan Baku / Kemasan, tentukan masuk ke Analisa Impor atau Lokal, serta Klasifikasi ABC-nya.</p>
                </div>
            </div>
            <button type="button" @click="showAddSkuModal = false" class="text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white transition cursor-pointer p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <form @submit.prevent="submitAddSku()" class="bg-[#F5F5F5] dark:bg-gray-950 text-xs">
        <div class="p-5 sm:p-6 space-y-5 max-h-[72vh] overflow-y-auto custom-scrollbar">
            {{-- 1. Pilih SKU --}}
            <div class="space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                    <label class="font-semibold text-[#101828] dark:text-white">1. Pilih SKU Bahan Baku / Kemasan <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        x-model.debounce.250ms="addSkuSearch"
                        @input="fetchAvailableAnalisaSkus()"
                        placeholder="Cari kode SKU atau nama..."
                        class="px-3 py-1.5 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-[#101828] dark:text-white w-56 focus:border-[#111111] dark:focus:border-white outline-none"
                    >
                </div>

                <div class="max-h-44 overflow-y-auto border border-[#E8E8E8] dark:border-gray-800 rounded-[10px] bg-white dark:bg-gray-900 divide-y divide-[#E8E8E8] dark:divide-gray-800 custom-scrollbar">
                    <template x-if="loadingAvailableAnalisaSkus">
                        <div class="py-6 text-center text-[#667085] dark:text-gray-400">Memuat daftar SKU...</div>
                    </template>
                    <template x-if="!loadingAvailableAnalisaSkus && availableAnalisaSkus.length === 0">
                        <div class="py-6 text-center text-[#667085] dark:text-gray-400">Tidak ada SKU bahan/kemas aktif yang ditemukan.</div>
                    </template>
                    <template x-for="item in availableAnalisaSkus" :key="item.id">
                        <div
                            @click="selectSkuToAdd(item)"
                            :class="String(addSkuForm.produk_id) === String(item.id) ? 'bg-gray-100/80 dark:bg-gray-800/80 border-l-2 border-l-[#111111] dark:border-l-white' : 'hover:bg-[#fafafa] dark:hover:bg-gray-800/40'"
                            class="flex items-center justify-between gap-3 px-3.5 py-2.5 cursor-pointer transition"
                        >
                            <div class="min-w-0">
                                <div class="font-semibold text-[#101828] dark:text-white truncate" x-text="item.nama"></div>
                                <div class="font-mono text-[10px] text-[#667085] dark:text-gray-400" x-text="item.sku + ' · ' + item.satuan + ' · ' + item.tipe"></div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium border"
                                      :class="item.is_impor ? 'bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800/50' : (item.profil_analisa === 'lokal' ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/50' : 'bg-gray-100 dark:bg-gray-800 text-[#667085] dark:text-gray-300 border-[#E8E8E8] dark:border-gray-700')"
                                      x-text="item.is_impor ? 'Profil: Impor' : (item.profil_analisa === 'lokal' ? 'Profil: Lokal' : 'Belum Diset')"></span>
                                <template x-if="item.klasifikasi_abc_kode">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-gray-100 dark:bg-gray-800 text-[#101828] dark:text-gray-200 border border-[#E8E8E8] dark:border-gray-700" x-text="'ABC: ' + item.klasifikasi_abc_kode"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <template x-if="addSkuForm.produk_id">
                    <div class="p-3 rounded-[10px] bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <span class="text-[#667085] dark:text-gray-400">SKU Terpilih:</span>
                            <strong class="text-[#101828] dark:text-white ml-1" x-text="addSkuForm.selectedNama"></strong>
                            <span class="font-mono text-[#667085] dark:text-gray-400 ml-1" x-text="'(' + addSkuForm.selectedSku + ')'"></span>
                        </div>
                        <div class="flex items-center gap-3 text-[11px] font-mono text-[#667085] dark:text-gray-400">
                            <span x-show="addSkuForm.current_stok_aktual !== null && addSkuForm.current_stok_aktual !== undefined">
                                Stok: <strong class="text-[#101828] dark:text-white" x-text="formatNumber(addSkuForm.current_stok_aktual) + ' ' + (addSkuForm.selectedSatuan || '')"></strong>
                            </span>
                            <span x-show="addSkuForm.current_hpp !== null && addSkuForm.current_hpp !== undefined">
                                HPP: <strong class="text-[#101828] dark:text-white" x-text="formatRupiah(addSkuForm.current_hpp)"></strong>
                            </span>
                        </div>
                    </div>
                </template>
            </div>

            {{-- 2. Pilih Masuk ke Analisa Impor atau Lokal --}}
            <div class="space-y-2.5">
                <label class="font-semibold text-[#101828] dark:text-white block">2. Masuk ke Profil Analisa <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label
                        :class="addSkuForm.target_profil === 'impor' ? 'border-[#111111] dark:border-white bg-white dark:bg-gray-900 ring-1 ring-[#111111] dark:ring-white' : 'border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/40'"
                        class="flex items-start gap-2.5 p-3.5 rounded-[10px] border cursor-pointer transition"
                    >
                        <input type="radio" name="add_sku_target_profil" value="impor" x-model="addSkuForm.target_profil" class="mt-0.5 text-[#111111] dark:text-white focus:ring-0">
                        <div>
                            <div class="font-semibold text-[#101828] dark:text-white">Bahan Baku Impor</div>
                            <p class="text-[11px] text-[#667085] dark:text-gray-400 mt-0.5">Menggunakan Klasifikasi ABC (+4/+2/+0 hari buffer), ADU ETA, dan Lead Time Impor.</p>
                        </div>
                    </label>
                    <label
                        :class="addSkuForm.target_profil === 'lokal' ? 'border-[#111111] dark:border-white bg-white dark:bg-gray-900 ring-1 ring-[#111111] dark:ring-white' : 'border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/40'"
                        class="flex items-start gap-2.5 p-3.5 rounded-[10px] border cursor-pointer transition"
                    >
                        <input type="radio" name="add_sku_target_profil" value="lokal" x-model="addSkuForm.target_profil" class="mt-0.5 text-[#111111] dark:text-white focus:ring-0">
                        <div>
                            <div class="font-semibold text-[#101828] dark:text-white">Bahan Baku Lokal</div>
                            <p class="text-[11px] text-[#667085] dark:text-gray-400 mt-0.5">Menggunakan 9 tahap Lead Time Lokal, ADU 4 bulan, dan buffer manual tanpa Klasifikasi ABC.</p>
                        </div>
                    </label>
                </div>

                <template x-if="addSkuForm.produk_id && addSkuForm.currentIsOpposite()">
                    <div class="p-3 rounded-[10px] border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-950/30 text-amber-900 dark:text-amber-200 text-[11px]">
                        Perhatian: SKU ini saat ini berada pada profil berbeda dan akan dipindahkan ke <strong x-text="addSkuForm.target_profil === 'impor' ? 'Analisa Impor' : 'Analisa Lokal'"></strong> (data working table profil lama akan dibersihkan agar tidak ganda).
                    </div>
                </template>
            </div>

            {{-- 3a. Parameter Analisa Impor & Klasifikasi ABC --}}
            <div x-show="addSkuForm.target_profil === 'impor'" class="space-y-3 p-4 rounded-[10px] bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-gray-800">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="font-semibold text-[#101828] dark:text-white block mb-1">Klasifikasi ABC <span class="text-rose-500">*</span></label>
                        <select x-model="addSkuForm.klasifikasi_abc_id" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 text-[#101828] dark:text-white font-semibold focus:border-[#111111] dark:focus:border-white outline-none">
                            <option value="">— Pilih Klasifikasi ABC —</option>
                            <template x-for="abc in klasifikasiAbcList" :key="abc.id">
                                <option :value="abc.id" x-text="abc.nama + ' (+' + abc.tambahan_buffer_hari + ' Hari)'"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="font-semibold text-[#101828] dark:text-white block mb-1">Total OUT (Opsional)</label>
                        <input type="number" step="0.01" min="0" x-model.number="addSkuForm.out" placeholder="0" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 font-mono text-[#101828] dark:text-white outline-none focus:border-[#111111] dark:focus:border-white">
                    </div>
                    <div>
                        <label class="font-semibold text-[#101828] dark:text-white block mb-1">Review Period (Hari)</label>
                        <input type="number" min="1" x-model.number="addSkuForm.review_period_impor" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 font-mono text-[#101828] dark:text-white outline-none focus:border-[#111111] dark:focus:border-white">
                    </div>
                    <div>
                        <label class="font-semibold text-[#101828] dark:text-white block mb-1">Lead Time Avg (Hari, Opsional)</label>
                        <input type="number" step="0.1" min="0" x-model.number="addSkuForm.lead_time_average" placeholder="Otomatis / 45" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 font-mono text-[#101828] dark:text-white outline-none focus:border-[#111111] dark:focus:border-white">
                    </div>
                    <div>
                        <label class="font-semibold text-[#101828] dark:text-white block mb-1">Lead Time Max (Hari, Opsional)</label>
                        <input type="number" step="0.1" min="0" x-model.number="addSkuForm.lead_time_max" placeholder="Otomatis / 60" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 font-mono text-[#101828] dark:text-white outline-none focus:border-[#111111] dark:focus:border-white">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="font-semibold text-[#101828] dark:text-white block mb-1">Harga per Satuan (Rp, Opsional)</label>
                        <input type="number" step="0.01" min="0" x-model.number="addSkuForm.harga_per_satuan" placeholder="Ikut harga HPP produk" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 font-mono text-[#101828] dark:text-white outline-none focus:border-[#111111] dark:focus:border-white">
                        <div class="mt-1.5 flex items-center justify-between text-xs text-[#667085] dark:text-gray-400" x-show="addSkuForm.current_hpp !== null && addSkuForm.current_hpp !== undefined">
                            <span class="inline-flex items-center gap-1 font-mono text-[11px]">
                                <span>HPP Sistem:</span>
                                <span class="font-medium text-[#101828] dark:text-white" x-text="formatRupiah(addSkuForm.current_hpp)"></span>
                            </span>
                            <button type="button"
                                    @click="addSkuForm.harga_per_satuan = addSkuForm.current_hpp"
                                    class="text-[#111111] dark:text-white underline font-medium text-[11px] transition-colors focus:outline-none cursor-pointer">
                                Gunakan Nilai Sistem
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3b. Parameter Analisa Lokal --}}
            <div x-show="addSkuForm.target_profil === 'lokal'" class="space-y-3 p-4 rounded-[10px] bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-gray-800">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="font-semibold text-[#101828] dark:text-white block mb-1">Terjual 4 Bulan (Opsional)</label>
                        <input type="number" step="0.01" min="0" x-model.number="addSkuForm.terjual_rata_rata_4bulan" placeholder="0" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 font-mono text-[#101828] dark:text-white outline-none focus:border-[#111111] dark:focus:border-white">
                    </div>
                    <div>
                        <label class="font-semibold text-[#101828] dark:text-white block mb-1">Review Period (Hari)</label>
                        <input type="number" min="1" x-model.number="addSkuForm.review_period_lokal" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 font-mono text-[#101828] dark:text-white outline-none focus:border-[#111111] dark:focus:border-white">
                    </div>
                    <div>
                        <label class="font-semibold text-[#101828] dark:text-white block mb-1">Tambahan Buffer (Hari, Opsional)</label>
                        <input type="number" step="0.1" min="0" x-model.number="addSkuForm.tambahan_buffer_hari" placeholder="0" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 font-mono text-[#101828] dark:text-white outline-none focus:border-[#111111] dark:focus:border-white">
                    </div>
                    <div>
                        <label class="font-semibold text-[#101828] dark:text-white block mb-1">Harga Satuan (Rp, Opsional)</label>
                        <input type="number" step="0.01" min="0" x-model.number="addSkuForm.harga_per_satuan" placeholder="Ikut harga HPP produk" class="w-full px-3 py-2 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 font-mono text-[#101828] dark:text-white outline-none focus:border-[#111111] dark:focus:border-white">
                        <div class="mt-1.5 flex items-center justify-between text-xs text-[#667085] dark:text-gray-400" x-show="addSkuForm.current_hpp !== null && addSkuForm.current_hpp !== undefined">
                            <span class="inline-flex items-center gap-1 font-mono text-[11px]">
                                <span>HPP Sistem:</span>
                                <span class="font-medium text-[#101828] dark:text-white" x-text="formatRupiah(addSkuForm.current_hpp)"></span>
                            </span>
                            <button type="button"
                                    @click="addSkuForm.harga_per_satuan = addSkuForm.current_hpp"
                                    class="text-[#111111] dark:text-white underline font-medium text-[11px] transition-colors focus:outline-none cursor-pointer">
                                Gunakan Nilai Sistem
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-gray-800 flex justify-end gap-2.5">
            <x-button type="button" @click="showAddSkuModal = false" variant="secondary" size="sm">
                Batal
            </x-button>
            <x-button type="submit" ::disabled="!addSkuForm.produk_id || savingAddSku || (addSkuForm.target_profil === 'impor' && !addSkuForm.klasifikasi_abc_id)" variant="primary" size="sm">
                <span x-text="savingAddSku ? 'Menyimpan...' : 'Simpan ke Analisa'"></span>
            </x-button>
        </div>
    </form>
</x-modal>

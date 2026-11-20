{{-- Modal: Tambah / Edit Analisa Produk Jadi Fulfillment (parameter per gudang) --}}
<x-modal name="showFulfillmentModal" maxWidth="4xl">
    <x-slot:header>
        <div class="px-6 py-4 border-b border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-[#101828] dark:text-white tracking-tight" x-text="ffForm.mode === 'edit' ? 'Edit Analisa Fulfillment' : 'Tambah Produk ke Analisa Fulfillment'"></h3>
                <p class="text-xs text-[#667085] dark:text-gray-400 mt-0.5" x-show="ffForm.produk">
                    <span class="font-medium text-[#101828] dark:text-gray-200" x-text="ffForm.produk?.nama"></span>
                    <span class="tabular-nums" x-text="' (' + (ffForm.produk?.sku || '') + ') · Kelipatan batch ' + formatNumber(ffForm.produk?.moq || 1)"></span>
                </p>
            </div>
            <button type="button" @click="showFulfillmentModal = false" class="text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white transition cursor-pointer p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-[6px]" aria-label="Tutup">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <form @submit.prevent="saveFulfillment()" class="bg-[#F5F5F5] dark:bg-gray-950">
        <div class="p-5 sm:p-6 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar text-xs">

            {{-- Langkah 1: pilih produk (hanya mode tambah) --}}
            <div x-show="ffForm.mode === 'add'" class="p-4 bg-white dark:bg-gray-900 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800">
                <label class="font-medium text-[#101828] dark:text-white block mb-2">Produk Jadi</label>
                <input type="search" x-model="ffForm.q" @input.debounce.300ms="searchFulfillmentProduk()" placeholder="Cari SKU atau nama produk jadi…" class="w-full px-3 py-2 text-xs border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white outline-none rounded-[6px]">
                <div class="mt-2 max-h-48 overflow-y-auto custom-scrollbar divide-y divide-[#E8E8E8] dark:divide-gray-800 border border-[#E8E8E8] dark:border-gray-800 rounded-[6px]">
                    <template x-for="p in ffForm.options" :key="p.id">
                        <button type="button" @click="pickFulfillmentProduk(p)" class="w-full flex items-center justify-between gap-3 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer"
                            :class="ffForm.produk?.id === p.id ? 'bg-gray-100 dark:bg-gray-800' : ''">
                            <span>
                                <span class="font-medium text-[#101828] dark:text-white" x-text="p.nama"></span>
                                <span class="tabular-nums text-[#667085] dark:text-gray-400" x-text="' · ' + p.sku"></span>
                            </span>
                            <span x-show="p.di_analisa" class="shrink-0 px-2 py-0.5 rounded-md text-[10px] font-medium bg-gray-100 dark:bg-gray-800 text-[#667085] dark:text-gray-300 border border-[#E8E8E8] dark:border-gray-700">Sudah di analisa</span>
                        </button>
                    </template>
                    <div x-show="ffForm.options.length === 0" class="px-3 py-4 text-center text-[#667085] dark:text-gray-400">Tidak ada produk jadi yang cocok.</div>
                </div>
            </div>

            {{-- Langkah 2: parameter per gudang --}}
            <div x-show="ffForm.produk" class="bg-white dark:bg-gray-900 rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 overflow-hidden">
                <div class="px-4 py-3 border-b border-[#E8E8E8] dark:border-gray-800">
                    <div class="font-medium text-[#101828] dark:text-white">Parameter per Gudang Fulfillment</div>
                    <p class="text-[11px] text-[#667085] dark:text-gray-400 mt-0.5">Centang gudang yang ikut dihitung. Stok &amp; Akan Datang diambil otomatis (stok fisik, transfer in-transit, rencana batch aktif).</p>
                </div>
                <div x-show="ffForm.loadingDetail" class="px-4 py-6 text-center text-[#667085] dark:text-gray-400">Memuat data gudang…</div>
                <div x-show="!ffForm.loadingDetail" class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead class="bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Gudang</th>
                                <th class="px-3 py-2 text-right font-medium" title="Rata-rata terjual per bulan selama 4 bulan terakhir">Terjual/bln (4 bln)</th>
                                <th class="px-3 py-2 text-right font-medium">Lead Time (hr)</th>
                                <th class="px-3 py-2 text-right font-medium">Buffer (hr)</th>
                                <th class="px-3 py-2 text-right font-medium">Review (hr)</th>
                                <th class="px-3 py-2 text-right font-medium">Stok</th>
                                <th class="px-3 py-2 text-right font-medium">Akan Datang</th>
                                <th class="px-3 py-2 text-right font-medium">Target</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                            <template x-for="g in ffForm.gudang" :key="g.gudang_id">
                                <tr :class="g.aktif ? '' : 'opacity-50'">
                                    <td class="px-3 py-2">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" x-model="g.aktif" class="rounded border-[#E8E8E8] dark:border-gray-700">
                                            <span class="font-medium text-[#101828] dark:text-white" x-text="g.nama"></span>
                                            <span x-show="g.is_pusat" class="px-1.5 py-0.5 rounded text-[10px] bg-gray-100 dark:bg-gray-800 text-[#667085] dark:text-gray-300">Pusat</span>
                                        </label>
                                    </td>
                                    <template x-for="field in ['terjual_rata_rata_4bulan', 'lead_time_distribusi', 'buffer_distribusi', 'review_period']" :key="field">
                                        <td class="px-2 py-2">
                                            <input type="number" min="0" :step="field === 'terjual_rata_rata_4bulan' ? '0.01' : '1'" x-model.number="g[field]" :disabled="!g.aktif"
                                                class="w-24 ml-auto block px-2 py-1.5 text-right text-xs tabular-nums border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-950 focus:border-[#111111] dark:focus:border-white text-[#101828] dark:text-white outline-none rounded-[6px] disabled:bg-gray-50 dark:disabled:bg-gray-900">
                                        </td>
                                    </template>
                                    <td class="px-3 py-2 text-right tabular-nums text-[#101828] dark:text-gray-200" x-text="formatNumber(g.stok_saat_ini)"></td>
                                    <td class="px-3 py-2 text-right tabular-nums text-[#101828] dark:text-gray-200" x-text="formatNumber(g.akan_datang)"></td>
                                    <td class="px-3 py-2 text-right tabular-nums font-medium text-[#101828] dark:text-white" x-text="g.aktif ? formatNumber(ffGudangTarget(g)) : '-'"></td>
                                </tr>
                            </template>
                            <tr x-show="ffForm.gudang.length === 0">
                                <td colspan="8" class="px-3 py-4 text-center text-[#667085] dark:text-gray-400">Belum ada gudang bertipe Fulfillment yang aktif.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Simulasi level ALL --}}
            <div x-show="ffForm.produk && !ffForm.loadingDetail" class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                <template x-for="s in ffSimulasi()" :key="s.label">
                    <div class="p-3 bg-white dark:bg-gray-900 rounded-lg border border-[#E8E8E8] dark:border-gray-800 text-center">
                        <div class="text-[10px] text-[#667085] dark:text-gray-400 font-medium uppercase tracking-wider" x-text="s.label"></div>
                        <div class="tabular-nums font-medium text-[#101828] dark:text-white text-xs mt-1" x-text="s.value"></div>
                    </div>
                </template>
            </div>
            <p x-show="ffForm.produk" class="text-[11px] text-[#667085] dark:text-gray-400">
                ADU = Terjual / 30 (tanpa pembulatan). Batas Min = ADU × (Lead Time + Buffer). Target = ADU × (Lead Time + Buffer + Review).
                ORDER bila Stok + Akan Datang ≤ Target; Qty dibulatkan ke atas ke kelipatan batch (MOQ di Master Produk).
            </p>
        </div>

        <div class="px-6 py-4 bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-gray-800 flex justify-end gap-2.5">
            <x-button type="button" @click="showFulfillmentModal = false" variant="secondary" size="sm">Batal</x-button>
            <x-button type="submit" ::disabled="savingFulfillment || !ffForm.produk || ffForm.loadingDetail" variant="primary" size="sm">
                <span x-text="savingFulfillment ? 'Menyimpan…' : 'Simpan'"></span>
            </x-button>
        </div>
    </form>
</x-modal>

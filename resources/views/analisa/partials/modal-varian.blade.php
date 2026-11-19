{{-- Modal Detail Varian Impor --}}
<x-modal name="showVarianModal" maxWidth="2xl">
    <x-slot:header>
        <div class="px-6 py-4 border-b border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-[#101828] dark:text-white tracking-tight" x-text="'Distribusi Varian: ' + (activeVarianProduct ? activeVarianProduct.nama : '')"></h3>
                <p class="text-xs text-[#667085] dark:text-gray-400 tabular-nums mt-0.5" x-text="activeVarianProduct ? activeVarianProduct.sku : ''"></p>
            </div>
            <button type="button" @click="showVarianModal = false" class="text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white transition cursor-pointer p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <div class="p-6 max-h-[60vh] overflow-y-auto bg-[#F5F5F5] dark:bg-gray-950 custom-scrollbar">
        <div class="rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 overflow-hidden bg-white dark:bg-gray-900">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 font-medium border-b border-[#E8E8E8] dark:border-gray-800 text-[13px]">
                    <tr>
                        <th class="py-3 px-4">Varian</th>
                        <th class="py-3 px-4 text-right font-medium">ADU</th>
                        <th class="py-3 px-4 text-right font-medium">Stok Fisik</th>
                        <th class="py-3 px-4 text-right font-medium">Proyeksi</th>
                        <th class="py-3 px-4 text-right font-medium text-[#101828] dark:text-white bg-gray-100/70 dark:bg-gray-800">Rekomendasi Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 bg-white dark:bg-gray-900">
                    <template x-if="activeVarianProduct && activeVarianProduct.varian">
                        <template x-for="v in activeVarianProduct.varian" :key="v.nama_varian || v.sku_varian || Math.random()">
                            <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition">
                                <td class="py-2.5 px-4 font-medium text-[#101828] dark:text-white" x-text="v.nama_varian || v.nama || v.sku_varian || '-'"></td>
                                <td class="py-2.5 px-4 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatNumber(v.adu)"></td>
                                <td class="py-2.5 px-4 text-right tabular-nums text-[#101828] dark:text-white" x-text="formatNumber(v.stok)"></td>
                                <td class="py-2.5 px-4 text-right tabular-nums text-[#101828] dark:text-white" x-text="formatNumber(v.proyeksi)"></td>
                                <td class="py-2.5 px-4 text-right tabular-nums font-medium text-[#101828] dark:text-white bg-[#fafafa] dark:bg-gray-800/40" x-text="formatNumber(v.rekomendasi_order || v.qty_order || 0)"></td>
                            </tr>
                        </template>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="px-6 py-3.5 bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-gray-800 flex justify-end">
        <x-button type="button" @click="showVarianModal = false" variant="secondary" size="sm">
            Tutup
        </x-button>
    </div>
</x-modal>

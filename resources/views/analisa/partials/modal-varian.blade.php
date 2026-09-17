{{-- Modal Detail Varian Impor --}}
{{-- Follows AdminLTE Modal Standard & SOLID Principles --}}
<x-modal name="showVarianModal" maxWidth="2xl" variant="primary">
    <x-slot:header>
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-gray-800 tracking-tight" x-text="'Distribusi Varian: ' + (activeVarianProduct ? activeVarianProduct.nama : '')"></h3>
                <p class="text-xs text-gray-500 font-mono mt-0.5" x-text="activeVarianProduct ? activeVarianProduct.sku : ''"></p>
            </div>
            <button type="button" @click="showVarianModal = false" class="text-gray-400 hover:text-gray-600 transition cursor-pointer p-1 rounded-sm hover:bg-gray-200/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <div class="max-h-[60vh] overflow-y-auto">
        <table class="w-full text-xs text-left border border-gray-200 border-collapse">
            <thead class="bg-gray-100 text-gray-700 font-semibold border-b border-gray-200 text-[11px]">
                <tr>
                    <th class="py-2.5 px-3">Varian</th>
                    <th class="py-2.5 px-3 text-right font-semibold">ADU</th>
                    <th class="py-2.5 px-3 text-right font-semibold">Stok Fisik</th>
                    <th class="py-2.5 px-3 text-right font-semibold">Proyeksi</th>
                    <th class="py-2.5 px-3 text-right font-bold text-gray-900 bg-gray-50">Rekomendasi Qty</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                <template x-if="activeVarianProduct && activeVarianProduct.varian">
                    <template x-for="v in activeVarianProduct.varian" :key="v.nama_varian || v.sku_varian || Math.random()">
                        <tr class="hover:bg-gray-50 transition">
                            <td class="py-2 px-3 font-medium text-gray-900" x-text="v.nama_varian || v.nama || v.sku_varian || '-'"></td>
                            <td class="py-2 px-3 text-right font-mono text-gray-600" x-text="formatNumber(v.adu)"></td>
                            <td class="py-2 px-3 text-right font-mono text-gray-700" x-text="formatNumber(v.stok)"></td>
                            <td class="py-2 px-3 text-right font-mono text-gray-700" x-text="formatNumber(v.proyeksi)"></td>
                            <td class="py-2 px-3 text-right font-mono font-bold text-primary-800 bg-gray-50" x-text="formatNumber(v.rekomendasi_order || v.qty_order || 0)"></td>
                        </tr>
                    </template>
                </template>
            </tbody>
        </table>
    </div>

    <div class="pt-3 mt-3 border-t border-gray-200 flex justify-end">
        <button type="button" @click="showVarianModal = false" class="px-3.5 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-sm hover:bg-gray-50 shadow-xs cursor-pointer">
            Tutup
        </button>
    </div>
</x-modal>

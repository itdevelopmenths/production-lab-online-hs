{{-- Modal Detail Varian Impor --}}
<x-modal name="showVarianModal" maxWidth="2xl">
    <x-slot:header>
        <div class="px-6 py-4 border-b border-border bg-card flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold text-foreground tracking-tight" x-text="'Distribusi Varian: ' + (activeVarianProduct ? activeVarianProduct.nama : '')"></h3>
                <p class="text-xs text-muted-foreground font-mono mt-0.5" x-text="activeVarianProduct ? activeVarianProduct.sku : ''"></p>
            </div>
            <button type="button" @click="showVarianModal = false" class="text-muted-foreground hover:text-foreground transition cursor-pointer p-1.5 rounded-lg hover:bg-accent">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <div class="p-6 max-h-[60vh] overflow-y-auto">
        <div class="rounded-xl border border-border overflow-hidden">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-muted/70 text-muted-foreground font-semibold border-b border-border text-[11px] uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Varian</th>
                        <th class="py-3 px-4 text-right font-semibold">ADU</th>
                        <th class="py-3 px-4 text-right font-semibold">Stok Fisik</th>
                        <th class="py-3 px-4 text-right font-semibold">Proyeksi</th>
                        <th class="py-3 px-4 text-right font-bold text-foreground bg-muted/40">Rekomendasi Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border bg-card">
                    <template x-if="activeVarianProduct && activeVarianProduct.varian">
                        <template x-for="v in activeVarianProduct.varian" :key="v.nama_varian || v.sku_varian || Math.random()">
                            <tr class="hover:bg-muted/40 transition">
                                <td class="py-2.5 px-4 font-medium text-foreground" x-text="v.nama_varian || v.nama || v.sku_varian || '-'"></td>
                                <td class="py-2.5 px-4 text-right font-mono text-muted-foreground" x-text="formatNumber(v.adu)"></td>
                                <td class="py-2.5 px-4 text-right font-mono text-foreground" x-text="formatNumber(v.stok)"></td>
                                <td class="py-2.5 px-4 text-right font-mono text-foreground" x-text="formatNumber(v.proyeksi)"></td>
                                <td class="py-2.5 px-4 text-right font-mono font-bold text-foreground bg-muted/40" x-text="formatNumber(v.rekomendasi_order || v.qty_order || 0)"></td>
                            </tr>
                        </template>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="px-6 py-3.5 bg-muted/30 border-t border-border flex justify-end">
        <x-button type="button" @click="showVarianModal = false" variant="outline" size="sm">
            Tutup
        </x-button>
    </div>
</x-modal>

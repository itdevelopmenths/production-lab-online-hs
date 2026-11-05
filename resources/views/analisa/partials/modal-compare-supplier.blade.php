{{-- Modal Perbandingan & Pemilihan Supplier Analisa Stok (PRD v2.4 Bab 5) --}}
<x-modal name="showCompareSupplierModal" maxWidth="3xl">
    <x-slot:header>
        <div class="px-6 py-4 border-b border-border bg-card flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-foreground tracking-tight">Perbandingan & Pemilihan Supplier</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        <span class="font-medium text-foreground" x-text="compareProduct.nama"></span>
                        <span class="font-mono text-muted-foreground" x-text="' (' + compareProduct.sku + ')'"></span>
                    </p>
                </div>
            </div>
            <button type="button" @click="showCompareSupplierModal = false" class="text-muted-foreground hover:text-foreground transition cursor-pointer p-1.5 rounded-lg hover:bg-accent">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </x-slot:header>

    <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
        <div class="text-xs text-muted-foreground bg-muted/40 p-3 rounded-xl border border-border flex items-start gap-2.5">
            <svg class="size-4 text-primary shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <p class="font-medium text-foreground">Satu Produk Satu Supplier Aktif untuk Analisa</p>
                <p class="mt-0.5 text-[11px]">Memilih supplier akan mengunci supplier tersebut sebagai acuan kalkulasi stok dan PO. Sistem akan menggunakan riwayat lead time empiris (rata-rata dan maksimum) dari transaksi PO selesai vendor tersebut jika tersedia, atau parameter manual jika belum ada riwayat.</p>
            </div>
        </div>

        <template x-if="loadingCompareSupplier">
            <div class="py-12 text-center text-muted-foreground flex flex-col items-center justify-center gap-2">
                <svg class="animate-spin size-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <span class="text-xs">Memuat perbandingan vendor...</span>
            </div>
        </template>

        <template x-if="!loadingCompareSupplier">
            <div class="overflow-x-auto border border-border rounded-xl">
                <table class="w-full text-xs text-left">
                    <thead class="bg-muted/70 text-muted-foreground text-[11px] uppercase font-semibold border-b border-border tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Supplier</th>
                            <th class="py-3 px-3 text-center">Relasi</th>
                            <th class="py-3 px-4 text-center">PO Selesai</th>
                            <th class="py-3 px-4 text-right">Avg LT</th>
                            <th class="py-3 px-4 text-right">Max LT</th>
                            <th class="py-3 px-4 text-right">Harga HPP</th>
                            <th class="py-3 px-4 text-right">Min Order</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card">
                        <template x-for="s in compareProduct.suppliers" :key="s.supplier_id">
                            <tr class="hover:bg-muted/40 transition" :class="s.is_current ? 'bg-primary/5' : ''">
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        <div class="font-semibold text-foreground" x-text="s.supplier_nama"></div>
                                        <span x-show="s.is_current" class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300">AKTIF</span>
                                    </div>
                                    <div class="text-[10px] text-muted-foreground font-mono mt-0.5" x-text="s.sumber_lt === 'otomatis' ? 'Riwayat Empiris PO' : 'Setting Manual Default'"></div>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono capitalize" :class="s.status === 'utama' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-muted text-muted-foreground'" x-text="s.status || 'terdaftar'"></span>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-medium text-foreground">
                                    <span x-text="s.total_po_selesai + ' PO'"></span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-muted-foreground">
                                    <span x-text="formatLt(s.average_lt_days) + ' Hari'"></span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-muted-foreground">
                                    <span x-text="formatLt(s.max_lt_days) + ' Hari'"></span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-foreground" x-text="'Rp ' + formatNumber(s.harga_terakhir)"></td>
                                <td class="py-3 px-4 text-right font-mono text-muted-foreground" x-text="formatNumber(s.min_order_qty)"></td>
                                <td class="py-3 px-4 text-center">
                                    <template x-if="s.is_current">
                                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Aktif</span>
                                    </template>
                                    <template x-if="!s.is_current">
                                        <button 
                                            type="button" 
                                            @click="selectSupplier(s.supplier_id)" 
                                            :disabled="savingSelectSupplier"
                                            class="px-3 py-1.5 bg-primary hover:bg-primary/90 text-primary-foreground disabled:opacity-50 text-xs font-medium rounded-lg shadow-xs transition cursor-pointer"
                                        >
                                            <span x-show="!savingSelectSupplier">Gunakan Supplier</span>
                                            <span x-show="savingSelectSupplier">Menyimpan...</span>
                                        </button>
                                    </template>
                                </td>
                            </tr>
                        </template>
                        <template x-if="compareProduct.suppliers.length === 0">
                            <tr>
                                <td colspan="8" class="py-8 text-center text-muted-foreground text-xs">
                                    Belum ada daftar supplier yang dihubungkan untuk produk ini.
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

    <x-slot:footer>
        <div class="px-6 py-3 bg-muted/30 border-t border-border flex justify-end">
            <button type="button" @click="showCompareSupplierModal = false" class="px-4 py-2 text-xs font-medium rounded-lg border border-border bg-card text-foreground hover:bg-accent transition cursor-pointer">
                Tutup
            </button>
        </div>
    </x-slot:footer>
</x-modal>


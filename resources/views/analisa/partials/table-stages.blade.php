{{-- Rincian 8 Tahap Lead Time. Dipakai bersama Lokal ($tipe = 'lokal', lead_time_lokal_stage) dan Impor ($tipe = 'impor', lead_time_impor_stage). --}}
<div x-show="{{ $tipe }}SubTab === 'stages'" class="space-y-4">
    {{-- Highlight Summary: Lead Time Terlama (Average & Max) --}}
    <template x-if="longestAvgStage() || longestMaxStage()">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-card class="tbl-modern" :noPadding="true">
                <div class="px-4 py-3 bg-[#f8fafb] dark:bg-gray-800/80 border-b border-[#E8E8E8] dark:border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <svg class="size-4 text-[#101828] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h4 class="text-xs font-medium text-[#101828] dark:text-white uppercase tracking-wider">Lead Time Terlama (Average)</h4>
                    </div>
                    <span class="px-2.5 py-1 text-xs font-medium tabular-nums bg-[#111111] dark:bg-white text-white dark:text-[#111111] rounded-lg" x-text="formatLt(longestAvgStage()?.days || 0) + ' Hari'"></span>
                </div>
                <div class="p-4 bg-white dark:bg-gray-900 text-xs">
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                            <tr>
                                <td class="py-2 text-[#667085] dark:text-gray-400 font-medium w-28">Bahan Baku</td>
                                <td class="py-2 text-[#101828] dark:text-white text-right">
                                    <span class="font-medium text-[#101828] dark:text-white" x-text="longestAvgStage()?.nama || '-'"></span>
                                    <template x-if="longestAvgStage()?.sku">
                                        <span class="tabular-nums text-[#667085] dark:text-gray-400 text-[11px] ml-1" x-text="'(' + longestAvgStage().sku + ')'"></span>
                                    </template>
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 text-[#667085] dark:text-gray-400 font-medium w-28">Supplier</td>
                                <td class="py-2 text-[#101828] dark:text-white font-medium text-right" x-text="longestAvgStage()?.supplier_nama || '-'"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card class="tbl-modern" :noPadding="true">
                <div class="px-4 py-3 bg-[#f8fafb] dark:bg-gray-800/80 border-b border-[#E8E8E8] dark:border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <svg class="size-4 text-[#101828] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <h4 class="text-xs font-medium text-[#101828] dark:text-white uppercase tracking-wider">Lead Time Terlama (Max Skenario)</h4>
                    </div>
                    <span class="px-2.5 py-1 text-xs font-medium tabular-nums bg-[#111111] dark:bg-white text-white dark:text-[#111111] rounded-lg" x-text="formatLt(longestMaxStage()?.days || 0) + ' Hari'"></span>
                </div>
                <div class="p-4 bg-white dark:bg-gray-900 text-xs">
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                            <tr>
                                <td class="py-2 text-[#667085] dark:text-gray-400 font-medium w-28">Bahan Baku</td>
                                <td class="py-2 text-[#101828] dark:text-white text-right">
                                    <span class="font-medium text-[#101828] dark:text-white" x-text="longestMaxStage()?.nama || '-'"></span>
                                    <template x-if="longestMaxStage()?.sku">
                                        <span class="tabular-nums text-[#667085] dark:text-gray-400 text-[11px] ml-1" x-text="'(' + longestMaxStage().sku + ')'"></span>
                                    </template>
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 text-[#667085] dark:text-gray-400 font-medium w-28">Supplier</td>
                                <td class="py-2 text-[#101828] dark:text-white font-medium text-right" x-text="longestMaxStage()?.supplier_nama || '-'"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </template>

    <x-card class="tbl-modern" 
        title="{{ $tipe === 'impor' ? 'Tabel Rincian 8 Tahap Lead Time Impor (lead_time_impor_stage)' : 'Tabel Rincian 8 Tahap Lead Time (lead_time_lokal_stage)' }}" 
        subtitle="Perencanaan, Konfirmasi Supplier, Persetujuan, Pembayaran, PO Terbit, Pengiriman, Unloading, dan Input Sistem"
        :noPadding="true"
    >
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
        </x-slot:icon>

        <div tabindex="0" role="region" aria-label="Tabel Rincian 8 Tahap Lead Time" class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-[11px] font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                    <tr>
                        <th class="py-3 px-2">Bahan</th>
                        <th class="py-3 px-2">Supplier</th>
                        <th class="py-3 px-2">Skenario</th>
                        <th class="py-3 px-2 text-right">Rencana</th>
                        <th class="py-3 px-2 text-right">Konfirmasi</th>
                        <th class="py-3 px-2 text-right">Setuju</th>
                        <th class="py-3 px-2 text-right">Bayar</th>
                        <th class="py-3 px-2 text-right">PO</th>
                        <th class="py-3 px-2 text-right">Kirim</th>
                        <th class="py-3 px-2 text-right">Unload</th>
                        <th class="py-3 px-2 text-right">Input</th>
                        <th class="py-3 px-2 text-right bg-gray-100/70 dark:bg-gray-800 font-medium text-[#101828] dark:text-white">Total</th>
                        <th class="py-3 px-2 text-center w-20">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 bg-white dark:bg-gray-900">
                    <template x-for="row in paginatedStageRows()" :key="row.key">
                            <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition" :class="row.type === 'max' ? 'border-b-2 border-[#E8E8E8] dark:border-gray-700' : ''">
                                <template x-if="row.type === 'average'"><td class="py-2.5 px-2" rowspan="2">
                                    <div class="font-medium text-[#101828] dark:text-white text-xs" x-text="row.stg.nama"></div>
                                    <div class="tabular-nums text-[10px] text-[#667085] dark:text-gray-400" x-text="row.stg.sku"></div>
                                </td></template>
                                <template x-if="row.type === 'average'"><td class="py-2.5 px-2" rowspan="2">
                                    <div class="flex flex-col gap-1">
                                        <button type="button" @click="openCompareSupplier(row.stg)" class="font-medium text-[#111111] dark:text-white hover:underline text-left cursor-pointer inline-flex items-center gap-1 text-xs" title="Bandingkan & Ganti Supplier">
                                            <span x-text="row.stg.supplier_nama || 'Pilih Supplier'"></span>
                                            <svg class="size-3 text-[#667085] dark:text-gray-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                        </button>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <template x-if="row.stg.sumber_lt === 'otomatis'">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card border border-border dark:border-border" title="Sumber: Rincian 8 Stage Histori PO Otomatis">
                                                    <span class="size-1 rounded-full bg-gray-400 shrink-0"></span>
                                                    <span x-text="'Otomatis' + (row.stg.jumlah_transaksi ? ' (' + row.stg.jumlah_transaksi + ' PO)' : '')"></span>
                                                </span>
                                            </template>
                                            <template x-if="row.stg.sumber_lt !== 'otomatis'">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium text-foreground dark:text-foreground bg-card dark:bg-card border border-border dark:border-border" title="Sumber: Estimasi Input Manual">
                                                    <span class="size-1 rounded-full bg-gray-400 shrink-0"></span>
                                                    <span>Manual</span>
                                                </span>
                                            </template>
                                            <a :href="'{{ route('purchasing.index', ['tab' => 'po']) }}' + (row.stg.supplier_nama && row.stg.supplier_nama !== '-' ? '&search=' + encodeURIComponent(row.stg.supplier_nama) : '')" href="{{ route('purchasing.index', ['tab' => 'po']) }}" class="text-[10px] text-[#667085] dark:text-gray-400 hover:text-[#101828] dark:hover:text-white underline" title="Lihat Daftar Purchase Order Supplier">Histori LT</a>
                                        </div>
                                    </div>
                                </td></template>
                                <td class="py-2.5 px-2 tabular-nums uppercase text-[10px] font-medium text-[#667085] dark:text-gray-400" x-text="row.type"></td>
                                <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(row.stg[row.type] ? row.stg[row.type].perencanaan : 0)"></td>
                                <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(row.stg[row.type] ? row.stg[row.type].supplier_confirm : 0)"></td>
                                <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(row.stg[row.type] ? row.stg[row.type].approval : 0)"></td>
                                <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(row.stg[row.type] ? row.stg[row.type].payment : 0)"></td>
                                <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(row.stg[row.type] ? row.stg[row.type].po : 0)"></td>
                                <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(row.stg[row.type] ? row.stg[row.type].pengiriman : 0)"></td>
                                <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(row.stg[row.type] ? row.stg[row.type].unloading : 0)"></td>
                                <td class="py-2.5 px-2 text-right tabular-nums text-[#667085] dark:text-gray-400" x-text="formatLt(row.stg[row.type] ? row.stg[row.type].input : 0)"></td>
                                <td class="py-2.5 px-2 text-right tabular-nums text-[#101828] dark:text-white bg-[#fafafa] dark:bg-gray-800/40" x-text="formatLt(row.stg[row.type] ? row.stg[row.type].total : 0) + ' Hari'"></td>
                                <template x-if="row.type === 'average'"><td class="py-2.5 px-2 text-center" rowspan="2">
                                    @can('analisa.manage')
                                    {{-- Sembunyikan Edit bila LT sudah otomatis dari histori PO --}}
                                    <template x-if="row.stg.sumber_lt !== 'otomatis'">
                                        <button type="button" @click="openStageModal(row.stg, '{{ $tipe }}')" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium transition cursor-pointer btn-soft rounded-[6px]" title="Edit Rincian 8 Tahap Lead Time">
                                            <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            Edit
                                        </button>
                                    </template>
                                    @endcan
                                </td></template>
                            </tr>
                    </template>
                    <template x-if="paginatedStages().length === 0">
                        <tr>
                            <td colspan="13" class="py-8 text-center text-[#667085] dark:text-gray-400 text-xs">
                                Tidak ada rincian tahap lead time yang cocok.
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination Bar --}}
        <x-slot:footer>
            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#667085] dark:text-gray-400">
                <div class="flex items-center gap-2 tabular-nums text-[11px]">
                    <span>Menampilkan <strong class="text-[#101828] dark:text-white" x-text="startItem()"></strong> - <strong class="text-[#101828] dark:text-white" x-text="endItem()"></strong> dari <strong class="text-[#101828] dark:text-white" x-text="currentDataLength()"></strong> item</span>
                </div>

                <div class="flex items-center gap-1.5" x-show="totalPages() > 1">
                    <button type="button" @click="gotoPage(page - 1)" :disabled="page <= 1" class="px-2.5 py-1 text-xs disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer btn-soft rounded-[6px]">
                        &laquo; Back
                    </button>
                    <template x-for="p in pagesList()" :key="p">
                        <div>
                            <template x-if="p === '...'">
                                <span class="px-2 py-1 text-[#667085] dark:text-gray-500">...</span>
                            </template>
                            <template x-if="p !== '...'">
                                <button type="button" @click="gotoPage(p)" :class="page === p ? 'bg-[#111111] dark:bg-white text-white dark:text-[#111111] font-medium border-[#111111] dark:border-white' : 'bg-white dark:bg-gray-900 text-[#101828] dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 border-[#E8E8E8] dark:border-gray-700'" class="min-w-[28px] px-2 py-1 text-xs border transition cursor-pointer rounded-[6px]" x-text="p"></button>
                            </template>
                        </div>
                    </template>
                    <button type="button" @click="gotoPage(page + 1)" :disabled="page >= totalPages()" class="px-2.5 py-1 text-xs disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer btn-soft rounded-[6px]">
                        Next &raquo;
                    </button>
                </div>
            </div>
        </x-slot:footer>
    </x-card>
</div>

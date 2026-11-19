{{-- Section: Produk Jadi Fulfillment --}}
<div x-show="tab === 'fulfillment'">
    <x-card 
        title="Tabel Analisa Produk Jadi (Fulfillment)" 
        subtitle="Monitoring stok fisik produk jadi terhadap batas aman dan alokasi pesanan distributor" 
        :noPadding="true"
    >
        <x-slot:icon>
            <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </x-slot:icon>

        {{-- Loading Spinner --}}
        <div x-show="loading" class="py-12 flex flex-col items-center justify-center text-[#667085] dark:text-gray-400">
            <svg class="animate-spin h-6 w-6 text-[#111111] dark:text-white mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-xs">Memuat kalkulasi data fulfillment...</span>
        </div>

        <div x-show="!loading" tabindex="0" role="region" aria-label="Tabel Analisa Produk Jadi Fulfillment" class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95 text-[#667085] dark:text-gray-400 text-xs font-medium border-b border-[#E8E8E8] dark:border-gray-800">
                    <tr id="ahead"></tr>
                </thead>
                <tbody id="abody" class="divide-y divide-[#E8E8E8] dark:divide-gray-800 bg-white dark:bg-gray-900"></tbody>
            </table>
        </div>

        {{-- Pagination Bar for Fulfillment --}}
        <x-slot:footer>
            <div x-show="!loading && rows.length > 0" class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#667085] dark:text-gray-400">
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

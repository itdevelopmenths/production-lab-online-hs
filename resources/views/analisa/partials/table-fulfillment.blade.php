{{-- Section: Produk Jadi Fulfillment --}}
<div x-show="tab === 'fulfillment'">
    <x-card 
        title="Tabel Analisa Produk Jadi (Fulfillment)" 
        subtitle="Monitoring stok fisik produk jadi terhadap batas aman dan alokasi pesanan distributor" 
        :noPadding="true"
    >
        {{-- Loading Spinner --}}
        <div x-show="loading" class="py-12 flex flex-col items-center justify-center text-gray-500">
            <svg class="animate-spin h-6 w-6 text-primary-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-xs">Memuat kalkulasi data fulfillment...</span>
        </div>

        <div x-show="!loading" class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-gray-100 text-gray-700 text-[11px] uppercase font-semibold border-b border-gray-200">
                    <tr id="ahead"></tr>
                </thead>
                <tbody id="abody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>

        {{-- Pagination Bar for Fulfillment --}}
        <x-slot:footer>
            <div x-show="!loading && rows.length > 0" class="flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
                <div class="flex items-center gap-2 font-mono text-[11px]">
                    <span>Menampilkan <strong x-text="startItem()"></strong> - <strong x-text="endItem()"></strong> dari <strong x-text="currentDataLength()"></strong> item</span>
                </div>

                <div class="flex items-center gap-1" x-show="totalPages() > 1">
                    <button type="button" @click="gotoPage(page - 1)" :disabled="page <= 1" class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                        &laquo; Prev
                    </button>

                    <template x-for="p in pagesList()" :key="p">
                        <div>
                            <template x-if="p === '...'">
                                <span class="px-2 py-1 text-gray-400">...</span>
                            </template>
                            <template x-if="p !== '...'">
                                <button type="button" @click="gotoPage(p)" :class="page === p ? 'bg-primary-700 text-white font-bold border-primary-700' : 'bg-white text-gray-700 hover:bg-gray-100 border-gray-300'" class="min-w-[28px] px-2 py-1 text-xs rounded border transition cursor-pointer" x-text="p"></button>
                            </template>
                        </div>
                    </template>

                    <button type="button" @click="gotoPage(page + 1)" :disabled="page >= totalPages()" class="px-2.5 py-1 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                        Next &raquo;
                    </button>
                </div>
            </div>
        </x-slot:footer>
    </x-card>
</div>

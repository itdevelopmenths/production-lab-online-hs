<x-app-layout title="Pusat Laporan & Rekapitulasi">
    <x-page-header
        title="Pusat Laporan & Rekapitulasi"
        subtitle="Pilih laporan operasional, inventori, pengadaan, atau lead time untuk audit mendalam"
        :breadcrumbs="['Laporan' => null]"
    />

    <div class="space-y-6">
        <!-- Section: Produksi & Manufaktur -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-1.5 h-3.5 bg-[#111111] dark:bg-white rounded-full"></span>
                <h4 class="text-xs font-semibold uppercase tracking-wider text-[#667085] dark:text-gray-400">Operasional Manufaktur & Mutu</h4>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="{{ route('reports.production') }}" class="group relative rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-[#111111]/40 dark:hover:border-gray-600 transition flex items-start gap-3.5">
                    <div class="size-[42px] rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 bg-[#fafafa] dark:bg-gray-800 text-[#111111] dark:text-white group-hover:bg-[#111111] group-hover:text-white dark:group-hover:bg-white dark:group-hover:text-[#111111] transition flex items-center justify-center shrink-0">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h5 class="text-sm font-semibold text-[#101828] dark:text-white">Laporan Produksi & Yield</h5>
                            <span class="text-[#667085] dark:text-gray-400 group-hover:text-[#101828] dark:group-hover:text-white group-hover:translate-x-0.5 transition text-xs font-medium">&rarr;</span>
                        </div>
                        <p class="text-xs text-[#667085] dark:text-gray-400 mt-1 leading-relaxed">Rekapitulasi batch selesai, perbandingan output baik vs rusak, dan persentase yield.</p>
                    </div>
                </a>

                <a href="{{ route('reports.defect') }}" class="group relative rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-rose-400/60 dark:hover:border-rose-700 transition flex items-start gap-3.5">
                    <div class="size-[42px] rounded-[10px] border border-rose-200 dark:border-rose-800/60 bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 group-hover:bg-rose-600 group-hover:text-white transition flex items-center justify-center shrink-0">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h5 class="text-sm font-semibold text-[#101828] dark:text-white">Laporan Defect & Kerusakan</h5>
                            <span class="text-[#667085] dark:text-gray-400 group-hover:text-[#101828] dark:group-hover:text-white group-hover:translate-x-0.5 transition text-xs font-medium">&rarr;</span>
                        </div>
                        <p class="text-xs text-[#667085] dark:text-gray-400 mt-1 leading-relaxed">Monitoring batch produksi dan transfer yang mengalami unit rusak/cacat serta defect rate.</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Section: Inventori & Material -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-1.5 h-3.5 bg-[#111111] dark:bg-white rounded-full"></span>
                <h4 class="text-xs font-semibold uppercase tracking-wider text-[#667085] dark:text-gray-400">Inventori & Logistik Material</h4>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="{{ route('reports.material') }}" class="group relative rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-[#111111]/40 dark:hover:border-gray-600 transition flex items-start gap-3.5">
                    <div class="size-[42px] rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 bg-[#fafafa] dark:bg-gray-800 text-[#111111] dark:text-white group-hover:bg-[#111111] group-hover:text-white dark:group-hover:bg-white dark:group-hover:text-[#111111] transition flex items-center justify-center shrink-0">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h5 class="text-sm font-semibold text-[#101828] dark:text-white">Pemakaian Bahan</h5>
                            <span class="text-[#667085] dark:text-gray-400 group-hover:text-[#101828] dark:group-hover:text-white group-hover:translate-x-0.5 transition text-xs font-medium">&rarr;</span>
                        </div>
                        <p class="text-xs text-[#667085] dark:text-gray-400 mt-1 leading-relaxed">Akumulasi konsumsi bahan baku dan kemasan yang dikeluarkan untuk proses produksi.</p>
                    </div>
                </a>

                <a href="{{ route('reports.low-stock') }}" class="group relative rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-[#111111]/40 dark:hover:border-gray-600 transition flex items-start gap-3.5">
                    <div class="size-[42px] rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 bg-[#fafafa] dark:bg-gray-800 text-[#111111] dark:text-white group-hover:bg-[#111111] group-hover:text-white dark:group-hover:bg-white dark:group-hover:text-[#111111] transition flex items-center justify-center shrink-0">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h5 class="text-sm font-semibold text-[#101828] dark:text-white">Variance Opname</h5>
                            <span class="text-[#667085] dark:text-gray-400 group-hover:text-[#101828] dark:group-hover:text-white group-hover:translate-x-0.5 transition text-xs font-medium">&rarr;</span>
                        </div>
                        <p class="text-xs text-[#667085] dark:text-gray-400 mt-1 leading-relaxed">Analisis deviasi selisih pemakaian teoritis BOM vs realisasi aktual di lantai produksi.</p>
                    </div>
                </a>

                <a href="{{ route('reports.fulfillment') }}" class="group relative rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-[#111111]/40 dark:hover:border-gray-600 transition flex items-start gap-3.5">
                    <div class="size-[42px] rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 bg-[#fafafa] dark:bg-gray-800 text-[#111111] dark:text-white group-hover:bg-[#111111] group-hover:text-white dark:group-hover:bg-white dark:group-hover:text-[#111111] transition flex items-center justify-center shrink-0">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h5 class="text-sm font-semibold text-[#101828] dark:text-white">Pergerakan Fulfillment</h5>
                            <span class="text-[#667085] dark:text-gray-400 group-hover:text-[#101828] dark:group-hover:text-white group-hover:translate-x-0.5 transition text-xs font-medium">&rarr;</span>
                        </div>
                        <p class="text-xs text-[#667085] dark:text-gray-400 mt-1 leading-relaxed">Log mutasi produk jadi antar gudang pusat dan hub fulfillment cabang.</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Section: Pengadaan & Finansial -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-1.5 h-3.5 bg-[#111111] dark:bg-white rounded-full"></span>
                <h4 class="text-xs font-semibold uppercase tracking-wider text-[#667085] dark:text-gray-400">Pengadaan & Finansial</h4>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="{{ route('reports.purchasing') }}" class="group relative rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-[#111111]/40 dark:hover:border-gray-600 transition flex items-start gap-3.5">
                    <div class="size-[42px] rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 bg-[#fafafa] dark:bg-gray-800 text-[#111111] dark:text-white group-hover:bg-[#111111] group-hover:text-white dark:group-hover:bg-white dark:group-hover:text-[#111111] transition flex items-center justify-center shrink-0">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h5 class="text-sm font-semibold text-[#101828] dark:text-white">Purchasing & Hutang Dagang</h5>
                            <span class="text-[#667085] dark:text-gray-400 group-hover:text-[#101828] dark:group-hover:text-white group-hover:translate-x-0.5 transition text-xs font-medium">&rarr;</span>
                        </div>
                        <p class="text-xs text-[#667085] dark:text-gray-400 mt-1 leading-relaxed">Rekapitulasi nilai komitmen purchase order, pembayaran termin, dan outstanding hutang.</p>
                    </div>
                </a>

                <a href="{{ route('reports.lead-time') }}" class="group relative rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)] hover:border-[#111111]/40 dark:hover:border-gray-600 transition flex items-start gap-3.5">
                    <div class="size-[42px] rounded-[10px] border border-[#E8E8E8] dark:border-gray-800 bg-[#fafafa] dark:bg-gray-800 text-[#111111] dark:text-white group-hover:bg-[#111111] group-hover:text-white dark:group-hover:bg-white dark:group-hover:text-[#111111] transition flex items-center justify-center shrink-0">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h5 class="text-sm font-semibold text-[#101828] dark:text-white">Laporan Lead Time Supplier</h5>
                            <span class="text-[#667085] dark:text-gray-400 group-hover:text-[#101828] dark:group-hover:text-white group-hover:translate-x-0.5 transition text-xs font-medium">&rarr;</span>
                        </div>
                        <p class="text-xs text-[#667085] dark:text-gray-400 mt-1 leading-relaxed">Analisis performa durasi pengiriman supplier 9-stage, deviasi per produk, dan histori PO.</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout title="Pusat Laporan & Rekapitulasi">
    <x-page-header
        title="Pusat Laporan & Rekapitulasi"
        subtitle="Direktori komprehensif pelaporan operasional laboratorium, konsumsi bahan baku, deviasi opname, dan liabilitas purchasing"
        :breadcrumbs="['Laporan' => null]"
    />

    <!-- Quick Navigation Groups -->
    <div class="space-y-6">
        <!-- Section: Produksi & Manufaktur -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-1.5 h-4 bg-primary-600 rounded-xs"></span>
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700">Operasional Manufaktur & Mutu</h4>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Laporan Produksi -->
                <a href="{{ route('reports.production') }}" class="group bg-white rounded-sm border border-gray-200 p-4 hover:border-primary-500 hover:shadow-sm transition flex items-start gap-3.5">
                    <div class="p-2.5 bg-primary-50 text-primary-700 rounded-sm group-hover:bg-primary-600 group-hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h5 class="text-sm font-semibold text-gray-900 group-hover:text-primary-600 transition">Laporan Produksi & Yield</h5>
                            <span class="text-gray-400 group-hover:text-primary-600 group-hover:translate-x-0.5 transition-transform text-xs font-semibold">&rarr;</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Rekapitulasi batch selesai, perbandingan output baik vs rusak, dan capaian persentase yield.</p>
                    </div>
                </a>

                <!-- Laporan Defect -->
                <a href="{{ route('reports.defect') }}" class="group bg-white rounded-sm border border-gray-200 p-4 hover:border-rose-500 hover:shadow-sm transition flex items-start gap-3.5">
                    <div class="p-2.5 bg-rose-50 text-rose-700 rounded-sm group-hover:bg-rose-600 group-hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h5 class="text-sm font-semibold text-gray-900 group-hover:text-rose-600 transition">Laporan Defect & Kerusakan</h5>
                            <span class="text-gray-400 group-hover:text-rose-600 group-hover:translate-x-0.5 transition-transform text-xs font-semibold">&rarr;</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Monitoring batch produksi yang mengalami unit rusak/cacat dan evaluasi defect rate.</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Section: Inventori & Material -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-1.5 h-4 bg-emerald-600 rounded-xs"></span>
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700">Inventori & Logistik Material</h4>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Pemakaian Bahan -->
                <a href="{{ route('reports.material') }}" class="group bg-white rounded-sm border border-gray-200 p-4 hover:border-emerald-500 hover:shadow-sm transition flex items-start gap-3.5">
                    <div class="p-2.5 bg-emerald-50 text-emerald-700 rounded-sm group-hover:bg-emerald-600 group-hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h5 class="text-sm font-semibold text-gray-900 group-hover:text-emerald-600 transition">Pemakaian Bahan</h5>
                            <span class="text-gray-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-transform text-xs font-semibold">&rarr;</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Akumulasi konsumsi bahan baku dan kemasan yang dikeluarkan untuk proses produksi.</p>
                    </div>
                </a>

                <!-- Variance Opname -->
                <a href="{{ route('reports.low-stock') }}" class="group bg-white rounded-sm border border-gray-200 p-4 hover:border-amber-500 hover:shadow-sm transition flex items-start gap-3.5">
                    <div class="p-2.5 bg-amber-50 text-amber-700 rounded-sm group-hover:bg-amber-600 group-hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h5 class="text-sm font-semibold text-gray-900 group-hover:text-amber-600 transition">Variance Opname</h5>
                            <span class="text-gray-400 group-hover:text-amber-600 group-hover:translate-x-0.5 transition-transform text-xs font-semibold">&rarr;</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Analisis deviasi selisih pemakaian teoritis BOM vs realisasi aktual di lantai produksi.</p>
                    </div>
                </a>

                <!-- Pergerakan Fulfillment -->
                <a href="{{ route('reports.fulfillment') }}" class="group bg-white rounded-sm border border-gray-200 p-4 hover:border-blue-500 hover:shadow-sm transition flex items-start gap-3.5">
                    <div class="p-2.5 bg-blue-50 text-blue-700 rounded-sm group-hover:bg-blue-600 group-hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h5 class="text-sm font-semibold text-gray-900 group-hover:text-blue-600 transition">Pergerakan Fulfillment</h5>
                            <span class="text-gray-400 group-hover:text-blue-600 group-hover:translate-x-0.5 transition-transform text-xs font-semibold">&rarr;</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Log mutasi produk jadi antar gudang pusat dan hub fulfillment cabang.</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Section: Pengadaan & Finansial -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-1.5 h-4 bg-purple-600 rounded-xs"></span>
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700">Pengadaan & Finansial</h4>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Purchasing & Hutang -->
                <a href="{{ route('reports.purchasing') }}" class="group bg-white rounded-sm border border-gray-200 p-4 hover:border-purple-500 hover:shadow-sm transition flex items-start gap-3.5">
                    <div class="p-2.5 bg-purple-50 text-purple-700 rounded-sm group-hover:bg-purple-600 group-hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h5 class="text-sm font-semibold text-gray-900 group-hover:text-purple-600 transition">Purchasing & Hutang Dagang</h5>
                            <span class="text-gray-400 group-hover:text-purple-600 group-hover:translate-x-0.5 transition-transform text-xs font-semibold">&rarr;</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Rekapitulasi nilai komitmen purchase order, pembayaran termin, dan outstanding hutang.</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>

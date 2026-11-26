@props([
    'title' => 'Heaven Scent',
    'subtitle' => null,
    'breadcrumbs' => [],
    'backUrl' => null,
    'actions' => null,
])
<!DOCTYPE html>
<html lang="id" class="h-full hs-app-shell">
<head>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('hs.theme');
                var isDark = t === 'dark' || (!t && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', isDark);
                document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
            } catch (e) {}
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F9FAFB">
    <title>{{ $title }} — Heaven Scent</title>

    <style>
        html, body {
            background-color: #F9FAFB;
        }
        html.dark, html.dark body {
            background-color: #030712;
        }
        [x-cloak] {
            display: none !important;
        }
        /* Sembunyikan halaman sampai Alpine siap agar sidebar tidak flicker saat boot.
           Fallback: tetap tampil setelah 1.5 detik bila JS gagal. */
        html.hs-booting body {
            visibility: hidden;
            animation: hs-reveal 0s linear 1.5s forwards;
        }
        html.hs-booting *, html.hs-booting *::before, html.hs-booting *::after {
            transition: none !important;
        }
        @keyframes hs-reveal {
            to { visibility: visible; }
        }
    </style>
    <script>
        document.documentElement.classList.add('hs-booting');
        document.addEventListener('alpine:initialized', function() {
            var reveal = function() { document.documentElement.classList.remove('hs-booting'); };
            requestAnimationFrame(reveal);
            setTimeout(reveal, 100);
        });
    </script>
    @php
        $vendorAsset = fn (string $path) => asset($path).'?v='.@filemtime(public_path($path));
    @endphp
    <link rel="stylesheet" href="{{ $vendorAsset('vendor/datatables/jquery.dataTables.min.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        function appSidebarState() {
            return {
                collapsed: (function() {
                    try {
                        return localStorage.getItem('devscore.sidebar-collapsed') === '1';
                    } catch(e) {
                        return false;
                    }
                })(),
                mobileOpen: false,
                menuQuery: '',
                isDark: document.documentElement.classList.contains('dark'),
                init() {
                    var self = this;
                    window.addEventListener('hs-theme-changed', function(e) {
                        self.isDark = (e.detail && e.detail.theme === 'dark') || document.documentElement.classList.contains('dark');
                    });
                },
                toggleCollapsed() {
                    this.collapsed = !this.collapsed;
                    try {
                        localStorage.setItem('devscore.sidebar-collapsed', this.collapsed ? '1' : '0');
                    } catch(e) {}
                    setTimeout(function() {
                        try {
                            if (typeof $ !== 'undefined' && $.fn.dataTable) {
                                $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
                            }
                            window.dispatchEvent(new Event('resize'));
                        } catch(e) {}
                    }, 250);
                }
            };
        }
    </script>
    <style>
        /* Spacer selebar sidebar (desktop saja) agar konten tidak tertimpa sidebar yang position:fixed */
        .hs-sidebar-spacer { display: none; flex: none; transition: width .3s ease-out; }
        @media (min-width: 1024px) { .hs-sidebar-spacer { display: block; } }
    </style>
</head>
<body class="min-h-screen font-sans antialiased text-text-base bg-gray-50 dark:bg-gray-950 flex flex-col"
      x-data="appSidebarState()">

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 lg:flex w-full min-w-0 overflow-x-hidden">
        {{-- Mobile Overlay --}}
        <div x-show="mobileOpen" x-cloak
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @click="mobileOpen = false"
             class="fixed inset-0 z-[90] bg-black/50 backdrop-blur-xs lg:hidden"></div>

        {{-- Heaven Scent Blueprint Sidebar --}}
        {{-- Spacer selebar sidebar: menjaga area konten tetap bergeser walau sidebar position:fixed --}}
        <div class="hs-sidebar-spacer" :style="collapsed ? 'width:80px' : 'width:264px'" aria-hidden="true"></div>

        {{-- Sidebar selalu setinggi layar (fixed), tidak ikut panjang konten halaman --}}
        <div class="fixed inset-y-0 left-0 z-[100] transition-all duration-300 ease-out lg:z-40 shrink-0" style="height:100vh;height:100dvh"
             :class="[
                 mobileOpen ? 'translate-x-0 w-[264px]' : '-translate-x-full lg:translate-x-0',
                 collapsed ? 'lg:w-[80px]' : 'lg:w-[264px]'
             ]">
            <aside class="bg-white dark:bg-[#0A0A0A] flex h-full w-full flex-col border-r border-[#E8E8E8] dark:border-border shadow-theme-xs overflow-hidden transition-all duration-300">

                {{-- Brand Header --}}
                <div class="flex items-center pt-4 pb-3 shrink-0"
                     :class="collapsed ? 'flex-col gap-3 px-3' : 'justify-between px-4'">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 min-w-0"
                       :class="collapsed ? 'justify-center' : ''">
                        <span class="relative overflow-hidden size-9 rounded-[8px] flex items-center justify-center shrink-0 border border-white/25 bg-gradient-to-b from-[#2c2c2c] to-[#0a0a0a] shadow-[inset_0_1px_1px_rgba(255,255,255,0.2),0_2px_4px_rgba(0,0,0,0.12)]">
                            <img src="{{ asset('Logo HS - black.png') }}" alt="Heaven Scent" class="size-5 object-contain brightness-0 invert" />
                        </span>
                        <div x-show="!collapsed" class="leading-tight min-w-0">
                            <span class="block text-xs font-medium tracking-tight text-[#111111] dark:text-white truncate">HEAVEN SCENT</span>
                            <span class="block text-[11px] font-medium text-text-muted truncate">Production Lab</span>
                        </div>
                    </a>

                    <button type="button"
                            @click="mobileOpen = false; toggleCollapsed()"
                            class="size-[40px] p-[8px] rounded-[8px] bg-[#FBFBFB] dark:bg-[#141414] border border-[#E1E1E1] dark:border-border text-text-muted hover:text-[#111111] dark:hover:text-white hover:border-[#DEDEDE] dark:hover:border-border-2 transition-colors cursor-pointer flex items-center justify-center shrink-0"
                            aria-label="Toggle sidebar">
                        <template x-if="collapsed">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                        </template>
                        <template x-if="!collapsed">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                        </template>
                    </button>
                </div>

                {{-- Search Bar --}}
                <div x-show="!collapsed" class="border-b border-[#E8E8E8] dark:border-border px-4 pb-3 shrink-0">
                    <label class="flex items-center h-[44px] px-3 gap-2 bg-[#FBFBFB] dark:bg-[#141414] border border-[#E1E1E1] dark:border-border rounded-lg focus-within:border-border-focus transition-colors">
                        <svg class="text-text-muted size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search"
                               x-model="menuQuery"
                               placeholder="Cari menu..."
                               class="w-full min-w-0 bg-transparent text-[13px] text-[#111111] dark:text-white placeholder:text-text-placeholder outline-none" />
                    </label>
                </div>
                <div x-show="collapsed" class="border-b border-[#E8E8E8] dark:border-border pb-3 flex justify-center shrink-0">
                    <button type="button"
                            @click="toggleCollapsed()"
                            class="size-[40px] p-[8px] rounded-[8px] bg-[#FBFBFB] dark:bg-[#141414] border border-[#E1E1E1] dark:border-border text-text-muted hover:text-[#111111] dark:hover:text-white transition-colors flex items-center justify-center"
                            title="Cari menu">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </button>
                </div>

                {{-- Navigation Items --}}
                <nav id="app-sidebar-nav"
                     :class="collapsed ? 'px-2.5 no-scrollbar' : 'px-3.5 custom-scrollbar'"
                     class="flex-1 space-y-5 overflow-y-auto py-4 sidebar-scroll">

                    {{-- Section: MENU --}}
                    <div :class="collapsed && 'flex flex-col items-center'">
                        <p :class="collapsed ? 'text-[10px] text-center' : 'text-[12px] px-3'"
                           class="text-text-muted font-medium uppercase tracking-[0.6px] mb-1.5 select-none">
                            Menu
                        </p>
                        <ul class="space-y-1 w-full">
                            <li class="w-full" x-show="!menuQuery || 'beranda dashboard'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                    </x-slot:icon>
                                    Beranda
                                </x-nav-item>
                            </li>
                        </ul>
                    </div>

                    {{-- Section: OPERASIONAL --}}
                    <div :class="collapsed && 'flex flex-col items-center'">
                        <p :class="collapsed ? 'text-[10px] text-center' : 'text-[12px] px-3'"
                           class="text-text-muted font-medium uppercase tracking-[0.6px] mb-1.5 select-none">
                            Operasional
                        </p>
                        <ul class="space-y-1 w-full">
                            @can('purchasing.view')
                            <li class="w-full" x-show="!menuQuery || 'purchasing order po'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('purchasing.index') }}" :active="request()->routeIs('purchasing.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    </x-slot:icon>
                                    Purchasing
                                </x-nav-item>
                            </li>
                            @endcan

                            @can('batch.view')
                            <li class="w-full" x-show="!menuQuery || 'produksi batch rilis'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('batches.index') }}" :active="request()->routeIs('batches.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                    </x-slot:icon>
                                    Produksi
                                </x-nav-item>
                            </li>
                            @endcan

                            @canany(['request.view', 'rt.view'])
                            <li class="w-full" x-show="!menuQuery || 'request permintaan bahan produk jadi'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('requests.index') }}" :active="request()->routeIs('requests.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </x-slot:icon>
                                    Permintaan Barang
                                </x-nav-item>
                            </li>
                            @endcanany

                            @canany(['transfer.view', 'rt.view'])
                            <li class="w-full" x-show="!menuQuery || 'transfer mutasi kirim logistik defect'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('transfers.index') }}" :active="request()->routeIs('transfers.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                    </x-slot:icon>
                                    Transfer Barang
                                </x-nav-item>
                            </li>
                            @endcanany

                            @can('defect.view')
                            <li class="w-full" x-show="!menuQuery || 'barang rusak defect cacat qc transfer'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('barang-rusak.index') }}" :active="request()->routeIs('barang-rusak.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                    </x-slot:icon>
                                    Barang Rusak
                                </x-nav-item>
                            </li>
                            @endcan

                            @can('analisa.view')
                            @php
                                $analisaTabAktif = request()->routeIs('analisa.index')
                                    ? (array_key_exists((string) request()->query('tab'), \App\Http\Controllers\AnalisaController::TABS) ? (string) request()->query('tab') : 'lokal')
                                    : null;
                            @endphp
                            <li class="w-full">
                                <x-nav-group
                                    title="Analisa Stok"
                                    :active="request()->routeIs('analisa.*')"
                                    keywords="analisa stok kpi analitik bahan baku lokal impor produk jadi gudang produksi fulfillment log riwayat snapshot">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                    </x-slot:icon>
                                    @foreach (\App\Http\Controllers\AnalisaController::TABS as $tabKey => $tabNama)
                                    <div x-show="!menuQuery || @js(strtolower('analisa stok ' . $tabNama)).includes(menuQuery.toLowerCase())">
                                        <x-nav-item href="{{ route('analisa.index', ['tab' => $tabKey]) }}" :active="$analisaTabAktif === $tabKey" :isChild="true">
                                            {{ $tabNama }}
                                        </x-nav-item>
                                    </div>
                                    @endforeach
                                </x-nav-group>
                            </li>
                            @endcan
                        </ul>
                    </div>

                    {{-- Section: PENGELOLAAN & REFERENSI (Collapsible Groups with #627EF2 Timeline) --}}
                    <div class="space-y-1.5" :class="collapsed && 'flex flex-col items-center'">
                        <p :class="collapsed ? 'text-[10px] text-center' : 'text-[12px] px-3'"
                           class="text-text-muted font-medium uppercase tracking-[0.6px] mb-1.5 select-none">
                            Inventori & Sistem
                        </p>

                        @canany(['stok.view', 'stok.log.view'])
                        <x-nav-group
                            title="Stok & Mutasi"
                            :active="request()->routeIs('stok.*')"
                            keywords="stok mutasi opname ledger log pergerakan riwayat kartu stok">
                            <x-slot:icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </x-slot:icon>
                            @can('stok.view')
                            <div x-show="!menuQuery || 'stok mutasi opname ledger'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('stok.index') }}" :active="request()->routeIs('stok.index') || request()->routeIs('stok.ledger*') || request()->routeIs('stok.opname*')" :isChild="true">
                                    Stok & Mutasi
                                </x-nav-item>
                            </div>
                            @endcan
                            @can('stok.log.view')
                            <div x-show="!menuQuery || 'log pergerakan riwayat kartu stok'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('stok.log-pergerakan') }}" :active="request()->routeIs('stok.log-pergerakan*')" :isChild="true">
                                    Log Pergerakan
                                </x-nav-item>
                            </div>
                            @endcan
                        </x-nav-group>
                        @endcanany

                        @canany(['produk.view', 'kategori.view', 'varian.view', 'klasifikasi_abc.view', 'gudang.view', 'supplier.view', 'uom.view', 'bom.view', 'bom.import', 'divisi.view'])
                        <x-nav-group
                            title="Master Data"
                            :active="request()->routeIs('produk.*') || request()->routeIs('kategori.*') || request()->routeIs('varian.*') || request()->routeIs('klasifikasi-abc.*') || request()->routeIs('gudang.*') || request()->routeIs('supplier.*') || request()->routeIs('uom.*') || request()->routeIs('bom.*') || request()->routeIs('divisi.*')"
                            keywords="master data produk barang katalog sku kategori category varian variant klasifikasi abc analisa gudang warehouse supplier vendor pemasok satuan uom unit bom resep formula impor import csv divisi departemen">
                            <x-slot:icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                            </x-slot:icon>
                            @php
                                $navProdukKelompok = null;
                                if (request()->routeIs('produk.*')) {
                                    $navProdukRoute = request()->route('produk');
                                    $navProdukKelompok = $navProdukRoute instanceof \App\Models\Produk
                                        ? \App\Models\Produk::kelompokDariTipe($navProdukRoute->tipe)
                                        : \App\Models\Produk::normalizeKelompok(request()->query('kelompok'));
                                }
                            @endphp
                            @can('produk.bahan.view')
                            <div x-show="!menuQuery || 'master data produk bahan baku kemasan kemas botol material sku'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ \App\Models\Produk::indexUrl(\App\Models\Produk::KELOMPOK_BAHAN) }}" :active="$navProdukKelompok === \App\Models\Produk::KELOMPOK_BAHAN" :isChild="true">
                                    Bahan Baku & Kemasan
                                </x-nav-item>
                            </div>
                            @endcan
                            @can('produk.produk_jadi.view')
                            <div x-show="!menuQuery || 'master data produk jadi barang jadi katalog sku'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ \App\Models\Produk::indexUrl(\App\Models\Produk::KELOMPOK_PRODUK_JADI) }}" :active="$navProdukKelompok === \App\Models\Produk::KELOMPOK_PRODUK_JADI" :isChild="true">
                                    Produk Jadi
                                </x-nav-item>
                            </div>
                            @endcan
                            @can('kategori.view')
                            <div x-show="!menuQuery || 'master data kategori category'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('kategori.index') }}" :active="request()->routeIs('kategori.*')" :isChild="true">
                                    Kategori
                                </x-nav-item>
                            </div>
                            @endcan
                            @can('varian.view')
                            <div x-show="!menuQuery || 'master data varian variant'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('varian.index') }}" :active="request()->routeIs('varian.*')" :isChild="true">
                                    Varian
                                </x-nav-item>
                            </div>
                            @endcan

                            @can('klasifikasi_abc.view')
                            <div x-show="!menuQuery || 'master data klasifikasi abc analisa'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('klasifikasi-abc.index') }}" :active="request()->routeIs('klasifikasi-abc.*')" :isChild="true">
                                    Klasifikasi ABC
                                </x-nav-item>
                            </div>
                            @endcan

                            @can('gudang.view')
                            <div x-show="!menuQuery || 'master data gudang warehouse'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('gudang.index') }}" :active="request()->routeIs('gudang.*')" :isChild="true">
                                    Gudang
                                </x-nav-item>
                            </div>
                            @endcan

                            @can('supplier.view')
                            <div x-show="!menuQuery || 'master data supplier vendor pemasok'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('supplier.index') }}" :active="request()->routeIs('supplier.*')" :isChild="true">
                                    Supplier
                                </x-nav-item>
                            </div>
                            @endcan

                            @can('uom.view')
                            <div x-show="!menuQuery || 'master data satuan uom unit'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('uom.index') }}" :active="request()->routeIs('uom.*')" :isChild="true">
                                    Satuan (UOM)
                                </x-nav-item>
                            </div>
                            @endcan

                            @can('bom.view')
                            <div x-show="!menuQuery || 'master data bom resep formula'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('bom.index') }}" :active="request()->routeIs('bom.index') || request()->routeIs('bom.edit')" :isChild="true">
                                    BOM (Resep)
                                </x-nav-item>
                            </div>
                            @endcan

                            @can('bom.import')
                            <div x-show="!menuQuery || 'master data impor bom import csv'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('bom.import-page') }}" :active="request()->routeIs('bom.import*')" :isChild="true">
                                    Impor BOM
                                </x-nav-item>
                            </div>
                            @endcan

                            @can('divisi.view')
                            <div x-show="!menuQuery || 'master data divisi departemen'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('divisi.index') }}" :active="request()->routeIs('divisi.*')" :isChild="true">
                                    Divisi
                                </x-nav-item>
                            </div>
                            @endcan
                        </x-nav-group>
                        @endcanany

                        @canany(['reports.view', 'report.view'])
                        <x-nav-group
                            title="Laporan"
                            :active="request()->routeIs('reports.*')"
                            keywords="laporan report rekapitulasi audit lead time supplier">
                            <x-slot:icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </x-slot:icon>
                            <div x-show="!menuQuery || 'laporan report rekapitulasi audit'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('reports.index') }}" :active="request()->routeIs('reports.index') || (request()->routeIs('reports.*') && !request()->routeIs('reports.lead-time'))" :isChild="true">
                                    Laporan
                                </x-nav-item>
                            </div>
                            <div x-show="!menuQuery || 'lead time supplier laporan'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('reports.lead-time') }}" :active="request()->routeIs('reports.lead-time')" :isChild="true">
                                    Lead Time Supplier
                                </x-nav-item>
                            </div>
                        </x-nav-group>
                        @endcanany

                        @if(auth()->user()->can('user.manage') || auth()->user()->can('role.manage'))
                        <x-nav-group
                            title="Sistem"
                            :active="request()->routeIs('users.*') || request()->routeIs('roles.*')"
                            keywords="sistem pengguna user akun peran wewenang role permission">
                            <x-slot:icon>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </x-slot:icon>
                            @can('user.manage')
                            <div x-show="!menuQuery || 'sistem pengguna user akun'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('users.index') }}" :active="request()->routeIs('users.*')" :isChild="true">
                                    Pengguna
                                </x-nav-item>
                            </div>
                            @endcan
                            @can('role.manage')
                            <div x-show="!menuQuery || 'sistem peran wewenang role permission'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('roles.index') }}" :active="request()->routeIs('roles.*')" :isChild="true">
                                    Peran & Wewenang
                                </x-nav-item>
                            </div>
                            @endcan
                        </x-nav-group>
                        @endif
                    </div>
                </nav>

                {{-- Pulihkan posisi scroll sidebar sebelum paint --}}
                <script>
                    (function() {
                        var nav = document.getElementById('app-sidebar-nav');
                        if (!nav) return;
                        var saved = null;
                        try { saved = sessionStorage.getItem('hs_sidebar_scroll'); } catch(e) {}

                        function restore() {
                            if (saved !== null) {
                                nav.scrollTop = parseInt(saved, 10) || 0;
                            } else {
                                var active = nav.querySelector('[data-nav-active="true"]');
                                if (active) {
                                    var top = active.getBoundingClientRect().top - nav.getBoundingClientRect().top + nav.scrollTop;
                                    if (top + active.offsetHeight > nav.clientHeight) {
                                        nav.scrollTop = top - nav.clientHeight / 2;
                                    }
                                }
                            }
                        }

                        restore();
                        document.addEventListener('alpine:initialized', function() { requestAnimationFrame(restore); });

                        window.addEventListener('pagehide', function() {
                            try { sessionStorage.setItem('hs_sidebar_scroll', nav.scrollTop); } catch(e) {}
                        });
                    })();
                </script>

                {{-- Sidebar Footer (User Menu & Dev Role Switcher) --}}
                <div class="relative min-h-[72px] border-t border-[#E8E8E8] dark:border-border shrink-0 flex items-center"
                     :class="collapsed ? 'p-2 justify-center' : 'p-3'"
                     x-data="{ userMenuOpen: false }">

                    {{-- Dropdown popover --}}
                    <div x-show="userMenuOpen"
                         x-cloak
                         @click.away="userMenuOpen = false"
                         :class="collapsed ? 'left-full ml-2 bottom-0' : 'right-2 bottom-full left-2 mb-2'"
                         class="bg-white dark:bg-[#141414] absolute rounded-[12px] border border-[#E8E8E8] dark:border-border p-1.5 shadow-theme-lg z-50 min-w-56 text-sm">
                        <div class="border-b border-[#E8E8E8] dark:border-border px-3 py-2.5">
                            <p class="font-medium text-[#111111] dark:text-white text-xs leading-snug break-words whitespace-normal">{{ auth()->user()->name }}</p>
                            <p class="text-text-muted truncate text-[11px] mt-0.5">{{ auth()->user()->email }}</p>
                            <span class="inline-block mt-1.5 px-2 py-0.5 text-[10px] font-medium bg-[#F3F4F6] dark:bg-gray-800 text-[#101828] dark:text-gray-200 uppercase rounded-md">
                                {{ auth()->user()->roleName() ?? 'User' }}
                            </span>
                        </div>

                        {{-- Quick role switch for dev --}}
                        @if(app()->environment('local', 'testing'))
                        <div class="px-3 py-2.5 border-b border-[#E8E8E8] dark:border-border bg-[#FBFBFB] dark:bg-gray-900/50">
                            <label class="block text-[10px] font-medium text-text-muted uppercase tracking-wider mb-1.5">Ganti Peran (Dev):</label>
                            <form method="POST" action="{{ route('switch-role') }}">
                                @csrf
                                <select name="role" onchange="this.form.submit()" class="w-full text-xs rounded-[6px] border border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 text-[#101828] dark:text-white px-2.5 py-1.5 outline-none">
                                    @php
                                        $currentRole = auth()->user()?->roleName() ?? 'manager';
                                        $availableRoles = [
                                            'manager' => 'Manager (Andyka)',
                                            'purchasing' => 'Purchasing (Rina)',
                                            'gudang' => 'Gudang (Budi)',
                                            'operasional' => 'Operasional (Dedi)',
                                            'fulfillment' => 'Fulfillment (Sari)',
                                        ];
                                    @endphp
                                    @foreach($availableRoles as $rKey => $rLabel)
                                        <option value="{{ $rKey }}" {{ $currentRole === $rKey ? 'selected' : '' }}>
                                            {{ $rLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                        @endif

                        @if(app(\App\Services\Qa\QaClock::class)->enabled())
                        <a href="{{ route('qa.clock') }}" class="hover:bg-gray-100 dark:hover:bg-gray-800 text-text-active flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium transition-colors">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>QA Clock</span>
                        </a>
                        @endif

                        <form method="POST" action="{{ route('logout') }}" class="mt-1">
                            @csrf
                            <button type="submit" class="hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 dark:text-red-400 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium cursor-pointer transition-colors">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                <span>Keluar</span>
                            </button>
                        </form>
                    </div>

                    {{-- User button trigger --}}
                    <button type="button"
                            @click="userMenuOpen = !userMenuOpen"
                            :aria-expanded="userMenuOpen"
                            :class="collapsed ? 'justify-center p-1.5' : 'w-full gap-2.5 px-2.5 py-2'"
                            class="hover:bg-[#FBFBFB] dark:hover:bg-white/[0.04] flex items-center rounded-lg text-left transition-colors cursor-pointer w-full group">
                        <span class="bg-[#111111] dark:bg-white text-white dark:text-[#111111] relative flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-medium shadow-theme-xs">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            <span class="border-white dark:border-[#0A0A0A] absolute right-0 bottom-0 size-2.5 rounded-full border-2 bg-gray-400"></span>
                        </span>

                        <div x-show="!collapsed" class="min-w-0 flex-1 flex items-center justify-between gap-1.5">
                            <div class="min-w-0 flex-1">
                                <span class="block text-xs font-medium text-[#111111] dark:text-white leading-snug break-words whitespace-normal" title="{{ auth()->user()->name }}">
                                    {{ auth()->user()->name }}
                                </span>
                                <span class="text-text-muted block text-[11px] leading-tight mt-0.5 capitalize truncate">
                                    {{ auth()->user()->roleName() ?? 'User' }}
                                </span>
                            </div>
                            <svg class="text-text-muted group-hover:text-[#111111] dark:group-hover:text-white size-3.5 transition-transform shrink-0"
                                 :class="userMenuOpen ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </button>
                </div>
            </aside>
        </div>

        {{-- Main Canvas & Sticky Glass AppHeader --}}
        <div class="flex min-w-0 flex-1 flex-col transition-all duration-300">
            <header class="sticky top-0 z-[50] w-full h-16 px-4 sm:px-5 lg:px-8 backdrop-blur-md bg-white/80 dark:bg-gray-950/80 border-b border-gray-100 dark:border-gray-800/60 shadow-theme-xs flex items-center justify-between gap-3">
                <div class="min-w-0 flex-1 flex items-center gap-2.5">
                    <button type="button"
                            @click="mobileOpen = true"
                            class="lg:hidden size-10 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-200 flex items-center justify-center shadow-sm cursor-pointer shrink-0"
                            aria-label="Buka menu">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>

                    @if ($backUrl)
                        <a href="{{ $backUrl }}"
                           class="size-10 rounded-full bg-white dark:bg-gray-900 border border-[#e5e7eb] dark:border-gray-700 text-gray-700 dark:text-gray-200 flex items-center justify-center shadow-[0px_0px_0px_2px_rgba(43,127,255,0.2),0px_1px_3px_0px_rgba(0,0,0,0.1)] hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer shrink-0"
                           aria-label="Kembali">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        </a>
                    @endif

                    <div class="min-w-0 flex-1">
                        <h1 class="text-sm md:text-base font-semibold text-gray-900 dark:text-white tracking-tight leading-tight truncate">{!! $title !!}</h1>
                        @if ($subtitle)
                            <p class="text-[10px] md:text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 font-medium truncate">{!! $subtitle !!}</p>
                        @endif
                    </div>
                </div>

                {{-- Header Actions + Dual-Theme Toggle --}}
                <div class="flex items-center gap-2 shrink-0">
                    {{ $actions ?? '' }}

                    <button type="button"
                            @click="hsTheme.toggle(); isDark = hsTheme.isDark()"
                            :aria-label="isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'"
                            :title="isDark ? 'Mode Terang' : 'Mode Gelap'"
                            class="size-9 rounded-[8px] border border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-colors inline-flex items-center justify-center shrink-0 shadow-theme-xs">
                        <svg x-show="isDark" x-cloak class="size-4 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <svg x-show="!isDark" class="size-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                </div>
            </header>

            {{-- Main Page Content --}}
            <main class="flex-1 min-w-0 px-4 py-4 sm:px-5 lg:px-8 lg:py-5">
                <div class="w-full max-w-none">
                    <x-qa-clock-banner />

                    {{-- Flash Alert Messages --}}
                    @if(session('success'))
                        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(function() { show = false; }, 4000)" x-transition class="mb-4">
                            <div class="rounded-[8px] border border-border dark:border-border bg-card dark:bg-card px-4 py-3 text-xs text-foreground dark:text-foreground flex items-center justify-between shadow-theme-xs">
                                <div class="flex items-center gap-2">
                                    <svg class="size-4 text-foreground dark:text-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span class="font-medium">{{ session('success') }}</span>
                                </div>
                                <button type="button" @click="show = false" class="text-foreground dark:text-foreground hover:text-foreground dark:hover:text-white">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if(session('error'))
                        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(function() { show = false; }, 6000)" x-transition class="mb-4">
                            <div class="rounded-[8px] border border-red-200 dark:border-red-500/20 bg-red-50/80 dark:bg-red-500/10 px-4 py-3 text-xs text-red-900 dark:text-red-300 flex items-center justify-between shadow-theme-xs">
                                <div class="flex items-center gap-2">
                                    <svg class="size-4 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span class="font-medium">{{ session('error') }}</span>
                                </div>
                                <button type="button" @click="show = false" class="text-red-700 dark:text-red-300 hover:text-red-900 dark:hover:text-white">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mb-4 rounded-[8px] border border-red-200 dark:border-red-500/20 bg-red-50/80 dark:bg-red-500/10 px-4 py-3 text-xs text-red-900 dark:text-red-300 shadow-theme-xs">
                            <div class="flex items-center gap-1.5 font-medium mb-1">
                                <svg class="size-3.5 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>Terdapat Kesalahan Input:</span>
                            </div>
                            <ul class="list-disc list-inside ml-5 space-y-0.5 text-xs text-red-800 dark:text-red-300">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </main>

            {{-- Footer --}}
            <footer class="py-2.5 px-4 sm:px-6 text-[11px] text-text-muted flex justify-between items-center border-t border-[#E8E8E8] dark:border-border bg-white dark:bg-[#0A0A0A]">
                <span>&copy; {{ date('Y') }} Heaven Scent Lab Enterprise. All rights reserved.</span>
            </footer>
        </div>
    </div>

    {{-- Global jQuery & DataTables Enterprise Setup --}}
    <script src="{{ $vendorAsset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ $vendorAsset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script>
        $(function() {
            if ($.fn && $.fn.dataTable) {
                $.fn.dataTable.ext.errMode = 'none';

                // Header kolom mengikuti perataan isi kolom (angka rata kanan, aksi di tengah)
                $(document).on('init.dt', function(e, settings) {
                    (settings.aoColumns || []).forEach(function(col) {
                        var cls = col.sClass || '';
                        if (!col.nTh) return;
                        if (/\btext-right\b/.test(cls)) $(col.nTh).addClass('dt-align-right');
                    });
                });

                $.extend(true, $.fn.dataTable.defaults, {
                    dom: "<'dt-layout-header'lf><'overflow-x-auto't><'dt-layout-footer'ip>",
                    autoWidth: false,
                    searchDelay: 400,
                    language: {
                        processing: '<div class="py-2 text-xs text-text-muted font-medium">Memuat data...</div>',
                        lengthMenu: '<span class="text-xs text-text-muted font-normal">Tampilkan</span> _MENU_ <span class="text-xs text-text-muted font-normal">data</span>',
                        search: '',
                        searchPlaceholder: 'Cari data...',
                        paginate: {
                            previous: 'Back',
                            next: 'Next'
                        },
                        info: 'Menampilkan _START_ &ndash; _END_ dari _TOTAL_ data',
                        infoEmpty: 'Menampilkan 0 data',
                        infoFiltered: '(disaring dari _MAX_ total)',
                        zeroRecords: '<div class="py-12 text-center text-text-muted text-sm">Tidak ditemukan data yang sesuai</div>',
                        emptyTable: '<div class="py-12 text-center text-text-muted text-sm">Belum ada data tersedia</div>'
                    }
                });
            }

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            let sessionModalActive = false;
            $(document).ajaxError(function(event, jqXHR, settings, thrownError) {
                if (settings.url && settings.url.includes('/session/ping')) return;

                if (jqXHR.status === 401 || jqXHR.status === 419) {
                    if (sessionModalActive) return;
                    sessionModalActive = true;
                    window.location.href = "{{ route('login') }}";
                }
            });

            function refreshSessionSecurity() {
                fetch("{{ route('session.ping') }}", {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(res) {
                    if (res.status === 401 || res.status === 419) {
                        window.location.href = "{{ route('login') }}";
                        return null;
                    }
                    return res.json();
                })
                .then(function(data) {
                    if (data && data.csrf_token) {
                        $('meta[name="csrf-token"]').attr('content', data.csrf_token);
                        $('input[name="_token"]').val(data.csrf_token);
                        $.ajaxSetup({
                            headers: {
                                'X-CSRF-TOKEN': data.csrf_token,
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                    }
                })
                .catch(function() {});
            }

            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') refreshSessionSecurity();
            });

            window.addEventListener('pageshow', function(e) {
                if (e.persisted) refreshSessionSecurity();
            });

            setInterval(refreshSessionSecurity, 10 * 60 * 1000);
        });
    </script>

    <x-app-dialog />

    @stack('scripts')
</body>
</html>

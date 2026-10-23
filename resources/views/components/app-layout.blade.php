@props([
    'title' => 'Heaven Scent',
    'subtitle' => 'Sistem Manajemen Laboratorium Produksi',
    'breadcrumbs' => [],
    'backUrl' => null,
    'actions' => null,
])
<!DOCTYPE html>
<html lang="id" class="h-full bg-[oklch(0.985_0_0)]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <meta name="color-scheme" content="light">
    <title>{{ $title }} — Heaven Scent</title>
    
    <style>
        :root {
            background-color: oklch(0.985 0 0) !important;
            color-scheme: light;
        }
        html, body {
            background-color: oklch(0.985 0 0) !important;
        }
        [x-cloak] {
            display: none !important;
        }
        /* Sembunyikan halaman sampai Alpine siap supaya sidebar tidak tampil tanpa class lalu
           beranimasi (kesan "render ulang"). Browser menahan tampilan halaman lama selama itu.
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
            // Satu frame setelah Alpine menerapkan class, agar perubahan awal tidak ikut beranimasi.
            // setTimeout sebagai cadangan untuk tab latar belakang (rAF tertunda).
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
</head>
<body class="min-h-screen font-sans antialiased text-foreground bg-background flex flex-col"
      x-data="{
          collapsed: (function() {
              try {
                  return localStorage.getItem('devscore.sidebar-collapsed') === '1';
              } catch(e) {
                  return false;
              }
          })(),
          mobileOpen: false,
          menuQuery: '',
          toggleCollapsed() {
              this.collapsed = !this.collapsed;
              try {
                  localStorage.setItem('devscore.sidebar-collapsed', this.collapsed ? '1' : '0');
              } catch(e) {}
              setTimeout(() => {
                  try {
                      if (typeof $ !== 'undefined' && $.fn.dataTable) {
                          $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
                      }
                      window.dispatchEvent(new Event('resize'));
                  } catch(e) {}
              }, 250);
          }
      }">
    
    <div class="flex min-h-screen">
        {{-- Mobile Overlay --}}
        <div x-show="mobileOpen" x-cloak
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @click="mobileOpen = false"
             class="fixed inset-0 z-40 bg-black/40 lg:hidden"></div>

        {{-- DevScore Style Sidebar --}}
        <div class="fixed inset-y-0 left-0 z-50 transition-all duration-300 ease-in-out lg:sticky lg:top-0 lg:h-screen lg:z-40"
             :class="[
                 mobileOpen ? 'translate-x-0 w-64' : '-translate-x-full lg:translate-x-0',
                 collapsed ? 'lg:w-[72px]' : 'lg:w-64'
             ]">
            <aside class="bg-card flex h-full w-full flex-col border-r border-border overflow-hidden transition-all duration-300">
                
                {{-- Brand Header --}}
                <div class="flex items-center pt-5 pb-4"
                     :class="collapsed ? 'flex-col gap-4 px-2' : 'justify-between px-5'">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5"
                       :class="collapsed ? 'justify-center' : ''">
                        <span class="bg-primary text-primary-foreground flex size-9 items-center justify-center rounded-lg text-sm font-bold shrink-0 shadow-xs">
                            HS
                        </span>
                        <div x-show="!collapsed" class="leading-tight">
                            <span class="block text-sm font-bold tracking-wide text-foreground">HEAVEN SCENT</span>
                            <span class="text-muted-foreground block text-[11px]">Production Lab</span>
                        </div>
                    </a>
                    
                    <button type="button"
                            @click="mobileOpen = false; toggleCollapsed()"
                            :class="collapsed ? 'border border-border bg-muted/50 shadow-xs' : ''"
                            class="text-muted-foreground hover:bg-accent rounded-md p-1.5 transition-colors cursor-pointer"
                            aria-label="Toggle sidebar">
                        <template x-if="collapsed">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                        </template>
                        <template x-if="!collapsed">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                        </template>
                    </button>
                </div>

                {{-- Search Bar --}}
                <div x-show="!collapsed" class="border-b border-border px-5 pb-5">
                    <label class="relative block">
                        <svg class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search"
                               x-model="menuQuery"
                               placeholder="Cari menu"
                               class="bg-card placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/20 h-10 w-full rounded-lg border border-border pr-3 pl-9 text-sm outline-none transition" />
                    </label>
                </div>
                <div x-show="collapsed" class="border-b border-border pb-5 flex justify-center">
                    <button type="button" @click="toggleCollapsed()" class="text-muted-foreground hover:text-foreground rounded-md p-2 hover:bg-accent transition-colors" title="Cari menu">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </button>
                </div>

                {{-- Navigation Items --}}
                <nav id="app-sidebar-nav"
                     :class="collapsed ? 'px-2 scrollbar-none' : 'px-3'"
                     class="flex-1 space-y-6 overflow-y-auto py-5 sidebar-scroll">
                    
                    {{-- Section: MENU --}}
                    <div :class="collapsed && 'flex flex-col items-center'">
                        <p :class="collapsed ? 'text-[10px] text-center' : 'text-xs px-3'"
                           class="text-muted-foreground font-semibold uppercase tracking-wider mb-2 select-none">
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
                        <p :class="collapsed ? 'text-[10px] text-center' : 'text-xs px-3'"
                           class="text-muted-foreground font-semibold uppercase tracking-wider mb-2 select-none">
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

                            @can('rt.view')
                            <li class="w-full" x-show="!menuQuery || 'request transfer logistik rt'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('rt.index') }}" :active="request()->routeIs('rt.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                    </x-slot:icon>
                                    Request & Transfer
                                </x-nav-item>
                            </li>
                            @endcan

                            @can('analisa.view')
                            <li class="w-full" x-show="!menuQuery || 'analisa stok kpi analitik'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('analisa.index') }}" :active="request()->routeIs('analisa.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                    </x-slot:icon>
                                    Analisa Stok
                                </x-nav-item>
                            </li>
                            @endcan
                        </ul>
                    </div>

                    {{-- Section: STOK & MUTASI --}}
                    @can('stok.view')
                    <div :class="collapsed && 'flex flex-col items-center'">
                        <p :class="collapsed ? 'text-[10px] text-center' : 'text-xs px-3'"
                           class="text-muted-foreground font-semibold uppercase tracking-wider mb-2 select-none">
                            Stok & Mutasi
                        </p>
                        <ul class="space-y-1 w-full">
                            <li class="w-full" x-show="!menuQuery || 'stok mutasi opname ledger'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('stok.index') }}" :active="request()->routeIs('stok.index') || request()->routeIs('stok.ledger*') || request()->routeIs('stok.opname*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    </x-slot:icon>
                                    Stok & Mutasi
                                </x-nav-item>
                            </li>
                            <li class="w-full" x-show="!menuQuery || 'log pergerakan riwayat kartu stok'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('stok.log-pergerakan') }}" :active="request()->routeIs('stok.log-pergerakan*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </x-slot:icon>
                                    Log Pergerakan
                                </x-nav-item>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    {{-- Section: MASTER DATA --}}
                    <div :class="collapsed && 'flex flex-col items-center'">
                        <p :class="collapsed ? 'text-[10px] text-center' : 'text-xs px-3'"
                           class="text-muted-foreground font-semibold uppercase tracking-wider mb-2 select-none">
                            Master Data
                        </p>
                        <ul class="space-y-1 w-full">
                            @can('produk.view')
                            <li class="w-full" x-show="!menuQuery || 'produk barang katalog sku'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('produk.index') }}" :active="request()->routeIs('produk.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                    </x-slot:icon>
                                    Produk
                                </x-nav-item>
                            </li>
                            <li class="w-full" x-show="!menuQuery || 'kategori category'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('kategori.index') }}" :active="request()->routeIs('kategori.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                    </x-slot:icon>
                                    Kategori
                                </x-nav-item>
                            </li>
                            <li class="w-full" x-show="!menuQuery || 'varian variant'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('varian.index') }}" :active="request()->routeIs('varian.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                    </x-slot:icon>
                                    Varian
                                </x-nav-item>
                            </li>
                            <li class="w-full" x-show="!menuQuery || 'klasifikasi abc analisa'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('klasifikasi-abc.index') }}" :active="request()->routeIs('klasifikasi-abc.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                                    </x-slot:icon>
                                    Klasifikasi ABC
                                </x-nav-item>
                            </li>
                            @endcan

                            @can('gudang.view')
                            <li class="w-full" x-show="!menuQuery || 'gudang warehouse'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('gudang.index') }}" :active="request()->routeIs('gudang.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    </x-slot:icon>
                                    Gudang
                                </x-nav-item>
                            </li>
                            @endcan

                            @can('supplier.view')
                            <li class="w-full" x-show="!menuQuery || 'supplier vendor pemasok'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('supplier.index') }}" :active="request()->routeIs('supplier.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z"/></svg>
                                    </x-slot:icon>
                                    Supplier
                                </x-nav-item>
                            </li>
                            @endcan

                            @can('uom.view')
                            <li class="w-full" x-show="!menuQuery || 'satuan uom unit'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('uom.index') }}" :active="request()->routeIs('uom.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                                    </x-slot:icon>
                                    Satuan (UOM)
                                </x-nav-item>
                            </li>
                            @endcan

                            @can('bom.view')
                            <li class="w-full" x-show="!menuQuery || 'bom resep formula'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('bom.index') }}" :active="request()->routeIs('bom.index') || request()->routeIs('bom.edit')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </x-slot:icon>
                                    BOM (Resep)
                                </x-nav-item>
                            </li>
                            @endcan

                            @can('bom.import')
                            <li class="w-full" x-show="!menuQuery || 'impor bom import csv'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('bom.import-page') }}" :active="request()->routeIs('bom.import*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    </x-slot:icon>
                                    Impor BOM
                                </x-nav-item>
                            </li>
                            @endcan

                            @can('divisi.view')
                            <li class="w-full" x-show="!menuQuery || 'divisi departemen'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('divisi.index') }}" :active="request()->routeIs('divisi.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                    </x-slot:icon>
                                    Divisi
                                </x-nav-item>
                            </li>
                            @endcan
                        </ul>
                    </div>

                    {{-- Section: LAPORAN --}}
                    @can('report.view')
                    <div :class="collapsed && 'flex flex-col items-center'">
                        <p :class="collapsed ? 'text-[10px] text-center' : 'text-xs px-3'"
                           class="text-muted-foreground font-semibold uppercase tracking-wider mb-2 select-none">
                            Laporan
                        </p>
                        <ul class="space-y-1 w-full">
                            <li class="w-full" x-show="!menuQuery || 'laporan report rekapitulasi audit'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('reports.index') }}" :active="request()->routeIs('reports.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </x-slot:icon>
                                    Laporan
                                </x-nav-item>
                            </li>
                        </ul>
                    </div>
                    @endcan

                    {{-- Section: SISTEM --}}
                    @if(auth()->user()->can('user.manage') || auth()->user()->can('role.manage'))
                    <div :class="collapsed && 'flex flex-col items-center'">
                        <p :class="collapsed ? 'text-[10px] text-center' : 'text-xs px-3'"
                           class="text-muted-foreground font-semibold uppercase tracking-wider mb-2 select-none">
                            Sistem
                        </p>
                        <ul class="space-y-1 w-full">
                            @can('user.manage')
                            <li class="w-full" x-show="!menuQuery || 'pengguna user akun'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('users.index') }}" :active="request()->routeIs('users.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                                    </x-slot:icon>
                                    Pengguna
                                </x-nav-item>
                            </li>
                            @endcan

                            @can('role.manage')
                            <li class="w-full" x-show="!menuQuery || 'peran wewenang role permission'.includes(menuQuery.toLowerCase())">
                                <x-nav-item href="{{ route('roles.index') }}" :active="request()->routeIs('roles.*')">
                                    <x-slot:icon>
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                                    </x-slot:icon>
                                    Peran & Wewenang
                                </x-nav-item>
                            </li>
                            @endcan
                        </ul>
                    </div>
                    @endif
                </nav>
                {{-- Pulihkan posisi scroll sidebar sebelum paint agar tidak loncat ke atas saat pindah menu --}}
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
                        // Alpine mengubah padding (collapsed) setelah init; ulangi sekali
                        document.addEventListener('alpine:initialized', function() { requestAnimationFrame(restore); });

                        window.addEventListener('pagehide', function() {
                            try { sessionStorage.setItem('hs_sidebar_scroll', nav.scrollTop); } catch(e) {}
                        });
                    })();
                </script>

                {{-- DevScore Bottom User Menu --}}
                <div class="relative border-t border-border"
                     :class="collapsed ? 'p-3 flex justify-center' : 'p-4'"
                     x-data="{ userMenuOpen: false }">
                    
                    {{-- Dropdown popover --}}
                    <div x-show="userMenuOpen"
                         x-cloak
                         @click.away="userMenuOpen = false"
                         :class="collapsed ? 'left-full ml-2 bottom-0' : 'right-4 bottom-full left-4 mb-2'"
                         class="bg-card absolute rounded-xl border border-border p-1 shadow-lg z-50 w-56 text-sm">
                        <div class="border-b border-border px-3 py-2.5">
                            <p class="truncate font-semibold text-foreground">{{ auth()->user()->name }}</p>
                            <p class="text-muted-foreground truncate text-xs">{{ auth()->user()->email }}</p>
                            <span class="inline-block mt-1.5 px-2 py-0.5 text-[10px] font-semibold bg-muted text-foreground uppercase rounded-md">
                                {{ auth()->user()->roleName() ?? 'User' }}
                            </span>
                        </div>

                        {{-- Quick role switch for dev --}}
                        @if(app()->environment('local', 'testing'))
                        <div class="px-3 py-2 border-b border-border bg-muted/40">
                            <label class="block text-[10px] font-semibold text-muted-foreground uppercase mb-1">Ganti Peran (Dev):</label>
                            <form method="POST" action="{{ route('switch-role') }}">
                                @csrf
                                <select name="role" onchange="this.form.submit()" class="w-full text-xs rounded-md border border-border bg-card px-2 py-1 outline-none">
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

                        <form method="POST" action="{{ route('logout') }}" class="mt-1">
                            @csrf
                            <button type="submit" class="hover:bg-destructive/10 text-destructive flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium cursor-pointer transition-colors">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                <span>Keluar</span>
                            </button>
                        </form>
                    </div>

                    {{-- User button trigger --}}
                    <button type="button"
                            @click="userMenuOpen = !userMenuOpen"
                            :aria-expanded="userMenuOpen"
                            :class="collapsed ? 'justify-center p-1' : 'w-full gap-3 p-1'"
                            class="hover:bg-accent flex items-center rounded-lg text-left transition-colors cursor-pointer w-full">
                        <span class="bg-primary text-primary-foreground relative flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold shadow-xs">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            <span class="border-card absolute right-0 bottom-0 size-2.5 rounded-full border-2 bg-emerald-500"></span>
                        </span>
                        
                        <div x-show="!collapsed" class="min-w-0 flex-1 flex items-center justify-between">
                            <div class="truncate">
                                <span class="block truncate text-sm font-semibold text-foreground">{{ auth()->user()->name }}</span>
                                <span class="text-muted-foreground block truncate text-xs">{{ auth()->user()->roleName() ?? 'User' }}</span>
                            </div>
                            <svg class="text-muted-foreground size-4 transition-transform shrink-0 ml-1"
                                 :class="userMenuOpen ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </button>
                </div>
            </aside>
        </div>

        {{-- Main Canvas & DevScore Top Header --}}
        <div class="flex min-w-0 flex-1 flex-col transition-all duration-300">
            <header class="bg-card sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border px-4 shadow-xs sm:px-8">
                <button type="button"
                        @click="mobileOpen = true"
                        class="text-muted-foreground hover:bg-accent rounded-md p-1.5 lg:hidden cursor-pointer"
                        aria-label="Buka menu">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <div class="min-w-0 flex-1 flex items-center gap-3">
                    @if ($backUrl)
                        <a href="{{ $backUrl }}"
                           class="text-muted-foreground hover:bg-accent flex items-center justify-center rounded-md p-2 transition-colors cursor-pointer"
                           aria-label="Kembali">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        </a>
                    @endif
                    <div>
                        <h1 class="truncate text-base font-semibold text-foreground leading-tight">{!! $title !!}</h1>
                        <p class="text-muted-foreground truncate text-xs">{!! $subtitle !!}</p>
                    </div>
                </div>

                {{-- Header Actions --}}
                <div class="flex items-center gap-3 shrink-0">
                    {{ $actions ?? '' }}
                </div>
            </header>

            {{-- Main Page Content --}}
            <main class="flex-1 px-4 py-8 sm:px-8 lg:py-10">
                <div class="mx-auto w-full max-w-7xl">
                    {{-- Flash Alert Messages --}}
                    @if(session('success'))
                        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition class="mb-6">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-900 flex items-center justify-between shadow-xs">
                                <div class="flex items-center gap-2.5">
                                    <svg class="size-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span class="font-medium">{{ session('success') }}</span>
                                </div>
                                <button type="button" @click="show = false" class="text-emerald-700 hover:text-emerald-900">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if(session('error'))
                        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition class="mb-6">
                            <div class="rounded-xl border border-red-200 bg-red-50/80 px-4 py-3 text-sm text-red-900 flex items-center justify-between shadow-xs">
                                <div class="flex items-center gap-2.5">
                                    <svg class="size-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span class="font-medium">{{ session('error') }}</span>
                                </div>
                                <button type="button" @click="show = false" class="text-red-700 hover:text-red-900">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mb-6 rounded-xl border border-red-200 bg-red-50/80 px-4 py-3 text-sm text-red-900 shadow-xs">
                            <div class="flex items-center gap-2 font-semibold mb-1">
                                <svg class="size-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>Terdapat Kesalahan Input:</span>
                            </div>
                            <ul class="list-disc list-inside ml-6 space-y-0.5 text-xs text-red-800">
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
            <footer class="py-4 px-4 sm:px-8 text-xs text-muted-foreground flex justify-between items-center border-t border-border bg-card">
                <span>&copy; {{ date('Y') }} Heaven Scent Lab Enterprise. All rights reserved.</span>
                <span>DevScore Theme Edition</span>
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

                $.extend(true, $.fn.dataTable.defaults, {
                    dom: "<'dt-layout-header'lf><'overflow-x-auto't><'dt-layout-footer'ip>",
                    autoWidth: false,
                    searchDelay: 400,
                    language: {
                        processing: '<div class="py-2 text-xs text-muted-foreground font-medium">Memuat data...</div>',
                        lengthMenu: '<span class="text-xs text-muted-foreground font-normal">Tampilkan</span> _MENU_ <span class="text-xs text-muted-foreground font-normal">data</span>',
                        search: '',
                        searchPlaceholder: 'Cari data...',
                        paginate: {
                            previous: '‹',
                            next: '›'
                        },
                        info: 'Menampilkan _START_ &ndash; _END_ dari _TOTAL_ data',
                        infoEmpty: 'Menampilkan 0 data',
                        infoFiltered: '(disaring dari _MAX_ total)',
                        zeroRecords: '<div class="py-12 text-center text-muted-foreground text-sm">Tidak ditemukan data yang sesuai</div>',
                        emptyTable: '<div class="py-12 text-center text-muted-foreground text-sm">Belum ada data tersedia</div>'
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

    @stack('scripts')
</body>
</html>

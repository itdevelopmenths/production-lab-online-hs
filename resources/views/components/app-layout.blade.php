@props(['title' => 'Heaven Scent', 'breadcrumbs' => []])
<!DOCTYPE html>
<html lang="id" class="h-full bg-[#f4f6f9]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Heaven Scent Enterprise</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="min-h-screen font-sans antialiased text-gray-800 bg-[#f4f6f9] flex flex-col" x-data="{ sidebarOpen: window.innerWidth >= 1024 }">
    <div class="min-h-screen flex flex-1">
        {{-- Sidebar Overlay (mobile) --}}
        <div x-show="sidebarOpen" x-cloak
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @click="sidebarOpen = false"
             class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

        {{-- Desktop Spacer --}}
        <div class="hidden lg:block shrink-0 transition-all duration-200 ease-in-out" :class="sidebarOpen ? 'w-60' : 'w-0'"></div>

        {{-- Dark Sidebar (AdminLTE 4 Style) --}}
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 w-60 bg-[#1f2937] border-r border-gray-800 transform transition-transform duration-200 ease-in-out flex flex-col h-screen select-none">
            
            {{-- Brand Header --}}
            <div class="flex items-center gap-2.5 h-14 px-4 bg-[#111827] border-b border-gray-800 shrink-0">
                <div class="w-7 h-7 rounded-sm bg-primary-600 border border-primary-500 flex items-center justify-center shadow-xs">
                    <span class="text-white font-black text-xs tracking-wider">HS</span>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xs font-bold text-white tracking-wide truncate">HEAVEN SCENT</h1>
                    <p class="text-[9px] text-gray-400 font-semibold tracking-widest uppercase">Production Lab</p>
                </div>
            </div>

            {{-- Navigation Items --}}
            <nav class="p-2 space-y-0.5 overflow-y-auto flex-1 text-xs">
                <x-nav-item href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    </x-slot:icon>
                    Dashboard
                </x-nav-item>

                <p class="px-3 pt-3 pb-1 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Operasional</p>

                @can('purchasing.view')
                <x-nav-item href="{{ route('purchasing.index') }}" :active="request()->routeIs('purchasing.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </x-slot:icon>
                    Purchasing
                </x-nav-item>
                @endcan

                @can('batch.view')
                <x-nav-item href="{{ route('batches.index') }}" :active="request()->routeIs('batches.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </x-slot:icon>
                    Produksi
                </x-nav-item>
                @endcan

                @can('rt.view')
                <x-nav-item href="{{ route('rt.index') }}" :active="request()->routeIs('rt.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    </x-slot:icon>
                    Request & Transfer
                </x-nav-item>
                @endcan

                @can('analisa.view')
                <x-nav-item href="{{ route('analisa.index') }}" :active="request()->routeIs('analisa.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </x-slot:icon>
                    Analisa Stok
                </x-nav-item>
                @endcan

                <p class="px-3 pt-3 pb-1 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Stok & Mutasi</p>

                @can('stok.view')
                <x-nav-item href="{{ route('stok.index') }}" :active="request()->routeIs('stok.index') || request()->routeIs('stok.ledger*') || request()->routeIs('stok.opname*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                    </x-slot:icon>
                    Stok & Mutasi
                </x-nav-item>

                <x-nav-item href="{{ route('stok.log-pergerakan') }}" :active="request()->routeIs('stok.log-pergerakan*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </x-slot:icon>
                    Log Pergerakan Stok
                </x-nav-item>
                @endcan

                <p class="px-3 pt-3 pb-1 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Master Data</p>

                @can('produk.view')
                <x-nav-item href="{{ route('produk.index') }}" :active="request()->routeIs('produk.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </x-slot:icon>
                    Produk
                </x-nav-item>

                <x-nav-item href="{{ route('kategori.index') }}" :active="request()->routeIs('kategori.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </x-slot:icon>
                    Kategori
                </x-nav-item>

                <x-nav-item href="{{ route('varian.index') }}" :active="request()->routeIs('varian.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    </x-slot:icon>
                    Varian
                </x-nav-item>
                @endcan

                @can('gudang.view')
                <x-nav-item href="{{ route('gudang.index') }}" :active="request()->routeIs('gudang.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </x-slot:icon>
                    Gudang
                </x-nav-item>
                @endcan

                @can('supplier.view')
                <x-nav-item href="{{ route('supplier.index') }}" :active="request()->routeIs('supplier.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z"/></svg>
                    </x-slot:icon>
                    Supplier
                </x-nav-item>
                @endcan

                @can('uom.view')
                <x-nav-item href="{{ route('uom.index') }}" :active="request()->routeIs('uom.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                    </x-slot:icon>
                    Satuan (UOM)
                </x-nav-item>
                @endcan

                @can('bom.view')
                <x-nav-item href="{{ route('bom.index') }}" :active="request()->routeIs('bom.index') || request()->routeIs('bom.edit')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </x-slot:icon>
                    BOM (Resep)
                </x-nav-item>
                @endcan

                @can('bom.import')
                <x-nav-item href="{{ route('bom.import-page') }}" :active="request()->routeIs('bom.import*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </x-slot:icon>
                    Impor BOM
                </x-nav-item>
                @endcan

                @can('divisi.view')
                <x-nav-item href="{{ route('divisi.index') }}" :active="request()->routeIs('divisi.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </x-slot:icon>
                    Divisi
                </x-nav-item>
                @endcan

                @can('report.view')
                <p class="px-3 pt-3 pb-1 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Laporan</p>
                <x-nav-item href="{{ route('reports.index') }}" :active="request()->routeIs('reports.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </x-slot:icon>
                    Laporan
                </x-nav-item>
                @endcan

                @if(auth()->user()->can('user.manage') || auth()->user()->can('role.manage'))
                <p class="px-3 pt-3 pb-1 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Sistem</p>
                @endif

                @can('user.manage')
                <x-nav-item href="{{ route('users.index') }}" :active="request()->routeIs('users.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                    </x-slot:icon>
                    Pengguna
                </x-nav-item>
                @endcan

                @can('role.manage')
                <x-nav-item href="{{ route('roles.index') }}" :active="request()->routeIs('roles.*')">
                    <x-slot:icon>
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    </x-slot:icon>
                    Peran & Wewenang
                </x-nav-item>
                @endcan
            </nav>
        </aside>

        {{-- Main Canvas --}}
        <div class="flex-1 flex flex-col min-w-0 min-h-screen">
            {{-- Top Navbar (AdminLTE 4 White Header) --}}
            <header class="sticky top-0 z-30 h-14 bg-white border-b border-gray-200 flex items-center justify-between px-4 lg:px-6 shrink-0 shadow-2xs">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = !sidebarOpen" class="p-1.5 rounded-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition cursor-pointer" title="Toggle Sidebar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <span class="hidden sm:inline-block text-xs font-semibold text-gray-700 uppercase tracking-wider">
                        {{ $title }}
                    </span>
                </div>

                <div class="flex items-center gap-2 sm:gap-3" x-data="{ open: false }">
                    {{-- Quick Role Switcher --}}
                    @if(app()->environment('local', 'testing'))
                    <form method="POST" action="{{ route('switch-role') }}" id="role-switcher-form" class="flex items-center">
                        @csrf
                        <div class="flex items-center gap-1.5 px-2 py-1 bg-amber-50 border border-amber-200 rounded-sm text-xs text-amber-800 shadow-2xs">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            <label for="switch-role-select" class="hidden sm:inline font-bold text-amber-900 text-[11px] uppercase">Peran:</label>
                            <select id="switch-role-select" name="role" onchange="this.form.submit()" class="bg-transparent border-0 py-0 pl-1 pr-6 text-xs font-semibold text-amber-900 focus:ring-0 focus:outline-none cursor-pointer">
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
                        </div>
                    </form>
                    @endif

                    {{-- User Profile Dropdown --}}
                    <div class="relative">
                        <button @click="open = !open" class="flex items-center gap-2 px-2 py-1 rounded-sm text-xs text-gray-700 hover:bg-gray-100 transition cursor-pointer">
                            <div class="w-7 h-7 rounded-sm bg-primary-700 flex items-center justify-center text-white font-bold text-xs shadow-2xs">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </div>
                            <div class="hidden sm:block text-left">
                                <span class="block text-xs font-bold text-gray-900 leading-tight truncate max-w-[120px]">{{ auth()->user()->name }}</span>
                                <span class="block text-[10px] text-gray-500 uppercase tracking-tight">{{ auth()->user()->roleName() ?? 'User' }}</span>
                            </div>
                            <svg class="w-3.5 h-3.5 hidden sm:block text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-1 w-52 bg-white rounded-sm shadow-md border border-gray-200 py-1 z-50 text-xs">
                            <div class="px-3 py-2 border-b border-gray-100 bg-gray-50">
                                <p class="font-bold text-gray-900 truncate">{{ auth()->user()->name }}</p>
                                <p class="text-[11px] text-gray-500 truncate">{{ auth()->user()->email }}</p>
                                <span class="inline-block mt-1 px-1.5 py-0.25 text-[10px] bg-primary-100 text-primary-800 font-bold uppercase rounded-sm">
                                    {{ auth()->user()->roleName() ?? 'User' }}
                                </span>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-3 py-2 text-rose-600 hover:bg-rose-50 flex items-center gap-2 cursor-pointer font-medium">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    Keluar Sistem
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Main Body --}}
            <main class="flex-1 p-4 lg:p-6">
                @if(session('success'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition class="mb-4">
                        <x-alert type="success" title="Sukses:">
                            {{ session('success') }}
                        </x-alert>
                    </div>
                @endif
                @if(session('error'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition class="mb-4">
                        <x-alert type="danger" title="Perhatian:">
                            {{ session('error') }}
                        </x-alert>
                    </div>
                @endif
                @if($errors->any())
                    <div class="mb-4">
                        <x-alert type="danger" title="Terdapat Kesalahan Input:">
                            <ul class="list-disc list-inside mt-1 space-y-0.5">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-alert>
                    </div>
                @endif

                {{ $slot }}
            </main>

            {{-- AdminLTE Footer --}}
            <footer class="bg-white border-t border-gray-200 py-2.5 px-4 lg:px-6 text-xs text-gray-500 flex flex-col sm:flex-row justify-between items-center gap-2 shrink-0 mt-auto">
                <div>
                    <strong>Copyright &copy; 2026 <a href="{{ route('dashboard') }}" class="text-primary-700 hover:underline font-semibold">Heaven Scent Lab</a>.</strong> All rights reserved.
                </div>
            </footer>
        </div>
    </div>

    {{-- Global jQuery & DataTables Enterprise Setup --}}
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(function() {
            // Enterprise: Suppress standard browser alerts on DataTables AJAX errors
            if ($.fn && $.fn.dataTable) {
                $.fn.dataTable.ext.errMode = 'none';

                $.extend(true, $.fn.dataTable.defaults, {
                    dom: "<'dt-layout-header'lf><'overflow-x-auto't><'dt-layout-footer'ip>",
                    autoWidth: false,
                    language: {
                        processing: '<div class="py-2 text-xs text-gray-500 font-medium">Memuat data...</div>',
                        lengthMenu: '<span class="text-xs text-gray-600 font-normal">Tampilkan</span> _MENU_ <span class="text-xs text-gray-600 font-normal">data</span>',
                        search: '<span class="text-xs text-gray-600 font-normal">Cari:</span>',
                        searchPlaceholder: 'Ketik kata kunci...',
                        paginate: {
                            previous: '‹',
                            next: '›'
                        },
                        info: 'Menampilkan _START_ &ndash; _END_ dari _TOTAL_ data',
                        infoEmpty: 'Menampilkan 0 data',
                        infoFiltered: '(disaring dari _MAX_ data)',
                        zeroRecords: '<div class="py-10 text-center text-gray-400 text-xs">Tidak ditemukan data yang sesuai</div>',
                        emptyTable: '<div class="py-10 text-center text-gray-400 text-xs">Belum ada data tersedia</div>'
                    }
                });
            }

            // Global jQuery Ajax Setup with CSRF Token
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            // Enterprise Global Ajax Error Interceptor (Graceful 401 & 419 handling)
            let sessionModalActive = false;
            $(document).ajaxError(function(event, jqXHR, settings, thrownError) {
                if (settings.url && settings.url.includes('/session/ping')) {
                    return;
                }

                if (jqXHR.status === 401 || jqXHR.status === 419) {
                    if (sessionModalActive) return;
                    sessionModalActive = true;

                    const title = jqXHR.status === 419 ? 'Sesi Telah Kedaluwarsa' : 'Sesi Login Berakhir';
                    const message = jqXHR.status === 419
                        ? 'Token keamanan sesi kerja Anda telah kedaluwarsa. Silakan login kembali.'
                        : 'Sesi autentikasi Anda telah berakhir untuk alasan keamanan. Silakan login kembali.';

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: title,
                            text: message,
                            confirmButtonText: 'Login Kembali',
                            confirmButtonColor: '#4f46e5',
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then(function() {
                            window.location.href = "{{ route('login') }}";
                        });
                    } else {
                        window.location.href = "{{ route('login') }}";
                    }
                }
            });

            // Enterprise Session Ping & Token Refresh Lifecycle
            function refreshSessionSecurity() {
                fetch("{{ route('session.ping') }}", {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(res) {
                    if (res.status === 401 || res.status === 419) {
                        if (sessionModalActive) return null;
                        sessionModalActive = true;

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Sesi Telah Berakhir',
                                text: 'Sesi kerja Anda telah kedaluwarsa. Silakan login kembali.',
                                confirmButtonText: 'Login Kembali',
                                confirmButtonColor: '#4f46e5',
                                allowOutsideClick: false,
                                allowEscapeKey: false
                            }).then(function() {
                                window.location.href = "{{ route('login') }}";
                            });
                        } else {
                            window.location.href = "{{ route('login') }}";
                        }
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

            // Listen for tab focus / wake-up
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') {
                    refreshSessionSecurity();
                }
            });

            window.addEventListener('pageshow', function(e) {
                if (e.persisted) {
                    refreshSessionSecurity();
                }
            });

            // Heartbeat check every 10 minutes
            setInterval(refreshSessionSecurity, 10 * 60 * 1000);
        });
    </script>

    @stack('scripts')
</body>
</html>

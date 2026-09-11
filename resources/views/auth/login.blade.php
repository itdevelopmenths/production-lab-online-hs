<!DOCTYPE html>
<html lang="id" class="h-full bg-[#f4f6f9]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — Heaven Scent Enterprise</title>
    <link rel="icon" type="image/png" href="{{ asset('Logo HS - black.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased text-gray-800 bg-[#f4f6f9] flex flex-col justify-center py-10 px-4 sm:px-6 lg:px-8 selection:bg-primary-500 selection:text-white">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        {{-- Brand Header --}}
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-sm bg-primary-600 border border-primary-500 flex items-center justify-center shadow-xs">
                    <span class="text-white font-black text-base tracking-wider">HS</span>
                </div>
                <div class="text-left">
                    <span class="text-lg font-black text-gray-900 tracking-tight leading-none block">HEAVEN SCENT</span>
                    <span class="text-[10px] font-bold text-gray-500 tracking-widest uppercase block mt-1">Laboratorium Produksi</span>
                </div>
            </div>
            <p class="text-xs text-gray-500 font-normal">Sistem Manajemen Batch & Kontrol Mutasi Gudang</p>
        </div>

        {{-- Login Box (AdminLTE 4 Sharp Card) --}}
        <div class="bg-white rounded-sm border border-gray-200 shadow-xs p-6 sm:p-8">
            <div class="mb-5 pb-3 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 leading-tight">Otentikasi Akun</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Silakan masuk menggunakan kredensial Anda</p>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[10px] font-bold bg-primary-50 text-primary-700 border border-primary-200">
                    LAB v3.2
                </span>
            </div>

            @if($errors->any())
            <div class="mb-4 p-3 bg-rose-50 border-l-3 border-rose-600 rounded-r-sm text-xs text-rose-800">
                <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <p class="font-semibold mb-0.5">Akses Ditolak:</p>
                        @foreach($errors->all() as $error)
                            <p class="text-rose-700 leading-relaxed">{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Alamat Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
                        </span>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                               class="w-full pl-9 pr-3 py-2 rounded-sm border border-gray-300 bg-white text-xs text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition shadow-xs"
                               placeholder="nama@heavenscent.id">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Kata Sandi</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                        <input type="password" name="password" required
                               class="w-full pl-9 pr-3 py-2 rounded-sm border border-gray-300 bg-white text-xs text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition shadow-xs"
                               placeholder="••••••••">
                    </div>
                </div>

                <div class="flex items-center justify-between pt-0.5">
                    <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="w-3.5 h-3.5 rounded-sm border-gray-300 text-primary-600 focus:ring-primary-500">
                        <span>Ingat sesi perangkat ini</span>
                    </label>
                </div>

                <button type="submit" class="w-full py-2.5 px-4 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded-sm shadow-xs transition focus:outline-none focus:ring-1 focus:ring-primary-500 focus:ring-offset-1 select-none flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    <span>Masuk ke Dashboard</span>
                </button>
            </form>

            @if(app()->environment('local', 'testing'))
            <div class="mt-6 pt-4 border-t border-gray-200">
                <div class="flex items-center justify-between mb-2.5">
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">⚡ Akses Cepat Pengujian (Dev)</p>
                    <span class="text-[9px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded-sm">Quick Login</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                    @php
                        $quickRoles = [
                            'manager' => 'Manager',
                            'purchasing' => 'Purchasing',
                            'gudang' => 'Gudang',
                            'operasional' => 'Operasional',
                            'fulfillment' => 'Fulfillment',
                        ];
                    @endphp
                    @foreach($quickRoles as $rKey => $rLabel)
                    <form method="POST" action="{{ route('switch-role') }}">
                        @csrf
                        <input type="hidden" name="role" value="{{ $rKey }}">
                        <button type="submit" class="w-full py-1.5 px-2 text-xs font-medium text-gray-700 bg-gray-50 hover:bg-primary-50 hover:text-primary-700 hover:border-primary-400 border border-gray-200 rounded-sm transition text-center truncate shadow-2xs cursor-pointer">
                            {{ $rLabel }}
                        </button>
                    </form>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Footer --}}
        <footer class="mt-6 text-center text-xs text-gray-400">
            <p><strong>Copyright &copy; {{ date('Y') }} <span class="text-gray-600 font-semibold">Heaven Scent Lab</span>.</strong> All rights reserved.</p>
            <p class="text-[11px] text-gray-400 mt-1">Sistem Otentikasi Terintegrasi Enterprise v3.2.0</p>
        </footer>
    </div>
</body>
</html>

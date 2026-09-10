<!DOCTYPE html>
<html lang="id" class="h-full bg-gradient-to-br from-gray-50 to-primary-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — Heaven Scent</title>
    <link rel="icon" type="image/png" href="{{ asset('Logo HS - black.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center mb-4">
                <img src="{{ asset('Logo HS - black.png') }}" alt="Heaven Scent Logo" class="w-16 h-16 object-contain">
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Heaven Scent</h1>
            <p class="text-gray-500 mt-1">Sistem Pencatatan Batch Produksi</p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl shadow-gray-200/50 border border-gray-100 p-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-6">Masuk ke Akun Anda</h2>

            @if($errors->any())
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent focus:bg-white transition" placeholder="admin@heavenscent.id">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent focus:bg-white transition" placeholder="••••••••">
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-500">
                        Ingat saya
                    </label>
                </div>
                <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold rounded-xl hover:from-primary-600 hover:to-primary-700 transition shadow-lg shadow-primary-500/25 text-sm">
                    Masuk
                </button>
            </form>

            @if(app()->environment('local', 'testing'))
            <div class="mt-6 pt-5 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2.5 text-center">⚡ Quick Login (Local Dev)</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
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
                        <button type="submit" class="w-full py-1.5 px-2 text-xs font-medium text-gray-700 bg-gray-50 hover:bg-primary-50 hover:text-primary-700 border border-gray-200 rounded-lg transition text-center truncate">
                            {{ $rLabel }}
                        </button>
                    </form>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">&copy; {{ date('Y') }} Heaven Scent. All rights reserved.</p>
    </div>
</body>
</html>

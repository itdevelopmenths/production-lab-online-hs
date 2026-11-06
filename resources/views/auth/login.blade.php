<!DOCTYPE html>
<html lang="id" class="h-full bg-zinc-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — Heaven Scent</title>
    <link rel="icon" type="image/png" href="{{ asset('Logo HS - black.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        input[type="checkbox"] {
            border-radius: 0.25rem;
            -webkit-appearance: none;
            appearance: none;
            width: 1rem;
            height: 1rem;
            border: 1px solid var(--color-border, #d4d4d8);
            background-color: transparent;
            display: inline-grid;
            place-content: center;
            cursor: pointer;
            margin: 0;
            vertical-align: middle;
            transition: all 0.15s ease;
        }
        input[type="checkbox"]:hover {
            border-color: var(--color-foreground, #18181b);
        }
        input[type="checkbox"]:checked {
            background-color: var(--color-primary, #18181b);
            border-color: var(--color-primary, #18181b);
        }
        input[type="checkbox"]:checked::before {
            content: "";
            width: 0.5rem;
            height: 0.5rem;
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
            background-color: #ffffff;
        }
    </style>
</head>
<body class="min-h-screen font-sans antialiased text-foreground bg-zinc-50 flex items-center justify-center p-4 selection:bg-neutral-900 selection:text-white">

    <div class="w-full max-w-sm bg-card text-card-foreground rounded-lg border border-border p-6 shadow-2xs">
        {{-- Brand Header --}}
        <div class="mb-5 flex items-center gap-3">
            <span class="bg-primary text-primary-foreground size-9 flex items-center justify-center font-bold text-xs rounded-md shadow-2xs shrink-0">
                HS
            </span>
            <div>
                <h1 class="text-base font-bold text-foreground tracking-tight leading-tight">Masuk ke Heaven Scent</h1>
                <p class="text-muted-foreground text-xs mt-0.5">Sistem Manajemen Laboratorium Produksi</p>
            </div>
        </div>

        {{-- Alert Error --}}
        @if($errors->any())
            <div class="mb-4 p-3 border border-red-200 bg-red-50 text-xs text-red-900 rounded-md">
                <div class="flex items-start gap-2">
                    <svg class="size-4 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div class="space-y-0.5">
                        <p class="font-bold text-red-950">Akses Ditolak:</p>
                        @foreach($errors->all() as $error)
                            <p class="text-red-800">{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Form Login --}}
        <form method="POST" action="{{ route('login') }}" class="space-y-3.5">
            @csrf

            {{-- Field Email --}}
            <div class="space-y-1">
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Email
                </label>
                <input id="email"
                       type="email"
                       name="email"
                       value="{{ old('email') }}"
                       required
                       autofocus
                       placeholder="nama@heavenscent.id"
                       class="w-full h-8.5 px-3 text-xs bg-background border border-border rounded-md text-foreground placeholder:text-muted-foreground focus:border-foreground focus:outline-none focus:ring-1 focus:ring-foreground/20 transition shadow-2xs" />
            </div>

            {{-- Field Password --}}
            <div class="space-y-1">
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Password
                </label>
                <div class="relative">
                    <input id="password"
                           type="password"
                           name="password"
                           required
                           placeholder="••••••••"
                           class="w-full h-8.5 px-3 pr-9 text-xs bg-background border border-border rounded-md text-foreground placeholder:text-muted-foreground focus:border-foreground focus:outline-none focus:ring-1 focus:ring-foreground/20 transition shadow-2xs" />
                    <button type="button"
                            id="togglePassword"
                            class="absolute inset-y-0 right-0 px-2.5 flex items-center text-muted-foreground hover:text-foreground cursor-pointer focus:outline-none"
                            title="Tampilkan / Sembunyikan sandi">
                        <svg id="eyeIcon" class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Remember Me --}}
            <div class="pt-0.5">
                <label class="inline-flex items-center gap-2 text-xs text-foreground cursor-pointer select-none">
                    <input type="checkbox"
                           name="remember" />
                    <span>Ingat saya</span>
                </label>
            </div>

            {{-- Submit Button --}}
            <button type="submit"
                    class="w-full h-8.5 px-3 mt-1 bg-primary text-primary-foreground hover:bg-neutral-800 active:bg-black text-xs font-semibold tracking-wide rounded-md transition-all flex items-center justify-center gap-2 cursor-pointer shadow-2xs">
                <span>Masuk</span>
                <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </button>
        </form>

        {{-- Akses Cepat Pengujian Dev (Khusus local / testing) --}}
        @if(app()->environment('local', 'testing'))
            <div class="mt-5 pt-4 border-t border-border">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                        Akses Cepat (Dev)
                    </span>
                    <span class="text-[9px] font-mono text-muted-foreground">Local Only</span>
                </div>
                <div class="grid grid-cols-2 gap-1.5">
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
                        <form method="POST" action="{{ route('login') }}" class="w-full">
                            @csrf
                            <input type="hidden" name="email" value="{{ $rKey }}@heavenscent.id">
                            <input type="hidden" name="password" value="password">
                            <button type="submit"
                                    class="w-full text-left px-2 py-1 border border-border/70 rounded bg-muted/40 hover:bg-accent hover:border-foreground/30 text-[10px] font-medium text-foreground transition-all cursor-pointer truncate">
                                {{ $rLabel }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Footer Form --}}
        <div class="mt-4 pt-3 border-t border-border text-center text-[10px] text-muted-foreground">
            <span>Heaven Scent Production System &copy; {{ date('Y') }}</span>
        </div>
    </div>

    {{-- Script Interaktif: Toggle Lihat Password --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var toggleBtn = document.getElementById('togglePassword');
            var passwordInput = document.getElementById('password');
            var eyeIcon = document.getElementById('eyeIcon');

            if (toggleBtn && passwordInput && eyeIcon) {
                toggleBtn.addEventListener('click', function () {
                    var isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');

                    if (isPassword) {
                        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />';
                    } else {
                        eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
                    }
                });
            }
        });
    </script>
</body>
</html>
<!DOCTYPE html>
<html lang="id" class="h-full">
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
    <title>Masuk — Heaven Scent</title>
    <link rel="icon" type="image/png" href="{{ asset('Logo HS - black.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased text-text-base bg-gray-50 dark:bg-gray-950 flex items-center justify-center p-4 sm:p-6 selection:bg-black selection:text-white dark:selection:bg-white dark:selection:text-black"
      x-data="{
          isDark: document.documentElement.classList.contains('dark'),
          init() {
              var self = this;
              window.addEventListener('hs-theme-changed', function(e) {
                  self.isDark = (e.detail && e.detail.theme === 'dark') || document.documentElement.classList.contains('dark');
              });
          }
      }">

    {{-- Top-Right Theme Toggle --}}
    <button type="button"
            @click="window.hsTheme && window.hsTheme.toggle(); isDark = window.hsTheme ? window.hsTheme.isDark() : document.documentElement.classList.contains('dark')"
            :aria-label="isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'"
            class="fixed top-4 right-4 z-50 size-9 border border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors inline-flex items-center justify-center shadow-theme-xs rounded-[6px]">
        <svg x-show="isDark" x-cloak class="size-4 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        <svg x-show="!isDark" class="size-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
        </svg>
    </button>

    <div class="w-full max-w-md bg-white dark:bg-gray-900 text-[#101828] dark:text-white rounded-[12px] border border-[#E8E8E8] dark:border-border shadow-theme-md overflow-hidden">
        {{-- Brand Header --}}
        <div class="px-6 py-5 bg-white dark:bg-gray-900 border-b border-[#E8E8E8] dark:border-border flex items-center gap-4">
            <div class="relative size-[56px] rounded-[12px] bg-gradient-to-b from-[#292929] from-[17%] to-[#0a0a0a] border border-[#212121] shadow-[0_2px_6px_rgba(0,0,0,0.12),inset_0_1px_1px_0_rgba(255,255,255,0.15),inset_0_-1px_2px_0_rgba(255,255,255,0.35)] flex items-center justify-center shrink-0">
                <div class="w-[42px] h-[24px] shrink-0 pointer-events-none">
                    <img src="{{ asset('Logo HS - black.png') }}" alt="Heaven Scent" class="w-full h-full object-contain brightness-0 invert pointer-events-none select-none" />
                </div>
            </div>
            <div class="min-w-0">
                <h1 class="text-base font-semibold text-[#101828] dark:text-white tracking-tight leading-tight">Masuk ke Heaven Scent</h1>
                <p class="text-text-muted text-xs mt-1 leading-snug">Sistem Manajemen Laboratorium Produksi</p>
            </div>
        </div>

        {{-- Form Body --}}
        <div class="bg-[#F5F5F5] dark:bg-gray-950 p-6 space-y-4">
            {{-- Alert Error --}}
            @if($errors->any())
                <div class="p-3.5 border border-red-200 dark:border-red-500/20 bg-red-50/90 dark:bg-red-500/10 text-xs text-red-900 dark:text-red-300 rounded-[8px]">
                    <div class="flex items-start gap-2">
                        <svg class="size-4 text-red-600 dark:text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div class="space-y-0.5">
                            <p class="font-medium text-red-950 dark:text-red-200">Akses Ditolak:</p>
                            @foreach($errors->all() as $error)
                                <p class="text-red-800 dark:text-red-300">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-3">
                @csrf

                {{-- Field Email --}}
                <div class="bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-border rounded-[8px] px-3.5 py-2.5 focus-within:border-[#111111] dark:focus-within:border-white transition-colors">
                    <label for="email" class="block text-[11px] font-medium text-text-muted uppercase tracking-wider mb-1">
                        Email
                    </label>
                    <input id="email"
                           type="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           placeholder="nama@heavenscent.id"
                           class="w-full bg-transparent border-0 p-0 text-[14px] font-medium text-text-active placeholder:text-text-placeholder focus:outline-none focus:ring-0" />
                </div>

                {{-- Field Password --}}
                <div class="bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-border rounded-[8px] px-3.5 py-2.5 focus-within:border-[#111111] dark:focus-within:border-white transition-colors">
                    <label for="password" class="block text-[11px] font-medium text-text-muted uppercase tracking-wider mb-1">
                        Password
                    </label>
                    <div class="relative flex items-center">
                        <input id="password"
                               type="password"
                               name="password"
                               required
                               placeholder="••••••••"
                               class="w-full bg-transparent border-0 p-0 pr-8 text-[14px] font-medium text-text-active placeholder:text-text-placeholder focus:outline-none focus:ring-0" />
                        <button type="button"
                                id="togglePassword"
                                class="absolute right-0 p-1 text-text-muted hover:text-text-active cursor-pointer focus:outline-none"
                                title="Tampilkan / Sembunyikan sandi">
                            <svg id="eyeIcon" class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Remember Me --}}
                <div class="pt-0.5 flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 text-xs text-text-active cursor-pointer select-none font-medium">
                        <input type="checkbox"
                               name="remember"
                               class="size-4 rounded-[4px] border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 accent-black dark:accent-white cursor-pointer" />
                        <span>Ingat saya</span>
                    </label>
                </div>

                {{-- Submit Button --}}
                <div class="pt-1">
                    <x-button type="submit" variant="primary" size="lg" class="w-full">
                        <span>Masuk</span>
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </x-button>
                </div>
            </form>

            {{-- Akses Cepat Pengujian Dev (Khusus local / testing) --}}
            @if(app()->environment('local', 'testing'))
                <div class="pt-4 border-t border-[#E8E8E8] dark:border-border">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-medium text-text-muted uppercase tracking-wider">
                            Akses Cepat (Dev)
                        </span>
                        <span class="text-[10px] tabular-nums text-text-muted">Local Only</span>
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
                                        class="w-full text-left px-2.5 py-1.5 border border-[#E8E8E8] dark:border-border rounded-[6px] bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-[#DEDEDE] dark:hover:border-gray-600 text-[11px] font-medium text-text-active transition-all cursor-pointer truncate shadow-theme-xs">
                                    {{ $rLabel }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Footer Form --}}
        <div class="px-6 py-3 bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-border text-center text-[11px] text-text-muted">
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

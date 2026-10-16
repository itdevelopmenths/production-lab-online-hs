<!DOCTYPE html>
<html lang="id" class="h-full bg-background">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — Heaven Scent</title>
    <link rel="icon" type="image/png" href="{{ asset('Logo HS - black.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Zero rounded corners: Pastikan seluruh elemen 100% bersudut siku tajam */
        *, *::before, *::after,
        input, button, select, textarea, div, span, label, form, a, img {
            border-radius: 0 !important;
            -webkit-border-radius: 0 !important;
        }

        /* Checkbox custom reset agar tidak ada lengkungan default browser */
        input[type="checkbox"] {
            border-radius: 0 !important;
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
            transform: scale(1);
            background-color: #ffffff;
        }
    </style>
</head>
<body class="min-h-screen font-sans antialiased text-foreground bg-background selection:bg-neutral-900 selection:text-white">
    <div class="min-h-screen grid lg:grid-cols-12">

        {{-- Sisi Kiri: Visual Eksklusif Laboratorium & Identitas Brand (Hero Side) --}}
        <div class="relative lg:col-span-7 xl:col-span-7 bg-neutral-950 flex flex-col justify-between p-6 sm:p-10 lg:p-14 min-h-[380px] lg:min-h-screen overflow-hidden text-white border-b lg:border-b-0 lg:border-r border-neutral-800">
            {{-- Gambar Latar Laboratorium Parfum --}}
            <img src="{{ asset('perfume-lab.jpg') }}"
                 alt="Heaven Scent Formulation Laboratory"
                 class="absolute inset-0 w-full h-full object-cover object-center scale-100 transition-transform duration-700 hover:scale-105" />

            {{-- Dark Overlay & Ambient Lighting --}}
            <div class="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/75 to-neutral-950/60 lg:bg-gradient-to-r lg:from-neutral-950/85 lg:via-neutral-950/70 lg:to-neutral-950/90 pointer-events-none"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_25%_25%,rgba(217,119,6,0.15),transparent_55%)] pointer-events-none"></div>

            {{-- Header Visual Kiri: Logo & Status --}}
            <div class="relative z-10 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="size-10 bg-white text-black font-extrabold flex items-center justify-center text-sm tracking-wider border border-white/20 shadow-sm shrink-0">
                        HS
                    </div>
                    <div>
                        <div class="text-xs font-bold tracking-widest uppercase text-white leading-none">HEAVEN SCENT</div>
                        <div class="text-[10px] tracking-wider uppercase text-neutral-400 mt-1">Laboratory &amp; Production Suite</div>
                    </div>
                </div>

                <div class="hidden sm:inline-flex items-center gap-2 border border-white/20 bg-black/50 backdrop-blur-md px-3 py-1 text-[11px] font-mono uppercase tracking-wider text-neutral-300">
                    <span class="size-1.5 bg-emerald-400"></span>
                    <span>System Online</span>
                </div>
            </div>

            {{-- Body Visual Kiri: Editorial & Filosofi Produksi --}}
            <div class="relative z-10 max-w-xl my-8 lg:my-auto">
                <div class="inline-flex items-center gap-2 border-l-2 border-amber-400 pl-3 py-0.5 text-xs font-mono uppercase tracking-widest text-amber-300 mb-4">
                    Artisanal Precision &bull; Industrial Standard
                </div>
                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-white leading-tight">
                    Sistem Terpadu Formulasi &amp; Kontrol Produksi Laboratorium
                </h2>
                <p class="mt-3 text-sm sm:text-base text-neutral-300/90 leading-relaxed max-w-lg">
                    Manajemen alur kerja laboratorium wewangian secara terpadu — dari standarisasi formula (BOM), penimbangan batch, audit persediaan konsentrat, hingga rilis produk jadi.
                </p>

                {{-- Feature Matrix Kotak Tegas (Zero Rounded) --}}
                <div class="mt-6 pt-6 border-t border-white/15 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="border border-white/15 bg-black/40 backdrop-blur-sm p-3">
                        <div class="text-[10px] font-mono text-amber-400 uppercase tracking-widest">[ 01 ] FORMULA</div>
                        <div class="font-semibold text-white mt-1">Master BOM &amp; Resep</div>
                    </div>
                    <div class="border border-white/15 bg-black/40 backdrop-blur-sm p-3">
                        <div class="text-[10px] font-mono text-amber-400 uppercase tracking-widest">[ 02 ] BATCH</div>
                        <div class="font-semibold text-white mt-1">Traceability Audit</div>
                    </div>
                    <div class="border border-white/15 bg-black/40 backdrop-blur-sm p-3">
                        <div class="text-[10px] font-mono text-amber-400 uppercase tracking-widest">[ 03 ] KONTROL</div>
                        <div class="font-semibold text-white mt-1">Quality Assurance Lab</div>
                    </div>
                </div>
            </div>

            {{-- Footer Kiri: Copyright --}}
            <div class="relative z-10 hidden lg:flex items-center justify-between text-[11px] font-mono text-neutral-400 border-t border-white/10 pt-4">
                <span>Heaven Scent Production System &copy; {{ date('Y') }}</span>
                <span>Enterprise v2.4</span>
            </div>
        </div>

        {{-- Sisi Kanan: Form Autentikasi (Auth Form Side) --}}
        <div class="lg:col-span-5 xl:col-span-5 flex flex-col justify-between bg-card text-card-foreground p-6 sm:p-10 lg:p-14 min-h-full">
            {{-- Header Mini Mobile --}}
            <div class="flex items-center justify-between lg:hidden pb-4 mb-4 border-b border-border">
                <span class="text-xs font-mono uppercase tracking-wider text-muted-foreground">Portal Akses Karyawan</span>
                <span class="text-xs font-semibold text-foreground">Heaven Scent</span>
            </div>

            <div class="w-full max-w-sm mx-auto my-auto py-4 sm:py-8">
                {{-- Brand Header Form --}}
                <div class="mb-6 flex items-center gap-3.5">
                    <img src="{{ asset('Logo HS - black.png') }}"
                         alt="Heaven Scent Logo"
                         class="h-11 w-auto object-contain shrink-0"
                         onerror="this.style.display='none'; document.getElementById('fallback-hs-logo').style.display='flex';" />
                    <span id="fallback-hs-logo" style="display: none;" class="bg-primary text-primary-foreground size-11 items-center justify-center font-bold text-base border border-border shrink-0">
                        HS
                    </span>
                    <div>
                        <h1 class="text-lg font-bold text-foreground tracking-tight leading-tight">Masuk ke Heaven Scent</h1>
                        <p class="text-muted-foreground text-xs mt-0.5">Sistem Manajemen Laboratorium Produksi</p>
                    </div>
                </div>

                {{-- Alert Error (Kotak Tanpa Rounded) --}}
                @if($errors->any())
                    <div class="mb-5 p-3.5 border-l-4 border-l-red-600 border border-red-200 bg-red-50 text-xs text-red-900 shadow-none">
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
                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf

                    {{-- Field Email --}}
                    <div class="space-y-1.5">
                        <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Email
                        </label>
                        <input id="email"
                               type="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autofocus
                               placeholder="nama@heavenscent.id"
                               class="w-full h-11 px-3.5 text-sm bg-background border border-border text-foreground placeholder:text-muted-foreground focus:border-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition" />
                    </div>

                    {{-- Field Password --}}
                    <div class="space-y-1.5">
                        <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Password
                        </label>
                        <div class="relative">
                            <input id="password"
                                   type="password"
                                   name="password"
                                   required
                                   placeholder="••••••••"
                                   class="w-full h-11 px-3.5 pr-10 text-sm bg-background border border-border text-foreground placeholder:text-muted-foreground focus:border-foreground focus:outline-none focus:ring-1 focus:ring-foreground transition" />
                            <button type="button"
                                    id="togglePassword"
                                    class="absolute inset-y-0 right-0 px-3 flex items-center text-muted-foreground hover:text-foreground cursor-pointer focus:outline-none"
                                    title="Tampilkan / Sembunyikan sandi">
                                <svg id="eyeIcon" class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Remember Me --}}
                    <div class="pt-1">
                        <label class="inline-flex items-center gap-2.5 text-xs text-foreground cursor-pointer select-none">
                            <input type="checkbox"
                                   name="remember" />
                            <span>Ingat saya</span>
                        </label>
                    </div>

                    {{-- Submit Button --}}
                    <button type="submit"
                            class="w-full h-11 px-4 mt-2 bg-primary text-primary-foreground hover:bg-neutral-800 active:bg-black text-sm font-semibold tracking-wide transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-none">
                        <span>Masuk</span>
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                </form>

                {{-- Akses Cepat Pengujian Dev (Khusus local / testing) --}}
                @if(app()->environment('local', 'testing'))
                    <div class="mt-8 pt-6 border-t border-border">
                        <div class="flex items-center justify-between mb-2.5">
                            <span class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                                <span>⚡ Akses Cepat Pengujian (Dev)</span>
                            </span>
                            <span class="text-[10px] font-mono text-muted-foreground">Local Only</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            @php
                                $quickRoles = [
                                    'manager' => 'Manager (Andyka)',
                                    'purchasing' => 'Purchasing (Rina)',
                                    'gudang' => 'Gudang (Budi)',
                                    'operasional' => 'Operasional (Dedi)',
                                    'fulfillment' => 'Fulfillment (Sari)',
                                ];
                            @endphp
                            @foreach($quickRoles as $rKey => $rLabel)
                                <form method="POST" action="{{ route('login') }}" class="w-full">
                                    @csrf
                                    <input type="hidden" name="email" value="{{ $rKey }}@heavenscent.id">
                                    <input type="hidden" name="password" value="password">
                                    <button type="submit"
                                            class="w-full text-left px-2.5 py-2 border border-border bg-muted/40 hover:bg-accent hover:border-foreground/30 text-[11px] font-medium text-foreground transition-colors cursor-pointer truncate">
                                        {{ $rLabel }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Footer Form --}}
            <div class="pt-6 border-t border-border text-center sm:text-left text-[11px] text-muted-foreground flex flex-col sm:flex-row items-center justify-between gap-2">
                <span>Keamanan terenkripsi &bull; Akses internal terbatas</span>
                <span class="font-mono text-[10px]">SC.HARUMNYA</span>
            </div>
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

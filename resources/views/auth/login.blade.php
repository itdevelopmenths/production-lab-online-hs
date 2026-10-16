<!DOCTYPE html>
<html lang="id" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — Heaven Scent</title>
    <link rel="icon" type="image/png" href="{{ asset('Logo HS - black.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Checkbox custom modern dengan sedikit lengkungan */
        input[type="checkbox"] {
            border-radius: 0.25rem;
            -webkit-appearance: none;
            appearance: none;
            width: 1.05rem;
            height: 1.05rem;
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
            width: 0.55rem;
            height: 0.55rem;
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
            transform: scale(1);
            background-color: #ffffff;
        }
    </style>
</head>
<body class="min-h-screen font-sans antialiased text-foreground bg-white flex items-center justify-center p-4 sm:p-6 lg:p-8 selection:bg-neutral-900 selection:text-white">

    {{-- Card Container Terpadu: Elegan, Bersudut Rounded Halus, dan Proporsional --}}
    <div class="w-full max-w-5xl bg-card text-card-foreground rounded-2xl shadow-xl shadow-neutral-900/10 border border-neutral-200 overflow-hidden grid lg:grid-cols-12 min-h-[600px] lg:min-h-[640px]">

        {{-- Sisi Kiri: Visual Eksklusif Laboratorium (Ukuran Tidak Terlalu Besar, Rounded Terpadu) --}}
        <div class="relative lg:col-span-5 bg-neutral-950 flex flex-col justify-between p-6 sm:p-8 lg:p-10 min-h-[320px] lg:min-h-[640px] overflow-hidden text-white">
            {{-- Gambar Latar Laboratorium Parfum --}}
            <img src="{{ asset('perfume-lab.jpg') }}"
                 alt="Heaven Scent Formulation Laboratory"
                 class="absolute inset-0 w-full h-full object-cover object-center scale-100 transition-transform duration-700 hover:scale-105" />

            {{-- Dark Overlay & Ambient Lighting --}}
            <div class="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/80 to-neutral-950/65 lg:bg-gradient-to-r lg:from-neutral-950/90 lg:via-neutral-950/80 lg:to-neutral-950/65 pointer-events-none"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_25%_25%,rgba(217,119,6,0.18),transparent_55%)] pointer-events-none"></div>

            {{-- Header Visual Kiri: Logo & Status --}}
            <div class="relative z-10 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="size-9 bg-white text-black font-extrabold flex items-center justify-center text-xs tracking-wider rounded-lg shadow-sm shrink-0">
                        HS
                    </div>
                    <div>
                        <div class="text-xs font-bold tracking-widest uppercase text-white leading-none">HEAVEN SCENT</div>
                        <div class="text-[10px] tracking-wider uppercase text-neutral-400 mt-1">Laboratory &amp; Production</div>
                    </div>
                </div>

                <div class="inline-flex items-center gap-1.5 border border-white/20 bg-black/50 backdrop-blur-md px-2.5 py-1 text-[10px] font-mono uppercase tracking-wider text-neutral-300 rounded-full">
                    <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>System Online</span>
                </div>
            </div>

            {{-- Body Visual Kiri: Editorial & Filosofi Produksi --}}
            <div class="relative z-10 my-6 lg:my-auto">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/15 border border-amber-400/30 text-[10px] font-mono uppercase tracking-wider text-amber-300 mb-3">
                    <span class="size-1 rounded-full bg-amber-400"></span>
                    <span>Artisanal Precision &bull; Industrial Standard</span>
                </div>
                <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-white leading-snug">
                    Sistem Terpadu Formulasi &amp; Kontrol Produksi Laboratorium
                </h2>
                <p class="mt-2.5 text-xs sm:text-sm text-neutral-300/90 leading-relaxed">
                    Manajemen alur kerja laboratorium wewangian secara terpadu — dari standarisasi BOM, penimbangan batch, audit persediaan konsentrat, hingga rilis produk.
                </p>

                {{-- Feature Matrix dengan Rounded Halus --}}
                <div class="mt-5 grid grid-cols-3 gap-2 text-xs">
                    <div class="border border-white/15 bg-black/40 backdrop-blur-sm p-2.5 rounded-lg">
                        <div class="text-[9px] font-mono text-amber-400 uppercase tracking-wider">[ 01 ] FORMULA</div>
                        <div class="font-medium text-white text-[11px] mt-0.5 leading-tight">Master BOM</div>
                    </div>
                    <div class="border border-white/15 bg-black/40 backdrop-blur-sm p-2.5 rounded-lg">
                        <div class="text-[9px] font-mono text-amber-400 uppercase tracking-wider">[ 02 ] BATCH</div>
                        <div class="font-medium text-white text-[11px] mt-0.5 leading-tight">Traceability</div>
                    </div>
                    <div class="border border-white/15 bg-black/40 backdrop-blur-sm p-2.5 rounded-lg">
                        <div class="text-[9px] font-mono text-amber-400 uppercase tracking-wider">[ 03 ] KONTROL</div>
                        <div class="font-medium text-white text-[11px] mt-0.5 leading-tight">QA Lab</div>
                    </div>
                </div>
            </div>

            {{-- Footer Kiri: Copyright --}}
            <div class="relative z-10 hidden lg:flex items-center justify-between text-[10px] font-mono text-neutral-400 border-t border-white/10 pt-3">
                <span>Heaven Scent Production System &copy; {{ date('Y') }}</span>
                <span>Enterprise v2.4</span>
            </div>
        </div>

        {{-- Sisi Kanan: Form Autentikasi (Auth Form Side) --}}
        <div class="lg:col-span-7 flex flex-col justify-between bg-card text-card-foreground p-6 sm:p-8 lg:p-10 min-h-full">
            <div class="w-full max-w-sm mx-auto my-auto py-2 sm:py-4">
                {{-- Brand Header Form --}}
                <div class="mb-6 flex items-center gap-3">
                    <img src="{{ asset('Logo HS - black.png') }}"
                         alt="Heaven Scent Logo"
                         class="h-10 w-auto object-contain shrink-0"
                         onerror="this.style.display='none'; document.getElementById('fallback-hs-logo').style.display='flex';" />
                    <span id="fallback-hs-logo" style="display: none;" class="bg-primary text-primary-foreground size-10 items-center justify-center font-bold text-sm rounded-lg border border-border shrink-0">
                        HS
                    </span>
                    <div>
                        <h1 class="text-lg font-bold text-foreground tracking-tight leading-tight">Masuk ke Heaven Scent</h1>
                        <p class="text-muted-foreground text-xs mt-0.5">Sistem Manajemen Laboratorium Produksi</p>
                    </div>
                </div>

                {{-- Alert Error (Rounded Halus) --}}
                @if($errors->any())
                    <div class="mb-5 p-3.5 border-l-4 border-l-red-600 border border-red-200 bg-red-50 text-xs text-red-900 rounded-lg shadow-xs">
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
                               class="w-full h-10 px-3.5 text-sm bg-background border border-border rounded-lg text-foreground placeholder:text-muted-foreground focus:border-foreground focus:outline-none focus:ring-2 focus:ring-foreground/15 transition shadow-xs" />
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
                                   class="w-full h-10 px-3.5 pr-10 text-sm bg-background border border-border rounded-lg text-foreground placeholder:text-muted-foreground focus:border-foreground focus:outline-none focus:ring-2 focus:ring-foreground/15 transition shadow-xs" />
                            <button type="button"
                                    id="togglePassword"
                                    class="absolute inset-y-0 right-0 px-3 flex items-center text-muted-foreground hover:text-foreground cursor-pointer focus:outline-none rounded-r-lg"
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
                            class="w-full h-10 px-4 mt-2 bg-primary text-primary-foreground hover:bg-neutral-800 active:bg-black text-sm font-semibold tracking-wide rounded-lg transition-all flex items-center justify-center gap-2 cursor-pointer shadow-xs hover:shadow">
                        <span>Masuk</span>
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                </form>

                {{-- Akses Cepat Pengujian Dev (Khusus local / testing) --}}
                @if(app()->environment('local', 'testing'))
                    <div class="mt-6 pt-5 border-t border-border">
                        <div class="flex items-center justify-between mb-2">
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
                                            class="w-full text-left px-2.5 py-1.5 border border-border/80 rounded-lg bg-muted/40 hover:bg-accent hover:border-foreground/30 text-[11px] font-medium text-foreground transition-all cursor-pointer truncate shadow-2xs">
                                        {{ $rLabel }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Footer Form --}}
            <div class="pt-5 border-t border-border text-center sm:text-left text-[11px] text-muted-foreground flex flex-col sm:flex-row items-center justify-between gap-2">
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

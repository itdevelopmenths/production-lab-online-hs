<!DOCTYPE html>
<html lang="id" class="h-full bg-[oklch(0.985_0_0)]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — Heaven Scent</title>
    <link rel="icon" type="image/png" href="{{ asset('Logo HS - black.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased text-foreground bg-background flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="bg-card text-card-foreground rounded-2xl border border-border shadow-sm p-6 sm:p-7">
            {{-- Brand Header --}}
            <div class="mb-6 flex items-center gap-3.5">
                <span class="bg-primary text-primary-foreground flex size-11 items-center justify-center rounded-xl font-bold text-base shadow-xs shrink-0">
                    HS
                </span>
                <div>
                    <h1 class="text-lg font-semibold text-foreground tracking-tight leading-tight">Masuk ke Heaven Scent</h1>
                    <p class="text-muted-foreground text-xs mt-0.5">Sistem Manajemen Laboratorium Produksi</p>
                </div>
            </div>

            @if($errors->any())
                <div class="mb-4 p-3 rounded-lg bg-red-50/80 border border-red-200 text-xs text-red-900">
                    <p class="font-semibold mb-1">Akses Ditolak:</p>
                    @foreach($errors->all() as $error)
                        <p class="text-red-700">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-foreground">Email</label>
                    <input type="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           placeholder="nama@heavenscent.id"
                           class="w-full h-10 px-3.5 py-2 text-sm rounded-lg border border-border bg-card text-foreground placeholder:text-muted-foreground outline-none transition focus:ring-2 focus:ring-primary/20" />
                </div>

                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-foreground">Password</label>
                    <input type="password"
                           name="password"
                           required
                           placeholder="••••••••"
                           class="w-full h-10 px-3.5 py-2 text-sm rounded-lg border border-border bg-card text-foreground placeholder:text-muted-foreground outline-none transition focus:ring-2 focus:ring-primary/20" />
                </div>

                <label class="flex items-center gap-2 text-sm text-foreground cursor-pointer select-none pt-1">
                    <input type="checkbox"
                           name="remember"
                           class="size-4 rounded border-border text-primary focus:ring-primary/20 transition" />
                    <span>Ingat saya</span>
                </label>

                <button type="submit"
                        class="w-full h-10 px-4 py-2 mt-2 bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg text-sm font-semibold transition-colors shadow-sm flex items-center justify-center gap-2 cursor-pointer">
                    Masuk
                </button>
            </form>

            @if(app()->environment('local', 'testing'))
                <div class="mt-6 pt-5 border-t border-border">
                    <p class="text-xs font-semibold text-muted-foreground mb-2.5">⚡ Akses Cepat Pengujian (Dev):</p>
                    <div class="grid grid-cols-2 gap-1.5">
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
                                        class="w-full text-left px-2.5 py-1.5 rounded-lg border border-border bg-muted/40 hover:bg-accent text-[11px] font-medium text-foreground transition-colors cursor-pointer truncate">
                                    {{ $rLabel }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>

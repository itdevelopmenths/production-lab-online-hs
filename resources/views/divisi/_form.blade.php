@php
    $divisi = $divisi ?? null;
@endphp
<div x-data="{
    nama: '{{ addslashes(old('nama', $divisi?->nama ?? '')) }}',
    kode: '{{ addslashes(old('kode', $divisi?->kode ?? '')) }}',
    color: '{{ old('color', $divisi?->color ?? 'slate') }}',
    isCustomSlug: {{ old('kode', $divisi?->kode) ? 'true' : 'false' }},
    
    slugify(text) {
        return text.toString().toLowerCase().trim()
            .replace(/\s+/g, '_')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '_');
    },

    onNamaChange() {
        if (!this.isCustomSlug) {
            this.kode = this.slugify(this.nama);
        }
    },

    getBadgeClass() {
        const classes = {
            'indigo': 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 border-indigo-500/20',
            'blue': 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20',
            'emerald': 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
            'teal': 'bg-teal-500/10 text-teal-700 dark:text-teal-400 border-teal-500/20',
            'amber': 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20',
            'rose': 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20',
            'purple': 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20',
            'cyan': 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400 border-cyan-500/20',
            'slate': 'bg-muted text-muted-foreground border-border'
        };
        return classes[this.color] || classes['slate'];
    }
}" class="space-y-6">

    {{-- Live Preview Card --}}
    <div class="p-4 rounded-xl border border-border bg-muted/40 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <span class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider block">Live Preview Tampilan Badge Divisi:</span>
            <div class="mt-2 flex items-center gap-2.5">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border transition-all duration-150"
                      :class="getBadgeClass()">
                    <span class="size-1.5 rounded-full mr-2 bg-current opacity-70"></span>
                    <span x-text="nama.trim() ? nama : 'Nama Divisi'"></span>
                </span>
                <span class="text-xs text-muted-foreground font-mono tracking-tight" x-show="kode.trim()">
                    (<span x-text="kode"></span>)
                </span>
            </div>
        </div>
        <p class="text-[11px] text-muted-foreground max-w-xs leading-normal">
            Aksen warna dan label badge ini akan langsung muncul di profil staf, filter pengguna, dan riwayat aktivitas sistem.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <x-form-group name="nama" label="Nama Divisi / Departemen" :required="true" help="Nama resmi departemen atau unit kerja (misal: Riset & Pengembangan / R&D)">
                <input type="text" 
                       name="nama" 
                       x-model="nama" 
                       @input="onNamaChange()"
                       required
                       placeholder="misal: Research & Development"
                       class="w-full text-xs rounded-lg border border-border bg-background px-3 py-2 text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 shadow-xs transition" />
            </x-form-group>
        </div>

        <div>
            <x-form-group name="kode" label="Kode Unik Identifikasi (Slug)" :required="true" help="Kode identitas sistem (huruf kecil tanpa spasi, misal: rnd)">
                <div class="relative">
                    <input type="text" 
                           name="kode" 
                           x-model="kode" 
                           @input="isCustomSlug = true"
                           required
                           placeholder="misal: rnd"
                           class="w-full text-xs font-mono rounded-lg border border-border bg-background px-3 py-2 text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 shadow-xs transition lowercase" />
                </div>
            </x-form-group>
        </div>

        {{-- Color Selection Palette --}}
        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-foreground mb-1">
                Aksen Warna Badge Divisi <span class="text-destructive">*</span>
            </label>
            <p class="text-xs text-muted-foreground mb-3">
                Pilih tema warna visual untuk mempermudah identifikasi staf antar-divisi di berbagai tabel data.
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2.5">
                @php
                    $colorStyles = [
                        'slate' => 'bg-slate-400',
                        'indigo' => 'bg-indigo-500',
                        'blue' => 'bg-blue-500',
                        'emerald' => 'bg-emerald-500',
                        'teal' => 'bg-teal-500',
                        'amber' => 'bg-amber-500',
                        'rose' => 'bg-rose-500',
                        'purple' => 'bg-purple-500',
                        'cyan' => 'bg-cyan-500',
                    ];
                @endphp

                @foreach($availableColors as $colKey => $colLabel)
                    <label class="relative flex items-center p-2.5 rounded-lg border cursor-pointer select-none transition"
                           :class="color === '{{ $colKey }}' ? 'ring-2 ring-primary border-primary bg-accent/30 shadow-xs' : 'border-border hover:border-foreground/30 bg-card'">
                        <input type="radio" 
                               name="color" 
                               value="{{ $colKey }}" 
                               x-model="color" 
                               class="text-primary focus:ring-primary h-3.5 w-3.5" />
                        <div class="ml-2 flex items-center gap-1.5 min-w-0">
                            <span class="size-3 rounded-full {{ $colorStyles[$colKey] ?? 'bg-slate-400' }} shrink-0"></span>
                            <span class="text-xs font-medium text-foreground truncate">{{ $colLabel }}</span>
                        </div>
                    </label>
                @endforeach
            </div>
            @error('color')
                <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <x-form-group name="deskripsi" label="Deskripsi / Catatan Peran Divisi" help="Penjelasan tugas pokok atau cakupan tanggung jawab divisi dalam rantai pasok">
                <x-textarea name="deskripsi" rows="3" placeholder="misal: Bertanggung jawab atas formulasi resep kimia baru, riset aroma, dan uji stabilitas produk...">{{ old('deskripsi', $divisi?->deskripsi) }}</x-textarea>
            </x-form-group>
        </div>

        <div class="md:col-span-2 pt-2 border-t border-border">
            <x-checkbox name="is_active" label="Status Aktif Divisi" :checked="old('is_active', $divisi?->is_active ?? true)" help="Divisi aktif dapat dipilih saat mendaftarkan staf baru di User Management" />
        </div>
    </div>
</div>

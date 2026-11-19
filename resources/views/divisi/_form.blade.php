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
            'indigo': 'bg-card dark:bg-card text-foreground dark:text-foreground border-border dark:border-border',
            'blue': 'bg-card dark:bg-card text-foreground dark:text-foreground border-border dark:border-border',
            'emerald': 'bg-card dark:bg-card text-foreground dark:text-foreground border-border dark:border-border',
            'teal': 'bg-card dark:bg-card text-foreground dark:text-foreground border-border dark:border-border',
            'amber': 'bg-card dark:bg-card text-foreground dark:text-foreground border-border dark:border-border',
            'rose': 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800/50',
            'purple': 'bg-card dark:bg-card text-foreground dark:text-foreground border-border dark:border-border',
            'cyan': 'bg-card dark:bg-card text-foreground dark:text-foreground border-border dark:border-border',
            'slate': 'bg-gray-100 dark:bg-gray-800 text-[#667085] dark:text-gray-300 border-[#E8E8E8] dark:border-gray-700'
        };
        return classes[this.color] || classes['slate'];
    }
}" class="space-y-3">

    {{-- Live Preview Card --}}
    <div class="p-4 rounded-[12px] border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div>
            <span class="text-[11px] font-medium text-[#667085] dark:text-gray-400 uppercase tracking-wider block">Pratinjau badge</span>
            <div class="mt-2 flex items-center gap-2.5">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border transition-all duration-150"
                      :class="getBadgeClass()">
                    <span class="size-1.5 rounded-full mr-2 bg-current opacity-70"></span>
                    <span x-text="nama.trim() ? nama : 'Nama Divisi'"></span>
                </span>
                <span class="text-xs text-[#667085] dark:text-gray-400 tabular-nums tracking-tight" x-show="kode.trim()">
                    (<span x-text="kode"></span>)
                </span>
            </div>
        </div>
        <p class="text-[11px] text-[#667085] dark:text-gray-400 max-w-xs leading-normal">
            Tampil di profil staf, filter pengguna, dan riwayat.
        </p>
    </div>

    <x-form-section card title="Identitas & Warna Divisi" subtitle="Nama, kode, dan warna badge">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3">
            <x-form-field name="nama" label="Nama Divisi" :required="true" help="Nama resmi departemen">
                <input type="text" 
                       name="nama" 
                       x-model="nama" 
                       @input="onNamaChange()"
                       required
                       placeholder="misal: Research & Development"
                       class="w-full h-9 text-[13px] border-0 bg-transparent px-0 py-1.5 text-[#101828] dark:text-white focus:outline-none focus:ring-0" />
            </x-form-field>

            <x-form-field name="kode" label="Kode Unik (Slug)" :required="true" help="Huruf kecil tanpa spasi, misal: rnd">
                <input type="text" 
                       name="kode" 
                       x-model="kode" 
                       @input="isCustomSlug = true"
                       required
                       placeholder="misal: rnd"
                       class="w-full h-9 text-[13px] tabular-nums border-0 bg-transparent px-0 py-1.5 text-[#101828] dark:text-white focus:outline-none focus:ring-0 lowercase" />
            </x-form-field>

            {{-- Color Selection Palette --}}
            <div class="md:col-span-2 pt-1">
                <label class="block text-xs font-medium text-[#101828] dark:text-white mb-1">
                    Aksen Warna Badge Divisi <span class="text-rose-500">*</span>
                </label>
                <p class="text-xs text-[#667085] dark:text-gray-400 mb-3">
                    Membantu mengenali divisi di tabel dan filter.
                </p>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                    @php
                        $colorStyles = [
                            'slate' => 'bg-slate-400',
                            'indigo' => 'bg-gray-400',
                            'blue' => 'bg-gray-400',
                            'emerald' => 'bg-gray-400',
                            'teal' => 'bg-gray-400',
                            'amber' => 'bg-gray-400',
                            'rose' => 'bg-rose-500',
                            'purple' => 'bg-gray-400',
                            'cyan' => 'bg-gray-400',
                        ];
                    @endphp

                    @foreach($availableColors as $colKey => $colLabel)
                        <label class="relative flex items-center p-2.5 rounded-lg border cursor-pointer select-none transition"
                               :class="color === '{{ $colKey }}' ? 'ring-1 ring-[#111111] dark:ring-white border-[#111111] dark:border-white bg-gray-50 dark:bg-gray-800 shadow-xs' : 'border-[#E8E8E8] dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-900'">
                            <input type="radio" 
                                   name="color" 
                                   value="{{ $colKey }}" 
                                   x-model="color" 
                                   class="text-[#111111] dark:text-white focus:ring-0 h-3.5 w-3.5" />
                            <div class="ml-2 flex items-center gap-1.5 min-w-0">
                                <span class="size-3 rounded-full {{ $colorStyles[$colKey] ?? 'bg-slate-400' }} shrink-0"></span>
                                <span class="text-xs font-medium text-[#101828] dark:text-gray-200">{{ $colLabel }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('color')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <x-form-field name="deskripsi" label="Deskripsi" help="Opsional: tugas pokok divisi">
                    <x-textarea name="deskripsi" rows="3" variant="seamless" placeholder="Tugas pokok divisi..." :value="old('deskripsi', $divisi?->deskripsi)" />
                </x-form-field>
            </div>

            <div class="md:col-span-2 pt-2 border-t border-[#E8E8E8] dark:border-gray-800">
                <x-checkbox name="is_active" label="Status Aktif Divisi" :checked="old('is_active', $divisi?->is_active ?? true)" help="Hanya divisi aktif yang bisa dipilih saat mendaftarkan staf" />
            </div>
        </div>
    </x-form-section>
</div>

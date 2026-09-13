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
            'indigo': 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
            'blue': 'bg-blue-50 text-blue-700 border-blue-200/80',
            'emerald': 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
            'teal': 'bg-teal-50 text-teal-700 border-teal-200/80',
            'amber': 'bg-amber-50 text-amber-700 border-amber-200/80',
            'rose': 'bg-rose-50 text-rose-700 border-rose-200/80',
            'purple': 'bg-purple-50 text-purple-700 border-purple-200/80',
            'cyan': 'bg-cyan-50 text-cyan-700 border-cyan-200/80',
            'slate': 'bg-slate-50 text-slate-700 border-slate-200/80'
        };
        return classes[this.color] || classes['slate'];
    }
}" class="space-y-6">

    {{-- Live Preview Card --}}
    <div class="p-4 rounded-sm bg-gradient-to-r from-slate-50 to-gray-50 border border-gray-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Live Preview Tampilan Badge Divisi:</span>
            <div class="mt-2 flex items-center gap-2.5">
                <span class="inline-flex items-center px-3 py-1 rounded text-xs font-semibold border transition-all duration-150"
                      :class="getBadgeClass()">
                    <span class="w-1.5 h-1.5 rounded-full mr-2 bg-current opacity-70"></span>
                    <span x-text="nama.trim() ? nama : 'Nama Divisi'"></span>
                </span>
                <span class="text-xs text-gray-400 font-mono tracking-tight" x-show="kode.trim()">
                    (<span x-text="kode"></span>)
                </span>
            </div>
        </div>
        <p class="text-[11px] text-gray-500 max-w-xs leading-normal">
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
                       class="w-full text-xs rounded-sm border-gray-300 focus:border-primary-500 focus:ring-primary-500 shadow-sm transition" />
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
                           class="w-full text-xs font-mono rounded-sm border-gray-300 focus:border-primary-500 focus:ring-primary-500 shadow-sm transition" />
                </div>
            </x-form-group>
        </div>

        {{-- Color Selection Palette --}}
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                Aksen Warna Badge Divisi <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-gray-500 mb-3">
                Pilih tema warna visual untuk mempermudah identifikasi staf antar-divisi di berbagai tabel data.
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2.5">
                @php
                    $colorStyles = [
                        'slate' => 'border-slate-300 bg-slate-50 text-slate-700',
                        'indigo' => 'border-indigo-300 bg-indigo-50 text-indigo-700',
                        'blue' => 'border-blue-300 bg-blue-50 text-blue-700',
                        'emerald' => 'border-emerald-300 bg-emerald-50 text-emerald-700',
                        'teal' => 'border-teal-300 bg-teal-50 text-teal-700',
                        'amber' => 'border-amber-300 bg-amber-50 text-amber-700',
                        'rose' => 'border-rose-300 bg-rose-50 text-rose-700',
                        'purple' => 'border-purple-300 bg-purple-50 text-purple-700',
                        'cyan' => 'border-cyan-300 bg-cyan-50 text-cyan-700',
                    ];
                @endphp

                @foreach($availableColors as $colKey => $colLabel)
                    <label class="relative flex items-center p-2.5 rounded-sm border cursor-pointer select-none transition"
                           :class="color === '{{ $colKey }}' ? 'ring-2 ring-primary-500 border-primary-500 shadow-xs' : 'border-gray-200 hover:border-gray-300 bg-white'">
                        <input type="radio" 
                               name="color" 
                               value="{{ $colKey }}" 
                               x-model="color" 
                               class="text-primary-600 focus:ring-primary-500 h-3.5 w-3.5" />
                        <div class="ml-2 flex items-center gap-1.5 min-w-0">
                            <span class="w-3 h-3 rounded-full {{ $colorStyles[$colKey] ?? 'bg-slate-400' }} border border-black/10 flex-shrink-0"></span>
                            <span class="text-xs font-medium text-gray-800 truncate">{{ $colLabel }}</span>
                        </div>
                    </label>
                @endforeach
            </div>
            @error('color')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <x-form-group name="deskripsi" label="Deskripsi / Catatan Peran Divisi" help="Penjelasan tugas pokok atau cakupan tanggung jawab divisi dalam rantai pasok">
                <x-textarea name="deskripsi" rows="3" placeholder="misal: Bertanggung jawab atas formulasi resep kimia baru, riset aroma, dan uji stabilitas produk...">{{ old('deskripsi', $divisi?->deskripsi) }}</x-textarea>
            </x-form-group>
        </div>

        <div class="md:col-span-2 pt-2 border-t border-gray-100">
            <x-checkbox name="is_active" label="Status Aktif Divisi" :checked="old('is_active', $divisi?->is_active ?? true)" help="Divisi aktif dapat dipilih saat mendaftarkan staf baru di User Management" />
        </div>
    </div>
</div>

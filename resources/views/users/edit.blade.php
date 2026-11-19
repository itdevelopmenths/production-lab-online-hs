<x-app-layout title="Edit Pengguna">
    <div class="w-full space-y-4">
        <x-page-header
            title="Edit Pengguna: {{ $user->name }}"
            subtitle="Perbarui profil akun, departemen divisi, hak akses gudang, dan peran wewenang sistem"
            :breadcrumbs="['Pengaturan' => null, 'Manajemen Pengguna' => route('users.index'), $user->name => null, 'Edit' => null]"
            :backUrl="route('users.index')"
        />

        <form action="{{ route('users.update', $user) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Card 1: Informasi Akun & Divisi (AppForm Shell) --}}
            <div class="bg-[#F5F5F5] dark:bg-gray-950 border border-[#E8E8E8] dark:border-gray-800 rounded-[12px] p-4 sm:p-5 space-y-5">
                <x-form-section title="Data Akun &amp; Departemen Organisasi ({{ $user->email }})" :cols="2">
                    <x-form-field name="name" label="Nama Lengkap" :required="true">
                        <x-input name="name" id="name" variant="seamless" value="{{ old('name', $user->name) }}" :required="true" />
                    </x-form-field>

                    <x-form-field name="email" label="Alamat Email" :required="true">
                        <x-input type="email" name="email" id="email" variant="seamless" value="{{ old('email', $user->email) }}" :required="true" />
                    </x-form-field>

                    <div class="md:col-span-2">
                        <x-form-field name="divisi_id" label="Divisi / Departemen" help="Menentukan unit kerja pengguna dalam struktur rantai pasok Heaven Scent @can('divisi.view') · <a href='{{ route('divisi.index') }}' target='_blank' class='underline text-text-active'>Kelola Master Divisi &rarr;</a> @endcan">
                            <x-select name="divisi_id" id="divisi_id" variant="seamless" placeholder="-- Pilih Divisi / Departemen --">
                                @if(isset($divisis) && $divisis->isNotEmpty())
                                    @foreach($divisis as $d)
                                        <option value="{{ $d->id }}" @selected((string) old('divisi_id', $user->divisi_id) === (string) $d->id || (empty($user->divisi_id) && old('divisi', $user->divisi) === $d->kode))>
                                            {{ $d->nama }} ({{ $d->kode }})
                                        </option>
                                    @endforeach
                                @else
                                    @foreach($divisiList as $divKey => $divLabel)
                                        <option value="{{ $divKey }}" @selected(old('divisi', $user->divisi) === $divKey)>{{ $divLabel }}</option>
                                    @endforeach
                                @endif
                            </x-select>
                        </x-form-field>
                    </div>

                    <x-form-field name="password" label="Kata Sandi Baru" help="Kosongkan jika tidak ingin mengubah kata sandi (min. 8 karakter)">
                        <x-input type="password" name="password" id="password" variant="seamless" placeholder="••••••••" />
                    </x-form-field>

                    <x-form-field name="password_confirmation" label="Konfirmasi Sandi" help="Ulangi jika mengubah kata sandi">
                        <x-input type="password" name="password_confirmation" id="password_confirmation" variant="seamless" placeholder="••••••••" />
                    </x-form-field>
                </x-form-section>
            </div>

            {{-- Card 2: Peran Utama & Akses Lokasi Fisik Gudang (LBAC) --}}
            <script>
                function userWarehouseAccessState(accessType, selectedGudangs, primaryGudang) {
                    return {
                        accessType: accessType || 'global',
                        selectedGudangs: selectedGudangs || [],
                        primaryGudang: primaryGudang ? String(primaryGudang) : '',
                        toggleGudang(id) {
                            id = parseInt(id, 10);
                            if (this.selectedGudangs.includes(id)) {
                                this.selectedGudangs = this.selectedGudangs.filter(function(g) { return g !== id; });
                                if (parseInt(this.primaryGudang, 10) === id) {
                                    this.primaryGudang = this.selectedGudangs.length > 0 ? String(this.selectedGudangs[0]) : '';
                                }
                            } else {
                                this.selectedGudangs.push(id);
                                if (!this.primaryGudang) {
                                    this.primaryGudang = String(id);
                                }
                            }
                        }
                    };
                }
            </script>
            <div x-data="userWarehouseAccessState('{{ old('warehouse_access_type', $isGlobalWarehouse ? 'global' : 'restricted') }}', {{ Js::from(array_map('intval', (array) old('gudang_ids', $assignedGudangIds))) }}, '{{ old('primary_gudang_id', $primaryGudangId ?? '') }}')">
                <x-card title="Peran &amp; Hak Akses Fasilitas Gudang" subtitle="Tentukan peran wewenang sistem dan batas wilayah/lokasi operasional staf">
                    <x-slot:icon>
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </x-slot:icon>
                    <div class="space-y-6">
                        {{-- Role Utama --}}
                        <div class="bg-[#F5F5F5] dark:bg-gray-950 border border-[#E8E8E8] dark:border-gray-800 rounded-[12px] p-4">
                            <x-form-field name="role" label="Peran Utama (Role)" :required="true" help="Menentukan grup hak akses default dan wewenang approval">
                                <x-select name="role" id="role" variant="seamless" placeholder="-- Pilih Role --" :required="true">
                                    @foreach($roles as $r)
                                        @php
                                            $rKey = is_object($r) ? $r->name : $r;
                                            $rLabel = is_object($r) ? ($r->display_name ? $r->display_name . ' (' . $r->name . ')' : $r->name) : $r;
                                        @endphp
                                        <option value="{{ $rKey }}" @selected(old('role', $user->roles->first()?->name) === $rKey)>{{ $rLabel }}</option>
                                    @endforeach
                                </x-select>
                            </x-form-field>
                        </div>

                        {{-- Mode Akses Gudang (LBAC) --}}
                        <div class="border-t border-[#E8E8E8] dark:border-gray-800 pt-5">
                            <label class="block text-xs font-medium text-text-active mb-1">
                                Lingkup Wilayah / Fasilitas Gudang (LBAC)
                            </label>
                            <p class="text-xs text-text-muted mb-4">
                                Batasi data stok, mutasi kartu stok, dan dokumen transfer yang dapat diakses pengguna berdasarkan gudang fisik.
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                                <label class="relative flex items-start p-3.5 rounded-[12px] border cursor-pointer select-none transition"
                                       :class="accessType === 'global' ? 'bg-neutral-100/80 dark:bg-gray-800/80 border-neutral-900 dark:border-white' : 'bg-white dark:bg-gray-900 border-[#E8E8E8] dark:border-gray-800 hover:border-gray-400 dark:hover:border-gray-600'">
                                    <input type="radio" name="warehouse_access_type" value="global" x-model="accessType" class="mt-0.5 text-neutral-900 dark:text-white focus:ring-0">
                                    <div class="ml-3">
                                        <span class="block text-xs font-medium text-text-active">Akses Seluruh Gudang (Global)</span>
                                        <span class="block text-[11px] text-text-muted mt-0.5 leading-normal">
                                            Pengguna bebas melihat stok dan membuat mutasi di semua fasilitas (Cocok untuk Manager, Direksi, Auditor).
                                        </span>
                                    </div>
                                </label>

                                <label class="relative flex items-start p-3.5 rounded-[12px] border cursor-pointer select-none transition"
                                       :class="accessType === 'restricted' ? 'bg-neutral-100/80 dark:bg-gray-800/80 border-neutral-900 dark:border-white' : 'bg-white dark:bg-gray-900 border-[#E8E8E8] dark:border-gray-800 hover:border-gray-400 dark:hover:border-gray-600'">
                                    <input type="radio" name="warehouse_access_type" value="restricted" x-model="accessType" class="mt-0.5 text-neutral-900 dark:text-white focus:ring-0">
                                    <div class="ml-3">
                                        <span class="block text-xs font-medium text-text-active">Dibatasi ke Fasilitas Tertentu</span>
                                        <span class="block text-[11px] text-text-muted mt-0.5 leading-normal">
                                            Pengguna hanya diizinkan melihat data pada gudang-gudang yang dicentang di bawah ini.
                                        </span>
                                    </div>
                                </label>
                            </div>

                            {{-- Checklist Gudang per Tipe --}}
                            <div x-show="accessType === 'restricted'" x-transition class="space-y-4 bg-[#fafafa] dark:bg-gray-950 p-4 rounded-[12px] border border-[#E8E8E8] dark:border-gray-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-text-active">Daftar Fasilitas Gudang yang Diizinkan:</span>
                                    <span class="text-[11px] text-text-muted">
                                        <span class="font-medium text-text-active tabular-nums" x-text="selectedGudangs.length"></span> gudang dipilih
                                    </span>
                                </div>

                                @php
                                    $groupedGudangs = $gudangs->groupBy(function($g) {
                                        return match($g->tipe) {
                                            'bahan_baku' => 'Gudang Bahan Baku & Kemasan',
                                            'operasional' => 'Gudang Operasional / Lab Produksi',
                                            'fulfillment', 'fulfillment_pusat', 'fulfillment_cabang' => 'Fulfillment & Distribusi Produk Jadi',
                                            default => 'Lainnya',
                                        };
                                    });
                                @endphp

                                @foreach($groupedGudangs as $groupTitle => $groupItems)
                                    <div class="bg-white dark:bg-gray-900 p-3.5 rounded-[12px] border border-[#E8E8E8] dark:border-gray-800">
                                        <div class="text-[11px] font-medium text-text-active uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                                            <span class="size-1.5 rounded-full bg-neutral-900 dark:bg-white"></span>
                                            {{ $groupTitle }}
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                                            @foreach($groupItems as $g)
                                                <div class="flex items-center justify-between p-2.5 rounded-[8px] border border-[#E8E8E8] dark:border-gray-800 bg-[#fafafa] dark:bg-gray-950 hover:bg-neutral-100/70 dark:hover:bg-gray-800/60 transition">
                                                    <label class="flex items-center gap-2 cursor-pointer flex-1 min-w-0 select-none">
                                                        <input type="checkbox" 
                                                               name="gudang_ids[]" 
                                                               value="{{ $g->id }}" 
                                                               :checked="selectedGudangs.includes({{ $g->id }})"
                                                               @change="toggleGudang({{ $g->id }})"
                                                               class="size-4 rounded border-gray-300 dark:border-gray-700 text-neutral-900 dark:text-white focus:ring-0">
                                                        <div class="min-w-0">
                                                            <div class="text-xs font-medium text-text-active truncate flex items-center gap-1">
                                                                <span>{{ $g->nama }}</span>
                                                                @if($g->isPusat())
                                                                    <span class="px-1.5 py-0.5 rounded-[6px] text-[9px] font-medium bg-card text-foreground dark:text-foreground border border-border">Pusat</span>
                                                                @endif
                                                            </div>
                                                            <div class="text-[10px] text-text-muted tabular-nums">{{ $g->kode }}</div>
                                                        </div>
                                                    </label>
                                                    <div x-show="selectedGudangs.includes({{ $g->id }})" class="pl-2">
                                                        <label class="inline-flex items-center gap-1 cursor-pointer text-[10px] text-text-active hover:underline" title="Jadikan Gudang Utama (Home Base)">
                                                            <input type="radio" 
                                                                   name="primary_gudang_id" 
                                                                   value="{{ $g->id }}" 
                                                                   x-model="primaryGudang"
                                                                   class="size-3 text-neutral-900 dark:text-white focus:ring-0">
                                                            <span>Utama</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- Card 3: Wewenang Khusus Pengguna (Direct Permissions) --}}
            <div x-data="{ openDirectPerms: {{ !empty($activeDirectPermissions) ? 'true' : 'false' }} }">
                <x-card title="Wewenang Khusus Pengguna (Direct Permissions)" subtitle="Tambahkan izin spesifik individual di luar cakupan peran utama tanpa perlu membuat peran baru">
                    <x-slot:icon>
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </x-slot:icon>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3.5 rounded-[12px] bg-[#fafafa] dark:bg-gray-800/40 border border-[#E8E8E8] dark:border-gray-800 text-text-active text-xs">
                            <div class="flex items-center gap-2">
                                <svg class="size-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>
                                    Secara default, pengguna mewarisi seluruh wewenang dari <strong>Peran Utama (Role)</strong>. 
                                    @if(!empty($activeDirectPermissions))
                                        Pengguna ini memiliki <strong>{{ count($activeDirectPermissions) }} wewenang khusus tambahan</strong> yang aktif.
                                    @else
                                        Gunakan matriks ini jika ingin memberikan wewenang khusus ekstra.
                                    @endif
                                </span>
                            </div>
                            <button type="button" 
                                    @click="openDirectPerms = !openDirectPerms" 
                                    class="text-xs font-medium text-text-active underline whitespace-nowrap cursor-pointer ml-3">
                                <span x-text="openDirectPerms ? 'Tutup Matriks Izin' : 'Buka Matriks Wewenang Khusus'"></span>
                            </button>
                        </div>

                        <div x-show="openDirectPerms" x-transition>
                            <x-permission-matrix :catalog="$catalog" :activePermissions="$errors->any() ? old('permissions', []) : $activeDirectPermissions" />
                        </div>
                    </div>

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2">
                            <x-button href="{{ route('users.index') }}" variant="secondary" size="sm">
                                Batal
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm">
                                <x-slot:icon>
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </x-slot:icon>
                                Perbarui Pengguna
                            </x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </div>
        </form>
    </div>
</x-app-layout>

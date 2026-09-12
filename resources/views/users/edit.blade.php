<x-app-layout title="Edit Pengguna">
    <div class="max-w-5xl mx-auto space-y-6">
        <x-page-header
            title="Edit Pengguna: {{ $user->name }}"
            subtitle="Perbarui profil akun, departemen divisi, hak akses gudang, dan peran wewenang sistem"
            :breadcrumbs="['Pengaturan' => null, 'Manajemen Pengguna' => route('users.index'), $user->name => null, 'Edit' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('users.index') }}" variant="secondary" size="xs">
                    &larr; Kembali ke Daftar
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <form action="{{ route('users.update', $user) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Card 1: Informasi Akun & Divisi --}}
            <x-card title="Data Akun & Departemen Organisasi" subtitle="Mengubah akun login: {{ $user->email }}" variant="primary">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <x-form-group name="name" label="Nama Lengkap" :required="true">
                            <x-input name="name" value="{{ old('name', $user->name) }}" :required="true" />
                        </x-form-group>
                    </div>

                    <div>
                        <x-form-group name="email" label="Alamat Email" :required="true">
                            <x-input type="email" name="email" value="{{ old('email', $user->email) }}" :required="true" />
                        </x-form-group>
                    </div>

                    <div class="md:col-span-2">
                        <x-form-group name="divisi" label="Divisi / Departemen Kerja" help="Menentukan unit kerja pengguna dalam struktur rantai pasok Heaven Scent">
                            <x-select name="divisi" placeholder="-- Pilih Divisi / Departemen --">
                                @foreach($divisiList as $divKey => $divLabel)
                                    <option value="{{ $divKey }}" @selected(old('divisi', $user->divisi) === $divKey)>{{ $divLabel }}</option>
                                @endforeach
                            </x-select>
                        </x-form-group>
                    </div>

                    <div>
                        <x-form-group name="password" label="Kata Sandi Baru" help="Kosongkan jika tidak ingin mengubah kata sandi (min. 8 karakter)">
                            <x-input type="password" name="password" placeholder="••••••••" />
                        </x-form-group>
                    </div>

                    <div>
                        <x-form-group name="password_confirmation" label="Konfirmasi Kata Sandi" help="Ulangi jika mengubah kata sandi">
                            <x-input type="password" name="password_confirmation" placeholder="••••••••" />
                        </x-form-group>
                    </div>
                </div>
            </x-card>

            {{-- Card 2: Peran Utama & Akses Lokasi Fisik Gudang (LBAC) --}}
            <div x-data="{
                accessType: '{{ old('warehouse_access_type', $isGlobalWarehouse ? 'global' : 'restricted') }}',
                selectedGudangs: {{ json_encode(array_map('intval', (array) old('gudang_ids', $assignedGudangIds))) }},
                primaryGudang: '{{ old('primary_gudang_id', $primaryGudangId ?? '') }}',
                
                toggleGudang(id) {
                    id = parseInt(id);
                    if (this.selectedGudangs.includes(id)) {
                        this.selectedGudangs = this.selectedGudangs.filter(g => g !== id);
                        if (parseInt(this.primaryGudang) === id) {
                            this.primaryGudang = this.selectedGudangs.length > 0 ? this.selectedGudangs[0] : '';
                        }
                    } else {
                        this.selectedGudangs.push(id);
                        if (!this.primaryGudang) {
                            this.primaryGudang = id;
                        }
                    }
                }
            }">
                <x-card title="Peran & Hak Akses Fasilitas Gudang" subtitle="Tentukan peran wewenang sistem dan batas wilayah/lokasi operasional staf" variant="primary">
                    <div class="space-y-6">
                        {{-- Role Utama --}}
                        <div>
                            <x-form-group name="role" label="Peran Utama (Role)" :required="true" help="Menentukan grup hak akses default dan wewenang approval">
                                <x-select name="role" placeholder="-- Pilih Role --" :required="true">
                                    @foreach($roles as $r)
                                        @php
                                            $rKey = is_object($r) ? $r->name : $r;
                                            $rLabel = is_object($r) ? ($r->display_name ? $r->display_name . ' (' . $r->name . ')' : $r->name) : $r;
                                        @endphp
                                        <option value="{{ $rKey }}" @selected(old('role', $user->roles->first()?->name) === $rKey)>{{ $rLabel }}</option>
                                    @endforeach
                                </x-select>
                            </x-form-group>
                        </div>

                        {{-- Mode Akses Gudang (LBAC) --}}
                        <div class="border-t border-gray-100 pt-5">
                            <label class="block text-xs font-bold text-gray-800 uppercase tracking-wider mb-2">
                                Lingkup Wilayah / Fasilitas Gudang (LBAC)
                            </label>
                            <p class="text-xs text-gray-500 mb-4">
                                Batasi data stok, mutasi kartu stok, dan dokumen transfer yang dapat diakses pengguna berdasarkan gudang fisik.
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                                <label class="relative flex items-start p-3 rounded-sm border cursor-pointer select-none transition"
                                       :class="accessType === 'global' ? 'bg-primary-50/40 border-primary-300 ring-1 ring-primary-200' : 'bg-white border-gray-200 hover:border-gray-300'">
                                    <input type="radio" name="warehouse_access_type" value="global" x-model="accessType" class="mt-0.5 text-primary-600 focus:ring-primary-500">
                                    <div class="ml-3">
                                        <span class="block text-xs font-bold text-gray-900">🌍 Akses Seluruh Gudang (Global)</span>
                                        <span class="block text-[11px] text-gray-500 mt-0.5 leading-normal">
                                            Pengguna bebas melihat stok dan membuat mutasi di semua fasilitas (Cocok untuk Manager, Direksi, Auditor).
                                        </span>
                                    </div>
                                </label>

                                <label class="relative flex items-start p-3 rounded-sm border cursor-pointer select-none transition"
                                       :class="accessType === 'restricted' ? 'bg-primary-50/40 border-primary-300 ring-1 ring-primary-200' : 'bg-white border-gray-200 hover:border-gray-300'">
                                    <input type="radio" name="warehouse_access_type" value="restricted" x-model="accessType" class="mt-0.5 text-primary-600 focus:ring-primary-500">
                                    <div class="ml-3">
                                        <span class="block text-xs font-bold text-gray-900">🏢 Dibatasi ke Fasilitas Tertentu</span>
                                        <span class="block text-[11px] text-gray-500 mt-0.5 leading-normal">
                                            Pengguna hanya diizinkan melihat data pada gudang-gudang yang dicentang di bawah ini.
                                        </span>
                                    </div>
                                </label>
                            </div>

                            {{-- Checklist Gudang per Tipe --}}
                            <div x-show="accessType === 'restricted'" x-transition class="space-y-4 bg-gray-50/70 p-4 rounded-sm border border-gray-200">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-gray-800">Daftar Fasilitas Gudang yang Diizinkan:</span>
                                    <span class="text-[11px] text-gray-500">
                                        <span class="font-bold text-primary-700" x-text="selectedGudangs.length"></span> gudang dipilih
                                    </span>
                                </div>

                                @php
                                    $groupedGudangs = $gudangs->groupBy(function($g) {
                                        return match($g->tipe) {
                                            'bahan_baku' => 'Gudang Bahan Baku & Kemasan',
                                            'operasional' => 'Gudang Operasional / Lab Produksi',
                                            'fulfillment_pusat', 'fulfillment_cabang' => 'Fulfillment & Distribusi Produk Jadi',
                                            default => 'Lainnya',
                                        };
                                    });
                                @endphp

                                @foreach($groupedGudangs as $groupTitle => $groupItems)
                                    <div class="bg-white p-3 rounded-sm border border-gray-200 shadow-2xs">
                                        <div class="text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-primary-600"></span>
                                            {{ $groupTitle }}
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                                            @foreach($groupItems as $g)
                                                <div class="flex items-center justify-between p-2 rounded border border-gray-100 bg-gray-50/40 hover:bg-gray-50 transition">
                                                    <label class="flex items-center gap-2 cursor-pointer flex-1 min-w-0 select-none">
                                                        <input type="checkbox" 
                                                               name="gudang_ids[]" 
                                                               value="{{ $g->id }}" 
                                                               :checked="selectedGudangs.includes({{ $g->id }})"
                                                               @change="toggleGudang({{ $g->id }})"
                                                               class="w-4 h-4 rounded-sm text-primary-600 focus:ring-primary-500 border-gray-300">
                                                        <div class="min-w-0">
                                                            <div class="text-xs font-semibold text-gray-900 truncate">{{ $g->nama }}</div>
                                                            <div class="text-[10px] text-gray-400 font-mono">{{ $g->kode }}</div>
                                                        </div>
                                                    </label>
                                                    <div x-show="selectedGudangs.includes({{ $g->id }})" class="pl-2">
                                                        <label class="inline-flex items-center gap-1 cursor-pointer text-[10px] text-amber-700 hover:text-amber-900" title="Jadikan Gudang Utama (Home Base)">
                                                            <input type="radio" 
                                                                   name="primary_gudang_id" 
                                                                   value="{{ $g->id }}" 
                                                                   x-model="primaryGudang"
                                                                   class="w-3 h-3 text-amber-600 focus:ring-amber-500 border-gray-300">
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
                <x-card title="Wewenang Khusus Pengguna (Direct Permissions)" subtitle="Tambahkan izin spesifik individual di luar cakupan peran utama tanpa perlu membuat peran baru" variant="primary">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 rounded-sm bg-blue-50/60 border border-blue-200/80 text-blue-800 text-xs">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                                    class="text-xs font-semibold text-primary-700 hover:text-primary-900 underline whitespace-nowrap cursor-pointer">
                                <span x-text="openDirectPerms ? 'Tutup Matriks Izin' : 'Buka Matriks Wewenang Khusus'"></span>
                            </button>
                        </div>

                        <div x-show="openDirectPerms" x-transition>
                            <x-permission-matrix :catalog="$catalog" :activePermissions="old('permissions', $activeDirectPermissions)" />
                        </div>
                    </div>

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2.5">
                            <x-button href="{{ route('users.index') }}" variant="secondary" size="sm">
                                Batal
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm">
                                <x-slot:icon>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
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

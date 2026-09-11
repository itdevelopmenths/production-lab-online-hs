<x-app-layout title="Tambah Pengguna">
    <x-page-header
        title="Tambah Pengguna Baru"
        subtitle="Daftarkan akun pengguna baru beserta peran akses sistem"
        :breadcrumbs="['Pengaturan' => null, 'Manajemen Pengguna' => route('users.index'), 'Tambah' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('users.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Daftar
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="max-w-3xl">
        <form action="{{ route('users.store') }}" method="POST">
            @csrf
            <x-card title="Data Akun & Kredensial" subtitle="Lengkapi profil pengguna, hak otorisasi peran, dan kata sandi masuk" variant="primary">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <x-form-group name="name" label="Nama Lengkap" :required="true" help="Nama lengkap staf operasional">
                            <x-input name="name" value="{{ old('name') }}" placeholder="misal: Budi Santoso" :required="true" />
                        </x-form-group>
                    </div>

                    <div class="md:col-span-2">
                        <x-form-group name="email" label="Alamat Email" :required="true" help="Digunakan sebagai identitas akun untuk login">
                            <x-input type="email" name="email" value="{{ old('email') }}" placeholder="budi@heavenscent.id" :required="true" />
                        </x-form-group>
                    </div>

                    <div class="md:col-span-2">
                        <x-form-group name="role" label="Peran / Hak Akses" :required="true" help="Menentukan menu dan wewenang fitur yang dapat diakses">
                            <x-select name="role" placeholder="-- Pilih Role --" :required="true">
                                @foreach($roles as $r)
                                    @php
                                        $rKey = is_object($r) ? $r->name : $r;
                                        $rLabel = is_object($r) ? ($r->display_name ? $r->display_name . ' (' . $r->name . ')' : $r->name) : $r;
                                    @endphp
                                    <option value="{{ $rKey }}" @selected(old('role') === $rKey)>{{ $rLabel }}</option>
                                @endforeach
                            </x-select>
                        </x-form-group>
                    </div>

                    <x-form-group name="password" label="Kata Sandi" :required="true" help="Minimal 8 karakter alfanumerik">
                        <x-input type="password" name="password" placeholder="••••••••" :required="true" />
                    </x-form-group>

                    <x-form-group name="password_confirmation" label="Konfirmasi Kata Sandi" :required="true" help="Ulangi kata sandi persis seperti di samping">
                        <x-input type="password" name="password_confirmation" placeholder="••••••••" :required="true" />
                    </x-form-group>
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
                            Simpan Pengguna
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

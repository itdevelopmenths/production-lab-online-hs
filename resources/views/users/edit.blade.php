<x-app-layout title="Edit Pengguna">
    <x-page-header
        title="Edit Pengguna"
        subtitle="Perbarui data profil atau hak akses peran pengguna"
        :breadcrumbs="['Pengaturan' => null, 'Manajemen Pengguna' => route('users.index'), $user->name => null, 'Edit' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('users.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Daftar
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="max-w-3xl">
        <form action="{{ route('users.update', $user) }}" method="POST">
            @csrf
            @method('PUT')
            <x-card title="Data Akun & Hak Akses" subtitle="Mengubah akun: {{ $user->email }}" variant="primary">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <x-form-group name="name" label="Nama Lengkap" :required="true">
                            <x-input name="name" value="{{ old('name', $user->name) }}" :required="true" />
                        </x-form-group>
                    </div>

                    <div class="md:col-span-2">
                        <x-form-group name="email" label="Alamat Email" :required="true">
                            <x-input type="email" name="email" value="{{ old('email', $user->email) }}" :required="true" />
                        </x-form-group>
                    </div>

                    <div class="md:col-span-2">
                        <x-form-group name="role" label="Peran / Hak Akses" :required="true">
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

                    <x-form-group name="password" label="Kata Sandi Baru" help="Kosongkan jika tidak ingin mengubah kata sandi (min. 8 karakter)">
                        <x-input type="password" name="password" placeholder="••••••••" />
                    </x-form-group>

                    <x-form-group name="password_confirmation" label="Konfirmasi Kata Sandi" help="Ulangi jika mengubah kata sandi">
                        <x-input type="password" name="password_confirmation" placeholder="••••••••" />
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
                            Perbarui Pengguna
                        </x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-app-layout>

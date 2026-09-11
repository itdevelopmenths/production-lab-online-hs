<x-app-layout title="Tambah Peran Baru">
    <x-page-header
        title="Tambah Peran Baru"
        subtitle="Daftarkan peran kustom baru dan atur matriks wewenang wewenang modul laboratorium"
        :breadcrumbs="['Sistem' => null, 'Peran & Wewenang' => route('roles.index'), 'Tambah' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('roles.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Daftar
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <form action="{{ route('roles.store') }}" method="POST">
        @csrf

        <div class="space-y-6">
            {{-- Form Metadata Peran --}}
            <x-card title="Informasi Identitas Peran" subtitle="Tentukan nama tampilan dan kode pengenal teknis peran" variant="primary">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <x-form-group name="display_name" label="Nama Tampilan Peran" :required="true" help="Contoh: Supervisor Produksi, Staff QC Lab, Auditor Finansial">
                            <x-input name="display_name" value="{{ old('display_name') }}" placeholder="misal: Supervisor QC Lab" :required="true" />
                        </x-form-group>
                    </div>

                    <div>
                        <x-form-group name="name" label="Kode Identifikasi Teknis (Key)" :required="true" help="Huruf kecil, angka, dan garis bawah (_). Digunakan untuk identifikasi wewenang.">
                            <x-input name="name" value="{{ old('name') }}" placeholder="misal: supervisor_qc" :required="true" />
                        </x-form-group>
                    </div>

                    <div class="md:col-span-2">
                        <x-form-group name="description" label="Deskripsi & Tanggung Jawab" help="Penjelasan tugas peran ini untuk panduan operasional">
                            <x-textarea name="description" placeholder="Jelaskan cakupan tanggung jawab peran ini..." rows="2">{{ old('description') }}</x-textarea>
                        </x-form-group>
                    </div>
                </div>
            </x-card>

            {{-- Permission Matrix Component --}}
            <div>
                <x-permission-matrix :catalog="$catalog" :activePermissions="old('permissions', $activePermissions)" />
            </div>

            {{-- Action Buttons --}}
            <div class="bg-white border border-gray-200 rounded-sm p-4 flex items-center justify-end gap-3 shadow-2xs">
                <x-button href="{{ route('roles.index') }}" variant="secondary" size="sm">
                    Batal
                </x-button>
                <x-button type="submit" variant="primary" size="sm">
                    <x-slot:icon>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </x-slot:icon>
                    Simpan Peran & Wewenang
                </x-button>
            </div>
        </div>
    </form>
</x-app-layout>

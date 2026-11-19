<x-app-layout title="Tambah Peran Baru">
    <div class="w-full space-y-4">
        <x-page-header
            title="Tambah Peran Baru"
            subtitle="Daftarkan peran kustom baru dan atur matriks wewenang wewenang modul laboratorium"
            :breadcrumbs="['Sistem' => null, 'Peran & Wewenang' => route('roles.index'), 'Tambah' => null]"
            :backUrl="route('roles.index')"
        />

        <form action="{{ route('roles.store') }}" method="POST">
            @csrf

            <div class="space-y-6">
                {{-- Form Metadata Peran (AppForm Shell) --}}
                <div class="bg-[#F5F5F5] dark:bg-gray-950 border border-[#E8E8E8] dark:border-gray-800 rounded-[12px] p-4 sm:p-5 space-y-5">
                    <x-form-section title="Informasi Identitas Peran" :cols="2">
                        <x-form-field name="display_name" label="Nama Tampilan" :required="true" help="Contoh: Supervisor Produksi, Staff QC Lab, Auditor Finansial">
                            <x-input name="display_name" id="display_name" variant="seamless" value="{{ old('display_name') }}" placeholder="misal: Supervisor QC Lab" :required="true" />
                        </x-form-field>

                        <x-form-field name="name" label="Kode Identifikasi (Key)" :required="true" help="Huruf kecil, angka, dan garis bawah (_). Digunakan untuk identifikasi wewenang.">
                            <x-input name="name" id="name" variant="seamless" value="{{ old('name') }}" placeholder="misal: supervisor_qc" :required="true" class="font-mono" />
                        </x-form-field>

                        <div class="md:col-span-2">
                            <x-form-field name="description" label="Deskripsi &amp; Tugas" align="top" help="Penjelasan tugas peran ini untuk panduan operasional">
                                <x-textarea name="description" id="description" variant="seamless" placeholder="Jelaskan cakupan tanggung jawab peran ini..." rows="2">{{ old('description') }}</x-textarea>
                            </x-form-field>
                        </div>
                    </x-form-section>
                </div>

                {{-- Permission Matrix Component --}}
                <div>
                    <x-permission-matrix :catalog="$catalog" :activePermissions="old('permissions', $activePermissions)" />
                </div>

                {{-- Action Buttons --}}
                <div class="bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-gray-800 rounded-[12px] p-4 flex items-center justify-end gap-2 shadow-xs">
                    <x-button href="{{ route('roles.index') }}" variant="secondary" size="sm">
                        Batal
                    </x-button>
                    <x-button type="submit" variant="primary" size="sm">
                        <x-slot:icon>
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </x-slot:icon>
                        Simpan Peran & Wewenang
                    </x-button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>

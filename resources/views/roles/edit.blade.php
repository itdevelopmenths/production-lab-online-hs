<x-app-layout title="Ubah Peran & Wewenang">
    <div class="flex justify-center w-full">
        <div class="w-full max-w-5xl space-y-6">
            <x-page-header
                title="Ubah Peran: {{ $role->display_name ?: $role->name }}"
                subtitle="Sesuaikan informasi peran dan konfigurasi wewenang modul laboratorium"
                :breadcrumbs="['Sistem' => null, 'Peran & Wewenang' => route('roles.index'), 'Ubah' => null]"
            >
                <x-slot:actions>
                    <x-button href="{{ route('roles.index') }}" variant="secondary" size="xs">
                        &larr; Kembali ke Daftar
                    </x-button>
                </x-slot:actions>
            </x-page-header>

            @if ($role->is_system)
                <x-alert type="warning" title="Peran Sistem Terproteksi:">
                    Peran ini merupakan peran bawaan sistem (Core System Role). Kode identifikasi (<code class="font-bold">{{ $role->name }}</code>) dikunci untuk menjamin integritas alur kerja, namun Anda dapat menyesuaikan wewenang modul di bawah secara leluasa.
                </x-alert>
            @endif

            <form action="{{ route('roles.update', $role) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    {{-- Form Metadata Peran --}}
                    <x-card title="Informasi Identitas Peran" subtitle="Perbarui nama tampilan atau deskripsi peran" variant="primary">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <x-form-group name="display_name" label="Nama Tampilan Peran" :required="true">
                                    <x-input name="display_name" value="{{ old('display_name', $role->display_name ?: $role->name) }}" placeholder="misal: Supervisor QC Lab" :required="true" />
                                </x-form-group>
                            </div>

                            <div>
                                <x-form-group name="name" label="Kode Identifikasi Teknis (Key)" :required="!$role->is_system" help="{{ $role->is_system ? 'Terkunci (Peran Bawaan Sistem)' : 'Identifier unik peran' }}">
                                    @if ($role->is_system)
                                        <x-input name="name" value="{{ $role->name }}" readonly="readonly" class="bg-gray-100 text-gray-500 cursor-not-allowed font-mono" />
                                    @else
                                        <x-input name="name" value="{{ old('name', $role->name) }}" placeholder="misal: supervisor_qc" :required="true" />
                                    @endif
                                </x-form-group>
                            </div>

                            <div class="md:col-span-2">
                                <x-form-group name="description" label="Deskripsi & Tanggung Jawab" help="Penjelasan tugas peran ini untuk panduan operasional">
                                    <x-textarea name="description" placeholder="Jelaskan cakupan tanggung jawab peran ini..." rows="2">{{ old('description', $role->description) }}</x-textarea>
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
                            Simpan Perubahan
                        </x-button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

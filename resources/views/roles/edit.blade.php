<x-app-layout title="Ubah Peran & Wewenang">
    <div class="w-full space-y-4">
        <x-page-header
            title="Ubah Peran: {{ $role->display_name ?: $role->name }}"
            subtitle="Sesuaikan informasi peran dan konfigurasi wewenang modul laboratorium"
            :breadcrumbs="['Sistem' => null, 'Peran & Wewenang' => route('roles.index'), 'Ubah' => null]"
            :backUrl="route('roles.index')"
        />

        @php($lockedPermissions = $role->name === 'manager' ? ['role.manage'] : [])

        @if ($role->name === 'manager')
            <x-alert type="info" title="Peran Manager:">
                Kelola Peran selalu aktif untuk Manager agar admin tidak mengunci dirinya sendiri. Wewenang lain bisa diatur bebas.
            </x-alert>
        @endif

        @if ($role->is_system)
            <x-alert type="warning" title="Peran Sistem Terproteksi:">
                Peran ini merupakan peran bawaan sistem (Core System Role). Kode identifikasi (<code class="font-medium tabular-nums">{{ $role->name }}</code>) dikunci untuk menjamin integritas alur kerja, namun Anda dapat menyesuaikan wewenang modul di bawah secara leluasa.
            </x-alert>
        @endif

        <form action="{{ route('roles.update', $role) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                {{-- Form Metadata Peran (AppForm Shell) --}}
                <div class="bg-[#F5F5F5] dark:bg-gray-950 border border-[#E8E8E8] dark:border-gray-800 rounded-[12px] p-4 sm:p-5 space-y-5">
                    <x-form-section title="Informasi Identitas Peran" :cols="2">
                        <x-form-field name="display_name" label="Nama Tampilan" :required="true">
                            <x-input name="display_name" id="display_name" variant="seamless" value="{{ old('display_name', $role->display_name ?: $role->name) }}" placeholder="misal: Supervisor QC Lab" :required="true" />
                        </x-form-field>

                        <x-form-field name="name" label="Kode Identifikasi (Key)" :required="!$role->is_system" :disabled="$role->is_system" help="{{ $role->is_system ? 'Terkunci (Peran Bawaan Sistem)' : 'Identifier unik peran' }}">
                            @if ($role->is_system)
                                <x-input name="name" id="name" variant="seamless" value="{{ $role->name }}" readonly="readonly" class="text-text-muted cursor-not-allowed tabular-nums" />
                            @else
                                <x-input name="name" id="name" variant="seamless" value="{{ old('name', $role->name) }}" placeholder="misal: supervisor_qc" :required="true" class="tabular-nums" />
                            @endif
                        </x-form-field>

                        <div class="md:col-span-2">
                            <x-form-field name="description" label="Deskripsi &amp; Tugas" align="top" help="Penjelasan tugas peran ini untuk panduan operasional">
                                <x-textarea name="description" id="description" variant="seamless" placeholder="Jelaskan cakupan tanggung jawab peran ini..." rows="2">{{ old('description', $role->description) }}</x-textarea>
                            </x-form-field>
                        </div>
                    </x-form-section>
                </div>

                {{-- Permission Matrix Component --}}
                <div>
                    <x-permission-matrix :catalog="$catalog" :activePermissions="$errors->any() ? old('permissions', []) : $activePermissions" :locked="$lockedPermissions" />
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
                        Simpan Perubahan
                    </x-button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>

@php
    $offsetHari = intdiv($offsetMinutes, 1440);
    $offsetJam = intdiv($offsetMinutes % 1440, 60);
    $offsetSisaMenit = $offsetMinutes % 60;
@endphp

<x-app-layout title="QA Clock">
    <div class="w-full space-y-4">
        <x-page-header
            title="QA Clock"
            subtitle="Majukan jam aplikasi untuk menguji lead time, SLA, dan jatuh tempo. Hanya tersedia di environment non-production."
            :breadcrumbs="['QA Tools' => null, 'QA Clock' => null]"
        />

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-card title="Jam asli" :bodyClass="'p-4 sm:p-5'">
                <p class="text-lg font-semibold tabular-nums text-[#101828] dark:text-white">{{ $realNow->format('d M Y H:i') }}</p>
                <p class="text-[11px] text-text-muted mt-0.5">{{ config('app.timezone') }}</p>
            </x-card>
            <x-card title="Jam QA" :bodyClass="'p-4 sm:p-5'">
                <p class="text-lg font-semibold tabular-nums text-[#101828] dark:text-white">{{ $fakeNow->format('d M Y H:i') }}</p>
                <p class="text-[11px] text-text-muted mt-0.5">Dipakai seluruh aplikasi saat offset aktif</p>
            </x-card>
            <x-card title="Offset" :bodyClass="'p-4 sm:p-5'">
                <p class="text-lg font-semibold tabular-nums text-[#101828] dark:text-white">+{{ $offsetHari }} hari {{ $offsetJam }} jam</p>
                <p class="text-[11px] text-text-muted mt-0.5">{{ number_format($offsetMinutes, 0, ',', '.') }} menit @if($offsetSisaMenit > 0)(termasuk {{ $offsetSisaMenit }} menit)@endif</p>
            </x-card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <form method="POST" action="{{ route('qa.clock.advance') }}">
                @csrf
                <x-card title="Majukan jam" subtitle="Menambah offset dari offset yang sedang berjalan" :noPadding="true">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 sm:p-5">
                        <x-form-field label="Hari" name="hari" help="0 sampai 365">
                            <x-input type="number" name="hari" :value="old('hari', 0)" min="0" max="365" variant="seamless" />
                        </x-form-field>
                        <x-form-field label="Jam" name="jam" help="0 sampai 999">
                            <x-input type="number" name="jam" :value="old('jam', 0)" min="0" max="999" variant="seamless" />
                        </x-form-field>
                    </div>
                    <x-slot:footer>
                        <div class="flex items-center justify-end">
                            <x-button type="submit" variant="primary" size="sm">Majukan</x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>

            <form method="POST" action="{{ route('qa.clock.set') }}">
                @csrf
                <x-card title="Set waktu tertentu" subtitle="Offset dihitung dari jam asli, tidak boleh mundur dari jam asli" :noPadding="true">
                    <div class="p-4 sm:p-5">
                        <x-form-field label="Waktu QA" name="waktu">
                            <x-input type="datetime-local" name="waktu" :value="old('waktu')" required variant="seamless" />
                        </x-form-field>
                    </div>
                    <x-slot:footer>
                        <div class="flex items-center justify-end">
                            <x-button type="submit" variant="primary" size="sm">Set waktu</x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>

        <x-card title="Kembalikan ke jam asli" subtitle="Offset diatur ulang menjadi 0" :noPadding="true">
            <form method="POST" action="{{ route('qa.clock.reset') }}"
                  x-data
                  @submit.prevent="appDialog.confirm('Data yang dibuat saat jam dimajukan akan tampak berada di masa depan setelah reset.', { title: 'Reset QA Clock?', tone: 'danger', confirmText: 'Reset' }).then(ok => { if (ok) $el.submit(); })"
                  class="flex items-center justify-between gap-3 p-4 sm:p-5">
                @csrf
                <p class="text-xs text-text-muted min-w-0">Data yang dibuat saat jam dimajukan akan tampak berada di masa depan setelah reset.</p>
                <x-button type="submit" variant="danger" size="sm">Reset</x-button>
            </form>
        </x-card>

        <x-callout variant="warning" title="Perlu diketahui">
            <ul class="list-disc list-inside space-y-1">
                <li>Sesi yang ditulis saat jam dimajukan membawa <code>last_activity</code> di masa depan. Ini tidak berbahaya dan tidak membuat user ter-logout.</li>
                <li>Kolom dengan default DB <code>useCurrent()</code> (misalnya <code>supplier_lt_history.dicatat_pada</code>, <code>supplier_lt_summary.diperbarui_pada</code>) memakai jam DB asli bila kode tidak mengisinya.</li>
                <li>Worker queue (<code>queue:work</code>) mengikuti QA Clock pada setiap polling. <code>schedule:work</code> yang dimulai saat offset &gt; 0 bisa membeku; jalankan <code>php artisan schedule:run</code> atau command-nya langsung.</li>
                <li>Fungsi native <code>date()</code>/<code>strtotime()</code> tidak mengikuti QA Clock (default tanggal form PO, nomor REQ/TRF); isi tanggal manual saat jam dimajukan.</li>
            </ul>
        </x-callout>
    </div>
</x-app-layout>

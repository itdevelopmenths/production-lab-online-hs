<x-app-layout title="Detail Batch {{ $batch->no_batch }}">
    <!-- Page Header & Action Tools -->
    <x-page-header
        :title="$batch->no_batch"
        :subtitle="$batch->produk->nama . ' (' . $batch->produk->sku . ') · Tanggal ' . $batch->tanggal->format('d M Y') . ($batch->creator ? ' · Oleh ' . $batch->creator->name : '')"
        :breadcrumbs="[
            'Produksi' => route('batches.index'),
            'Batch' => route('batches.index'),
            $batch->no_batch => null,
        ]"
    >
        <x-slot:actions>
            @php
                $statusVariant = match($batch->status) {
                    'rencana' => 'warning',
                    'release' => 'info',
                    'selesai' => 'success',
                    'dibatalkan' => 'danger',
                    default => 'gray',
                };
            @endphp
            <x-badge :variant="$statusVariant" :dot="true" size="sm">
                {{ $batch->status === 'release' ? 'Release (Berjalan)' : ucfirst($batch->status) }}
            </x-badge>

            <x-button href="{{ route('batches.index') }}" variant="secondary" size="xs">
                &larr; Kembali
            </x-button>

            @can('batch.release')
            @if($batch->status === 'rencana')
            <form method="POST" action="{{ route('batches.release', $batch) }}" class="inline">
                @csrf
                <x-button type="submit" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </x-slot:icon>
                    Release & Issue
                </x-button>
            </form>
            @endif
            @endcan

            @can('rt.create')
            @if($batch->status === 'selesai')
            <form method="POST" action="{{ route('batches.kirim', $batch) }}" class="inline">
                @csrf
                <x-button type="submit" variant="primary" size="xs">
                    <x-slot:icon>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3L9 8m-5 5h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293h3.172a1 1 0 00.707-.293l2.414-2.414a1 1 0 01.707-.293H20"/></svg>
                    </x-slot:icon>
                    Kirim ke Fulfillment
                </x-button>
            </form>
            @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <!-- AdminLTE 4 KPI Small Boxes Ribbon (4 Cards) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mb-5">
        <x-stat-box
            title="Target Rencana"
            :value="rtrim(rtrim(number_format($batch->qty_rencana, 2, ',', '.'), '0'), ',')"
            :subtitle="$batch->produk->satuan . ' · Target kuantitas batch'"
            variant="primary"
        />

        <x-stat-box
            title="Gudang Operasional"
            :value="$batch->gudangOperasional?->nama ?? 'Belum Ditentukan'"
            subtitle="Sumber penarikan bahan"
            variant="default"
        />

        <x-stat-box
            title="Gudang Tujuan"
            :value="$batch->gudangTujuan?->nama ?? 'Fulfillment Pusat'"
            subtitle="Tujuan produk jadi"
            variant="default"
        />

        <x-stat-box
            title="Kesiapan Bahan"
            :value="$hasDeficitFisik ? 'Stok Kurang' : ($hasDeficitRencana ? 'Defisit Rencana' : 'Siap Rilis')"
            :subtitle="$hasDeficitFisik ? 'Perlu transfer dari GD Pusat' : ($hasDeficitRencana ? 'Alokasi batch lain tinggi' : '100% bahan tersedia')"
            :variant="$hasDeficitFisik ? 'danger' : ($hasDeficitRencana ? 'warning' : 'success')"
        />
    </div>

    <!-- Sleek Inline Alert Bar (Jika bahan fisik kurang pada status rencana) -->
    @if($batch->status === 'rencana' && $hasDeficitFisik)
    <div class="mb-5">
        <x-alert type="danger" title="Peringatan Dini:">
            Saldo fisik bahan baku di {{ $batch->gudangOperasional?->nama ?? 'Gudang Operasional' }} belum mencukupi untuk batch ini.
            <x-slot:action>
                @can('rt.create')
                <x-button href="{{ route('rt.create') }}" variant="danger" size="xs">
                    + Buat Request Bahan
                </x-button>
                @endcan
            </x-slot:action>
        </x-alert>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Kolom Kiri: Tabel Alokasi Bahan & Modul Produksi -->
        <div class="lg:col-span-2 space-y-5">
            <!-- Tabel Alokasi Bahan Baku (BOM Explode) -->
            <x-card title="Alokasi Bahan Baku (BOM Explode)" subtitle="Formula resep × target kuantitas. Stok fisik dipotong saat Release & Issue." :noPadding="true">
                <x-slot:tools>
                    <span class="text-xs text-gray-500 font-mono font-medium">{{ $batch->alokasi->count() }} komponen resep</span>
                </x-slot:tools>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-100 border-b border-gray-200 text-gray-700 font-semibold">
                            <tr>
                                <th class="py-2.5 px-3.5">Bahan Baku</th>
                                <th class="py-2.5 px-3 text-right">Kebutuhan</th>
                                <th class="py-2.5 px-3 text-right">Stok Fisik Ops</th>
                                <th class="py-2.5 px-3 text-right">Kolom Rencana</th>
                                <th class="py-2.5 px-3 text-right">Stok GD Pusat</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($batch->alokasi as $a)
                            @php
                                $w = $earlyWarnings[$a->id] ?? null;
                            @endphp
                            <tr class="hover:bg-gray-50/70 transition">
                                <td class="py-2.5 px-3.5">
                                    <div class="font-bold text-gray-900">{{ $a->bahan->nama }}</div>
                                    <div class="font-mono text-[11px] text-gray-400">{{ $a->bahan->sku }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-right font-semibold text-gray-900">
                                    {{ rtrim(rtrim(number_format($a->qty_dialokasikan, 2, ',', '.'), '0'), ',') }}
                                    <span class="text-gray-400 font-normal">{{ $a->bahan->satuan }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium text-gray-700">
                                    @if($w)
                                        {{ rtrim(rtrim(number_format($w['stok_fisik_op'], 2, ',', '.'), '0'), ',') }}
                                        <span class="text-gray-400 font-normal">{{ $a->bahan->satuan }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium {{ ($w['stok_rencana'] ?? 0) < 0 ? 'text-rose-600 font-bold' : 'text-gray-700' }}">
                                    @if($w)
                                        {{ rtrim(rtrim(number_format($w['stok_rencana'], 2, ',', '.'), '0'), ',') }}
                                        <span class="text-gray-400 font-normal">{{ $a->bahan->satuan }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right text-gray-500">
                                    @if($w)
                                        {{ rtrim(rtrim(number_format($w['stok_pusat'], 2, ',', '.'), '0'), ',') }}
                                        <span class="text-gray-400 font-normal">{{ $a->bahan->satuan }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                    @if($batch->status === 'rencana')
                                        @if($w && $w['kurang_fisik'] > 0)
                                            <x-badge variant="danger">
                                                Kurang {{ rtrim(rtrim(number_format($w['kurang_fisik'], 2, ',', '.'), '0'), ',') }} {{ $a->bahan->satuan }}
                                            </x-badge>
                                        @elseif($w && !$w['is_cukup_rencana'])
                                            <x-badge variant="warning">
                                                Defisit Rencana
                                            </x-badge>
                                        @else
                                            <x-badge variant="success">
                                                Cukup
                                            </x-badge>
                                        @endif
                                    @else
                                        <x-badge variant="gray">
                                            {{ ucfirst($a->status) }}
                                        </x-badge>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3.5 text-center whitespace-nowrap">
                                    <a href="{{ route('stok.ledger', $a->bahan_id) }}" class="text-primary-700 hover:text-primary-900 text-xs font-semibold">
                                        Kartu Stok &rarr;
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-gray-400">
                                    Resep BOM untuk produk ini belum dikonfigurasi.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            <!-- Form Selesaikan Batch (Khusus status release) -->
            @can('batch.complete')
            @if($batch->status === 'release')
            <x-card title="Selesaikan Batch Produksi" subtitle="Input hasil QC produk jadi. Kuantitas baik otomatis masuk ke saldo stok gudang." variant="success">
                <form method="POST" action="{{ route('batches.complete', $batch) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Qty Baik (Pcs)</label>
                        <input type="number" step="0.01" name="qty_baik" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" placeholder="0" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Qty Rusak (Defect)</label>
                        <input type="number" step="0.01" name="qty_rusak" value="0" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" required>
                    </div>
                    <div>
                        <x-button type="submit" variant="success" size="sm" class="w-full">
                            Simpan Hasil Batch
                        </x-button>
                    </div>
                </form>
            </x-card>
            @endif
            @endcan

            <!-- Stock Opname Pemakaian Bahan (Jika release atau selesai) -->
            @if(in_array($batch->status, ['release', 'selesai']))
            <x-card title="Stock Opname Pemakaian Bahan" subtitle="Pemakaian teoritis (BOM) vs aktual di ruang produksi." :noPadding="true">
                @can('batch.opname')
                <form method="POST" action="{{ route('batches.opname', $batch) }}" class="p-3.5">
                    @csrf
                    <table class="w-full text-left text-xs mb-3">
                        <thead class="bg-gray-100 border-b border-gray-200 text-gray-700 font-semibold">
                            <tr>
                                <th class="py-2 px-3">Bahan</th>
                                <th class="py-2 px-3 text-right">Teoritis (BOM)</th>
                                <th class="py-2 px-3 text-right w-36">Pemakaian Aktual</th>
                                <th class="py-2 px-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($batch->alokasi as $index => $a)
                            @php
                                $op = $batch->opname->where('bahan_id', $a->bahan_id)->first();
                                $valAktual = $op ? $op->pemakaian_aktual : (float) $a->qty_dialokasikan;
                            @endphp
                            <tr>
                                <td class="py-2 px-3 font-medium text-gray-800">
                                    <input type="hidden" name="items[{{ $index }}][bahan_id]" value="{{ $a->bahan_id }}">
                                    {{ $a->bahan->nama }}
                                </td>
                                <td class="py-2 px-3 text-right font-mono text-gray-600">
                                    {{ rtrim(rtrim(number_format($a->qty_dialokasikan, 2, ',', '.'), '0'), ',') }} {{ $a->bahan->satuan }}
                                </td>
                                <td class="py-2 px-3 text-right">
                                    <input type="number" step="0.01" min="0" name="items[{{ $index }}][pemakaian_aktual]" value="{{ $valAktual }}" class="w-full text-right rounded-sm border-gray-300 text-xs py-1 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" required>
                                </td>
                                <td class="py-2 px-3">
                                    <input type="text" name="items[{{ $index }}][keterangan]" value="{{ $op?->keterangan }}" placeholder="Catatan opname" class="w-full rounded-sm border-gray-300 text-xs py-1 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="flex justify-end">
                        <x-button type="submit" variant="primary" size="sm">
                            Simpan Opname
                        </x-button>
                    </div>
                </form>
                @else
                <div class="p-3.5">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-100 border-b border-gray-200 text-gray-700 font-semibold">
                            <tr>
                                <th class="py-2 px-3">Bahan</th>
                                <th class="py-2 px-3 text-right">Teoritis</th>
                                <th class="py-2 px-3 text-right">Aktual</th>
                                <th class="py-2 px-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($batch->opname as $op)
                            <tr>
                                <td class="py-2 px-3 font-medium text-gray-800">{{ $op->bahan?->nama }}</td>
                                <td class="py-2 px-3 text-right font-mono">{{ rtrim(rtrim(number_format($op->pemakaian_teoritis, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="py-2 px-3 text-right font-mono font-semibold">{{ rtrim(rtrim(number_format($op->pemakaian_aktual, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="py-2 px-3 text-gray-500">{{ $op->keterangan ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="py-3 text-center text-gray-400">Belum ada data opname tercatat.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endcan
            </x-card>
            @endif
        </div>

        <!-- Kolom Kanan: Detail QC & Aksi Manajemen -->
        <div class="space-y-5">
            <!-- Hasil QC & Produksi (Jika selesai atau release) -->
            @if(in_array($batch->status, ['release', 'selesai']))
            <x-card title="Hasil QC & Produksi" variant="primary">
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="bg-gray-50 p-2.5 rounded-sm border border-gray-200">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase">Qty Baik</p>
                        <p class="text-xl font-bold text-emerald-700 mt-0.5">
                            {{ $batch->qty_baik !== null ? rtrim(rtrim(number_format($batch->qty_baik, 2, ',', '.'), '0'), ',') : '-' }}
                        </p>
                    </div>
                    <div class="bg-gray-50 p-2.5 rounded-sm border border-gray-200">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase">Qty Rusak</p>
                        <p class="text-xl font-bold text-rose-700 mt-0.5">
                            {{ $batch->qty_rusak !== null ? rtrim(rtrim(number_format($batch->qty_rusak, 2, ',', '.'), '0'), ',') : '-' }}
                        </p>
                    </div>
                </div>
                <div class="pt-2 mt-2 border-t border-gray-100 flex items-center justify-between text-xs">
                    <span class="text-gray-500">Yield QC</span>
                    <span class="font-bold text-gray-900 font-mono">
                        {{ $batch->yield() !== null ? number_format($batch->yield(), 1) . '%' : '-' }}
                    </span>
                </div>
                <div class="flex items-center justify-between text-xs pt-1">
                    <span class="text-gray-500">Defect Rate</span>
                    <span class="font-bold text-gray-900 font-mono">
                        {{ $batch->defectRate() !== null ? number_format($batch->defectRate(), 1) . '%' : '-' }}
                    </span>
                </div>
            </x-card>
            @endif

            <!-- Panel Aksi Utama -->
            <x-card title="Kontrol Batch" variant="default">
                @if($batch->status === 'rencana')
                    @can('batch.release')
                    <form method="POST" action="{{ route('batches.release', $batch) }}">
                        @csrf
                        <x-button type="submit" variant="primary" size="md" class="w-full">
                            <x-slot:icon>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </x-slot:icon>
                            Release & Issue Bahan
                        </x-button>
                    </form>
                    <p class="text-[11px] text-gray-500 text-center mt-1">Menarik stok fisik bahan baku dari gudang operasional.</p>
                    @endcan

                    @can('rt.create')
                    <div class="mt-2.5">
                        <x-button href="{{ route('rt.create') }}" variant="secondary" size="sm" class="w-full">
                            + Buat Request Bahan
                        </x-button>
                    </div>
                    @endcan

                    @can('batch.cancel')
                    <form method="POST" action="{{ route('batches.cancel', $batch) }}" onsubmit="return confirm('Batalkan rencana batch ini? Alokasi bahan akan dilepas.');" class="pt-3 mt-3 border-t border-gray-100">
                        @csrf
                        <x-button type="submit" variant="link" size="xs" class="w-full text-rose-600 hover:text-rose-800">
                            Batalkan Batch
                        </x-button>
                    </form>
                    @endcan
                @endif

                @if($batch->status === 'selesai')
                    @can('rt.create')
                    <form method="POST" action="{{ route('batches.kirim', $batch) }}">
                        @csrf
                        <x-button type="submit" variant="primary" size="md" class="w-full">
                            <x-slot:icon>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3L9 8m-5 5h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293h3.172a1 1 0 00.707-.293l2.414-2.414a1 1 0 01.707-.293H20"/></svg>
                            </x-slot:icon>
                            Kirim ke Fulfillment
                        </x-button>
                    </form>
                    <p class="text-[11px] text-gray-500 text-center mt-1">Buat dokumen transfer produk jadi ke fulfillment hub.</p>
                    @endcan
                @endif

                @if($batch->status === 'dibatalkan')
                    <div class="py-2 px-3 bg-gray-50 rounded-sm text-center text-xs text-gray-500 font-medium">
                        Batch ini telah dibatalkan.
                    </div>
                @endif
            </x-card>

            <!-- Ringkasan Info Sistem -->
            <x-card title="Informasi Sistem" variant="default">
                <div class="space-y-2 text-xs text-gray-600">
                    <div class="flex justify-between">
                        <span>No Batch</span>
                        <span class="font-mono text-gray-900 font-semibold">{{ $batch->no_batch }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tipe Produk</span>
                        <span class="capitalize text-gray-900 font-medium">{{ str_replace('_', ' ', $batch->produk->tipe) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Satuan Unit</span>
                        <span class="text-gray-900 font-medium">{{ $batch->produk->satuan }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Dibuat Pada</span>
                        <span class="text-gray-900 font-medium">{{ $batch->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>

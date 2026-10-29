<x-app-layout title="Detail Request & Transfer {{ $requestTransfer->no_transaksi }}">
    @php
        $rt = $requestTransfer;
        $approval = in_array($rt->jenis, ['req_bahan'], true);
        
        $rtStatusVariant = match($rt->status) {
            'diajukan' => 'warning',
            'disetujui' => 'primary',
            'diproses' => 'info',
            'dikirim' => 'gold',
            'selesai' => 'success',
            'dibatalkan' => 'danger',
            default => 'gray',
        };

        $pipelineSteps = $approval
            ? ['Draft', 'Diajukan', 'Disetujui', 'Diproses', 'Selesai']
            : ['Draft', 'Kirim Barang', 'Selesai'];

        $currentStep = match($rt->status) {
            'draft' => 1,
            'diajukan' => 2,
            'disetujui' => 3,
            'diproses', 'dikirim' => $approval ? 4 : 2,
            'selesai' => $approval ? 5 : 3,
            'dibatalkan' => 1,
            default => 1,
        };
    @endphp

    <!-- Page Header & Action Tools -->
    <x-page-header
        :title="$rt->no_transaksi"
        :subtitle="ucwords(str_replace('_', ' ', $rt->jenis)) . ' · Dibuat tanggal ' . $rt->created_at->format('d M Y') . ($rt->creator ? ' · Oleh ' . $rt->creator->name : '')"
        :breadcrumbs="[
            'Request & Transfer' => route('rt.index'),
            $rt->no_transaksi => null,
        ]"
    >
        <x-slot:actions>
            @if(in_array($rt->status, ['diproses', 'dikirim', 'selesai'], true))
            <x-button href="{{ route('rt.surat-jalan', $rt) }}" target="_blank" variant="secondary" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                </x-slot:icon>
                Cetak Surat Jalan
            </x-button>
            @endif

            @if($rt->status === 'draft' && auth()->user()->can('rt.create'))
            <x-button href="{{ route('rt.edit', $rt) }}" variant="warning" size="xs">
                <x-slot:icon>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </x-slot:icon>
                Edit Dokumen
            </x-button>
            @endif

            <x-badge :variant="$rtStatusVariant" :dot="true" size="sm">
                {{ ucwords(str_replace('_', ' ', $rt->status)) }}
            </x-badge>

            <x-button href="{{ route('rt.index') }}" variant="secondary" size="xs">
                &larr; Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if($rt->status === 'dibatalkan')
    <div class="mb-5">
        <x-alert type="danger" title="Dokumen Telah Dibatalkan">
            <div class="mt-1 text-xs text-rose-800">
                <span class="font-semibold">Alasan Pembatalan:</span>
                <span class="italic">{{ $rt->alasan_batal ?: 'Tidak ada alasan pembatalan tercatat.' }}</span>
            </div>
        </x-alert>
    </div>
    @endif

    <!-- AdminLTE 4 KPI Small Boxes Ribbon (4 Cards) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mb-5">
        <x-stat-box
            title="Jenis Transaksi"
            :value="ucwords(str_replace('_', ' ', $rt->jenis))"
            :subtitle="$approval ? 'Pipeline 5-Tahap (Approval)' : 'Pipeline 3-Tahap (Langsung)'"
            variant="default"
        />

        <x-stat-box
            title="Gudang Asal (Sumber)"
            :value="$rt->gudangAsal?->nama ?? '—'"
            subtitle="Lokasi penarikan stok"
            variant="default"
        />

        <x-stat-box
            title="Gudang Tujuan (Penerima)"
            :value="$rt->gudangTujuan?->nama ?? '—'"
            subtitle="Lokasi penerima stok"
            variant="default"
        />

        <x-stat-box
            title="Kesiapan Stok Gudang Asal"
            :value="($hasDeficit && in_array($rt->status, ['draft', 'diajukan', 'disetujui'], true)) ? 'Stok Asal Kurang' : 'Stok Asal Cukup'"
            :subtitle="($hasDeficit && in_array($rt->status, ['draft', 'diajukan', 'disetujui'], true)) ? 'Saldo gudang asal tidak mencukupi' : 'Siap diproses / dikirim'"
            :variant="($hasDeficit && in_array($rt->status, ['draft', 'diajukan', 'disetujui'], true)) ? 'danger' : 'success'"
        />
    </div>

    <!-- Slim Inline Critical Warning Notice -->
    @if($hasDeficit && in_array($rt->status, ['draft', 'diajukan', 'disetujui'], true))
    <div class="mb-5">
        <x-alert type="danger" title="Peringatan Kritis:">
            Stok fisik di {{ $rt->gudangAsal?->nama ?? 'Gudang Asal' }} tidak mencukupi untuk memenuhi dokumen ini. Pengiriman akan ditolak jika saldo belum terpenuhi.
            <x-slot:action>
                <x-button href="{{ route('stok.index') }}" variant="danger" size="xs">
                    Pantau Menu Stok &rarr;
                </x-button>
            </x-slot:action>
        </x-alert>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Kolom Kiri: Tabel Rincian Item Mutasi -->
        <div class="lg:col-span-2 space-y-5">
            <x-card title="Rincian Item Mutasi Stok" subtitle="Daftar barang yang diminta, dikirim, dan diterima antar gudang." :noPadding="true">
                <x-slot:tools>
                    <span class="text-xs text-gray-500 font-mono font-medium">{{ $rt->items->count() }} item</span>
                </x-slot:tools>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr>
                                <th class="py-2.5 px-3.5">Produk</th>
                                <th class="py-2.5 px-3 text-right">Diminta</th>
                                <th class="py-2.5 px-3 text-right">Stok Fisik Asal</th>
                                <th class="py-2.5 px-3 text-right">Dikirim</th>
                                <th class="py-2.5 px-3 text-right">Diterima</th>
                                <th class="py-2.5 px-3 text-right text-emerald-800 dark:text-emerald-400">Kondisi Baik</th>
                                <th class="py-2.5 px-3 text-right text-rose-800 dark:text-rose-400">Kondisi Rusak</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($rt->items as $it)
                            @php
                                $w = $itemWarnings[$it->id] ?? null;
                            @endphp
                            <tr class="hover:bg-gray-50/70 transition">
                                <td class="py-2.5 px-3.5">
                                    <div class="font-bold text-gray-900">{{ $it->produk->nama }}</div>
                                    <div class="font-mono text-[11px] text-gray-400">{{ $it->produk->sku }}</div>
                                    @if($it->keterangan_rusak)
                                        <div class="text-[11px] text-rose-600 italic mt-0.5">Catatan rusak: {{ $it->keterangan_rusak }}</div>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right font-semibold text-gray-900">
                                    {{ \App\Helpers\NumberHelper::formatQty($it->qty_diminta) }}
                                    <span class="text-gray-400 font-normal">{{ $it->produk->satuan }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium {{ ($w && !$w['is_cukup'] && in_array($rt->status, ['draft', 'diajukan', 'disetujui'], true)) ? 'text-rose-600 font-bold' : 'text-gray-700' }}">
                                    @if($w)
                                        {{ \App\Helpers\NumberHelper::formatQty($w['stok_fisik_asal']) }}
                                        <span class="text-gray-400 font-normal">{{ $it->produk->satuan }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium text-gray-700">
                                    @if($it->qty_dikirim !== null)
                                        {{ \App\Helpers\NumberHelper::formatQty($it->qty_dikirim) }}
                                        <span class="text-gray-400 font-normal">{{ $it->produk->satuan }}</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium text-gray-700">
                                    @if($it->qty_diterima !== null)
                                        {{ \App\Helpers\NumberHelper::formatQty($it->qty_diterima) }}
                                        <span class="text-gray-400 font-normal">{{ $it->produk->satuan }}</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold">
                                    @if($rt->status === 'selesai')
                                        <span class="text-emerald-700">{{ $it->qty_baik !== null ? \App\Helpers\NumberHelper::formatQty($it->qty_baik) . ' ' . $it->produk->satuan : '-' }}</span>
                                    @elseif(in_array($rt->status, ['diproses', 'dikirim']))
                                        <span class="text-gray-400 font-normal italic text-[11px]">Menunggu QC</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold">
                                    @if($rt->status === 'selesai')
                                        <span class="{{ (float)($it->qty_rusak ?? 0) > 0 ? 'text-rose-700' : 'text-gray-500' }}">
                                            {{ $it->qty_rusak !== null ? \App\Helpers\NumberHelper::formatQty($it->qty_rusak) . ' ' . $it->produk->satuan : '-' }}
                                        </span>
                                    @elseif(in_array($rt->status, ['diproses', 'dikirim']))
                                        <span class="text-gray-400 font-normal italic text-[11px]">Menunggu QC</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                    @if(in_array($rt->status, ['draft', 'diajukan', 'disetujui'], true))
                                        @if($w && $w['kurang'] > 0)
                                            <x-badge variant="danger">
                                                Kurang {{ \App\Helpers\NumberHelper::formatQty($w['kurang']) }} {{ $it->produk->satuan }}
                                            </x-badge>
                                        @else
                                            <x-badge variant="success">
                                                Tersedia
                                            </x-badge>
                                        @endif
                                    @elseif(in_array($rt->status, ['diproses', 'dikirim'], true))
                                        @if($it->qty_dikirim !== null && (float)$it->qty_dikirim < (float)$it->qty_diminta)
                                            <x-badge variant="warning">
                                                Kirim Parsial
                                            </x-badge>
                                        @else
                                            <x-badge variant="info">
                                                Dalam Perjalanan
                                            </x-badge>
                                        @endif
                                    @elseif($rt->status === 'selesai')
                                        @if($it->qty_diterima !== null && $it->qty_dikirim !== null && (float)$it->qty_diterima < (float)$it->qty_dikirim)
                                            <x-badge variant="warning">
                                                Selisih Terima
                                            </x-badge>
                                        @elseif((float)($it->qty_rusak ?? 0) > 0)
                                            <x-badge variant="danger">
                                                Ada Rusak
                                            </x-badge>
                                        @else
                                            <x-badge variant="success">
                                                Lengkap
                                            </x-badge>
                                        @endif
                                    @else
                                        <x-badge variant="gray">
                                            {{ ucfirst($rt->status) }}
                                        </x-badge>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3.5 text-center whitespace-nowrap">
                                    <a href="{{ route('stok.ledger', $it->produk_id) }}" class="text-foreground hover:underline text-xs font-semibold">
                                        Kartu Stok &rarr;
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if($rt->catatan)
            <x-card title="Catatan Dokumen">
                <p class="text-xs text-gray-700 leading-relaxed">{{ $rt->catatan }}</p>
            </x-card>
            @endif
        </div>

        <!-- Kolom Kanan: Workflow Pipeline & Kontrol Aksi -->
        <div class="space-y-5">
            <!-- Pipeline Progress Tracker -->
            <x-card title="Tahapan Alur Dokumen" variant="primary">
                <x-pipeline-tracker
                    :steps="$pipelineSteps"
                    :currentStep="$currentStep"
                    :isCancelled="$rt->status === 'dibatalkan'"
                />
            </x-card>

            <!-- Panel Kontrol Aksi -->
            <x-card title="Aksi Dokumen" variant="default">
                @if($hasDeficit && in_array($rt->status, ['draft', 'diajukan', 'disetujui'], true))
                <div class="mb-3">
                    <x-alert type="danger" title="Peringatan Kritis:">
                        Saldo fisik di gudang asal tidak cukup untuk memenuhi permintaan ini.
                    </x-alert>
                </div>
                @endif

                <div class="space-y-2">
                    @if($approval)
                        @can('rt.submit')
                        @if($rt->status === 'draft')
                        <form method="POST" action="{{ route('rt.transition', $rt) }}" x-data="{ submitting: false }" @submit="if(submitting) return false; submitting = true">
                            @csrf
                            <input type="hidden" name="aksi" value="submit">
                            <x-button type="submit" variant="primary" size="md" class="w-full" ::disabled="submitting" ::class="{ 'opacity-75 cursor-not-allowed pointer-events-none': submitting }">
                                <span x-show="!submitting">Ajukan Permintaan</span>
                                <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Memproses...
                                </span>
                            </x-button>
                        </form>
                        <p class="text-[11px] text-gray-400 text-center">Kirimkan permintaan untuk disetujui Manager.</p>
                        @endif
                        @endcan

                        @can('rt.approve')
                        @if($rt->status === 'diajukan')
                        <form method="POST" action="{{ route('rt.transition', $rt) }}" x-data="{ submitting: false }" @submit="if(submitting) return false; submitting = true">
                            @csrf
                            <input type="hidden" name="aksi" value="approve">
                            <x-button type="submit" variant="success" size="md" class="w-full" ::disabled="submitting" ::class="{ 'opacity-75 cursor-not-allowed pointer-events-none': submitting }">
                                <span x-show="!submitting">Setujui Permintaan</span>
                                <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Memproses...
                                </span>
                            </x-button>
                        </form>
                        <p class="text-[11px] text-gray-400 text-center">Wewenang Manager untuk menyetujui mutasi bahan.</p>
                        @endif
                        @endcan

                        @can('rt.process')
                        @if($rt->status === 'disetujui')
                        <form method="POST" action="{{ route('rt.transition', $rt) }}" x-data="{ submitting: false }" @submit="if(submitting) return false; submitting = true">
                            @csrf
                            <input type="hidden" name="aksi" value="process">
                            <x-button type="submit" variant="primary" size="md" class="w-full" ::disabled="submitting" ::class="{ 'opacity-75 cursor-not-allowed pointer-events-none': submitting }">
                                <span x-show="!submitting">Proses & Kirim (Stok OUT)</span>
                                <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Memproses...
                                </span>
                            </x-button>
                        </form>
                        <p class="text-[11px] text-gray-400 text-center">Gudang menyiapkan dan memotong stok fisik asal.</p>
                        @endif
                        @endcan
                    @else
                        @can('rt.ship')
                        @if($rt->status === 'draft')
                        <form method="POST" action="{{ route('rt.transition', $rt) }}" x-data="{ submitting: false }" @submit="if(submitting) return false; submitting = true">
                            @csrf
                            <input type="hidden" name="aksi" value="ship">
                            <x-button type="submit" variant="primary" size="md" class="w-full" ::disabled="submitting" ::class="{ 'opacity-75 cursor-not-allowed pointer-events-none': submitting }">
                                <span x-show="!submitting">Kirim Barang (Stok OUT)</span>
                                <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Memproses...
                                </span>
                            </x-button>
                        </form>
                        <p class="text-[11px] text-gray-400 text-center">Memotong stok fisik gudang asal secara langsung.</p>
                        @endif
                        @endcan
                    @endif

                    @if(in_array($rt->status, ['diproses', 'dikirim'], true))
                        @if($canReceive)
                        @php
                            $receiveItems = $rt->items->values()->map(function($it) {
                                $qtyKirim = (int) round($it->qty_dikirim ?? $it->qty_diminta);
                                return [
                                    'id' => (string) $it->id,
                                    'maxQty' => $qtyKirim,
                                    'qtyBaik' => $qtyKirim,
                                    'qtyRusak' => 0,
                                ];
                            })->values()->all();
                        @endphp
                        <div class="border border-emerald-200 bg-emerald-50/50 rounded-lg p-3.5 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-xs font-bold text-emerald-950 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Konfirmasi Penerimaan Barang</span>
                                </h4>
                                <span class="text-[10px] bg-emerald-100 text-emerald-800 font-semibold px-2 py-0.5 rounded-full">Inbound QC</span>
                            </div>
                            <form method="POST" action="{{ route('rt.transition', $rt) }}"
                                x-data="{
                                    submitting: false,
                                    items: {{ Js::from($receiveItems) }},
                                    get isReceiveValid() {
                                        return this.items.length > 0 && this.items.every(it => {
                                            const b = parseInt(it.qtyBaik, 10);
                                            const r = parseInt(it.qtyRusak, 10);
                                            return !isNaN(b) && !isNaN(r) && b >= 0 && r >= 0 && (b + r) === it.maxQty;
                                        });
                                    },
                                    autoCalculateRusak(index) {
                                        const it = this.items[index];
                                        if (!it) return;
                                        const b = parseInt(it.qtyBaik, 10) || 0;
                                        it.qtyRusak = Math.max(0, it.maxQty - b);
                                    }
                                }"
                                @submit="if(submitting || !isReceiveValid) { $event.preventDefault(); return false; } submitting = true">
                                @csrf
                                <input type="hidden" name="aksi" value="receive">
                                <div class="space-y-3 mb-3">
                                    @foreach($rt->items->values() as $index => $it)
                                    @php
                                        $qtyKirim = (int) round($it->qty_dikirim ?? $it->qty_diminta);
                                        $satuan = $it->produk->satuan ?? 'pcs';
                                    @endphp
                                    <div class="bg-card p-3 rounded-lg border border-emerald-200/80 shadow-xs text-xs space-y-2">
                                        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $it->id }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <div class="font-bold text-foreground text-xs leading-snug">{{ $it->produk->nama }}</div>
                                                <div class="text-[11px] text-muted-foreground mt-0.5">
                                                    Dikirim: <span class="font-semibold font-mono text-foreground">{{ $qtyKirim }} {{ $satuan }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2.5 pt-1">
                                            <div>
                                                <label class="block text-[11px] font-semibold text-emerald-700 dark:text-emerald-400 mb-1">
                                                    Qty Baik ({{ $satuan }})
                                                </label>
                                                <input
                                                    type="number"
                                                    step="1"
                                                    min="0"
                                                    :max="items[{{ $index }}]?.maxQty"
                                                    name="items[{{ $index }}][qty_baik]"
                                                    x-model.number="items[{{ $index }}].qtyBaik"
                                                    value="{{ $qtyKirim }}"
                                                    onkeydown="if(event.key === '.' || event.key === ',') event.preventDefault()"
                                                    class="w-full rounded-lg border border-emerald-300 bg-background text-foreground text-xs py-1.5 px-2.5 font-mono text-right focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 shadow-xs"
                                                    required
                                                >
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-semibold text-rose-700 dark:text-rose-400 mb-1">
                                                    Qty Rusak ({{ $satuan }})
                                                </label>
                                                <input
                                                    type="number"
                                                    step="1"
                                                    min="0"
                                                    :max="items[{{ $index }}]?.maxQty"
                                                    name="items[{{ $index }}][qty_rusak]"
                                                    x-model.number="items[{{ $index }}].qtyRusak"
                                                    value="0"
                                                    onkeydown="if(event.key === '.' || event.key === ',') event.preventDefault()"
                                                    class="w-full rounded-lg border border-rose-300 bg-background text-foreground text-xs py-1.5 px-2.5 font-mono text-right focus:border-rose-600 focus:ring-1 focus:ring-rose-600 shadow-xs"
                                                    required
                                                >
                                            </div>
                                        </div>

                                        <!-- Indikator Validasi Selisih Real-time & Auto-hitung -->
                                        <div class="pt-2 border-t border-border flex items-center justify-between text-[11px]">
                                            <template x-if="(parseInt(items[{{ $index }}]?.qtyBaik, 10) || 0) + (parseInt(items[{{ $index }}]?.qtyRusak, 10) || 0) === items[{{ $index }}]?.maxQty">
                                                <span class="text-emerald-700 dark:text-emerald-400 font-semibold inline-flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    <span>Pas (Total: <span x-text="items[{{ $index }}]?.maxQty"></span> {{ $satuan }})</span>
                                                </span>
                                            </template>
                                            <template x-if="(parseInt(items[{{ $index }}]?.qtyBaik, 10) || 0) + (parseInt(items[{{ $index }}]?.qtyRusak, 10) || 0) !== items[{{ $index }}]?.maxQty">
                                                <span class="text-rose-600 dark:text-rose-400 font-semibold inline-flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                    <span>Selisih: <span x-text="((parseInt(items[{{ $index }}]?.qtyBaik, 10) || 0) + (parseInt(items[{{ $index }}]?.qtyRusak, 10) || 0)) - items[{{ $index }}]?.maxQty"></span> {{ $satuan }} (Wajib Pas)</span>
                                                </span>
                                            </template>
                                            <button type="button" class="text-[11px] text-primary hover:underline font-medium cursor-pointer" @click="autoCalculateRusak({{ $index }})">
                                                Hitung Rusak Otomatis
                                            </button>
                                        </div>

                                        <div>
                                            <input
                                                type="text"
                                                name="items[{{ $index }}][keterangan_rusak]"
                                                placeholder="Catatan kerusakan transit (jika ada)..."
                                                class="w-full rounded-lg border border-border bg-background text-foreground text-xs py-1.5 px-2.5 focus:border-ring focus:ring-1 focus:ring-ring shadow-xs placeholder:text-muted-foreground"
                                            >
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <x-button
                                    type="submit"
                                    variant="success"
                                    size="default"
                                    class="w-full justify-center"
                                    ::disabled="submitting || !isReceiveValid"
                                    ::class="{ 'opacity-50 cursor-not-allowed': !isReceiveValid, 'opacity-75 cursor-not-allowed pointer-events-none': submitting }"
                                >
                                    <span class="inline-flex items-center justify-center gap-1.5">
                                        <svg x-show="submitting" x-cloak class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        <span x-text="submitting ? 'Memproses...' : 'Konfirmasi Terima (Stok IN)'">Konfirmasi Terima (Stok IN)</span>
                                    </span>
                                </x-button>
                                <p x-show="!isReceiveValid" x-cloak class="text-[11px] text-rose-600 text-center font-medium mt-1.5">
                                    Periksa kuantitas: Total (Baik + Rusak) harus tepat sama dengan kuantitas dikirim.
                                </p>
                            </form>
                            <p class="text-[11px] text-muted-foreground text-center">Hanya kuantitas baik yang masuk ke saldo stok fisik gudang.</p>
                        </div>
                        @else
                        <div class="py-3 px-3.5 bg-amber-50 border border-amber-200 rounded-sm text-xs text-amber-900">
                            <div class="font-bold flex items-center gap-1.5 mb-1 text-amber-800">
                                <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Menunggu Penerimaan & QC Inbound</span>
                            </div>
                            <p class="text-[11px] text-amber-700 leading-relaxed">
                                Barang dalam perjalanan. Sesuai prinsip <em>Separation of Duties (SoD)</em>, konfirmasi penerimaan dan input inspeksi fisik (Kondisi Baik / Rusak) hanya dapat dilakukan oleh petugas <strong>Gudang {{ $rt->gudangTujuan?->nama ?? 'Tujuan' }}</strong>.
                            </p>
                        </div>
                        @endif
                    @endif

                    @php
                        $cancellable = in_array($rt->status, ['draft', 'diajukan', 'disetujui'], true);
                        $canCancelThis = $cancellable && (auth()->user()->can('rt.cancel') || (in_array($rt->status, ['draft', 'diajukan'], true) && (int)$rt->created_by === (int)auth()->id() && auth()->user()->can('rt.create')));
                    @endphp

                    @if($canCancelThis)
                    <div class="pt-2 border-t border-gray-100" x-data="{ openCancelModal: false, alasan: '', isSubmitting: false }">
                        <x-button type="button" variant="danger" size="xs" class="w-full" @click="openCancelModal = true">
                            <span class="flex items-center justify-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Batalkan Transfer / Request
                            </span>
                        </x-button>

                        <x-modal name="openCancelModal" maxWidth="md">
                            <x-slot:header>
                                <div class="px-6 py-5 border-b border-border/60 bg-destructive/5 flex items-center justify-between">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="size-10 rounded-xl bg-destructive/10 text-destructive flex items-center justify-center shrink-0 border border-destructive/20">
                                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="text-base font-bold text-foreground tracking-tight">Batalkan Dokumen Mutasi</h3>
                                            <p class="text-xs text-muted-foreground truncate">{{ $rt->no_transaksi }} &middot; {{ ucwords(str_replace('_', ' ', $rt->jenis)) }}</p>
                                        </div>
                                    </div>
                                    <button type="button" @click="openCancelModal = false" class="text-muted-foreground hover:text-foreground transition cursor-pointer p-1.5 rounded-lg hover:bg-accent shrink-0">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </x-slot:header>

                            <form method="POST" action="{{ route('rt.transition', $rt) }}" @submit="if(alasan.trim().length >= 5) { isSubmitting = true } else { $event.preventDefault() }" class="space-y-4">
                                @csrf
                                <input type="hidden" name="aksi" value="cancel">

                                <div class="p-3.5 bg-destructive/10 border border-destructive/20 rounded-xl text-xs text-destructive space-y-1">
                                    <div class="font-bold flex items-center gap-1.5">
                                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Peringatan Pembatalan Dokumen
                                    </div>
                                    <p class="text-destructive/90 leading-relaxed text-[11px]">
                                        Setelah dibatalkan, status dokumen transfer akan menjadi <strong>Dibatalkan</strong>. Pengeluaran dan penerimaan fisik barang tidak dapat dilanjutkan.
                                    </p>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label for="alasan_pembatalan_show" class="text-xs font-semibold text-foreground">
                                            Alasan Pembatalan <span class="text-destructive">*</span>
                                        </label>
                                        <span class="text-[10px] text-muted-foreground">Wajib diisi</span>
                                    </div>
                                    <textarea
                                        id="alasan_pembatalan_show"
                                        name="alasan"
                                        x-model="alasan"
                                        rows="3"
                                        required
                                        minlength="5"
                                        maxlength="500"
                                        placeholder="Tuliskan alasan pembatalan dokumen mutasi ini (minimal 5 karakter)..."
                                        class="w-full rounded-xl border border-border bg-card p-3 text-xs text-foreground placeholder:text-muted-foreground/50 focus:border-destructive focus:ring-2 focus:ring-destructive/20 outline-none transition"
                                    ></textarea>
                                    <div class="flex items-center justify-between mt-1 text-[11px]">
                                        <span class="text-muted-foreground">Minimal 5 karakter</span>
                                        <span :class="alasan.trim().length >= 5 ? 'text-emerald-600 font-semibold' : 'text-muted-foreground'" x-text="alasan.trim().length + ' / 500 karakter'"></span>
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-border/60 flex items-center justify-end gap-2.5">
                                    <x-button type="button" variant="secondary" size="sm" @click="openCancelModal = false">
                                        Kembali
                                    </x-button>
                                    <x-button type="submit" variant="danger" size="sm" ::disabled="isSubmitting || alasan.trim().length < 5" ::class="{ 'opacity-50 cursor-not-allowed': alasan.trim().length < 5, 'opacity-75 cursor-not-allowed pointer-events-none': isSubmitting }">
                                        <span x-show="!isSubmitting">Konfirmasi Batalkan Dokumen</span>
                                        <span x-show="isSubmitting" x-cloak class="inline-flex items-center gap-1.5">
                                            <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Membatalkan...
                                        </span>
                                    </x-button>
                                </div>
                            </form>
                        </x-modal>
                    </div>
                    @endif

                    @if(in_array($rt->status, ['selesai', 'dibatalkan'], true))
                    <div class="py-2.5 px-3 bg-gray-50 rounded-sm text-center text-xs text-gray-500 font-medium">
                        Dokumen ini telah berstatus final ({{ ucfirst($rt->status) }}).
                    </div>
                    @endif
                </div>
            </x-card>

            <!-- Ringkasan Info Sistem -->
            <x-card title="Informasi Dokumen" variant="default">
                <div class="space-y-2 text-xs text-gray-600">
                    <div class="flex justify-between">
                        <span>No Transaksi</span>
                        <span class="font-mono text-gray-900 font-semibold">{{ $rt->no_transaksi }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Jenis Dokumen</span>
                        <span class="capitalize text-gray-900 font-medium">{{ str_replace('_', ' ', $rt->jenis) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Dibuat Pada</span>
                        <span class="text-gray-900 font-medium">{{ $rt->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @if($rt->creator)
                    <div class="flex justify-between">
                        <span>Operator</span>
                        <span class="text-gray-900 font-medium">{{ $rt->creator->name }}</span>
                    </div>
                    @endif
                    @if($rt->alasan_batal)
                    <div class="flex justify-between text-rose-600 pt-1 border-t border-gray-100">
                        <span>Alasan Batal</span>
                        <span class="font-medium text-right max-w-[60%]">{{ $rt->alasan_batal }}</span>
                    </div>
                    @endif
                </div>
            </x-card>
        </div>
    </div>

    <!-- Log & Jejak Riwayat Audit Dokumen Mutasi & Transfer -->
    @canany(['rt.audit', 'audit.view'])
    <div class="mt-6">
        <x-operational-audit-history :auditable="$rt" title="Jejak Riwayat Audit Dokumen {{ $rt->no_transaksi }}" />
    </div>
    @endcanany
</x-app-layout>

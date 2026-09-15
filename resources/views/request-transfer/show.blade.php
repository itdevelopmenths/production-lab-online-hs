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
                        <thead class="bg-gray-100 border-b border-gray-200 text-gray-700 font-semibold">
                            <tr>
                                <th class="py-2.5 px-3.5">Produk</th>
                                <th class="py-2.5 px-3 text-right">Diminta</th>
                                <th class="py-2.5 px-3 text-right">Stok Fisik Asal</th>
                                <th class="py-2.5 px-3 text-right">Dikirim</th>
                                <th class="py-2.5 px-3 text-right">Diterima</th>
                                <th class="py-2.5 px-3 text-right text-emerald-800">Kondisi Baik</th>
                                <th class="py-2.5 px-3 text-right text-rose-800">Kondisi Rusak</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
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
                                    <a href="{{ route('stok.ledger', $it->produk_id) }}" class="text-primary-700 hover:text-primary-900 text-xs font-semibold">
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
                        <div class="border border-emerald-200 bg-emerald-50/50 rounded-sm p-3">
                            <h4 class="text-xs font-bold text-emerald-900 mb-2">Konfirmasi Penerimaan Barang</h4>
                            <form method="POST" action="{{ route('rt.transition', $rt) }}" x-data="{ submitting: false }" @submit="if(submitting) return false; submitting = true">
                                @csrf
                                <input type="hidden" name="aksi" value="receive">
                                <div class="space-y-2.5 mb-3">
                                    @foreach($rt->items as $index => $it)
                                    @php
                                        $qtyKirim = (float) ($it->qty_dikirim ?? $it->qty_diminta);
                                        $satuan = $it->produk->satuan ?? 'pcs';
                                        $satuanLower = strtolower(trim($satuan));
                                        $isDecimal = in_array($satuanLower, ['ml', 'l', 'liter', 'gr', 'gram', 'kg', 'kilogram'], true);
                                        $step = $isDecimal ? '0.01' : '1';
                                        $valBaik = $isDecimal ? $qtyKirim : (int) round($qtyKirim);
                                        $valMax = $isDecimal ? $qtyKirim : (int) round($qtyKirim);
                                    @endphp
                                    <div class="bg-white p-2.5 rounded-sm border border-emerald-100 text-xs" x-data="{
                                        maxQty: {{ $valMax }},
                                        qtyBaik: {{ $valBaik }},
                                        qtyRusak: 0,
                                        isDecimal: {{ $isDecimal ? 'true' : 'false' }},
                                        onRusakChange() {
                                            let r = parseFloat(this.qtyRusak) || 0;
                                            if (r > this.maxQty) {
                                                r = this.maxQty;
                                            }
                                            this.qtyRusak = this.isDecimal ? Number(r.toFixed(2)) : Math.round(r);
                                            let remaining = Math.max(0, this.maxQty - this.qtyRusak);
                                            this.qtyBaik = this.isDecimal ? Number(remaining.toFixed(2)) : Math.round(remaining);
                                        }
                                    }">
                                        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $it->id }}">
                                        <div class="font-bold text-gray-900">{{ $it->produk->nama }}</div>
                                        <div class="text-[11px] text-gray-500 mb-2">Dikirim: <span class="font-semibold font-mono text-gray-800">{{ \App\Helpers\NumberHelper::formatQty($qtyKirim) }} {{ $satuan }}</span></div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-[11px] font-semibold text-emerald-800 mb-0.5">Qty Baik ({{ $satuan }})</label>
                                                <input
                                                    type="number"
                                                    step="{{ $step }}"
                                                    min="0"
                                                    max="{{ $valMax }}"
                                                    name="items[{{ $index }}][qty_baik]"
                                                    x-model.number="qtyBaik"
                                                    value="{{ $valBaik }}"
                                                    @if(!$isDecimal) onkeydown="if(event.key === '.' || event.key === ',') event.preventDefault()" @endif
                                                    class="w-full text-right rounded-sm border-gray-300 text-xs py-1 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 font-mono"
                                                    required
                                                >
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-semibold text-rose-800 mb-0.5">Qty Rusak ({{ $satuan }})</label>
                                                <input
                                                    type="number"
                                                    step="{{ $step }}"
                                                    min="0"
                                                    max="{{ $valMax }}"
                                                    name="items[{{ $index }}][qty_rusak]"
                                                    x-model.number="qtyRusak"
                                                    @input="onRusakChange()"
                                                    value="0"
                                                    @if(!$isDecimal) onkeydown="if(event.key === '.' || event.key === ',') event.preventDefault()" @endif
                                                    class="w-full text-right rounded-sm border-gray-300 text-xs py-1 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 font-mono"
                                                    required
                                                >
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <input type="text" name="items[{{ $index }}][keterangan_rusak]" placeholder="Catatan kerusakan transit (jika ada)..." class="w-full rounded-sm border-gray-300 text-[11px] py-1 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <x-button type="submit" variant="success" size="md" class="w-full" ::disabled="submitting" ::class="{ 'opacity-75 cursor-not-allowed pointer-events-none': submitting }">
                                    <span x-show="!submitting">Konfirmasi Terima (Stok IN)</span>
                                    <span x-show="submitting" x-cloak class="inline-flex items-center justify-center gap-1.5">
                                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        Memproses...
                                    </span>
                                </x-button>
                            </form>
                            <p class="text-[11px] text-gray-500 text-center mt-1.5">Hanya kuantitas baik yang masuk ke saldo stok fisik gudang.</p>
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
                        $canCancelThis = auth()->user()->can('rt.cancel') || (in_array($rt->status, ['draft', 'diajukan'], true) && (int)$rt->created_by === (int)auth()->id() && auth()->user()->can('rt.create'));
                    @endphp

                    @if(!in_array($rt->status, ['selesai', 'dibatalkan'], true) && $canCancelThis)
                    <form method="POST" action="{{ route('rt.transition', $rt) }}" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan dokumen transfer {{ $rt->no_transaksi }}?');" class="pt-2 border-t border-gray-100" x-data="{ submitting: false }" @submit="if(submitting) return false; submitting = true">
                        @csrf
                        <input type="hidden" name="aksi" value="cancel">
                        <x-button type="submit" variant="danger" size="xs" class="w-full" ::disabled="submitting">
                            <span x-show="!submitting" class="flex items-center justify-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Batalkan Transfer / Request
                            </span>
                            <span x-show="submitting" x-cloak>Membatalkan...</span>
                        </x-button>
                    </form>
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
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>

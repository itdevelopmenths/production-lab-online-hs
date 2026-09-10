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
                                </td>
                                <td class="py-2.5 px-3 text-right font-semibold text-gray-900">
                                    {{ rtrim(rtrim(number_format($it->qty_diminta, 2, ',', '.'), '0'), ',') }}
                                    <span class="text-gray-400 font-normal">{{ $it->produk->satuan }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium {{ ($w && !$w['is_cukup'] && in_array($rt->status, ['draft', 'diajukan', 'disetujui'], true)) ? 'text-rose-600 font-bold' : 'text-gray-700' }}">
                                    @if($w)
                                        {{ rtrim(rtrim(number_format($w['stok_fisik_asal'], 2, ',', '.'), '0'), ',') }}
                                        <span class="text-gray-400 font-normal">{{ $it->produk->satuan }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium text-gray-700">
                                    @if($it->qty_dikirim !== null)
                                        {{ rtrim(rtrim(number_format($it->qty_dikirim, 2, ',', '.'), '0'), ',') }}
                                        <span class="text-gray-400 font-normal">{{ $it->produk->satuan }}</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium text-gray-700">
                                    @if($it->qty_diterima !== null)
                                        {{ rtrim(rtrim(number_format($it->qty_diterima, 2, ',', '.'), '0'), ',') }}
                                        <span class="text-gray-400 font-normal">{{ $it->produk->satuan }}</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                    @if(in_array($rt->status, ['draft', 'diajukan', 'disetujui'], true))
                                        @if($w && $w['kurang'] > 0)
                                            <x-badge variant="danger">
                                                Kurang {{ rtrim(rtrim(number_format($w['kurang'], 2, ',', '.'), '0'), ',') }} {{ $it->produk->satuan }}
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
                        <form method="POST" action="{{ route('rt.transition', $rt) }}">
                            @csrf
                            <input type="hidden" name="aksi" value="submit">
                            <x-button type="submit" variant="primary" size="md" class="w-full">
                                Ajukan Permintaan
                            </x-button>
                        </form>
                        <p class="text-[11px] text-gray-400 text-center">Kirimkan permintaan untuk disetujui Manager.</p>
                        @endif
                        @endcan

                        @can('rt.approve')
                        @if($rt->status === 'diajukan')
                        <form method="POST" action="{{ route('rt.transition', $rt) }}">
                            @csrf
                            <input type="hidden" name="aksi" value="approve">
                            <x-button type="submit" variant="success" size="md" class="w-full">
                                Setujui Permintaan
                            </x-button>
                        </form>
                        <p class="text-[11px] text-gray-400 text-center">Wewenang Manager untuk menyetujui mutasi bahan.</p>
                        @endif
                        @endcan

                        @can('rt.process')
                        @if($rt->status === 'disetujui')
                        <form method="POST" action="{{ route('rt.transition', $rt) }}">
                            @csrf
                            <input type="hidden" name="aksi" value="process">
                            <x-button type="submit" variant="primary" size="md" class="w-full">
                                Proses & Kirim (Stok OUT)
                            </x-button>
                        </form>
                        <p class="text-[11px] text-gray-400 text-center">Gudang menyiapkan dan memotong stok fisik asal.</p>
                        @endif
                        @endcan
                    @else
                        @can('rt.ship')
                        @if($rt->status === 'draft')
                        <form method="POST" action="{{ route('rt.transition', $rt) }}">
                            @csrf
                            <input type="hidden" name="aksi" value="ship">
                            <x-button type="submit" variant="primary" size="md" class="w-full">
                                Kirim Barang (Stok OUT)
                            </x-button>
                        </form>
                        <p class="text-[11px] text-gray-400 text-center">Memotong stok fisik gudang asal secara langsung.</p>
                        @endif
                        @endcan
                    @endif

                    @can('rt.receive')
                    @if(in_array($rt->status, ['diproses', 'dikirim'], true))
                    <form method="POST" action="{{ route('rt.transition', $rt) }}">
                        @csrf
                        <input type="hidden" name="aksi" value="receive">
                        <x-button type="submit" variant="success" size="md" class="w-full">
                            Konfirmasi Terima (Stok IN)
                        </x-button>
                    </form>
                    <p class="text-[11px] text-gray-400 text-center">Menambahkan stok fisik ke gudang tujuan.</p>
                    @endif
                    @endcan

                    @can('rt.cancel')
                    @if(!in_array($rt->status, ['selesai', 'dibatalkan'], true))
                    <form method="POST" action="{{ route('rt.transition', $rt) }}" onsubmit="return confirm('Batalkan dokumen transfer ini?');" class="pt-2 border-t border-gray-100">
                        @csrf
                        <input type="hidden" name="aksi" value="cancel">
                        <x-button type="submit" variant="link" size="xs" class="w-full text-rose-600 hover:text-rose-800">
                            Batalkan Dokumen
                        </x-button>
                    </form>
                    @endif
                    @endcan

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

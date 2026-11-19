<x-app-layout title="Detail Transfer {{ $transfer->no_transfer }}">
    @php
        $transferItemsData = $transfer->items->map(fn($it) => [
            'id' => $it->id,
            'nama' => $it->produk?->nama,
            'sku' => $it->produk?->sku,
            'satuan' => $it->produk?->satuan ?? 'Pcs',
            'qty_dikirim' => (float) $it->qty_dikirim,
            'qty_baik' => (float) ($it->qty_baik ?? $it->qty_dikirim),
            'qty_rusak' => (float) ($it->qty_rusak ?? 0),
            'keterangan_rusak' => $it->keterangan_rusak ?? '',
        ])->values()->toArray();
    @endphp

    <script>
        function transferShowData() {
            return {
                openReceiveModal: false,
                openCancelModal: false,
                alasanCancel: '',
                items: @js($transferItemsData),
                isReceiveValid() {
                    for (let i = 0; i < this.items.length; i++) {
                        const it = this.items[i];
                        const sum = parseFloat(it.qty_baik || 0) + parseFloat(it.qty_rusak || 0);
                        if (Math.abs(sum - parseFloat(it.qty_dikirim)) > 0.0001) {
                            return false;
                        }
                        if (parseFloat(it.qty_rusak) > 0 && (!it.keterangan_rusak || it.keterangan_rusak.trim() === '')) {
                            return false;
                        }
                    }
                    return true;
                }
            };
        }
    </script>

    <div x-data="transferShowData()" class="space-y-6">

        <x-page-header
            title="Detail Transfer Barang"
            :subtitle="$transfer->no_transfer . ' · ' . $transfer->created_at->format('d/m/Y H:i')"
            :breadcrumbs="['Transfer' => route('transfers.index'), $transfer->no_transfer => null]"
            :backUrl="route('transfers.index')"
        />

        @php
            $statusLabel = match($transfer->status) {
                'draft' => 'Draft',
                'dikirim' => 'In Transit (Dikirim)',
                'selesai' => 'Selesai Diterima',
                'dibatalkan' => 'Dibatalkan',
                default => ucfirst($transfer->status),
            };
            $trfSteps = [
                ['label' => 'Draft', 'hint' => 'Disiapkan gudang asal'],
                ['label' => 'Dikirim', 'hint' => 'In transit'],
                ['label' => 'Diterima', 'hint' => 'Stok masuk gudang tujuan'],
            ];
            $trfPos = match($transfer->status) { 'draft' => 1, 'dikirim' => 2, 'selesai' => 3, default => 1 };
            $trfTerminal = $transfer->status === 'dibatalkan' ? 'Dibatalkan' : null;
            $totalRusak = (float) $transfer->items->sum('qty_rusak');
            $totalKirim = (float) $transfer->items->sum('qty_dikirim');
            $fmtTrf = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
        @endphp

        <x-doc-hero :title="$transfer->no_transfer" :meta="'Dibuat oleh <span class=&quot;font-medium text-text-active&quot;>' . e($transfer->creator?->name ?? 'Sistem') . '</span> pada ' . $transfer->created_at->format('d/m/Y H:i')">
            <x-slot:icon><svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg></x-slot:icon>
            <x-slot:badge>
                <x-badge :variant="$transfer->status" :pill="true">{{ $statusLabel }}</x-badge>
            </x-slot:badge>
            <x-slot:actions>
                @if(in_array($transfer->status, ['dikirim', 'selesai'], true))
                    <x-button href="{{ route('transfers.surat-jalan', $transfer) }}" target="_blank" variant="secondary" size="sm">
                        <x-slot:icon>
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        </x-slot:icon>
                        Cetak Surat Jalan
                    </x-button>
                @endif

                @if($canShip)
                    <form method="POST" action="{{ route('transfers.ship', $transfer) }}" class="inline">
                        @csrf
                        <x-button type="submit" variant="primary">
                            <x-slot:icon>
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </x-slot:icon>
                            Kirim Sekarang (In Transit)
                        </x-button>
                    </form>
                @endif

                @if($canReceive)
                    <x-button type="button" variant="primary" @click="openReceiveModal = true">
                        <x-slot:icon><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></x-slot:icon>
                        Konfirmasi Penerimaan Barang
                    </x-button>
                @elseif($receiveBlockReason)
                    <span class="text-[11px] text-[#667085] dark:text-gray-400 italic px-2.5 py-1.5 bg-gray-100 dark:bg-gray-800 rounded-lg border border-[#E8E8E8] dark:border-gray-700">
                        {{ $receiveBlockReason }}
                    </span>
                @endif

                @if($canCancel)
                    <button type="button" @click="openCancelModal = true" class="px-3.5 py-2 text-xs font-medium border border-rose-300 dark:border-rose-800 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition cursor-pointer rounded-[6px]">
                        Batalkan Transfer
                    </button>
                @endif

            </x-slot:actions>
        </x-doc-hero>

        <x-flow-stepper title="Alur Transfer" :steps="$trfSteps" :current="$trfPos" :completed="$transfer->status === 'selesai'" :terminal="$trfTerminal" />

        {{-- Ringkasan --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <div class="xl:col-span-6 card-elevated rounded-[14px] border border-border bg-card p-4 sm:p-5 min-w-0">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="text-[12px] text-muted-foreground">Rute Pengiriman</span>
                    <span class="size-8 rounded-[9px] bg-[#F4F6FE] dark:bg-[#627EF2]/15 text-[#3C55C9] dark:text-[#AEBBF9] flex items-center justify-center [&>svg]:size-4" aria-hidden="true"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg></span>
                </div>
                <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-start gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] text-muted-foreground">Gudang Asal (Pengirim)</p>
                        <p class="mt-0.5 text-[15px] font-semibold text-foreground leading-snug">{{ $transfer->gudangAsal?->nama ?? '-' }}</p>
                        <p class="text-[11px] text-muted-foreground tabular-nums">{{ $transfer->gudangAsal?->kode }}</p>
                        @if($transfer->waktu_dikirim)
                            <p class="mt-1.5 text-[11px] text-muted-foreground tabular-nums">Dikirim {{ $transfer->waktu_dikirim->format('d/m/Y H:i') }} · {{ $transfer->sender?->name ?? 'Staff' }}</p>
                        @endif
                    </div>
                    <span class="mt-3 text-muted-foreground" aria-hidden="true">→</span>
                    <div class="min-w-0">
                        <p class="text-[11px] text-muted-foreground">Gudang Tujuan (Penerima)</p>
                        <p class="mt-0.5 text-[15px] font-semibold text-foreground leading-snug">{{ $transfer->gudangTujuan?->nama ?? '-' }}</p>
                        <p class="text-[11px] text-muted-foreground tabular-nums">{{ $transfer->gudangTujuan?->kode }}</p>
                        @if($transfer->waktu_diterima)
                            <p class="mt-1.5 text-[11px] text-muted-foreground tabular-nums">Diterima {{ $transfer->waktu_diterima->format('d/m/Y H:i') }} · {{ $transfer->receiver?->name ?? 'Staff' }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <x-info-tile label="Referensi Request" class="xl:col-span-3">
                @if($transfer->stockRequest)
                    <a href="{{ route('requests.show', $transfer->stockRequest) }}" class="text-[15px] font-semibold tabular-nums text-foreground hover:underline">{{ $transfer->stockRequest->no_request }}</a>
                @else
                    <p class="text-[15px] font-semibold text-muted-foreground">Transfer Mandiri</p>
                @endif
                <p class="mt-1 text-[12px] text-muted-foreground">{{ \App\Models\Transfer::JENIS_LABELS[$transfer->jenis] ?? ucfirst(str_replace('_', ' ', $transfer->jenis)) }}</p>
            </x-info-tile>

            <x-info-tile label="Status Barang Rusak" class="xl:col-span-3" :tone="$totalRusak > 0 ? 'danger' : 'default'">
                <p class="tabular-nums leading-none {{ $totalRusak > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-foreground' }}"><span class="text-[28px] font-semibold tracking-[-0.03em]">{{ $fmtTrf($totalRusak) }}</span><span class="text-[13px] font-medium text-muted-foreground"> rusak</span></p>
                <p class="mt-1.5 text-[12px] text-muted-foreground tabular-nums">dari {{ $fmtTrf($totalKirim) }} dikirim · {{ $transfer->items->count() }} item</p>
            </x-info-tile>
        </div>

        @if($transfer->catatan)
        <div class="p-4 rounded-[12px] bg-[#F5F5F5] dark:bg-gray-900 border border-[#E8E8E8] dark:border-gray-800 text-xs">
            <span class="font-medium text-[#101828] dark:text-white">Catatan Pengiriman:</span>
            <p class="text-[#667085] dark:text-gray-400 mt-0.5">{{ $transfer->catatan }}</p>
        </div>
        @endif

        @if($transfer->status === 'dibatalkan' && $transfer->alasan_batal)
        <div class="p-4 rounded-[12px] bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800/50 text-rose-900 dark:text-rose-200 text-xs">
            <span class="font-medium">Alasan Pembatalan Transfer:</span>
            <p class="mt-0.5">{{ $transfer->alasan_batal }}</p>
        </div>
        @endif

        {{-- Items Table --}}
        <x-card class="tbl-modern" variant="elevated" title="Rincian Barang Mutasi" subtitle="Daftar produk, volume pengiriman, serta verifikasi barang baik vs rusak" :noPadding="true">
            <x-slot:icon>
                <svg class="size-5 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </x-slot:icon>

            <div tabindex="0" role="region" aria-label="Rincian Barang Mutasi" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-[#f8fafb] dark:bg-gray-800/95">
                        <tr class="border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 text-[13px] font-medium">
                            <th class="px-4 py-3 text-left w-12">No</th>
                            <th class="px-4 py-3 text-left">SKU</th>
                            <th class="px-4 py-3 text-left">Nama Produk</th>
                            <th class="px-4 py-3 text-left w-24">Satuan</th>
                            <th class="px-4 py-3 text-right w-32">Qty Dikirim</th>
                            <th class="px-4 py-3 text-right w-32">Qty Diterima (Baik)</th>
                            <th class="px-4 py-3 text-right w-32 text-rose-700 dark:text-rose-400">Qty Rusak</th>
                            <th class="px-4 py-3 text-left">Keterangan Rusak / Kondisi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                        @foreach($transfer->items as $idx => $it)
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 {{ ($it->qty_rusak ?? 0) > 0 ? 'bg-rose-50/20 dark:bg-rose-950/15' : '' }}">
                            <td class="px-4 py-3 text-center text-[#667085] dark:text-gray-400">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3 tabular-nums font-medium text-[#101828] dark:text-white">{{ $it->produk?->sku ?? '-' }}</td>
                            <td class="px-4 py-3 font-medium text-[#101828] dark:text-white">{{ $it->produk?->nama ?? '-' }}</td>
                            <td class="px-4 py-3 text-[#667085] dark:text-gray-400">{{ $it->produk?->satuan ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums text-[#101828] dark:text-white">{{ number_format($it->qty_dikirim, 2) }}</td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums text-foreground dark:text-foreground">
                                {{ $transfer->status === 'selesai' ? number_format($it->qty_baik ?? 0, 2) : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums text-rose-600 dark:text-rose-400">
                                @if($transfer->status === 'selesai')
                                    {{ ($it->qty_rusak ?? 0) > 0 ? number_format($it->qty_rusak, 2) : '0' }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if(($it->qty_rusak ?? 0) > 0)
                                    <x-badge variant="danger" size="xs" :pill="true">{{ $it->keterangan_rusak }}</x-badge>
                                @else
                                    <span class="text-[#667085] dark:text-gray-400">{{ $it->keterangan ?? '-' }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- Modal Konfirmasi Penerimaan Barang (Strict Anti-Fraud Invariant) --}}
        @if($canReceive)
        <x-modal name="openReceiveModal" maxWidth="3xl">
            <x-slot:header>
                <div class="px-6 py-4 border-b border-[#E8E8E8] dark:border-gray-800 bg-card dark:bg-card flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-foreground dark:text-foreground">Konfirmasi Penerimaan Barang Transfer</h3>
                        <p class="text-xs text-foreground dark:text-foreground">Verifikasi fisik barang datang, hitung barang baik & barang rusak secara akurat</p>
                    </div>
                </div>
            </x-slot:header>

            <form method="POST" action="{{ route('transfers.receive', $transfer) }}" class="p-6 space-y-5 bg-[#F5F5F5] dark:bg-gray-950">
                @csrf

                <div class="p-3.5 bg-card dark:bg-card border border-border dark:border-border rounded-xl text-xs text-foreground dark:text-foreground space-y-1">
                    <div class="font-medium flex items-center gap-1.5">
                        <svg class="size-4 shrink-0 text-foreground dark:text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Aturan Anti-Fraud Penerimaan (Strict Invariant):
                    </div>
                    <p class="leading-relaxed">
                        Total <strong>Qty Baik + Qty Rusak</strong> harus sama persis dengan <strong>Qty Dikirim</strong>. Jika terdapat barang rusak (Qty Rusak > 0), kolom keterangan rusak wajib diisi dengan rincian kerusakan untuk audit defect bulanan.
                    </p>
                </div>

                <div class="overflow-x-auto border border-[#E8E8E8] dark:border-gray-800 rounded-xl bg-white dark:bg-gray-900">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-[#f8fafb] dark:bg-gray-800/95 border-b border-[#E8E8E8] dark:border-gray-800 text-[#667085] dark:text-gray-400 font-medium">
                                <th class="px-3 py-2.5 text-left">Produk</th>
                                <th class="px-3 py-2.5 text-right w-24">Qty Dikirim</th>
                                <th class="px-3 py-2.5 text-right w-32">Qty Baik <span class="text-rose-500">*</span></th>
                                <th class="px-3 py-2.5 text-right w-32">Qty Rusak <span class="text-rose-500">*</span></th>
                                <th class="px-3 py-2.5 text-left">Keterangan Rusak</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800">
                            <template x-for="(item, idx) in items" :key="item.id">
                                <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40">
                                    <td class="px-3 py-3">
                                        <input type="hidden" :name="`items[${idx}][id]`" :value="item.id">
                                        <div class="font-medium text-[#101828] dark:text-white" x-text="item.nama"></div>
                                        <div class="tabular-nums text-[10px] text-[#667085] dark:text-gray-400" x-text="`${item.sku} (${item.satuan})`"></div>
                                    </td>
                                    <td class="px-3 py-3 text-right font-medium tabular-nums text-[#101828] dark:text-white" x-text="item.qty_dikirim"></td>
                                    <td class="px-3 py-3">
                                        <input type="number" step="0.01" min="0" :max="item.qty_dikirim" :name="`items[${idx}][qty_baik]`" x-model.number="item.qty_baik" class="w-full h-8 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 px-2 text-right font-medium text-foreground dark:text-foreground text-xs outline-none focus:border-[#111111] dark:focus:border-white rounded-[6px]" required>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input type="number" step="0.01" min="0" :max="item.qty_dikirim" :name="`items[${idx}][qty_rusak]`" x-model.number="item.qty_rusak" class="w-full h-8 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 px-2 text-right font-medium text-rose-700 dark:text-rose-400 text-xs outline-none focus:border-[#111111] dark:focus:border-white rounded-[6px]" required>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input type="text" :name="`items[${idx}][keterangan_rusak]`" x-model="item.keterangan_rusak" :required="item.qty_rusak > 0" placeholder="Pecah, bocor, kadaluarsa..." class="w-full h-8 border border-[#E8E8E8] dark:border-gray-700 bg-white dark:bg-gray-900 text-[#101828] dark:text-white px-2 text-xs outline-none focus:border-[#111111] dark:focus:border-white rounded-[6px]">
                                        <template x-for="diff in [parseFloat(item.qty_dikirim) - (parseFloat(item.qty_baik || 0) + parseFloat(item.qty_rusak || 0))]">
                                            <div x-show="Math.abs(diff) > 0.0001" class="text-[10px] text-rose-600 dark:text-rose-400 font-medium mt-1">
                                                Selisih: <span x-text="diff.toFixed(2)"></span> (Wajib 0)
                                            </div>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <x-button type="button" variant="secondary" @click="openReceiveModal = false">Batal</x-button>
                    <x-button type="submit" variant="primary" x-bind:disabled="!isReceiveValid()">Konfirmasi &amp; Terima Stok</x-button>
                </div>
            </form>
        </x-modal>
        @endif

        {{-- Modal Pembatalan Transfer --}}
        @if($canCancel)
        @php
            $isInTransitCancel = $transfer->status === 'dikirim';
        @endphp
        <x-modal name="openCancelModal" maxWidth="md">
            <x-slot:header>
                <div class="px-6 py-4 border-b border-[#E8E8E8] dark:border-gray-800 bg-rose-50 dark:bg-rose-950/30 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-rose-950 dark:text-rose-100">
                        {{ $isInTransitCancel ? 'Batalkan Transfer In Transit' : 'Batalkan Draft Transfer' }}
                    </h3>
                </div>
            </x-slot:header>
            <form method="POST" action="{{ route('transfers.cancel', $transfer) }}" class="p-6 space-y-4 bg-[#F5F5F5] dark:bg-gray-950">
                @csrf
                @if($isInTransitCancel)
                <div class="p-3 rounded-xl bg-card dark:bg-card border border-border dark:border-border text-foreground dark:text-foreground text-xs leading-relaxed">
                    <strong>Perhatian:</strong> Transfer ini sedang berstatus <strong>In Transit</strong>. Membatalkan transfer akan mengembalikan seluruh kuantitas kirim ke saldo stok <strong>{{ $transfer->gudangAsal?->nama ?? 'gudang asal' }}</strong>.
                </div>
                @endif
                <x-form-field name="alasan" label="Alasan Pembatalan" :required="$isInTransitCancel" :help="$isInTransitCancel ? null : 'Opsional'">
                    <x-textarea id="cancel-transfer-alasan"
                              name="alasan"
                              x-model="alasanCancel"
                              rows="3"
                              variant="seamless"
                              :required="$isInTransitCancel"
                              placeholder="{{ $isInTransitCancel ? 'Jelaskan alasan pembatalan pengiriman In Transit...' : 'Alasan pembatalan draft transfer...' }}" />
                </x-form-field>
                <div class="flex justify-end gap-2 pt-2">
                    <x-button type="button" variant="secondary" @click="openCancelModal = false">Tutup</x-button>
                    <button type="submit" class="px-4 py-2 text-xs font-medium bg-rose-600 hover:bg-rose-700 text-white transition cursor-pointer rounded-[6px]">
                        {{ $isInTransitCancel ? 'Konfirmasi Batalkan Transfer' : 'Batalkan Transfer' }}
                    </button>
                </div>
            </form>
        </x-modal>
        @endif

        {{-- Jejak Audit Operasional Transfer --}}
        <x-operational-audit-history :auditable="$transfer" title="Jejak Riwayat Audit Transfer {{ $transfer->no_transfer }}" />
    </div>
</x-app-layout>


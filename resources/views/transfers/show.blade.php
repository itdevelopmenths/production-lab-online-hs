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

        {{-- Header Status Bar --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 rounded-2xl bg-card border border-border">
            <div class="flex items-center gap-4">
                <div class="size-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <svg class="size-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-xl font-bold font-mono text-foreground">{{ $transfer->no_transfer }}</h2>
                        @php
                            $statusBadge = match($transfer->status) {
                                'draft' => 'bg-gray-100 text-gray-700 border-gray-300',
                                'dikirim' => 'bg-amber-100 text-amber-800 border-amber-300',
                                'selesai' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                'dibatalkan' => 'bg-rose-100 text-rose-800 border-rose-300',
                                default => 'bg-gray-100 text-gray-700 border-gray-300',
                            };
                            $statusLabel = match($transfer->status) {
                                'draft' => 'Draft',
                                'dikirim' => 'In Transit (Dikirim)',
                                'selesai' => 'Selesai Diterima',
                                'dibatalkan' => 'Dibatalkan',
                                default => ucfirst($transfer->status),
                            };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusBadge }}">
                            {{ $statusLabel }}
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Dibuat oleh <span class="font-medium text-foreground">{{ $transfer->creator?->name ?? 'Sistem' }}</span> pada {{ $transfer->created_at->format('d/m/Y H:i') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if(in_array($transfer->status, ['dikirim', 'selesai'], true))
                    <a href="{{ route('transfers.surat-jalan', $transfer) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg border border-border bg-card hover:bg-muted text-foreground transition">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Cetak Surat Jalan
                    </a>
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
                    <button type="button" @click="openReceiveModal = true" class="px-3.5 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition flex items-center gap-1.5 shadow-xs">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Konfirmasi Penerimaan Barang
                    </button>
                @elseif($transfer->status === 'dikirim')
                    <span class="text-[11px] text-muted-foreground italic px-2.5 py-1.5 bg-muted rounded-lg border border-border">
                        Penerimaan memerlukan petugas gudang yang berbeda (Segregation of Duties).
                    </span>
                @endif

                @if($canCancel)
                    <button type="button" @click="openCancelModal = true" class="px-3.5 py-2 text-xs font-semibold rounded-lg border border-destructive/30 text-destructive hover:bg-destructive/10 transition cursor-pointer">
                        Batalkan Transfer
                    </button>
                @endif
            </div>
        </div>

        {{-- Grid Info --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-card class="p-4">
                <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Gudang Asal (Pengirim)</div>
                <div class="text-sm font-semibold text-foreground mt-1">{{ $transfer->gudangAsal?->nama ?? '-' }}</div>
                <div class="text-[10px] text-muted-foreground">{{ $transfer->gudangAsal?->kode }}</div>
                @if($transfer->waktu_dikirim)
                    <div class="text-[10px] text-primary mt-1">Dikirim: {{ $transfer->waktu_dikirim->format('d/m/Y H:i') }} ({{ $transfer->sender?->name ?? 'Staff' }})</div>
                @endif
            </x-card>

            <x-card class="p-4">
                <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Gudang Tujuan (Penerima)</div>
                <div class="text-sm font-semibold text-foreground mt-1">{{ $transfer->gudangTujuan?->nama ?? '-' }}</div>
                <div class="text-[10px] text-muted-foreground">{{ $transfer->gudangTujuan?->kode }}</div>
                @if($transfer->waktu_diterima)
                    <div class="text-[10px] text-emerald-600 mt-1">Diterima: {{ $transfer->waktu_diterima->format('d/m/Y H:i') }} ({{ $transfer->receiver?->name ?? 'Staff' }})</div>
                @endif
            </x-card>

            <x-card class="p-4">
                <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Referensi Request</div>
                <div class="text-sm font-semibold text-foreground mt-1 font-mono">
                    @if($transfer->stockRequest)
                        <a href="{{ route('requests.show', $transfer->stockRequest) }}" class="text-primary hover:underline">
                            {{ $transfer->stockRequest->no_request }}
                        </a>
                    @else
                        <span class="text-muted-foreground">Transfer Mandiri</span>
                    @endif
                </div>
                <div class="text-[10px] text-muted-foreground font-medium">{{ \App\Models\Transfer::JENIS_LABELS[$transfer->jenis] ?? ucfirst(str_replace('_', ' ', $transfer->jenis)) }}</div>
            </x-card>

            <x-card class="p-4">
                <div class="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Status Barang Rusak</div>
                @php
                    $totalRusak = (float) $transfer->items->sum('qty_rusak');
                @endphp
                <div class="text-sm font-bold {{ $totalRusak > 0 ? 'text-rose-600' : 'text-emerald-600' }} mt-1">
                    {{ $totalRusak > 0 ? number_format($totalRusak, 2) . ' Rusak / Defect' : '0 Rusak (Kondisi Baik)' }}
                </div>
                <div class="text-[10px] text-muted-foreground">Dari total {{ number_format($transfer->items->sum('qty_dikirim'), 2) }} dikirim</div>
            </x-card>
        </div>

        @if($transfer->catatan)
        <div class="p-4 rounded-xl bg-muted/40 border border-border text-xs">
            <span class="font-semibold text-foreground">Catatan Pengiriman:</span>
            <p class="text-muted-foreground mt-0.5">{{ $transfer->catatan }}</p>
        </div>
        @endif

        @if($transfer->status === 'dibatalkan' && $transfer->alasan_batal)
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs">
            <span class="font-bold">Alasan Pembatalan Transfer:</span>
            <p class="mt-0.5">{{ $transfer->alasan_batal }}</p>
        </div>
        @endif

        {{-- Items Table --}}
        <x-card>
            <x-slot:header>
                <div>
                    <h2 class="text-lg font-semibold text-foreground tracking-tight">Rincian Barang Mutasi</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Daftar produk, volume pengiriman, serta verifikasi barang baik vs rusak</p>
                </div>
            </x-slot:header>

            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-muted/70 border-b border-border text-muted-foreground font-medium">
                            <th class="px-4 py-3 text-left w-12">No</th>
                            <th class="px-4 py-3 text-left">SKU</th>
                            <th class="px-4 py-3 text-left">Nama Produk</th>
                            <th class="px-4 py-3 text-left w-24">Satuan</th>
                            <th class="px-4 py-3 text-right w-32">Qty Dikirim</th>
                            <th class="px-4 py-3 text-right w-32">Qty Diterima (Baik)</th>
                            <th class="px-4 py-3 text-right w-32 text-rose-700">Qty Rusak</th>
                            <th class="px-4 py-3 text-left">Keterangan Rusak / Kondisi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($transfer->items as $idx => $it)
                        <tr class="hover:bg-muted/30 {{ ($it->qty_rusak ?? 0) > 0 ? 'bg-rose-50/20' : '' }}">
                            <td class="px-4 py-3 text-center text-muted-foreground">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3 font-mono font-medium text-foreground">{{ $it->produk?->sku ?? '-' }}</td>
                            <td class="px-4 py-3 font-semibold text-foreground">{{ $it->produk?->nama ?? '-' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ $it->produk?->satuan ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-bold text-foreground">{{ number_format($it->qty_dikirim, 2) }}</td>
                            <td class="px-4 py-3 text-right font-bold text-emerald-600">
                                {{ $transfer->status === 'selesai' ? number_format($it->qty_baik ?? 0, 2) : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-extrabold text-rose-600">
                                @if($transfer->status === 'selesai')
                                    {{ ($it->qty_rusak ?? 0) > 0 ? number_format($it->qty_rusak, 2) : '0' }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if(($it->qty_rusak ?? 0) > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-rose-100 text-rose-800 border border-rose-200">
                                        {{ $it->keterangan_rusak }}
                                    </span>
                                @else
                                    <span class="text-muted-foreground">{{ $it->keterangan ?? '-' }}</span>
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
                <div class="px-6 py-4 border-b border-border bg-emerald-50 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-emerald-950">Konfirmasi Penerimaan Barang Transfer</h3>
                        <p class="text-xs text-emerald-800">Verifikasi fisik barang datang, hitung barang baik & barang rusak secara akurat</p>
                    </div>
                </div>
            </x-slot:header>

            <form method="POST" action="{{ route('transfers.receive', $transfer) }}" class="p-6 space-y-5">
                @csrf

                <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <svg class="size-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Aturan Anti-Fraud Penerimaan (Strict Invariant):
                    </div>
                    <p class="leading-relaxed">
                        Total <strong>Qty Baik + Qty Rusak</strong> harus sama persis dengan <strong>Qty Dikirim</strong>. Jika terdapat barang rusak (Qty Rusak > 0), kolom keterangan rusak wajib diisi dengan rincian kerusakan untuk audit defect bulanan.
                    </p>
                </div>

                <div class="overflow-x-auto border border-border rounded-xl">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-muted/80 border-b border-border text-muted-foreground font-medium">
                                <th class="px-3 py-2.5 text-left">Produk</th>
                                <th class="px-3 py-2.5 text-right w-24">Qty Dikirim</th>
                                <th class="px-3 py-2.5 text-right w-32">Qty Baik <span class="text-destructive">*</span></th>
                                <th class="px-3 py-2.5 text-right w-32">Qty Rusak <span class="text-destructive">*</span></th>
                                <th class="px-3 py-2.5 text-left">Keterangan Rusak</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template x-for="(item, idx) in items" :key="item.id">
                                <tr class="hover:bg-muted/20">
                                    <td class="px-3 py-3">
                                        <input type="hidden" :name="`items[${idx}][id]`" :value="item.id">
                                        <div class="font-semibold text-foreground" x-text="item.nama"></div>
                                        <div class="font-mono text-[10px] text-muted-foreground" x-text="`${item.sku} (${item.satuan})`"></div>
                                    </td>
                                    <td class="px-3 py-3 text-right font-bold text-foreground" x-text="item.qty_dikirim"></td>
                                    <td class="px-3 py-3">
                                        <input type="number" step="0.01" min="0" :max="item.qty_dikirim" :name="`items[${idx}][qty_baik]`" x-model.number="item.qty_baik" class="w-full h-8 rounded border border-border px-2 text-right font-semibold text-emerald-700 text-xs" required>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input type="number" step="0.01" min="0" :max="item.qty_dikirim" :name="`items[${idx}][qty_rusak]`" x-model.number="item.qty_rusak" class="w-full h-8 rounded border border-border px-2 text-right font-semibold text-rose-700 text-xs" required>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input type="text" :name="`items[${idx}][keterangan_rusak]`" x-model="item.keterangan_rusak" :required="item.qty_rusak > 0" placeholder="Pecah, bocor, kadaluarsa..." class="w-full h-8 rounded border border-border px-2 text-xs">
                                        <template x-for="diff in [parseFloat(item.qty_dikirim) - (parseFloat(item.qty_baik || 0) + parseFloat(item.qty_rusak || 0))]">
                                            <div x-show="Math.abs(diff) > 0.0001" class="text-[10px] text-rose-600 font-semibold mt-1">
                                                Selisih: <span x-text="diff.toFixed(2)"></span> (Wajib 0)
                                            </div>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <x-button type="button" variant="outline" @click="openReceiveModal = false">Batal</x-button>
                    <button type="submit" :disabled="!isReceiveValid()" class="px-5 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition disabled:opacity-40 disabled:cursor-not-allowed">
                        Konfirmasi & Terima Stok
                    </button>
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
                <div class="px-6 py-4 border-b border-border bg-rose-50 flex items-center justify-between">
                    <h3 class="text-base font-bold text-rose-950">
                        {{ $isInTransitCancel ? 'Batalkan Transfer In Transit' : 'Batalkan Draft Transfer' }}
                    </h3>
                </div>
            </x-slot:header>
            <form method="POST" action="{{ route('transfers.cancel', $transfer) }}" class="p-6 space-y-4">
                @csrf
                @if($isInTransitCancel)
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs leading-relaxed">
                    <strong>Perhatian:</strong> Transfer ini sedang berstatus <strong>In Transit</strong>. Membatalkan transfer akan mengembalikan seluruh kuantitas kirim ke saldo stok <strong>{{ $transfer->gudangAsal?->nama ?? 'gudang asal' }}</strong>.
                </div>
                @endif
                <div>
                    <label for="cancel-transfer-alasan" class="block text-xs font-semibold text-foreground mb-1">
                        Alasan Pembatalan
                        @if($isInTransitCancel)
                            <span class="text-destructive">*</span>
                        @else
                            <span class="text-muted-foreground font-normal">(Opsional)</span>
                        @endif
                    </label>
                    <textarea id="cancel-transfer-alasan"
                              name="alasan"
                              x-model="alasanCancel"
                              rows="3"
                              @if($isInTransitCancel) required minlength="3" @endif
                              class="w-full text-xs rounded-lg border border-border p-2 focus:ring-2 focus:ring-primary/20"
                              placeholder="{{ $isInTransitCancel ? 'Jelaskan alasan pembatalan pengiriman In Transit...' : 'Alasan pembatalan draft transfer...' }}"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="outline" @click="openCancelModal = false">Tutup</x-button>
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition cursor-pointer">
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


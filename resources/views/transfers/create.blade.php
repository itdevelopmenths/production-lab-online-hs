<x-app-layout title="Buat Transfer Barang" subtitle="Inisiasi pengiriman / mutasi fisik barang antar gudang">
    @php
        $initialItems = old('items', !empty($prefilledItems) ? $prefilledItems : [
            ['produk_id' => '', 'qty_dikirim' => 1, 'keterangan' => '']
        ]);
    @endphp

    <script>
        function transferCreateData() {
            return {
                requestId: @js($stockRequest?->id ?? ''),
                gudangAsalId: @js((string) old('gudang_asal_id', $defaultGudangAsalId ?? '')),
                gudangTujuanId: @js((string) old('gudang_tujuan_id', $defaultGudangTujuanId ?? '')),
                jenis: @js(old('jenis', $defaultJenis ?? 'distribusi')),
                items: @js($initialItems),
                products: @js($products),
                init() {
                    this.$watch('gudangAsalId', () => this.refreshAllStock());
                },
                addItem() {
                    this.items.push({ produk_id: '', qty_dikirim: 1, keterangan: '', stok_tersedia: null, request_item_id: null });
                },
                removeItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                    }
                },
                getSatuan(prodId) {
                    const p = this.products.find(x => x.id == prodId);
                    return p ? p.satuan : '-';
                },
                async updateStock(item) {
                    if (!item.produk_id || !this.gudangAsalId) return;
                    try {
                        const res = await fetch(`{{ route('stok.current-stock') }}?produk_id=${item.produk_id}&gudang_id=${this.gudangAsalId}`);
                        if (res.ok) {
                            const data = await res.json();
                            item.stok_tersedia = data.stok_tersedia ?? data.saldo ?? 0;
                        }
                    } catch (e) {}
                },
                refreshAllStock() {
                    this.items.forEach(item => this.updateStock(item));
                }
            };
        }
    </script>

    <div x-data="transferCreateData()">
        <form method="POST" action="{{ route('transfers.store') }}" class="space-y-6">
            @csrf
            @if($stockRequest)
                <input type="hidden" name="request_id" value="{{ $stockRequest->id }}">
            @endif
            @if(!empty($referensiBatchId))
                <input type="hidden" name="referensi_batch_id" value="{{ $referensiBatchId }}">
            @endif

            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground tracking-tight">Formulir Transfer / Mutasi Barang</h2>
                            <p class="text-xs text-muted-foreground mt-0.5">Penetapan gudang pengirim, gudang tujuan, dan pemotongan stok pada pengiriman</p>
                        </div>
                    </div>
                </x-slot:header>

                @if($stockRequest)
                <div class="p-4 mb-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-900 text-xs flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="size-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center font-bold">
                            RT
                        </div>
                        <div>
                            <span class="font-bold">Memproses Dokumen Permintaan:</span>
                            <span class="font-mono bg-white px-2 py-0.5 rounded border border-blue-300 font-semibold ml-1">{{ $stockRequest->no_request }}</span>
                            <div class="text-[11px] text-blue-700 mt-0.5">Permintaan dari {{ $stockRequest->gudangPemohon?->nama }} ke {{ $stockRequest->gudangTujuan?->nama }}</div>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">
                        Disetujui
                    </span>
                </div>
                @endif

                @if($batch)
                <div class="p-4 mb-4 rounded-xl bg-sky-50 border border-sky-200 text-sky-900 text-xs flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="size-8 rounded-lg bg-sky-100 text-sky-600 flex items-center justify-center font-bold">
                            BP
                        </div>
                        <div>
                            <span class="font-bold">Kirim Produk Jadi dari Batch Produksi:</span>
                            <span class="font-mono bg-white px-2 py-0.5 rounded border border-sky-300 font-semibold ml-1">{{ $batch->no_batch }}</span>
                            <div class="text-[11px] text-sky-700 mt-0.5">Pengiriman hasil produksi dari {{ $batch->gudangOperasional?->nama }} ke {{ $batch->gudangTujuan?->nama ?? 'Fulfillment Pusat' }}</div>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-300">
                        {{ ucfirst($batch->status) }}
                    </span>
                </div>
                @endif

                @if($errors->any())
                <div class="p-4 mb-4 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs">
                    <div class="font-bold mb-1">Terdapat kesalahan input:</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1.5">Jenis Transfer <span class="text-destructive">*</span></label>
                        <select name="jenis" x-model="jenis" class="w-full h-10 text-xs rounded-lg border border-border bg-card px-3 focus:ring-2 focus:ring-primary/20" required>
                            @foreach(\App\Models\Transfer::JENIS_LABELS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1.5">Gudang Asal (Pengirim) <span class="text-destructive">*</span></label>
                        <select name="gudang_asal_id" x-model="gudangAsalId" class="w-full h-10 text-xs rounded-lg border border-border bg-card px-3 focus:ring-2 focus:ring-primary/20" required>
                            <option value="">-- Pilih Gudang Asal --</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->nama }} ({{ $w->kode }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1.5">Gudang Tujuan (Penerima) <span class="text-destructive">*</span></label>
                        <select name="gudang_tujuan_id" x-model="gudangTujuanId" class="w-full h-10 text-xs rounded-lg border border-border bg-card px-3 focus:ring-2 focus:ring-primary/20" required>
                            <option value="">-- Pilih Gudang Tujuan --</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->nama }} ({{ $w->kode }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-5">
                    <label class="block text-xs font-semibold text-foreground mb-1.5">Catatan Pengiriman</label>
                    <textarea name="catatan" rows="2" class="w-full text-xs rounded-lg border border-border p-2.5 focus:ring-2 focus:ring-primary/20" placeholder="Catatan ekspedisi, armada angkut, atau instruksi serah terima...">{{ old('catatan') }}</textarea>
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground tracking-tight">Rincian Barang Dikirim</h2>
                            <p class="text-xs text-muted-foreground mt-0.5">Pastikan kuantitas pengiriman tidak melebihi stok yang tersedia di gudang asal</p>
                        </div>
                        @if(!$stockRequest)
                        <button type="button" @click="addItem()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition">
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Tambah Baris
                        </button>
                        @endif
                    </div>
                </x-slot:header>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-muted/70 border-b border-border text-muted-foreground font-medium">
                                <th class="px-4 py-2.5 text-left w-12">No</th>
                                <th class="px-4 py-2.5 text-left">Produk</th>
                                <th class="px-4 py-2.5 text-left w-24">Satuan</th>
                                @if($stockRequest)
                                <th class="px-4 py-2.5 text-right w-28">Qty Diminta</th>
                                @endif
                                <th class="px-4 py-2.5 text-right w-28">Stok Tersedia</th>
                                <th class="px-4 py-2.5 text-right w-36">Qty Dikirim</th>
                                <th class="px-4 py-2.5 text-left">Keterangan</th>
                                @if(!$stockRequest)
                                <th class="px-4 py-2.5 text-center w-16">Hapus</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="hover:bg-muted/30">
                                    <td class="px-4 py-3 font-medium text-muted-foreground text-center" x-text="index + 1"></td>
                                    <td class="px-4 py-3">
                                        @if($stockRequest)
                                            <input type="hidden" :name="`items[${index}][request_item_id]`" :value="item.request_item_id">
                                            <input type="hidden" :name="`items[${index}][produk_id]`" :value="item.produk_id">
                                            <div class="font-semibold text-foreground" x-text="item.nama"></div>
                                            <div class="font-mono text-[10px] text-muted-foreground" x-text="item.sku"></div>
                                        @else
                                            <template x-if="item.request_item_id">
                                                <input type="hidden" :name="`items[${index}][request_item_id]`" :value="item.request_item_id">
                                            </template>
                                            <select :name="`items[${index}][produk_id]`" x-model="item.produk_id" @change="updateStock(item)" class="w-full h-9 rounded-lg border border-border bg-card px-2.5 text-xs" required>
                                                <option value="">-- Pilih Produk --</option>
                                                <template x-for="p in products" :key="p.id">
                                                    <option :value="p.id" x-text="`${p.nama} (${p.sku})`" :selected="p.id == item.produk_id"></option>
                                                </template>
                                            </select>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-block px-2.5 py-1 bg-muted rounded font-medium text-foreground" x-text="item.satuan || getSatuan(item.produk_id)"></span>
                                    </td>
                                    @if($stockRequest)
                                    <td class="px-4 py-3 text-right font-medium text-muted-foreground" x-text="item.qty_diminta"></td>
                                    @endif
                                    <td class="px-4 py-3 text-right">
                                        <span :class="item.stok_tersedia !== undefined && item.stok_tersedia !== null && parseFloat(item.qty_dikirim || 0) > parseFloat(item.stok_tersedia) ? 'text-destructive font-bold' : 'text-emerald-600 font-medium'"
                                              x-text="item.stok_tersedia !== undefined && item.stok_tersedia !== null ? parseFloat(item.stok_tersedia).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 2}) : '-'">
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="0.001" min="0.001" :name="`items[${index}][qty_dikirim]`" x-model="item.qty_dikirim" class="w-full h-9 rounded-lg border border-border px-2.5 text-xs text-right font-medium" required>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="text" :name="`items[${index}][keterangan]`" x-model="item.keterangan" placeholder="Keterangan opsional" class="w-full h-9 rounded-lg border border-border px-2.5 text-xs">
                                    </td>
                                    @if(!$stockRequest)
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" @click="removeItem(index)" :disabled="items.length <= 1" class="text-destructive hover:opacity-80 disabled:opacity-30 transition p-1">
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                    @endif
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 bg-muted/20 border-t border-border flex items-center justify-between">
                    <x-button href="{{ route('transfers.index') }}" variant="outline">Kembali ke Daftar</x-button>
                    <div class="flex items-center gap-3">
                        <button type="submit" name="action" value="save_draft" class="px-4 py-2 text-xs font-semibold rounded-lg border border-border bg-card hover:bg-muted text-foreground transition">
                            Simpan Sebagai Draft
                        </button>
                        <button type="submit" name="action" value="ship" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary hover:bg-primary/90 text-primary-foreground transition shadow-xs">
                            Kirim Sekarang (In Transit - Potong Stok)
                        </button>
                    </div>
                </div>
            </x-card>
        </form>
    </div>
</x-app-layout>


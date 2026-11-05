<x-app-layout title="Edit Permintaan Barang" subtitle="Perbarui rincian permintaan barang">
    <div x-data="{
        jenis: '{{ old('jenis', $stockRequest->jenis) }}',
        gudangPemohonId: '{{ old('gudang_pemohon_id', $stockRequest->gudang_pemohon_id) }}',
        gudangTujuanId: '{{ old('gudang_tujuan_id', $stockRequest->gudang_tujuan_id) }}',
        items: @json(old('items', $stockRequest->items->map(fn($it) => [
            'produk_id' => $it->produk_id,
            'qty_diminta' => (float) $it->qty_diminta,
            'keterangan' => $it->keterangan ?? '',
        ]))),
        products: @json($produk),
        addItem() {
            this.items.push({ produk_id: '', qty_diminta: 1, keterangan: '' });
        },
        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },
        getSatuan(prodId) {
            const p = this.products.find(x => x.id == prodId);
            return p ? p.satuan : '-';
        }
    }">
        <form method="POST" action="{{ route('requests.update', $stockRequest) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-semibold text-foreground tracking-tight">Edit Permintaan Barang</h2>
                                <span class="font-mono text-xs px-2 py-0.5 rounded bg-muted text-primary font-bold">{{ $stockRequest->no_request }}</span>
                            </div>
                            <p class="text-xs text-muted-foreground mt-0.5">Perbarui rincian gudang dan kuantitas barang yang diminta</p>
                        </div>
                        <div>
                            @if($stockRequest->status === 'disetujui')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 border border-purple-300">
                                    Revisi Pasca Approval (FR-REQ-08)
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 border border-gray-300">
                                    Status Saat Ini: {{ ucfirst($stockRequest->status) }}
                                </span>
                            @endif
                        </div>
                    </div>
                </x-slot:header>

                @if($stockRequest->status === 'disetujui')
                <div class="p-4 mb-4 rounded-xl bg-purple-50 border border-purple-200 text-purple-900 text-xs flex items-start gap-3">
                    <svg class="size-5 text-purple-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <div class="font-bold">Ketentuan Revisi Permintaan Disetujui (FR-REQ-08):</div>
                        <p class="mt-0.5 leading-relaxed text-purple-800">
                            Karena permintaan ini sebelumnya sudah berstatus <strong>Disetujui</strong>, setiap perubahan pada kuantitas atau rincian item akan secara otomatis <strong>mereset status dokumen kembali menjadi 'Diajukan'</strong> dan membutuhkan persetujuan (approval) ulang dari Manajer sebelum dapat diproses transfer stoknya.
                        </p>
                    </div>
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
                        <label class="block text-xs font-semibold text-foreground mb-1.5">Jenis Permintaan</label>
                        <input type="text" readonly value="{{ $stockRequest->jenis === 'request_bahan' ? 'Request Bahan Baku' : 'Request Produk Jadi' }}" class="w-full h-10 text-xs rounded-lg border border-border bg-muted px-3 text-muted-foreground font-medium">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1.5">Gudang Pemohon <span class="text-destructive">*</span></label>
                        <select name="gudang_pemohon_id" x-model="gudangPemohonId" class="w-full h-10 text-xs rounded-lg border border-border bg-card px-3 focus:ring-2 focus:ring-primary/20" required>
                            @foreach($gudang as $g)
                                <option value="{{ $g->id }}">{{ $g->nama }} ({{ $g->kode }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-foreground mb-1.5">Gudang Penyedia <span class="text-destructive">*</span></label>
                        <select name="gudang_tujuan_id" x-model="gudangTujuanId" class="w-full h-10 text-xs rounded-lg border border-border bg-card px-3 focus:ring-2 focus:ring-primary/20" required>
                            @foreach($gudang as $g)
                                <option value="{{ $g->id }}">{{ $g->nama }} ({{ $g->kode }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if($stockRequest->batch)
                <div class="mt-4 p-3.5 bg-amber-50 border border-amber-200 rounded-xl flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-amber-800">Terkait Batch Produksi:</span>
                        <span class="font-mono bg-white px-2 py-0.5 rounded border border-amber-300 text-amber-900 font-semibold">{{ $stockRequest->batch->no_batch }}</span>
                    </div>
                    <input type="hidden" name="batch_id" value="{{ $stockRequest->batch_id }}">
                </div>
                @endif

                <div class="mt-5">
                    <label class="block text-xs font-semibold text-foreground mb-1.5">Catatan / Keperluan</label>
                    <textarea name="catatan" rows="2" class="w-full text-xs rounded-lg border border-border p-2.5 focus:ring-2 focus:ring-primary/20">{{ old('catatan', $stockRequest->catatan) }}</textarea>
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground tracking-tight">Rincian Barang</h2>
                            <p class="text-xs text-muted-foreground mt-0.5">Ubah item dan jumlah kuantitas yang diminta</p>
                        </div>
                        <button type="button" @click="addItem()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition">
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Tambah Baris
                        </button>
                    </div>
                </x-slot:header>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-muted/70 border-b border-border text-muted-foreground font-medium">
                                <th class="px-4 py-2.5 text-left w-12">No</th>
                                <th class="px-4 py-2.5 text-left">Produk</th>
                                <th class="px-4 py-2.5 text-left w-28">Satuan</th>
                                <th class="px-4 py-2.5 text-left w-36">Qty Diminta</th>
                                <th class="px-4 py-2.5 text-left">Keterangan</th>
                                <th class="px-4 py-2.5 text-center w-16">Hapus</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="hover:bg-muted/30">
                                    <td class="px-4 py-3 font-medium text-muted-foreground text-center" x-text="index + 1"></td>
                                    <td class="px-4 py-3">
                                        <select :name="`items[${index}][produk_id]`" x-model="item.produk_id" class="w-full h-9 rounded-lg border border-border bg-card px-2.5 text-xs" required>
                                            <option value="">-- Pilih Produk --</option>
                                            <template x-for="p in products" :key="p.id">
                                                <option :value="p.id" x-text="`${p.nama} (${p.sku})`" :selected="p.id == item.produk_id"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-block px-2.5 py-1 bg-muted rounded font-medium text-foreground" x-text="getSatuan(item.produk_id)"></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="0.001" min="0.001" :name="`items[${index}][qty_diminta]`" x-model="item.qty_diminta" class="w-full h-9 rounded-lg border border-border px-2.5 text-xs text-right font-medium" required>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="text" :name="`items[${index}][keterangan]`" x-model="item.keterangan" placeholder="Keterangan opsional" class="w-full h-9 rounded-lg border border-border px-2.5 text-xs">
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" @click="removeItem(index)" :disabled="items.length <= 1" class="text-destructive hover:opacity-80 disabled:opacity-30 transition p-1">
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 bg-muted/20 border-t border-border flex items-center justify-between">
                    <x-button href="{{ route('requests.show', $stockRequest) }}" variant="outline">Batal</x-button>
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary hover:bg-primary/90 text-primary-foreground transition shadow-xs">
                        Simpan Perubahan
                    </button>
                </div>
            </x-card>
        </form>
    </div>
</x-app-layout>

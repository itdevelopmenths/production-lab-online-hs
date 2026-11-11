<x-app-layout title="Edit Permintaan Barang">
    @php
        $productsById = $produk->keyBy('id');
        $rawItems = old('items', $stockRequest->items->map(fn($it) => [
            'produk_id' => $it->produk_id,
            'qty_diminta' => (float) $it->qty_diminta,
            'keterangan' => $it->keterangan ?? '',
        ])->values()->toArray());
        $initialItems = collect($rawItems)->map(function($it) use ($productsById) {
            $prodId = $it['produk_id'] ?? '';
            $prod = !empty($prodId) ? $productsById->get($prodId) : null;
            return [
                'produk_id' => $prodId,
                'qty_diminta' => $it['qty_diminta'] ?? 1,
                'keterangan' => $it['keterangan'] ?? '',
                'satuan' => $prod?->satuan ?? '-',
                'selectedItem' => $prod ? [
                    'id' => $prod->id,
                    'sku' => $prod->sku,
                    'nama' => $prod->nama,
                    'satuan' => $prod->satuan,
                    'tipe' => $prod->tipe,
                ] : null,
            ];
        })->values()->toArray();
    @endphp

    <script>
        function requestEditData() {
            return {
                jenis: @js(old('jenis', $stockRequest->jenis)),
                batchId: @js(old('batch_id', $stockRequest->referensi_batch_id ?? '')),
                gudangPemohonId: @js((string) old('gudang_pemohon_id', $stockRequest->gudang_pemohon_id)),
                gudangTujuanId: @js((string) old('gudang_tujuan_id', $stockRequest->gudang_tujuan_id)),
                items: @js($initialItems),
                activeRow: null,
                addItem() {
                    this.items.push({
                        produk_id: '',
                        qty_diminta: 1,
                        keterangan: '',
                        satuan: '-',
                        selectedItem: null
                    });
                },
                removeItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                        if (this.activeRow === index) {
                            this.activeRow = null;
                        }
                    }
                },
                isProductSelectedElsewhere(productId, rowIndex) {
                    if (!productId) return false;
                    const pStr = String(productId);
                    return this.items.some((it, idx) => idx !== rowIndex && String(it.produk_id) === pStr);
                },
                alertDuplicate(product) {
                    const nama = product && product.nama ? product.nama : 'Produk ini';
                    if (window.Swal) {
                        window.Swal.fire({
                            icon: 'warning',
                            title: 'Produk Sudah Dipilih',
                            text: `Produk "${nama}" sudah ada di baris lain. Setiap produk hanya boleh dipilih 1 kali.`,
                            confirmButtonColor: '#0284c7',
                            confirmButtonText: 'Mengerti'
                        });
                    } else {
                        alert(`Produk "${nama}" sudah ada di baris lain. Setiap produk hanya boleh dipilih 1 kali.`);
                    }
                },
                onProductSelected(item, product, index) {
                    if (product) {
                        if (this.isProductSelectedElsewhere(product.id, index)) {
                            this.alertDuplicate(product);
                            item.produk_id = '';
                            item.satuan = '-';
                            item.selectedItem = null;
                            return;
                        }
                        item.produk_id = product.id;
                        item.satuan = product.satuan || '-';
                        item.selectedItem = product;
                    } else {
                        item.produk_id = '';
                        item.satuan = '-';
                        item.selectedItem = null;
                    }
                }
            };
        }
    </script>

    <div x-data="requestEditData()" class="space-y-4">
        <x-page-header
            title="Edit Permintaan Barang"
            :subtitle="$stockRequest->no_request"
            :breadcrumbs="['Permintaan' => route('requests.index'), $stockRequest->no_request => route('requests.show', $stockRequest), 'Edit' => null]"
            :backUrl="route('requests.show', $stockRequest)"
        />

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
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-muted text-muted-foreground border border-border">
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

                @if($stockRequest->jenis === 'request_bahan')
                <div class="mt-4 p-3.5 bg-amber-50/70 border border-amber-200 rounded-xl space-y-2">
                    <label class="block text-xs font-semibold text-amber-900">
                        Batch Produksi Terkait (Opsional):
                    </label>
                    <select name="batch_id" x-model="batchId" class="w-full h-9 rounded-lg border border-border bg-card px-3 text-xs focus:ring-2 focus:ring-primary/20">
                        <option value="">-- Tanpa Batch (Permintaan Bebas) --</option>
                        @foreach($activeBatches as $ab)
                            <option value="{{ $ab->id }}" @selected(old('batch_id', $stockRequest->referensi_batch_id) == $ab->id)>
                                {{ $ab->no_batch }} (Status: {{ ucfirst($ab->status) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="mt-5">
                    <label class="block text-xs font-semibold text-foreground mb-1.5">Catatan / Keperluan</label>
                    <textarea name="catatan" rows="2" class="w-full text-xs rounded-lg border border-border bg-card p-2.5 focus:ring-2 focus:ring-primary/20">{{ old('catatan', $stockRequest->catatan) }}</textarea>
                </div>
            </x-card>

            <x-card class="relative z-10" overflow="visible">
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

                <div class="overflow-visible min-h-[240px]">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-muted/70 border-b border-border text-muted-foreground font-medium">
                                <th class="px-4 py-2.5 text-left w-12">No</th>
                                <th class="px-4 py-2.5 text-left w-[42%]">Produk</th>
                                <th class="px-4 py-2.5 text-left w-24">Satuan</th>
                                <th class="px-4 py-2.5 text-left w-36">Qty Diminta</th>
                                <th class="px-4 py-2.5 text-left">Keterangan</th>
                                <th class="px-4 py-2.5 text-center w-16">Hapus</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template x-for="(item, index) in items" :key="index">
                                <tr
                                    class="hover:bg-muted/30 transition"
                                    :style="'position: relative; z-index: ' + (activeRow === index ? 200 : (100 - index))"
                                    @hs-dropdown-opened.window="if ($event.detail === `items[${index}][produk_id]`) activeRow = index"
                                    @hs-dropdown-closed.window="if ($event.detail === `items[${index}][produk_id]` && activeRow === index) activeRow = null"
                                    @product-selected="onProductSelected(item, $event.detail, index)"
                                >
                                    <td class="px-4 py-3 font-medium text-muted-foreground text-center" x-text="index + 1"></td>
                                    <td class="px-4 py-3" :style="activeRow === index ? 'position: relative; z-index: 200;' : ''">
                                        <div
                                            x-data="productSearchSelect({
                                                name: `items[${index}][produk_id]`,
                                                selectedId: item.produk_id,
                                                selectedItem: item.selectedItem,
                                                filterTipe: () => (jenis === 'request_bahan' ? ['bahan', 'kemas', 'bahan_baku', 'kemasan'] : ['produk_jadi']),
                                                placeholder: jenis === 'request_bahan' ? '— Cari Bahan / Kemasan —' : '— Cari Produk Jadi —',
                                                url: '{{ route('produk.select-data', [], false) }}'
                                            })"
                                            x-init="init()"
                                            class="relative w-full"
                                            :class="open ? 'z-[9999]' : 'z-10'"
                                            style="position: relative;"
                                            @click.outside="open = false; if (activeRow === index) activeRow = null"
                                            @keydown.escape.window="open = false; if (activeRow === index) activeRow = null"
                                            @hs-dropdown-opened.window="if ($event.detail && $event.detail !== `items[${index}][produk_id]`) open = false"
                                        >
                                            <input type="hidden" :name="`items[${index}][produk_id]`" :value="selectedId" required>

                                            <div
                                                @click="activeRow = index; toggle()"
                                                role="button"
                                                tabindex="0"
                                                @keydown.enter.prevent="activeRow = index; toggle()"
                                                @keydown.space.prevent="activeRow = index; toggle()"
                                                class="w-full flex items-center justify-between text-left rounded-lg border border-input bg-card px-3 py-2 text-xs shadow-xs hover:border-foreground/30 focus:outline-none focus:ring-1 focus:ring-ring transition cursor-pointer select-none"
                                            >
                                                <div class="flex items-center gap-1.5 truncate flex-1 min-w-0 pointer-events-none">
                                                    <template x-if="selectedItem">
                                                        <div class="flex items-center gap-1.5 truncate">
                                                            <span class="font-mono text-foreground font-semibold bg-muted px-1.5 py-0.5 rounded-md border border-border text-[10px]" x-text="selectedItem.sku"></span>
                                                            <span class="font-medium text-foreground truncate" x-text="selectedItem.nama"></span>
                                                            <span class="text-[9px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="selectedItem.satuan"></span>
                                                        </div>
                                                    </template>
                                                    <template x-if="!selectedItem">
                                                        <span class="text-muted-foreground font-normal" x-text="placeholder"></span>
                                                    </template>
                                                </div>
                                                <div class="flex items-center gap-1 ml-1 shrink-0 pointer-events-none">
                                                    <svg class="w-3.5 h-3.5 text-muted-foreground transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                                </div>
                                            </div>

                                            <div
                                                x-show="open"
                                                x-cloak
                                                style="display: none; position: absolute; left: 0; top: 100%; z-index: 9999; width: 100%; min-width: 320px; box-sizing: border-box;"
                                                class="absolute left-0 top-full z-[9999] mt-1.5 w-full min-w-[320px] bg-card rounded-xl border border-border shadow-xl text-xs overflow-hidden"
                                            >
                                                <div class="p-2.5 border-b border-border bg-muted/50 flex items-center gap-2">
                                                    <svg class="w-3.5 h-3.5 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                    <input type="text" x-model="search" @input="onSearch($event)" @keydown.enter.prevent="fetchItems(1, false)" x-ref="searchInput" placeholder="Cari nama / SKU produk..." class="w-full bg-background border border-input rounded-lg px-2.5 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring" />
                                                </div>

                                                <div class="max-h-48 overflow-y-auto divide-y divide-border">
                                                    <template x-for="p in items" :key="p.id">
                                                        <div
                                                            @click="isProductSelectedElsewhere(p.id, index) ? alertDuplicate(p) : select(p)"
                                                            :class="isProductSelectedElsewhere(p.id, index) ? 'bg-muted/30 text-muted-foreground/60 cursor-not-allowed opacity-60' : (selectedId == p.id ? 'bg-muted text-foreground font-semibold' : 'text-foreground hover:bg-muted/50 cursor-pointer')"
                                                            class="px-3.5 py-2.5 transition flex items-center justify-between gap-2 select-none"
                                                        >
                                                            <div class="flex items-center gap-2 truncate pointer-events-none">
                                                                <span class="font-mono text-foreground font-medium bg-muted px-1.5 py-0.5 rounded-md border border-border text-[10px]" x-text="p.sku"></span>
                                                                <span class="truncate font-medium" :class="isProductSelectedElsewhere(p.id, index) ? 'text-muted-foreground' : 'text-foreground'" x-text="p.nama"></span>
                                                                <template x-if="isProductSelectedElsewhere(p.id, index)">
                                                                    <span class="text-[9px] font-semibold text-rose-600 dark:text-rose-400 bg-rose-500/10 px-1.5 py-0.5 rounded border border-rose-500/20">Sudah Dipilih</span>
                                                                </template>
                                                            </div>
                                                            <div class="flex items-center gap-1.5 shrink-0 pointer-events-none">
                                                                <span class="text-[9px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="p.satuan"></span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <div x-show="loading" class="py-3 text-center text-muted-foreground">Memuat produk...</div>
                                                    <div x-show="!loading && items.length === 0" class="py-3 text-center text-muted-foreground">Tidak ada produk cocok.</div>
                                                </div>

                                                <template x-if="hasMore && !loading">
                                                    <div class="p-2 border-t border-border bg-muted/30 text-center">
                                                        <button type="button" @click.stop="loadMore()" class="text-xs text-primary font-medium hover:underline">
                                                            Muat Lebih Banyak
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-block px-2.5 py-1 bg-muted rounded font-medium text-foreground" x-text="item.satuan"></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" step="0.001" min="0.001" :name="`items[${index}][qty_diminta]`" x-model="item.qty_diminta" class="w-full h-9 rounded-lg border border-border bg-card px-2.5 text-xs text-right font-medium focus:ring-2 focus:ring-primary/20" required>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="text" :name="`items[${index}][keterangan]`" x-model="item.keterangan" placeholder="Keterangan opsional" class="w-full h-9 rounded-lg border border-border bg-card px-2.5 text-xs focus:ring-2 focus:ring-primary/20">
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" @click="removeItem(index)" :disabled="items.length <= 1" class="text-destructive hover:opacity-80 disabled:opacity-30 transition p-1" title="Hapus Baris">
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

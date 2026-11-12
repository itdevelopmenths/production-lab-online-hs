<x-app-layout title="Buat Transfer Barang">
    @php
        $productsById = $products->keyBy('id');
        $prefilledByReqItemId = collect($prefilledItems)->filter(fn ($p) => !empty($p['request_item_id']))->keyBy('request_item_id');
        $prefilledByProdukId = collect($prefilledItems)->keyBy('produk_id');

        $buildSelectedItem = function ($prodId, $prod, $prefill = null) {
            if ($prod) {
                return [
                    'id' => $prod->id,
                    'sku' => $prod->sku,
                    'nama' => $prod->nama,
                    'satuan' => $prod->satuan,
                    'tipe' => $prod->tipe,
                ];
            }
            if (!empty($prodId) && is_array($prefill)) {
                return [
                    'id' => (int) $prodId,
                    'sku' => $prefill['sku'] ?? '',
                    'nama' => $prefill['nama'] ?? '',
                    'satuan' => $prefill['satuan'] ?? '-',
                    'tipe' => $prefill['tipe'] ?? null,
                ];
            }
            return null;
        };

        $rawItems = old('items');
        if (is_array($rawItems) && !empty($rawItems)) {
            $initialItems = collect($rawItems)->values()->map(function ($it, $idx) use ($productsById, $prefilledByReqItemId, $prefilledByProdukId, $buildSelectedItem) {
                $reqItemId = $it['request_item_id'] ?? null;
                $prodId = $it['produk_id'] ?? '';
                $prefill = ($reqItemId && $prefilledByReqItemId->has($reqItemId))
                    ? $prefilledByReqItemId->get($reqItemId)
                    : (!empty($prodId) ? $prefilledByProdukId->get($prodId) : null);
                $prod = !empty($prodId) ? $productsById->get($prodId) : null;

                return [
                    '_uid' => $idx + 1,
                    'request_item_id' => $reqItemId ?? ($prefill['request_item_id'] ?? null),
                    'produk_id' => $prodId,
                    'sku' => $prefill['sku'] ?? ($prod?->sku ?? ''),
                    'nama' => $prefill['nama'] ?? ($prod?->nama ?? ''),
                    'satuan' => $prefill['satuan'] ?? ($prod?->satuan ?? '-'),
                    'qty_diminta' => $prefill['qty_diminta'] ?? null,
                    'stok_tersedia' => $prefill['stok_tersedia'] ?? null,
                    'stok_loading' => false,
                    'stok_error' => false,
                    'qty_dikirim' => $it['qty_dikirim'] ?? ($prefill['qty_dikirim'] ?? 1),
                    'keterangan' => $it['keterangan'] ?? ($prefill['keterangan'] ?? ''),
                    'selectedItem' => $buildSelectedItem($prodId, $prod, $prefill),
                ];
            })->values()->toArray();
        } elseif (!empty($prefilledItems)) {
            $initialItems = collect($prefilledItems)->values()->map(function ($prefill, $idx) use ($productsById, $buildSelectedItem) {
                $prodId = $prefill['produk_id'] ?? '';
                $prod = !empty($prodId) ? $productsById->get($prodId) : null;

                return [
                    '_uid' => $idx + 1,
                    'request_item_id' => $prefill['request_item_id'] ?? null,
                    'produk_id' => $prodId,
                    'sku' => $prefill['sku'] ?? ($prod?->sku ?? ''),
                    'nama' => $prefill['nama'] ?? ($prod?->nama ?? ''),
                    'satuan' => $prefill['satuan'] ?? ($prod?->satuan ?? '-'),
                    'qty_diminta' => $prefill['qty_diminta'] ?? null,
                    'stok_tersedia' => $prefill['stok_tersedia'] ?? null,
                    'stok_loading' => false,
                    'stok_error' => false,
                    'qty_dikirim' => $prefill['qty_dikirim'] ?? 1,
                    'keterangan' => $prefill['keterangan'] ?? '',
                    'selectedItem' => $buildSelectedItem($prodId, $prod, $prefill),
                ];
            })->values()->toArray();
        } else {
            $initialItems = [
                [
                    '_uid' => 1,
                    'request_item_id' => null,
                    'produk_id' => '',
                    'sku' => '',
                    'nama' => '',
                    'satuan' => '-',
                    'qty_diminta' => null,
                    'stok_tersedia' => null,
                    'stok_loading' => false,
                    'stok_error' => false,
                    'qty_dikirim' => 1,
                    'keterangan' => '',
                    'selectedItem' => null,
                ],
            ];
        }
    @endphp

    <script>
        function transferCreateData() {
            return {
                requestId: @js($stockRequest?->id ?? ''),
                gudangAsalId: @js((string) old('gudang_asal_id', $defaultGudangAsalId ?? '')),
                gudangTujuanId: @js((string) old('gudang_tujuan_id', $defaultGudangTujuanId ?? '')),
                jenis: @js(old('jenis', $defaultJenis ?? 'antar_fulfillment')),
                canTransferBahan: @js($canTransferBahan ?? true),
                canTransferProdukJadi: @js($canTransferProdukJadi ?? true),
                nextUid: {{ count($initialItems) + 1 }},
                items: @js($initialItems),
                products: @js($products),
                activeRow: null,
                submitAction: 'save_draft',
                submittedAttempt: false,
                submitting: false,
                validationMessage: '',
                init() {
                    this.$watch('gudangAsalId', () => {
                        this.validationMessage = '';
                        this.refreshAllStock();
                    });
                    this.$watch('gudangTujuanId', () => {
                        this.validationMessage = '';
                    });
                    if (this.gudangAsalId) {
                        this.items.forEach(item => {
                            if (item.produk_id && (item.stok_tersedia === null || item.stok_tersedia === undefined)) {
                                this.updateStock(item);
                            }
                        });
                    }
                },
                getFilterTipe() {
                    const bahanTipes = ['bahan', 'kemas', 'bahan_baku', 'kemasan'];
                    const pjTipes = ['produk_jadi'];
                    if (this.jenis === 'pemenuhan_request_bahan' || this.jenis === 'retur_bahan') {
                        return bahanTipes;
                    }
                    if (this.jenis === 'kirim_produk_jadi' || this.jenis === 'retur_produk_jadi') {
                        return pjTipes;
                    }
                    if (this.canTransferBahan && this.canTransferProdukJadi) {
                        return null;
                    }
                    if (this.canTransferBahan) {
                        return bahanTipes;
                    }
                    if (this.canTransferProdukJadi) {
                        return pjTipes;
                    }
                    return [];
                },
                onJenisChange() {
                    this.validationMessage = '';
                    const allowedTipes = this.getFilterTipe();
                    this.items.forEach(item => {
                        const hasSelectedMismatch = item.selectedItem && item.selectedItem.tipe && Array.isArray(allowedTipes) && !allowedTipes.includes(item.selectedItem.tipe);
                        if (hasSelectedMismatch) {
                            item.produk_id = '';
                            item.sku = '';
                            item.nama = '';
                            item.satuan = '-';
                            item.stok_tersedia = null;
                            item.stok_error = false;
                            item.selectedItem = null;
                            if (this.activeRow === item._uid) {
                                this.activeRow = null;
                            }
                            item._uid = ++this.nextUid;
                        } else if (!item.produk_id) {
                            item._uid = ++this.nextUid;
                        }
                    });
                },
                makeEmptyRow() {
                    return {
                        _uid: ++this.nextUid,
                        request_item_id: null,
                        produk_id: '',
                        sku: '',
                        nama: '',
                        satuan: '-',
                        qty_diminta: null,
                        stok_tersedia: null,
                        stok_loading: false,
                        stok_error: false,
                        qty_dikirim: 1,
                        keterangan: '',
                        selectedItem: null,
                    };
                },
                addItem() {
                    this.items.push(this.makeEmptyRow());
                    this.validationMessage = '';
                },
                removeItem(index) {
                    if (this.items.length > 1) {
                        const removed = this.items[index];
                        this.items.splice(index, 1);
                        if (removed && this.activeRow === removed._uid) {
                            this.activeRow = null;
                        }
                        this.validationMessage = '';
                    }
                },
                getSatuan(prodId) {
                    const p = this.products.find(x => x.id == prodId);
                    return p ? (p.satuan || '-') : '-';
                },
                displaySatuan(item) {
                    if (item.satuan && item.satuan !== '-') {
                        return item.satuan;
                    }
                    return this.getSatuan(item.produk_id);
                },
                formatQty(val) {
                    const num = Number(val);
                    if (!Number.isFinite(num)) return '-';
                    return num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
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
                    this.validationMessage = '';
                    if (product) {
                        if (this.isProductSelectedElsewhere(product.id, index)) {
                            this.alertDuplicate(product);
                            item.produk_id = '';
                            item.sku = '';
                            item.nama = '';
                            item.satuan = '-';
                            item.stok_tersedia = null;
                            item.stok_error = false;
                            item.selectedItem = null;
                            return;
                        }
                        item.produk_id = product.id;
                        item.sku = product.sku || '';
                        item.nama = product.nama || '';
                        item.satuan = product.satuan || '-';
                        item.selectedItem = product;
                        this.updateStock(item);
                    } else {
                        item.produk_id = '';
                        item.sku = '';
                        item.nama = '';
                        item.satuan = '-';
                        item.stok_tersedia = null;
                        item.stok_error = false;
                        item.selectedItem = null;
                    }
                },
                async updateStock(item) {
                    if (!item.produk_id || !this.gudangAsalId) {
                        item.stok_tersedia = null;
                        item.stok_loading = false;
                        item.stok_error = false;
                        return;
                    }
                    item.stok_loading = true;
                    item.stok_error = false;
                    const requestedProdukId = item.produk_id;
                    const requestedGudangId = this.gudangAsalId;
                    try {
                        const url = `{{ route('stok.current-stock', [], false) }}?produk_id=${encodeURIComponent(requestedProdukId)}&gudang_id=${encodeURIComponent(requestedGudangId)}`;
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (item.produk_id != requestedProdukId || this.gudangAsalId != requestedGudangId) {
                            return;
                        }
                        if (res.ok) {
                            const data = await res.json();
                            const qty = Number(data.qty ?? data.stok_tersedia ?? data.saldo ?? 0);
                            item.stok_tersedia = Number.isFinite(qty) ? qty : 0;
                        } else {
                            item.stok_tersedia = null;
                            item.stok_error = true;
                        }
                    } catch (e) {
                        if (item.produk_id == requestedProdukId && this.gudangAsalId == requestedGudangId) {
                            item.stok_tersedia = null;
                            item.stok_error = true;
                        }
                    } finally {
                        if (item.produk_id == requestedProdukId && this.gudangAsalId == requestedGudangId) {
                            item.stok_loading = false;
                        }
                    }
                },
                refreshAllStock() {
                    this.items.forEach(item => this.updateStock(item));
                },
                isExceedingStock(item) {
                    if (!this.gudangAsalId || !item.produk_id) return false;
                    if (item.stok_tersedia === null || item.stok_tersedia === undefined) return false;
                    const qty = Number(item.qty_dikirim || 0);
                    return Number.isFinite(qty) && qty > Number(item.stok_tersedia);
                },
                isSameWarehouse() {
                    return Boolean(this.gudangAsalId && this.gudangTujuanId && String(this.gudangAsalId) === String(this.gudangTujuanId));
                },
                hasInsufficientStockItems() {
                    return this.items.some(item => this.isExceedingStock(item));
                },
                onSubmit(event) {
                    if (this.submitting) {
                        event.preventDefault();
                        return;
                    }
                    const action = (event.submitter && event.submitter.value) ? event.submitter.value : this.submitAction;
                    this.submittedAttempt = true;
                    this.validationMessage = '';

                    if (this.isSameWarehouse()) {
                        event.preventDefault();
                        this.validationMessage = 'Gudang tujuan harus berbeda dengan gudang asal.';
                        this.$nextTick(() => document.getElementById('transfer-gudang-tujuan')?.focus());
                        return;
                    }

                    const missingProduk = this.items.find(it => !it.produk_id);
                    if (missingProduk) {
                        event.preventDefault();
                        this.validationMessage = 'Pilih produk pada setiap baris transfer sebelum menyimpan.';
                        this.$nextTick(() => document.querySelector(`[data-product-trigger="${missingProduk._uid}"]`)?.focus());
                        return;
                    }

                    const invalidQty = this.items.find(it => {
                        const qty = Number(it.qty_dikirim);
                        return !Number.isFinite(qty) || qty <= 0;
                    });
                    if (invalidQty) {
                        event.preventDefault();
                        this.validationMessage = 'Kuantitas kirim pada setiap baris harus lebih besar dari 0.';
                        this.$nextTick(() => document.getElementById(`transfer-qty-${invalidQty._uid}`)?.focus());
                        return;
                    }

                    if (action === 'ship') {
                        const loadingItem = this.items.find(it => it.stok_loading);
                        if (loadingItem) {
                            event.preventDefault();
                            this.validationMessage = 'Sedang memeriksa ketersediaan stok gudang asal. Silakan tunggu sebentar lalu coba lagi.';
                            return;
                        }

                        const exceedItem = this.items.find(it => this.isExceedingStock(it));
                        if (exceedItem) {
                            event.preventDefault();
                            const labelNama = exceedItem.nama || exceedItem.selectedItem?.nama || 'produk terpilih';
                            const satuan = this.displaySatuan(exceedItem);
                            this.validationMessage = `Stok gudang asal tidak mencukupi untuk "${labelNama}" (tersedia: ${this.formatQty(exceedItem.stok_tersedia)} ${satuan}, dikirim: ${this.formatQty(exceedItem.qty_dikirim)} ${satuan}). Kurangi kuantitas atau simpan sebagai draft.`;
                            this.$nextTick(() => document.getElementById(`transfer-qty-${exceedItem._uid}`)?.focus());
                            return;
                        }
                    }

                    this.submitting = true;
                }
            };
        }
    </script>

    <div x-data="transferCreateData()" class="space-y-4">
        <x-page-header
            title="Buat Transfer Barang"
            subtitle="Inisiasi pengiriman / mutasi fisik barang antar gudang"
            :breadcrumbs="['Transfer' => route('transfers.index'), 'Buat Baru' => null]"
            :backUrl="route('transfers.index')"
        />

        <form method="POST" action="{{ route('transfers.store') }}" @submit="onSubmit($event)" class="space-y-6">
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
                <div class="p-4 mb-4 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs" role="alert">
                    <div class="font-bold mb-1">Terdapat kesalahan input:</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div x-show="validationMessage" x-cloak role="alert" class="p-3.5 mb-4 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-start gap-2">
                    <svg class="size-4 shrink-0 mt-0.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span class="font-medium" x-text="validationMessage"></span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label for="transfer-jenis" class="block text-xs font-semibold text-foreground mb-1.5">Jenis Transfer <span class="text-destructive">*</span></label>
                        <select id="transfer-jenis" name="jenis" x-model="jenis" @change="onJenisChange()" class="w-full h-10 text-xs rounded-lg border border-border bg-card px-3 focus:ring-2 focus:ring-primary/20" required>
                            @foreach(\App\Models\Transfer::JENIS_LABELS as $key => $label)
                                @if(in_array($key, $allowedJenis ?? \App\Models\Transfer::JENIS, true))
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="transfer-gudang-asal" class="block text-xs font-semibold text-foreground mb-1.5">Gudang Asal (Pengirim) <span class="text-destructive">*</span></label>
                        <select id="transfer-gudang-asal" name="gudang_asal_id" x-model="gudangAsalId" class="w-full h-10 text-xs rounded-lg border border-border bg-card px-3 focus:ring-2 focus:ring-primary/20" required>
                            <option value="">-- Pilih Gudang Asal --</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->nama }} ({{ $w->kode }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="transfer-gudang-tujuan" class="block text-xs font-semibold text-foreground mb-1.5">Gudang Tujuan (Penerima) <span class="text-destructive">*</span></label>
                        <select id="transfer-gudang-tujuan" name="gudang_tujuan_id" x-model="gudangTujuanId" :aria-invalid="isSameWarehouse() ? 'true' : 'false'" :class="isSameWarehouse() ? 'border-destructive focus:ring-destructive/20' : 'border-border focus:ring-primary/20'" class="w-full h-10 text-xs rounded-lg border bg-card px-3 focus:ring-2" required>
                            <option value="">-- Pilih Gudang Tujuan --</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->nama }} ({{ $w->kode }})</option>
                            @endforeach
                        </select>
                        <p x-show="isSameWarehouse()" x-cloak class="text-[11px] font-medium text-destructive mt-1">Gudang tujuan tidak boleh sama dengan gudang asal.</p>
                    </div>
                </div>

                <div class="mt-5">
                    <label for="transfer-catatan" class="block text-xs font-semibold text-foreground mb-1.5">Catatan Pengiriman</label>
                    <textarea id="transfer-catatan" name="catatan" rows="2" class="w-full text-xs rounded-lg border border-border p-2.5 focus:ring-2 focus:ring-primary/20" placeholder="Catatan ekspedisi, armada angkut, atau instruksi serah terima...">{{ old('catatan') }}</textarea>
                </div>
            </x-card>

            <x-card class="relative z-10" overflow="visible">
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground tracking-tight">Rincian Barang Dikirim</h2>
                            <p class="text-xs text-muted-foreground mt-0.5">Pastikan kuantitas pengiriman tidak melebihi stok yang tersedia di gudang asal</p>
                        </div>
                    </div>
                </x-slot:header>

                <div class="overflow-visible min-h-[240px]">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-muted/70 border-b border-border text-muted-foreground font-medium">
                                <th class="px-4 py-2.5 text-left w-12">No</th>
                                <th class="px-4 py-2.5 text-left w-[36%]">Produk</th>
                                <th class="px-4 py-2.5 text-left w-24">Satuan</th>
                                @if($stockRequest)
                                <th class="px-4 py-2.5 text-right w-28">Qty Diminta</th>
                                @endif
                                <th class="px-4 py-2.5 text-right w-36">Stok Tersedia</th>
                                <th class="px-4 py-2.5 text-right w-44">Qty Dikirim</th>
                                <th class="px-4 py-2.5 text-left">Keterangan</th>
                                @if(!$stockRequest)
                                <th class="px-4 py-2.5 text-center w-16">Hapus</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template x-for="(item, index) in items" :key="item._uid">
                                <tr
                                    class="hover:bg-muted/30 transition"
                                    :style="'position: relative; z-index: ' + (activeRow === item._uid ? 200 : (100 - index))"
                                    @hs-dropdown-opened.window="if ($event.detail === `items[${item._uid}][produk_id]`) activeRow = item._uid"
                                    @hs-dropdown-closed.window="if ($event.detail === `items[${item._uid}][produk_id]` && activeRow === item._uid) activeRow = null"
                                    @product-selected="onProductSelected(item, $event.detail, index)"
                                >
                                    <td class="px-4 py-3 font-medium text-muted-foreground text-center align-top pt-4" x-text="index + 1"></td>
                                    <td class="px-4 py-3 align-top" :style="activeRow === item._uid ? 'position: relative; z-index: 200;' : ''">
                                        @if($stockRequest)
                                            <input type="hidden" :name="`items[${index}][request_item_id]`" :value="item.request_item_id">
                                            <input type="hidden" :name="`items[${index}][produk_id]`" :value="item.produk_id">
                                            <div class="font-semibold text-foreground" x-text="item.nama"></div>
                                            <div class="font-mono text-[10px] text-muted-foreground" x-text="item.sku"></div>
                                        @else
                                            <template x-if="item.request_item_id">
                                                <input type="hidden" :name="`items[${index}][request_item_id]`" :value="item.request_item_id">
                                            </template>
                                            <div
                                                x-data="productSearchSelect({
                                                    name: `items[${item._uid}][produk_id]`,
                                                    selectedId: item.produk_id,
                                                    selectedItem: item.selectedItem,
                                                    filterTipe: () => getFilterTipe(),
                                                    placeholder: canTransferBahan && canTransferProdukJadi ? '— Cari & Pilih Produk / Bahan —' : (canTransferBahan ? '— Cari & Pilih Bahan Baku —' : '— Cari & Pilih Produk Jadi —'),
                                                    url: '{{ route('produk.select-data', [], false) }}'
                                                })"
                                                x-init="init()"
                                                class="relative w-full"
                                                :class="open ? 'z-[9999]' : 'z-10'"
                                                style="position: relative;"
                                                @click.outside="open = false; if (activeRow === item._uid) activeRow = null"
                                                @keydown.escape.window="open = false; if (activeRow === item._uid) activeRow = null"
                                                @hs-dropdown-opened.window="if ($event.detail && $event.detail !== `items[${item._uid}][produk_id]`) open = false"
                                            >
                                                <input type="hidden" :name="`items[${index}][produk_id]`" :value="selectedId">

                                                <div
                                                    @click="activeRow = item._uid; toggle()"
                                                    :data-product-trigger="item._uid"
                                                    role="button"
                                                    tabindex="0"
                                                    :aria-expanded="open ? 'true' : 'false'"
                                                    :aria-invalid="submittedAttempt && !item.produk_id ? 'true' : 'false'"
                                                    @keydown.enter.prevent="activeRow = item._uid; toggle()"
                                                    @keydown.space.prevent="activeRow = item._uid; toggle()"
                                                    :class="submittedAttempt && !item.produk_id ? 'border-destructive ring-1 ring-destructive/30' : 'border-input hover:border-foreground/30'"
                                                    class="w-full flex items-center justify-between text-left rounded-lg border bg-card px-3 py-2 text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring transition cursor-pointer select-none"
                                                >
                                                    <div class="flex items-center gap-1.5 truncate flex-1 min-w-0 pointer-events-none">
                                                        <template x-if="selectedItem">
                                                            <div class="flex items-center gap-1.5 truncate">
                                                                <span class="font-mono text-foreground font-semibold bg-muted px-1.5 py-0.5 rounded-md border border-border text-[10px]" x-text="selectedItem.sku"></span>
                                                                <span class="font-medium text-foreground truncate" x-text="selectedItem.nama"></span>
                                                                <span class="text-[9px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="selectedItem.satuan || '-'"></span>
                                                            </div>
                                                        </template>
                                                        <template x-if="!selectedItem">
                                                            <span class="text-muted-foreground font-normal" x-text="placeholder"></span>
                                                        </template>
                                                    </div>
                                                    <div class="flex items-center gap-1 ml-1 shrink-0">
                                                        <button
                                                            type="button"
                                                            x-show="selectedItem"
                                                            x-cloak
                                                            @click.stop="clear()"
                                                            class="p-0.5 text-muted-foreground hover:text-destructive rounded transition cursor-pointer"
                                                            aria-label="Hapus pilihan produk"
                                                            title="Hapus pilihan"
                                                        >
                                                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                        <svg class="w-3.5 h-3.5 text-muted-foreground transition-transform duration-150 pointer-events-none" :class="open ? 'rotate-180' : ''" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                                    </div>
                                                </div>

                                                <div
                                                    x-show="open"
                                                    x-cloak
                                                    style="display: none; position: absolute; left: 0; top: 100%; z-index: 9999; width: 100%; box-sizing: border-box;"
                                                    class="absolute left-0 top-full z-[9999] mt-1.5 w-full min-w-64 sm:min-w-80 max-w-[calc(100vw-2rem)] bg-card rounded-xl border border-border shadow-xl text-xs overflow-hidden"
                                                >
                                                    <div class="p-2.5 border-b border-border bg-muted/50 flex items-center gap-2">
                                                        <svg class="w-3.5 h-3.5 text-muted-foreground shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                        <input
                                                            type="text"
                                                            x-model="search"
                                                            @input="onSearch($event)"
                                                            @keydown.enter.prevent="fetchItems(1, false)"
                                                            x-ref="searchInput"
                                                            autocorrect="off"
                                                            autocapitalize="none"
                                                            spellcheck="false"
                                                            placeholder="Cari nama / SKU produk..."
                                                            class="w-full bg-background border border-input rounded-lg px-2.5 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                                                        />
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
                                                                    <span class="text-[9px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="p.satuan || '-'"></span>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        <div x-show="loading" class="py-3 text-center text-muted-foreground">Memuat produk...</div>
                                                        <div x-show="!loading && items.length === 0" class="py-3 text-center text-muted-foreground">Tidak ada produk cocok.</div>
                                                    </div>

                                                    <template x-if="hasMore && !loading">
                                                        <div class="p-2 border-t border-border bg-muted/30 text-center">
                                                            <button type="button" @click.stop="loadMore()" class="text-xs text-primary font-medium hover:underline cursor-pointer">
                                                                Muat Lebih Banyak
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                            <p x-show="submittedAttempt && !item.produk_id" x-cloak class="text-[11px] font-medium text-destructive mt-1">Produk wajib dipilih.</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 align-top pt-3.5">
                                        <span class="inline-block px-2.5 py-1 bg-muted rounded font-mono font-semibold text-foreground border border-border/60" x-text="displaySatuan(item)"></span>
                                    </td>
                                    @if($stockRequest)
                                    <td class="px-4 py-3 text-right font-medium text-muted-foreground align-top pt-4">
                                        <span x-text="formatQty(item.qty_diminta) + ' ' + displaySatuan(item)"></span>
                                    </td>
                                    @endif
                                    <td class="px-4 py-3 text-right align-top pt-3.5">
                                        <template x-if="!gudangAsalId && item.produk_id">
                                            <span class="text-[11px] text-amber-600 italic">Pilih gudang asal</span>
                                        </template>
                                        <template x-if="gudangAsalId && item.produk_id && item.stok_loading">
                                            <span class="text-[11px] text-muted-foreground">Memuat stok...</span>
                                        </template>
                                        <template x-if="gudangAsalId && item.produk_id && !item.stok_loading && item.stok_error">
                                            <span class="text-[11px] text-amber-600 font-medium">Gagal cek stok</span>
                                        </template>
                                        <template x-if="gudangAsalId && item.produk_id && !item.stok_loading && !item.stok_error && item.stok_tersedia !== null && item.stok_tersedia !== undefined">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded-full font-mono text-[11px] font-semibold border whitespace-nowrap"
                                                :class="(isExceedingStock(item) || Number(item.stok_tersedia) <= 0) ? 'text-destructive bg-destructive/10 border-destructive/20' : 'text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/20'"
                                                x-text="formatQty(item.stok_tersedia) + ' ' + displaySatuan(item)"
                                            ></span>
                                        </template>
                                        <template x-if="!item.produk_id">
                                            <span class="text-muted-foreground">-</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <div class="relative flex items-center">
                                            <input
                                                :id="`transfer-qty-${item._uid}`"
                                                type="number"
                                                step="0.001"
                                                min="0.001"
                                                :name="`items[${index}][qty_dikirim]`"
                                                x-model="item.qty_dikirim"
                                                :aria-invalid="isExceedingStock(item) ? 'true' : 'false'"
                                                :class="isExceedingStock(item) ? 'border-destructive focus:ring-destructive/20' : 'border-border focus:ring-primary/20'"
                                                class="w-full h-9 rounded-lg border bg-card pl-2.5 pr-16 text-xs text-right font-mono font-medium focus:ring-2"
                                                required
                                            >
                                            <span class="absolute right-2 px-1.5 py-0.5 text-[10px] font-mono font-semibold text-muted-foreground bg-muted border border-border rounded pointer-events-none max-w-[54px] truncate"
                                                  x-text="displaySatuan(item) !== '-' ? displaySatuan(item) : 'Unit'"></span>
                                        </div>
                                        <template x-if="isExceedingStock(item)">
                                            <p class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold text-destructive">
                                                <svg class="size-3 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                <span>Melebihi stok tersedia!</span>
                                            </p>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <input type="text" :name="`items[${index}][keterangan]`" x-model="item.keterangan" placeholder="Keterangan opsional" class="w-full h-9 rounded-lg border border-border bg-card px-2.5 text-xs">
                                    </td>
                                    @if(!$stockRequest)
                                    <td class="px-4 py-3 text-center align-top pt-3.5">
                                        <button type="button" @click="removeItem(index)" :disabled="items.length <= 1" class="text-destructive hover:opacity-80 disabled:opacity-30 transition p-1 cursor-pointer disabled:cursor-not-allowed" title="Hapus baris" aria-label="Hapus baris">
                                            <svg class="size-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                    @endif
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 bg-muted/20 border-t border-border flex items-center justify-between gap-3">
                    <div>
                        @if(!$stockRequest)
                        <button type="button" @click="addItem()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition cursor-pointer">
                            <svg class="size-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Tambah Baris
                        </button>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit" name="action" value="save_draft" @click="submitAction = 'save_draft'" :disabled="submitting" class="px-4 py-2 text-xs font-semibold rounded-lg border border-border bg-card hover:bg-muted text-foreground transition cursor-pointer disabled:opacity-50">
                            Simpan Sebagai Draft
                        </button>
                        <button type="submit" name="action" value="ship" @click="submitAction = 'ship'" :disabled="submitting" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary hover:bg-primary/90 text-primary-foreground transition shadow-xs cursor-pointer disabled:opacity-50">
                            Kirim Sekarang (In Transit - Potong Stok)
                        </button>
                    </div>
                </div>
            </x-card>
        </form>
    </div>
</x-app-layout>

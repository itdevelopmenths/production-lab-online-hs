<x-app-layout title="Edit Purchase Order {{ $purchaseOrder->no_po }}">
    @php
        $oldItems = old('items');
        $oldTermins = old('termins');

        $hppMap = $hppMap ?? [];
        $rows = [];
        if (is_array($oldItems)) {
            foreach ($oldItems as $item) {
                $p = \App\Models\Produk::find($item['produk_id'] ?? null);
                $hppLama = $p ? (float) ($hppMap[$p->id] ?? $p->harga_hpp ?? 0) : 0;
                $qtySatuanBeli = $item['qty_satuan_beli'] ?? $item['qty'] ?? '';
                $satuanBeli = $item['satuan_beli'] ?? ($p?->satuan ?? 'UNIT');
                $faktorKonversi = (float) ($item['faktor_konversi'] ?? 1);
                $baseQty = (float) ($item['qty'] ?? (($qtySatuanBeli !== '' ? (float)$qtySatuanBeli : 0) * $faktorKonversi));
                $rows[] = [
                    'produk_id' => $item['produk_id'] ?? '',
                    'qty_satuan_beli' => $qtySatuanBeli,
                    'satuan_beli' => $satuanBeli,
                    'faktor_konversi' => $faktorKonversi,
                    'qty' => $baseQty,
                    'harga' => $item['harga_total'] ?? '',
                    'satuan' => $p?->satuan ?? 'UNIT',
                    'harga_hpp_lama' => $hppLama,
                    'selectedItem' => $p ? [
                        'id' => $p->id,
                        'sku' => $p->sku,
                        'nama' => $p->nama,
                        'satuan' => $p->satuan,
                        'harga_hpp' => $hppLama,
                    ] : null,
                ];
            }
        } else {
            $rows = $purchaseOrder->items->map(function($item) use ($hppMap) {
                $hppLama = (float) ($hppMap[$item->produk_id] ?? $item->produk?->harga_hpp ?? 0);
                return [
                    'produk_id' => $item->produk_id,
                    'qty_satuan_beli' => (float) ($item->qty_satuan_beli ?? $item->qty),
                    'satuan_beli' => $item->satuan_beli ?? ($item->produk?->satuan ?? 'UNIT'),
                    'faktor_konversi' => (float) ($item->faktor_konversi ?? 1),
                    'qty' => (float) $item->qty,
                    'harga' => (float) $item->harga_total,
                    'satuan' => $item->produk?->satuan ?? 'UNIT',
                    'harga_hpp_lama' => $hppLama,
                    'selectedItem' => $item->produk ? [
                        'id' => $item->produk->id,
                        'sku' => $item->produk->sku,
                        'nama' => $item->produk->nama,
                        'satuan' => $item->produk->satuan,
                        'harga_hpp' => $hppLama,
                    ] : null,
                ];
            })->values()->all();
        }

        $termins = [];
        if (is_array($oldTermins)) {
            foreach ($oldTermins as $tm) {
                $termins[] = [
                    'tanggal_tempo' => $tm['tanggal_tempo'] ?? '',
                    'nominal_tagihan' => (float) ($tm['nominal_tagihan'] ?? 0),
                    'keterangan' => $tm['keterangan'] ?? '',
                ];
            }
        } else {
            $termins = $purchaseOrder->termins->sortBy('termin_ke')->map(fn($tm) => [
                'tanggal_tempo' => $tm->tanggal_tempo ? $tm->tanggal_tempo->format('Y-m-d') : '',
                'nominal_tagihan' => (float) $tm->nominal_tagihan,
                'keterangan' => $tm->keterangan ?? ('Termin ' . $tm->termin_ke),
            ])->values()->all();
        }

        $initialData = [
            'skemaBayar' => old('skema_bayar', $purchaseOrder->skema_bayar ?? 'cash'),
            'headerDiskon' => (float) old('diskon_total', $purchaseOrder->diskon_total ?? 0),
            'headerPpn' => (float) old('ppn_nominal', $purchaseOrder->ppn_nominal ?? 0),
            'headerOngkir' => (float) old('ongkos_kirim', $purchaseOrder->ongkos_kirim ?? 0),
            'headerAdj' => (float) old('adjustment', $purchaseOrder->adjustment ?? 0),
            'rows' => $rows,
            'termins' => $termins,
        ];

        $totalDibayar = $purchaseOrder->totalDibayar();
    @endphp

    <div class="max-w-6xl mx-auto space-y-6">
        <x-page-header
            title="Edit Purchase Order {{ $purchaseOrder->no_po }}"
            subtitle="Perbarui data pengadaan bahan baku, kuantitas, harga, dan jadwal pembayaran"
            :breadcrumbs="['Purchasing' => route('purchasing.index'), $purchaseOrder->no_po => route('purchasing.show', $purchaseOrder), 'Edit PO' => null]"
        >
            <x-slot:actions>
                <x-button href="{{ route('purchasing.show', $purchaseOrder) }}" variant="outline" size="sm">
                    &larr; Kembali
                </x-button>
            </x-slot:actions>
        </x-page-header>

        @if($totalDibayar > 0)
            <div>
                <x-alert type="warning" title="Perhatian Pembayaran:">
                    PO ini telah memiliki pencatatan pembayaran sebesar <strong>Rp {{ number_format($totalDibayar, 0, ',', '.') }}</strong>. Nilai grand total baru tidak boleh lebih kecil dari nominal yang telah terbayar.
                </x-alert>
            </div>
        @endif

        <div x-data="poForm({{ Js::from($initialData) }})" x-init="init()">
            <form method="POST" action="{{ route('purchasing.update', $purchaseOrder) }}">
            @csrf
            @method('PUT')
            <div class="space-y-6">
                {{-- Card 1: Informasi Header PO & Lokasi --}}
                <x-card title="Informasi Purchase Order" subtitle="Identitas pengadaan, supplier rekanan, gudang tujuan, dan nomor invoice">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <x-form-group name="supplier_id" label="Pemasok / Supplier" :required="true" help="Daftar rekanan aktif terverifikasi">
                            <x-select name="supplier_id" placeholder="— Pilih Supplier —" :required="true">
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}" @selected(old('supplier_id', $purchaseOrder->supplier_id) == $s->id)>
                                        {{ $s->nama }} ({{ ucfirst($s->kategori) }})
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form-group>

                        <x-form-group name="gudang_id" label="Lokasi PO (Gudang Tujuan)" help="Gudang penerima saat barang datang">
                            <x-select name="gudang_id" placeholder="— Pilih Gudang Penerima —">
                                @foreach($gudang as $g)
                                    <option value="{{ $g->id }}" @selected(old('gudang_id', $purchaseOrder->gudang_id) == $g->id)>
                                        {{ $g->nama }} ({{ ucwords(str_replace('_', ' ', $g->tipe)) }})
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form-group>

                        <x-form-group name="no_invoice" label="No Invoice / Vendor Ref" help="Nomor invoice referensi dari vendor">
                            <x-input type="text" name="no_invoice" value="{{ old('no_invoice', $purchaseOrder->no_invoice) }}" placeholder="Contoh: INV/PO/202609/0001" />
                        </x-form-group>

                        <x-form-group name="tanggal" label="Tanggal PO" :required="true">
                            <x-input type="date" name="tanggal" value="{{ old('tanggal', $purchaseOrder->tanggal?->format('Y-m-d')) }}" :required="true" />
                        </x-form-group>

                        <x-form-group name="eta" label="Estimasi Kedatangan (ETA)" help="Perkiraan pesanan tiba di gudang">
                            <x-input type="date" name="eta" value="{{ old('eta', $purchaseOrder->eta?->format('Y-m-d')) }}" />
                        </x-form-group>

                        <x-form-group name="sumber_dana" label="Sumber Dana / Rekening">
                            <x-input type="text" name="sumber_dana" value="{{ old('sumber_dana', $purchaseOrder->sumber_dana) }}" placeholder="Contoh: BCA Operasional" />
                        </x-form-group>
                    </div>

                    <div class="mt-5 pt-5 border-t border-border grid grid-cols-1 md:grid-cols-3 gap-5">
                        <x-form-group name="skema_bayar" label="Skema Pembayaran" :required="true">
                            <select name="skema_bayar" x-model="skemaBayar" class="w-full rounded-lg border border-input bg-background text-xs py-2 px-3 text-foreground focus:border-ring focus:ring-1 focus:ring-ring shadow-xs">
                                <option value="cash">Tunai (Cash / Transfer Penuh)</option>
                                <option value="tempo">Tempo (Jatuh Tempo Tunggal)</option>
                                <option value="termin">Termin (Pembayaran Bertahap / Cicil)</option>
                            </select>
                        </x-form-group>

                        <div x-show="skemaBayar === 'tempo'" x-cloak>
                            <x-form-group name="tanggal_tempo" label="Jatuh Tempo Pembayaran (Deadline)" :required="true" help="Batas akhir pelunasan tagihan tempo">
                                <x-input type="date" name="tanggal_tempo" x-bind:required="skemaBayar === 'tempo'" value="{{ old('tanggal_tempo', $purchaseOrder->tanggal_tempo ? $purchaseOrder->tanggal_tempo->format('Y-m-d') : '') }}" />
                            </x-form-group>
                        </div>
                    </div>
                </x-card>

                {{-- Card 2: Rincian Bahan yang Dipesan --}}
                <x-card title="Daftar Bahan Baku" subtitle="Input kuantitas dan total harga per bahan. Diskon, PPN, ongkir, dan adjustment diatur pada formulir Global di bawah dan dialokasikan ke HPP secara realtime." :noPadding="true" class="relative z-20" overflow="visible">
                    <div class="overflow-visible min-h-[220px]">
                        <table class="w-full text-xs">
                            <thead class="bg-muted/70 border-b border-border text-muted-foreground font-semibold uppercase text-[11px] tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left w-[36%]">Bahan Baku <span class="text-destructive">*</span></th>
                                    <th class="px-3 py-3 text-left w-56">Qty Satuan Beli & Konversi <span class="text-destructive">*</span></th>
                                    <th class="px-3 py-3 text-right w-44">Total Harga <span class="text-destructive">*</span></th>
                                    <th class="px-4 py-3 text-right w-52 bg-muted/40 text-foreground">HPP / Satuan Dasar</th>
                                    <th class="px-2 py-3 text-center w-12">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border bg-card">
                                <template x-for="(row, i) in rows" :key="i">
                                    <tr
                                        class="hover:bg-muted/40 transition"
                                        :style="'position: relative; z-index: ' + (activeRow === i ? 200 : (100 - i))"
                                        @hs-dropdown-opened.window="if ($event.detail === `items[${i}][produk_id]`) activeRow = i"
                                        @hs-dropdown-closed.window="if ($event.detail === `items[${i}][produk_id]` && activeRow === i) activeRow = null"
                                        @product-selected="onProductSelected(row, $event.detail)"
                                    >
                                        {{-- Kolom 1: Produk Search Select --}}
                                        <td class="px-4 py-3" :style="activeRow === i ? 'position: relative; z-index: 200;' : ''">
                                            <div
                                                x-data="productSearchSelect({
                                                    name: `items[${i}][produk_id]`,
                                                    selectedId: row.produk_id,
                                                    selectedItem: row.selectedItem,
                                                    filterTipe: ['bahan', 'kemas'],
                                                    placeholder: '— Pilih Bahan / Kemasan —',
                                                    url: '{{ route('produk.select-data', [], false) }}'
                                                })"
                                                x-init="init()"
                                                class="relative w-full"
                                                :class="open ? 'z-[9999]' : 'z-10'"
                                                style="position: relative;"
                                                @click.outside="open = false; if (activeRow === i) activeRow = null"
                                                @keydown.escape.window="open = false; if (activeRow === i) activeRow = null"
                                                @hs-dropdown-opened.window="if ($event.detail && $event.detail !== `items[${i}][produk_id]`) open = false"
                                            >
                                                <input type="hidden" :name="`items[${i}][produk_id]`" :value="selectedId" required>

                                                <div
                                                    @click="activeRow = i; toggle()"
                                                    role="button"
                                                    tabindex="0"
                                                    @keydown.enter.prevent="activeRow = i; toggle()"
                                                    @keydown.space.prevent="activeRow = i; toggle()"
                                                    class="w-full flex items-center justify-between text-left rounded-lg border border-input bg-background px-3 py-2 text-xs shadow-xs hover:border-foreground/30 focus:outline-none focus:ring-1 focus:ring-ring transition cursor-pointer select-none"
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

                                                <template x-if="selectedItem">
                                                    <div class="mt-1 flex items-center gap-1.5 text-[10px] font-mono">
                                                        <span class="text-muted-foreground">HPP Lama:</span>
                                                        <template x-if="parseFloat(selectedItem.harga_hpp || 0) > 0">
                                                            <span class="font-semibold text-amber-700 dark:text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded-md border border-amber-500/20" x-text="'Rp ' + formatHpp(selectedItem.harga_hpp) + (selectedItem.satuan ? ' / ' + selectedItem.satuan : '')"></span>
                                                        </template>
                                                        <template x-if="!parseFloat(selectedItem.harga_hpp || 0)">
                                                            <span class="text-muted-foreground italic bg-muted px-1.5 py-0.5 rounded-md border border-border">Belum ada HPP</span>
                                                        </template>
                                                    </div>
                                                </template>

                                                <div
                                                    x-show="open"
                                                    x-cloak
                                                    style="display: none; position: absolute; left: 0; top: 100%; z-index: 9999; width: 100%; min-width: 320px; box-sizing: border-box;"
                                                    class="absolute left-0 top-full z-[9999] mt-1.5 w-full min-w-[320px] bg-card rounded-xl border border-border shadow-xl text-xs overflow-hidden"
                                                >
                                                    <div class="p-2.5 border-b border-border bg-muted/50 flex items-center gap-2">
                                                        <svg class="w-3.5 h-3.5 text-muted-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                        <input type="text" x-model="search" @input="onSearch($event)" @keydown.enter.prevent="fetchItems(1, false)" x-ref="searchInput" placeholder="Cari nama / SKU bahan..." class="w-full bg-background border border-input rounded-lg px-2.5 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring" />
                                                    </div>

                                                    <div class="max-h-48 overflow-y-auto divide-y divide-border">
                                                        <template x-for="item in items" :key="item.id">
                                                            <div @click="select(item)" :class="selectedId == item.id ? 'bg-muted text-foreground font-semibold' : 'text-foreground hover:bg-muted/50'" class="px-3.5 py-2.5 cursor-pointer transition flex items-center justify-between gap-2 select-none">
                                                                <div class="flex items-center gap-2 truncate pointer-events-none">
                                                                    <span class="font-mono text-foreground font-medium bg-muted px-1.5 py-0.5 rounded-md border border-border text-[10px]" x-text="item.sku"></span>
                                                                    <span class="truncate font-medium text-foreground" x-text="item.nama"></span>
                                                                </div>
                                                                <div class="flex items-center gap-1.5 shrink-0 pointer-events-none">
                                                                    <span class="text-[9px] font-mono text-amber-700 dark:text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded-md border border-amber-500/20" x-text="'HPP: Rp ' + formatHpp(item.harga_hpp || 0)"></span>
                                                                    <span class="text-[9px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="item.satuan"></span>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        <div x-show="!loading && items.length === 0" class="py-3 text-center text-muted-foreground">Tidak ada produk cocok.</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Kolom 2: Qty Satuan Beli & Konversi --}}
                                        <td class="px-4 py-3">
                                            <div class="space-y-1.5">
                                                <div class="flex items-center gap-1.5">
                                                    {{-- Input Qty Satuan Beli --}}
                                                    <input
                                                        type="number"
                                                        step="any"
                                                        min="0.001"
                                                        :name="`items[${i}][qty_satuan_beli]`"
                                                        x-model.number="row.qty_satuan_beli"
                                                        @input="updateRowQty(row)"
                                                        placeholder="Qty"
                                                        required
                                                        class="w-24 rounded-lg border border-input bg-background text-xs py-2 px-2.5 font-mono text-right text-foreground focus:border-ring focus:ring-1 focus:ring-ring shadow-xs"
                                                    />

                                                    {{-- Dropdown Satuan Beli --}}
                                                    <select
                                                        :name="`items[${i}][satuan_beli]`"
                                                        x-model="row.satuan_beli"
                                                        @change="onSatuanBeliChange(row)"
                                                        class="flex-1 rounded-lg border border-input bg-background text-xs py-2 px-2 text-foreground focus:border-ring focus:ring-1 focus:ring-ring shadow-xs"
                                                    >
                                                        <template x-for="opt in getUomOptions(row.satuan, row.satuan_beli, row.faktor_konversi)" :key="opt.code">
                                                            <option :value="opt.code" x-text="opt.label" :selected="opt.code === row.satuan_beli || opt.code.toLowerCase() === (row.satuan_beli || '').toLowerCase()"></option>
                                                        </template>
                                                    </select>
                                                </div>

                                                <input type="hidden" :name="`items[${i}][faktor_konversi]`" :value="row.faktor_konversi">

                                                {{-- Live Indicator Kuantitas Fisik Masuk Gudang --}}
                                                <div class="text-[10px] font-mono text-foreground bg-muted px-2 py-0.5 rounded-md border border-border flex items-center justify-between mt-1 select-none" x-show="row.qty > 0 && row.satuan_beli && row.satuan_beli.toLowerCase() !== (row.satuan || '').toLowerCase()">
                                                    <span class="text-muted-foreground">Masuk fisik:</span>
                                                    <span class="font-bold text-foreground" x-text="formatQty(row.qty) + ' ' + (row.satuan || '')"></span>
                                                </div>

                                                {{-- Hidden Base Qty Input Sent to Backend --}}
                                                <input type="hidden" :name="`items[${i}][qty]`" :value="row.qty">
                                            </div>
                                        </td>

                                        {{-- Kolom 3: Total Harga Kotor --}}
                                        <td class="px-4 py-3">
                                            <div class="relative flex items-center">
                                                <input type="hidden" :name="`items[${i}][harga_total]`" :value="row.harga">
                                                <input
                                                    type="text"
                                                    inputmode="numeric"
                                                    :value="formatThousand(row.harga)"
                                                    @input="updateRowField(row, 'harga', $event)"
                                                    @keydown="handleKeyDown($event)"
                                                    placeholder="0"
                                                    required
                                                    class="w-full rounded-lg border border-input bg-background text-xs py-2 px-2.5 font-mono text-right text-foreground focus:border-ring focus:ring-1 focus:ring-ring shadow-xs font-medium"
                                                />
                                            </div>
                                        </td>

                                        {{-- Kolom 4: Live HPP / Satuan Dasar (Hasil Alokasi Biaya Global) --}}
                                        <td class="px-4 py-3 text-right bg-muted/20">
                                            <template x-if="calculateRowHpp(row) > 0">
                                                <div>
                                                    <div class="font-mono font-bold text-xs text-foreground" x-text="'Rp ' + formatHpp(calculateRowHpp(row)) + ' / ' + (row.satuan || 'unit')"></div>
                                                    <div class="text-[10px] text-muted-foreground font-mono mt-0.5" x-text="'Net: Rp ' + formatHpp(calculateRowNet(row))"></div>
                                                    <template x-if="row.selectedItem && parseFloat(row.selectedItem.harga_hpp || 0) > 0">
                                                        <div class="text-[10px] font-mono mt-0.5 text-muted-foreground">
                                                            <span>HPP Lama: </span>
                                                            <span class="font-semibold text-foreground" x-text="'Rp ' + formatHpp(row.selectedItem.harga_hpp)"></span>
                                                        </div>
                                                    </template>
                                                    <template x-if="subtotalProduk() > 0 && parseFloat(row.harga) > 0">
                                                        <div class="text-[9px] text-muted-foreground font-mono mt-0.5" x-text="'Porsi: ' + (Math.round((parseFloat(row.harga) / subtotalProduk()) * 1000) / 10) + '%'"></div>
                                                    </template>
                                                </div>
                                            </template>
                                            <template x-if="!calculateRowHpp(row) || calculateRowHpp(row) <= 0">
                                                <span class="text-muted-foreground italic text-[11px]">—</span>
                                            </template>
                                        </td>

                                        {{-- Kolom 5: Hapus --}}
                                        <td class="px-3 py-3 text-center">
                                            <button type="button" @click="if(rows.length > 1) { rows.splice(i,1); recalcAll(); }" :disabled="rows.length <= 1"
                                                    class="p-1.5 text-muted-foreground hover:text-destructive hover:bg-destructive/10 disabled:opacity-30 disabled:cursor-not-allowed transition rounded-lg"
                                                    title="Hapus baris">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 bg-muted/30 border-t border-border flex items-center justify-between">
                        <x-button type="button" @click="addRow()" variant="outline" size="sm">
                            <x-slot:icon>
                                <svg class="w-3.5 h-3.5 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </x-slot:icon>
                            Tambah Baris Bahan
                        </x-button>

                        <div class="text-xs text-muted-foreground font-mono">
                            Total Baris: <span class="font-bold text-foreground" x-text="rows.length"></span> item
                        </div>
                    </div>
                </x-card>

                {{-- Card 3: Rekapitulasi Biaya & Grand Total --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 relative z-10">
                    <x-card title="Biaya Tambahan Global (Opsional)" subtitle="Biaya tingkat PO yang otomatis dialokasikan ke HPP bahan baku">
                        <div class="space-y-3.5">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <label class="text-xs text-foreground font-medium">Diskon Global PO</label>
                                    <span class="text-[10px] text-muted-foreground font-mono truncate" x-show="headerDiskonMode === 'persen' && headerDiskon > 0" x-text="'(= Rp ' + formatThousand(headerDiskon) + ')'"></span>
                                </div>
                                <div class="w-48">
                                    <input type="hidden" name="diskon_total" :value="headerDiskon">
                                    <div class="relative flex rounded-lg shadow-xs overflow-hidden border border-input">
                                        <button
                                            type="button"
                                            @click="toggleHeaderDiskonMode()"
                                            class="px-2.5 py-1.5 text-[10px] font-mono font-bold transition cursor-pointer select-none shrink-0"
                                            :class="headerDiskonMode === 'persen' ? 'bg-destructive/15 text-destructive' : 'bg-muted text-muted-foreground'"
                                            :title="'Klik untuk ganti mode: ' + (headerDiskonMode === 'persen' ? 'Persentase (%)' : 'Nominal (Rp)')"
                                        >
                                            <span x-text="headerDiskonMode === 'persen' ? '%' : 'Rp'"></span>
                                        </button>
                                        <template x-if="headerDiskonMode === 'persen'">
                                            <input
                                                type="number"
                                                step="any"
                                                min="0"
                                                max="100"
                                                x-model.number="headerDiskonVal"
                                                @input="recalcHeaderDiskon()"
                                                @keydown.enter.prevent
                                                placeholder="0"
                                                class="w-full text-right text-xs py-1.5 px-2 bg-background font-mono text-destructive border-0 focus:ring-0"
                                            />
                                        </template>
                                        <template x-if="headerDiskonMode !== 'persen'">
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                :value="formatThousand(headerDiskon)"
                                                @input="updateHeaderField('headerDiskon', $event)"
                                                @keydown="handleKeyDown($event)"
                                                placeholder="0"
                                                class="w-full text-right text-xs py-1.5 px-2 bg-background font-mono text-destructive border-0 focus:ring-0"
                                            />
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <label class="text-xs text-foreground font-medium">PPN Global PO</label>
                                    <span class="text-[10px] text-muted-foreground font-mono truncate" x-show="headerPpnMode === 'persen' && headerPpn > 0" x-text="'(= Rp ' + formatThousand(headerPpn) + ')'"></span>
                                </div>
                                <div class="w-48">
                                    <input type="hidden" name="ppn_nominal" :value="headerPpn">
                                    <div class="relative flex rounded-lg shadow-xs overflow-hidden border border-input">
                                        <button
                                            type="button"
                                            @click="toggleHeaderPpnMode()"
                                            class="px-2.5 py-1.5 text-[10px] font-mono font-bold transition cursor-pointer select-none shrink-0"
                                            :class="headerPpnMode === 'persen' ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'"
                                            :title="'Klik untuk ganti mode: ' + (headerPpnMode === 'persen' ? 'Persentase (%)' : 'Nominal (Rp)')"
                                        >
                                            <span x-text="headerPpnMode === 'persen' ? '%' : 'Rp'"></span>
                                        </button>
                                        <template x-if="headerPpnMode === 'persen'">
                                            <input
                                                type="number"
                                                step="any"
                                                min="0"
                                                max="100"
                                                x-model.number="headerPpnVal"
                                                @input="recalcHeaderPpn()"
                                                @keydown.enter.prevent
                                                placeholder="0"
                                                class="w-full text-right text-xs py-1.5 px-2 bg-background font-mono text-foreground border-0 focus:ring-0"
                                            />
                                        </template>
                                        <template x-if="headerPpnMode !== 'persen'">
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                :value="formatThousand(headerPpn)"
                                                @input="updateHeaderField('headerPpn', $event)"
                                                @keydown="handleKeyDown($event)"
                                                placeholder="0"
                                                class="w-full text-right text-xs py-1.5 px-2 bg-background font-mono text-foreground border-0 focus:ring-0"
                                            />
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <label class="text-xs text-foreground font-medium">Ongkos Kirim Global (Rp)</label>
                                <div class="w-48">
                                    <input type="hidden" name="ongkos_kirim" :value="headerOngkir">
                                    <input type="text" inputmode="numeric" :value="formatThousand(headerOngkir)" @input="updateHeaderField('headerOngkir', $event)" @keydown="handleKeyDown($event)" placeholder="0" class="w-full text-right text-xs py-1.5 px-2.5 rounded-lg border border-input bg-background text-foreground font-mono focus:border-ring focus:ring-1 focus:ring-ring shadow-xs">
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <label class="text-xs text-foreground font-medium">Adjustment / Pembulatan (Rp)</label>
                                <div class="w-48">
                                    <input type="hidden" name="adjustment" :value="headerAdj">
                                    <input type="text" inputmode="numeric" :value="formatThousand(headerAdj)" @input="updateHeaderField('headerAdj', $event)" @keydown="handleKeyDown($event)" placeholder="0" class="w-full text-right text-xs py-1.5 px-2.5 rounded-lg border border-input bg-background text-foreground font-mono focus:border-ring focus:ring-1 focus:ring-ring shadow-xs">
                                </div>
                            </div>
                        </div>
                    </x-card>

                    <x-card title="Ringkasan Nilai Pengadaan">
                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between text-muted-foreground">
                                <span>Subtotal Bahan (Kotor):</span>
                                <span class="font-mono font-semibold text-foreground" x-text="'Rp ' + formatThousand(subtotalProduk())"></span>
                            </div>
                            <div class="flex justify-between text-destructive">
                                <span>Total Diskon:</span>
                                <span class="font-mono font-semibold" x-text="'- Rp ' + formatThousand(totalDiskon())"></span>
                            </div>
                            <div class="flex justify-between text-muted-foreground">
                                <span>Total PPN:</span>
                                <span class="font-mono font-semibold text-foreground" x-text="'+ Rp ' + formatThousand(totalPpn())"></span>
                            </div>
                            <div class="flex justify-between text-muted-foreground">
                                <span>Total Ongkos Kirim:</span>
                                <span class="font-mono font-semibold text-foreground" x-text="'+ Rp ' + formatThousand(totalOngkir())"></span>
                            </div>
                            <div class="flex justify-between text-muted-foreground">
                                <span>Total Adjustment:</span>
                                <span class="font-mono font-semibold text-foreground" x-text="'+ Rp ' + formatThousand(totalAdj())"></span>
                            </div>
                            <div class="pt-3 border-t border-border flex justify-between items-center text-sm">
                                <span class="font-bold text-foreground">Grand Total Tagihan:</span>
                                <span class="font-mono font-extrabold text-foreground text-lg" x-text="'Rp ' + formatThousand(grandTotal())"></span>
                            </div>
                        </div>
                    </x-card>
                </div>

                {{-- Card 4: Jadwal Pembayaran Termin (Hanya aktif jika skema_bayar == termin) --}}
                <div x-show="skemaBayar === 'termin'" x-cloak class="transition-all duration-200">
                    <fieldset :disabled="skemaBayar !== 'termin'" class="border-0 p-0 m-0 min-w-0">
                        <x-card title="Jadwal Pembayaran Termin (Cicilan)" subtitle="Tentukan periode pembayaran dan tanggal jatuh tempo tiap termin" :noPadding="true">
                            <div class="p-4 border-b border-border flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-muted-foreground font-medium">Bagi Cepat:</span>
                                    <button type="button" @click="generateEqualTermins(2)" class="px-2.5 py-1 bg-muted hover:bg-muted/80 rounded-md text-xs text-foreground transition font-medium">2 Termin (50:50)</button>
                                    <button type="button" @click="generateEqualTermins(3)" class="px-2.5 py-1 bg-muted hover:bg-muted/80 rounded-md text-xs text-foreground transition font-medium">3 Termin</button>
                                    <button type="button" @click="generateEqualTermins(4)" class="px-2.5 py-1 bg-muted hover:bg-muted/80 rounded-md text-xs text-foreground transition font-medium">4 Termin</button>
                                </div>

                                <button type="button" @click="termins.push({tanggal_tempo:'',nominal_tagihan:0,keterangan:''})" class="text-xs font-semibold text-foreground hover:underline">
                                    + Tambah Termin Manual
                                </button>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead class="bg-muted/70 border-b border-border text-muted-foreground font-semibold uppercase text-[11px] tracking-wider">
                                        <tr>
                                            <th class="px-4 py-3 text-center w-16">Termin</th>
                                            <th class="px-4 py-3 text-left w-44">Jatuh Tempo <span class="text-destructive">*</span></th>
                                            <th class="px-4 py-3 text-right w-52">Nominal Tagihan (Rp) <span class="text-destructive">*</span></th>
                                            <th class="px-4 py-3 text-left">Keterangan</th>
                                            <th class="px-4 py-3 text-center w-12">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border bg-card">
                                        <template x-for="(tm, idx) in termins" :key="idx">
                                            <tr class="hover:bg-muted/40 transition">
                                                <td class="px-4 py-2.5 text-center font-mono font-bold text-foreground" x-text="idx + 1"></td>
                                                <td class="px-4 py-2.5">
                                                    <input type="date" :name="`termins[${idx}][tanggal_tempo]`" x-model="tm.tanggal_tempo" :required="skemaBayar === 'termin'" :disabled="skemaBayar !== 'termin'" class="w-full text-xs py-2 px-2.5 rounded-lg border border-input bg-background text-foreground focus:border-ring focus:ring-1 focus:ring-ring">
                                                </td>
                                                <td class="px-4 py-2.5">
                                                    <input type="hidden" :name="`termins[${idx}][nominal_tagihan]`" :value="tm.nominal_tagihan" :disabled="skemaBayar !== 'termin'">
                                                    <input type="text" inputmode="numeric" :value="formatThousand(tm.nominal_tagihan)" @input="updateTerminNominal(tm, $event)" @keydown="handleKeyDown($event)" :required="skemaBayar === 'termin'" :disabled="skemaBayar !== 'termin'" placeholder="0" class="w-full text-right font-mono text-xs py-2 px-2.5 rounded-lg border border-input bg-background focus:border-ring focus:ring-1 focus:ring-ring font-semibold text-foreground">
                                                </td>
                                                <td class="px-4 py-2.5">
                                                    <input type="text" :name="`termins[${idx}][keterangan]`" x-model="tm.keterangan" :disabled="skemaBayar !== 'termin'" placeholder="Contoh: DP 30% / Pelunasan" class="w-full text-xs py-2 px-2.5 rounded-lg border border-input bg-background text-foreground focus:border-ring focus:ring-1 focus:ring-ring">
                                                </td>
                                                <td class="px-4 py-2.5 text-center">
                                                    <button type="button" @click="if(termins.length > 1) termins.splice(idx,1)" :disabled="termins.length <= 1" class="p-1.5 text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-lg transition disabled:opacity-30">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <div class="p-4 bg-muted/30 border-t border-border flex items-center justify-between text-xs">
                                <div class="font-medium text-foreground">
                                    Akumulasi Termin: <span class="font-mono font-bold text-foreground" x-text="'Rp ' + formatThousand(totalTermins())"></span>
                                </div>
                                <div>
                                    <template x-if="totalTermins() === grandTotal()">
                                        <span class="text-emerald-700 dark:text-emerald-400 font-semibold bg-emerald-500/10 px-2.5 py-1 rounded-full border border-emerald-500/20">✓ Sesuai dengan Grand Total</span>
                                    </template>
                                    <template x-if="totalTermins() !== grandTotal()">
                                        <span class="text-amber-700 dark:text-amber-400 font-semibold bg-amber-500/10 px-2.5 py-1 rounded-full border border-amber-500/20" x-text="'Selisih: Rp ' + formatThousand(Math.abs(grandTotal() - totalTermins())) + (grandTotal() > totalTermins() ? ' (Kurang)' : ' (Lebih)')"></span>
                                    </template>
                                </div>
                            </div>
                        </x-card>
                    </fieldset>
                </div>

                {{-- Card 5: Catatan / Instruksi Khusus PO --}}
                <x-card title="Catatan / Instruksi Khusus PO" subtitle="Tambahkan catatan internal atau arahan pengiriman untuk supplier" :noPadding="true">
                    <div class="p-4">
                        <textarea
                            name="catatan"
                            rows="3"
                            placeholder="Contoh: Pesanan mendesak dari hasil analisa stok mingguan. Mohon kirimkan COA bersamaan dengan barang fisik."
                            class="w-full text-xs rounded-lg border border-input bg-background text-foreground p-3 focus:ring-1 focus:ring-ring focus:border-ring shadow-xs"
                        >{{ old('catatan', $purchaseOrder->catatan) }}</textarea>
                    </div>
                </x-card>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <x-button href="{{ route('purchasing.show', $purchaseOrder) }}" variant="outline" size="sm">
                        Batal
                    </x-button>
                    <x-button type="submit" variant="primary" size="sm">
                        <x-slot:icon>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </x-slot:icon>
                        Simpan Perubahan PO
                    </x-button>
                </div>
            </div>
        </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function poForm(initial = {}) {
            const rawRows = (initial.rows && initial.rows.length > 0) ? initial.rows : [
                {
                    produk_id: '',
                    qty_satuan_beli: '',
                    satuan_beli: '',
                    faktor_konversi: 1,
                    qty: '',
                    harga: '',
                    satuan: '',
                    selectedItem: null
                }
            ];

            const rows = rawRows.map(r => ({
                ...r,
                _lockedSatuanBeli: r.satuan_beli || '',
                _lockedFaktorKonversi: (r.faktor_konversi !== undefined && r.faktor_konversi !== null && r.faktor_konversi !== '') ? r.faktor_konversi : 1,
                _lockedQtySatuanBeli: (r.qty_satuan_beli !== undefined && r.qty_satuan_beli !== null) ? r.qty_satuan_beli : '',
                _lockedQty: (r.qty !== undefined && r.qty !== null) ? r.qty : '',
                _isInitialized: false,
            }));

            return {
                uomList: @json($uomList ?? []),
                skemaBayar: initial.skemaBayar || 'cash',
                headerDiskonMode: 'nominal',
                headerDiskonVal: 0,
                headerDiskon: initial.headerDiskon || 0,

                headerPpnMode: 'nominal',
                headerPpnVal: 0,
                headerPpn: initial.headerPpn || 0,

                headerOngkir: initial.headerOngkir || 0,
                headerAdj: initial.headerAdj || 0,
                rows: rows,
                termins: (initial.termins && initial.termins.length > 0) ? initial.termins : [
                    { tanggal_tempo: '', nominal_tagihan: 0, keterangan: 'Termin 1' }
                ],
                activeRow: null,

                init() {
                    this.$nextTick(() => {
                        this.rows.forEach(row => {
                            const targetSatuanBeli = row._lockedSatuanBeli || row.satuan_beli;
                            const targetFaktor = row._lockedFaktorKonversi !== undefined ? row._lockedFaktorKonversi : row.faktor_konversi;

                            if (targetSatuanBeli && row.satuan) {
                                const options = this.getUomOptions(row.satuan, targetSatuanBeli, targetFaktor);
                                const match = options.find(o => o.code === targetSatuanBeli || o.code.toLowerCase() === targetSatuanBeli.toLowerCase());
                                if (match) {
                                    row.satuan_beli = match.code;
                                    row.faktor_konversi = (targetFaktor !== undefined && targetFaktor !== null && targetFaktor !== '')
                                        ? targetFaktor
                                        : (match.factor || 1);
                                } else {
                                    row.satuan_beli = targetSatuanBeli;
                                    row.faktor_konversi = targetFaktor || 1;
                                }
                            }

                            if (row._lockedQtySatuanBeli !== '' && row._lockedQtySatuanBeli !== null && row._lockedQtySatuanBeli !== undefined) {
                                row.qty_satuan_beli = row._lockedQtySatuanBeli;
                            }
                            if (row._lockedQty !== '' && row._lockedQty !== null && row._lockedQty !== undefined) {
                                row.qty = row._lockedQty;
                            } else {
                                this.updateRowQty(row);
                            }

                            row._isInitialized = true;
                        });
                        this.recalcAll();
                    });
                },

                getUomOptions(baseSatuan, currentSatuanBeli = null, currentFactor = null) {
                    const s = (baseSatuan || '').toLowerCase().trim();
                    const curKey = (currentSatuanBeli || '').toLowerCase().trim();
                    const options = [];
                    const seen = new Set();

                    // 1. Ambil dari database Master UOM yang cocok dengan satuan dasar produk
                    if (Array.isArray(this.uomList)) {
                        this.uomList.forEach(u => {
                            const uKode = (u.kode || '').toLowerCase().trim();
                            const uBasis = (u.satuan_dasar || '').toLowerCase().trim();
                            const uKat = (u.kategori || '').toLowerCase().trim();
                            const factor = parseFloat(u.faktor_konversi) || 1;

                            // Cocokkan jika:
                            // a) satuan_dasar sama dengan baseSatuan produk
                            // b) kodenya persis sama dengan baseSatuan
                            // c) kategori Volume jika produk ml/l/liter
                            // d) kategori Massa jika produk gr/g/kg
                            let isMatch = (uBasis === s) || (uKode === s);
                            if (!isMatch && (s === 'ml' || s === 'l' || s === 'liter' || s === 'mililiter')) {
                                isMatch = (uBasis === 'ml' || uBasis === 'l' || uKat === 'volume' || uKode === 'galon' || uKode === 'drum');
                            } else if (!isMatch && (s === 'gr' || s === 'g' || s === 'gram' || s === 'kg' || s === 'kilogram')) {
                                isMatch = (uBasis === 'gr' || uBasis === 'kg' || uKat === 'massa');
                            } else if (!isMatch && (s === 'pcs' || s === 'unit' || s === 'botol' || s === 'box')) {
                                isMatch = (uBasis === 'pcs' || uKat === 'satuan hitung' || uKat === 'kemasan');
                            }

                            if (isMatch && !seen.has(uKode)) {
                                seen.add(uKode);
                                const factorDisplay = (factor == parseInt(factor) ? parseInt(factor) : factor).toLocaleString('id-ID');
                                const label = factor > 1 
                                    ? `${u.nama} (${factorDisplay} ${baseSatuan || ''})`
                                    : (uBasis === s || uKode === s ? `${u.nama} (${u.kode} - Satuan Dasar)` : `${u.nama} (${u.kode})`);
                                options.push({
                                    code: u.kode,
                                    label: label,
                                    factor: factor,
                                    isBase: (uKode === s || (uBasis === s && factor === 1))
                                });
                            }
                        });
                    }

                    // 2. Pastikan satuan dasar produk selalu ada di opsi jika belum ada di Master UOM
                    if (!seen.has(s) && baseSatuan) {
                        seen.add(s);
                        options.unshift({ code: baseSatuan, label: `${baseSatuan} (Satuan Dasar)`, factor: 1, isBase: true });
                    }

                    // 3. Pastikan jika ada currentSatuanBeli yang sudah terpasang, tetap ada di options
                    if (curKey) {
                        const found = options.some(o => o.code.toLowerCase().trim() === curKey);
                        if (!found) {
                            seen.add(curKey);
                            const factorNum = parseFloat(currentFactor) || 1;
                            const factorDisplay = (factorNum == parseInt(factorNum) ? parseInt(factorNum) : factorNum).toLocaleString('id-ID');
                            options.push({
                                code: currentSatuanBeli,
                                label: `${currentSatuanBeli} (${factorDisplay} ${baseSatuan || ''})`,
                                factor: factorNum
                            });
                        }
                    }

                    // Urutkan opsi: Satuan dasar di paling atas, lalu urut faktor terkecil ke terbesar
                    options.sort((a, b) => {
                        const aIsBase = a.isBase || (a.code.toLowerCase().trim() === s);
                        const bIsBase = b.isBase || (b.code.toLowerCase().trim() === s);
                        if (aIsBase && !bIsBase) return -1;
                        if (!aIsBase && bIsBase) return 1;
                        return (parseFloat(a.factor) || 0) - (parseFloat(b.factor) || 0);
                    });

                    return options;
                },

                onProductSelected(row, product) {
                    if (product) {
                        const isSameProduct = (row.produk_id && String(row.produk_id) === String(product.id));
                        row.produk_id = product.id;
                        row.satuan = product.satuan;
                        row.selectedItem = product;

                        // Hanya inisialisasi satuan beli baru jika row sudah diinisialisasi DAN produk berbeda,
                        // atau jika satuan_beli pada row masih kosong
                        if (row._isInitialized && !isSameProduct) {
                            row.satuan_beli = product.satuan || 'UNIT';
                            row.faktor_konversi = 1;
                            row._lockedSatuanBeli = row.satuan_beli;
                            row._lockedFaktorKonversi = 1;
                            this.updateRowQty(row);
                        } else if (!row.satuan_beli) {
                            row.satuan_beli = product.satuan || 'UNIT';
                            row.faktor_konversi = 1;
                            row._lockedSatuanBeli = row.satuan_beli;
                            row._lockedFaktorKonversi = 1;
                            this.updateRowQty(row);
                        }
                    } else {
                        row.produk_id = '';
                        row.satuan = '';
                        row.selectedItem = null;
                        row.satuan_beli = '';
                        row.faktor_konversi = 1;
                        row.qty = 0;
                        row.qty_satuan_beli = '';
                        row._lockedSatuanBeli = '';
                        row._lockedFaktorKonversi = 1;
                        this.recalcAll();
                    }
                },

                onSatuanBeliChange(row) {
                    const options = this.getUomOptions(row.satuan, row.satuan_beli, row.faktor_konversi);
                    const opt = options.find(o => o.code === row.satuan_beli || o.code.toLowerCase() === (row.satuan_beli || '').toLowerCase());
                    if (opt && opt.factor !== null && opt.factor !== undefined) {
                        row.faktor_konversi = opt.factor;
                    }
                    row._lockedSatuanBeli = row.satuan_beli;
                    row._lockedFaktorKonversi = row.faktor_konversi;
                    this.updateRowQty(row);
                },

                updateRowQty(row) {
                    const qtyBeli = parseFloat(row.qty_satuan_beli) || 0;
                    const factor = parseFloat(row.faktor_konversi) || 1;
                    row.qty = Math.round(qtyBeli * factor * 100) / 100;
                    this.recalcAll();
                },  

                calculateRowHppPerSatuanBeli(row) {
                    const qBeli = parseFloat(row.qty_satuan_beli) || 0;
                    if (qBeli <= 0) return 0;
                    const net = this.calculateRowNet(row);
                    return Math.round((net / qBeli) * 100) / 100;
                },

                formatQty(val) {
                    if (val === '' || val === null || val === undefined || isNaN(val)) return '0';
                    const num = Number(val);
                    if (Math.abs(num - Math.round(num)) < 0.00001) {
                        return num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                    }
                    return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                addRow() {
                    this.rows.push({
                        produk_id: '',
                        qty_satuan_beli: '',
                        satuan_beli: '',
                        faktor_konversi: 1,
                        qty: '',
                        harga: '',
                        satuan: '',
                        selectedItem: null,
                        _lockedSatuanBeli: '',
                        _lockedFaktorKonversi: 1,
                        _lockedQtySatuanBeli: '',
                        _lockedQty: '',
                        _isInitialized: true
                    });
                },

                recalcAll() {
                    if (this.headerDiskonMode === 'persen') this.recalcHeaderDiskon();
                    if (this.headerPpnMode === 'persen') this.recalcHeaderPpn();
                },

                toggleHeaderDiskonMode() {
                    if (this.headerDiskonMode === 'persen') {
                        this.headerDiskonMode = 'nominal';
                    } else {
                        this.headerDiskonMode = 'persen';
                        const sub = this.subtotalProduk();
                        if (!this.headerDiskonVal && this.headerDiskon > 0 && sub > 0) {
                            this.headerDiskonVal = Math.round(((parseFloat(this.headerDiskon) || 0) / sub) * 10000) / 100;
                        }
                    }
                    this.recalcHeaderDiskon();
                },

                recalcHeaderDiskon() {
                    if (this.headerDiskonMode === 'persen') {
                        const sub = this.subtotalProduk();
                        const pct = parseFloat(this.headerDiskonVal) || 0;
                        this.headerDiskon = Math.round((sub * pct) / 100);
                    }
                    if (this.headerPpnMode === 'persen') {
                        this.recalcHeaderPpn();
                    }
                },

                toggleHeaderPpnMode() {
                    if (this.headerPpnMode === 'persen') {
                        this.headerPpnMode = 'nominal';
                    } else {
                        this.headerPpnMode = 'persen';
                        const dpp = Math.max(0, this.subtotalProduk() - this.totalDiskon());
                        if (!this.headerPpnVal && this.headerPpn > 0 && dpp > 0) {
                            this.headerPpnVal = Math.round(((parseFloat(this.headerPpn) || 0) / dpp) * 10000) / 100;
                        } else if (!this.headerPpnVal) {
                            this.headerPpnVal = 11;
                        }
                    }
                    this.recalcHeaderPpn();
                },

                recalcHeaderPpn() {
                    if (this.headerPpnMode === 'persen') {
                        const dpp = Math.max(0, this.subtotalProduk() - this.totalDiskon());
                        const pct = parseFloat(this.headerPpnVal) || 0;
                        this.headerPpn = Math.round((dpp * pct) / 100);
                    }
                },

                formatThousand(val) {
                    if (val === '' || val === null || val === undefined) return '';
                    if (typeof val === 'number') {
                        return Math.round(val).toLocaleString('id-ID');
                    }
                    const str = val.toString().trim();
                    if (str.includes('.')) {
                        const num = parseFloat(str);
                        if (!isNaN(num)) {
                            return Math.round(num).toLocaleString('id-ID');
                        }
                    }
                    const clean = str.replace(/\D/g, '');
                    if (!clean) return '';
                    return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                },

                formatHpp(val) {
                    if (val === '' || val === null || val === undefined || isNaN(val)) return '0';
                    const num = Number(val);
                    if (Math.abs(num - Math.round(num)) < 0.00001) {
                        return num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                    }
                    return num.toLocaleString('id-ID', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 2
                    });
                },

                updateRowField(row, field, event) {
                    const input = event.target;
                    const oldVal = input.value;
                    const oldPos = input.selectionEnd || 0;
                    const digitsBeforeCursor = (oldVal.slice(0, oldPos).match(/\d/g) || []).length;
                    
                    let rawDigits = oldVal.replace(/\D/g, '');
                    if (rawDigits === '') {
                        row[field] = 0;
                        input.value = '';
                        this.recalcAll();
                        return;
                    }
                    if (rawDigits.length > 1 && rawDigits.startsWith('0')) {
                        rawDigits = String(parseInt(rawDigits, 10) || 0);
                    }
                    
                    const num = parseInt(rawDigits, 10);
                    row[field] = isNaN(num) ? 0 : num;
                    
                    const formatted = this.formatThousand(rawDigits);
                    input.value = formatted;
                    this.repositionCursor(input, formatted, digitsBeforeCursor);
                    this.recalcAll();
                },

                updateHeaderField(field, event) {
                    const input = event.target;
                    const oldVal = input.value;
                    const oldPos = input.selectionEnd || 0;
                    const digitsBeforeCursor = (oldVal.slice(0, oldPos).match(/\d/g) || []).length;
                    
                    let rawDigits = oldVal.replace(/\D/g, '');
                    if (rawDigits === '') {
                        this[field] = 0;
                        input.value = '';
                        this.recalcAll();
                        return;
                    }
                    if (rawDigits.length > 1 && rawDigits.startsWith('0')) {
                        rawDigits = String(parseInt(rawDigits, 10) || 0);
                    }
                    
                    const num = parseInt(rawDigits, 10);
                    this[field] = isNaN(num) ? 0 : num;
                    
                    const formatted = this.formatThousand(rawDigits);
                    input.value = formatted;
                    this.repositionCursor(input, formatted, digitsBeforeCursor);
                    this.recalcAll();
                },

                updateTerminNominal(tm, event) {
                    const input = event.target;
                    const oldVal = input.value;
                    const oldPos = input.selectionEnd || 0;
                    const digitsBeforeCursor = (oldVal.slice(0, oldPos).match(/\d/g) || []).length;
                    
                    let rawDigits = oldVal.replace(/\D/g, '');
                    if (rawDigits === '') {
                        tm.nominal_tagihan = 0;
                        input.value = '';
                        return;
                    }
                    if (rawDigits.length > 1 && rawDigits.startsWith('0')) {
                        rawDigits = String(parseInt(rawDigits, 10) || 0);
                    }
                    
                    const num = parseInt(rawDigits, 10);
                    tm.nominal_tagihan = isNaN(num) ? 0 : num;
                    
                    const formatted = this.formatThousand(rawDigits);
                    input.value = formatted;
                    this.repositionCursor(input, formatted, digitsBeforeCursor);
                },

                repositionCursor(input, formatted, digitsBeforeCursor) {
                    let newPos = 0;
                    let countedDigits = 0;
                    for (let i = 0; i < formatted.length; i++) {
                        if (/\d/.test(formatted[i])) countedDigits++;
                        if (countedDigits === digitsBeforeCursor) {
                            newPos = i + 1;
                            break;
                        }
                    }
                    if (countedDigits < digitsBeforeCursor || newPos > formatted.length) {
                        newPos = formatted.length;
                    }
                    try { input.setSelectionRange(newPos, newPos); } catch (e) {}
                },

                handleKeyDown(event) {
                    if (event.key === 'Backspace') {
                        const input = event.target;
                        if (input.selectionStart === input.selectionEnd && input.selectionStart > 0) {
                            if (input.value[input.selectionStart - 1] === '.') {
                                event.preventDefault();
                                const pos = input.selectionStart - 1;
                                const val = input.value;
                                input.value = val.slice(0, pos - 1) + val.slice(pos);
                                input.dispatchEvent(new Event('input'));
                            }
                        }
                    } else if (event.key === 'Delete') {
                        const input = event.target;
                        if (input.selectionStart === input.selectionEnd && input.selectionStart < input.value.length) {
                            if (input.value[input.selectionStart] === '.') {
                                event.preventDefault();
                                const pos = input.selectionStart;
                                const val = input.value;
                                input.value = val.slice(0, pos) + val.slice(pos + 2);
                                input.dispatchEvent(new Event('input'));
                            }
                        }
                    }
                },

                calculateRowNet(row) {
                    const h = parseFloat(row.harga) || 0;
                    if (h <= 0) return 0;

                    const sub = this.subtotalProduk();
                    const diskonTotal = parseFloat(this.headerDiskon) || 0;
                    const ppnTotal = parseFloat(this.headerPpn) || 0;
                    const ongkirTotal = parseFloat(this.headerOngkir) || 0;
                    const adjTotal = parseFloat(this.headerAdj) || 0;

                    const ratio = sub > 0 ? (h / sub) : (this.rows.length > 0 ? (1 / this.rows.length) : 0);

                    const d = diskonTotal * ratio;
                    const p = ppnTotal * ratio;
                    const o = ongkirTotal * ratio;
                    const a = adjTotal * ratio;

                    return Math.max(0, Math.round((h - d + p + o + a) * 100) / 100);
                },

                calculateRowHpp(row) {
                    const q = parseFloat(row.qty) || 0;
                    if (q <= 0) return 0;
                    const net = this.calculateRowNet(row);
                    return Math.round((net / q) * 10000) / 10000;
                },

                subtotalProduk() {
                    return this.rows.reduce((acc, r) => acc + (parseFloat(r.harga) || 0), 0);
                },

                totalDiskon() {
                    return Math.round((parseFloat(this.headerDiskon) || 0) * 100) / 100;
                },

                totalPpn() {
                    return Math.round((parseFloat(this.headerPpn) || 0) * 100) / 100;
                },

                totalOngkir() {
                    return Math.round((parseFloat(this.headerOngkir) || 0) * 100) / 100;
                },

                totalAdj() {
                    return Math.round((parseFloat(this.headerAdj) || 0) * 100) / 100;
                },

                grandTotal() {
                    const gt = this.subtotalProduk() - this.totalDiskon() + this.totalPpn() + this.totalOngkir() + this.totalAdj();
                    return Math.max(0, Math.round(gt));
                },

                totalTermins() {
                    return this.termins.reduce((acc, tm) => acc + (parseFloat(tm.nominal_tagihan) || 0), 0);
                },

                generateEqualTermins(n) {
                    const total = this.grandTotal();
                    if (total <= 0) return;
                    const perTerm = Math.floor(total / n);
                    const remainder = total - (perTerm * n);

                    this.termins = [];
                    for (let i = 0; i < n; i++) {
                        const nom = (i === n - 1) ? (perTerm + remainder) : perTerm;
                        this.termins.push({
                            tanggal_tempo: '',
                            nominal_tagihan: nom,
                            keterangan: `Termin ${i + 1} (${Math.round((nom / total) * 100)}%)`
                        });
                    }
                }
            };
        }
    </script>
    @endpush
</x-app-layout>

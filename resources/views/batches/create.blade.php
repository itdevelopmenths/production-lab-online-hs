<x-app-layout title="Rencana Batch Baru">
    @php
        $oldOutputs = old('outputs');
        $initialRows = [];
        if (!empty($oldOutputs) && is_array($oldOutputs)) {
            foreach (array_values($oldOutputs) as $idx => $out) {
                $pid = $out['produk_id'] ?? '';
                $p = !empty($pid) ? $produk->firstWhere('id', (int) $pid) : null;
                $hasBomForRow = !empty($pid) && isset($bomMap[$pid]) && count($bomMap[$pid]) > 0;
                $initialRows[] = [
                    '_uid' => $idx + 1,
                    'produk_id' => $pid,
                    'qty_rencana' => $out['qty_rencana'] ?? '',
                    'satuan' => $p?->satuan ?? '',
                    'error' => (!empty($pid) && !$hasBomForRow)
                        ? 'Produk "' . ($p?->nama ?? 'yang dipilih') . '" ditolak karena belum memiliki formula BOM.'
                        : '',
                    'selectedItem' => $p ? ['id' => $p->id, 'nama' => $p->nama, 'sku' => $p->sku, 'satuan' => $p->satuan] : null,
                ];
            }
        }
        if (empty($initialRows)) {
            $singlePid = old('produk_id', '');
            $p = $singlePid ? $produk->firstWhere('id', (int) $singlePid) : null;
            $hasBomForRow = !empty($singlePid) && isset($bomMap[$singlePid]) && count($bomMap[$singlePid]) > 0;
            $initialRows[] = [
                '_uid' => 1,
                'produk_id' => $singlePid,
                'qty_rencana' => old('qty_rencana', ''),
                'satuan' => $p?->satuan ?? '',
                'error' => (!empty($singlePid) && !$hasBomForRow)
                    ? 'Produk "' . ($p?->nama ?? 'yang dipilih') . '" ditolak karena belum memiliki formula BOM.'
                    : '',
                'selectedItem' => $p ? ['id' => $p->id, 'nama' => $p->nama, 'sku' => $p->sku, 'satuan' => $p->satuan] : null,
            ];
        }
    @endphp

    <div class="w-full space-y-4">
        <x-page-header
            title="Rencana Batch Baru"
            :breadcrumbs="['Produksi' => route('batches.index'), 'Rencana Baru' => null]"
            :backUrl="route('batches.index')"
        />

        <div x-data="batchCreateForm()">
            <form method="POST" action="{{ route('batches.store') }}" @submit="onSubmit($event)">
                @csrf
                <div class="space-y-6">
                    @if($errors->any())
                    <div role="alert" class="p-4 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs">
                        <div class="font-bold mb-1">Batch produksi gagal disimpan:</div>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <div
                        x-show="formError"
                        x-cloak
                        role="alert"
                        aria-live="polite"
                        class="p-3.5 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-start gap-2"
                    >
                        <svg class="size-4 shrink-0 mt-0.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span class="font-medium" x-text="formError"></span>
                    </div>

                    {{-- Card 1: Parameter & Lokasi Pabrik --}}
                    <x-card title="Parameter Fasilitas & Jadwal" subtitle="Pilih fasilitas gudang operasional penarik bahan dan gudang tujuan produk jadi" class="relative z-0">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <x-form-group name="gudang_operasional_id" label="Gudang Operasional (Sumber Bahan)" help="Gudang asal pengambilan stok bahan baku saat batch dirilis">
                                <x-select name="gudang_operasional_id" placeholder="— Pilih Gudang Bahan —">
                                    @foreach($gudangOp as $g)
                                        <option value="{{ $g->id }}" @selected(old('gudang_operasional_id') == $g->id)>{{ $g->nama }}</option>
                                    @endforeach
                                </x-select>
                            </x-form-group>

                            <x-form-group name="gudang_tujuan_rencana_id" label="Gudang Tujuan (Produk Jadi)" help="Gudang penyimpanan setelah produk selesai diproduksi">
                                <x-select name="gudang_tujuan_rencana_id" placeholder="— Pilih Gudang Output —">
                                    @foreach($gudangFf as $g)
                                        <option value="{{ $g->id }}" @selected(old('gudang_tujuan_rencana_id') == $g->id)>{{ $g->nama }}</option>
                                    @endforeach
                                </x-select>
                            </x-form-group>

                            <x-form-group name="tanggal" label="Tanggal Rencana" :required="true">
                                <x-input type="date" name="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" :required="true" />
                            </x-form-group>
                        </div>
                    </x-card>

                    {{-- Card 2: Multi-Output Produk Jadi Luaran --}}
                    <x-card title="Produk Jadi Luaran (Multi-Output)" subtitle="Hanya produk jadi yang sudah memiliki formula BOM yang dapat dipilih dan diproduksi" :noPadding="true" class="relative z-20" overflow="visible">
                        <div class="overflow-visible relative z-20">
                            <table class="w-full text-xs">
                                <thead class="bg-muted/70 border-b border-border text-muted-foreground font-semibold uppercase text-[11px] tracking-wider">
                                    <tr>
                                        <th class="px-4 py-3 text-left w-[65%]">Produk Jadi (BOM) <span class="text-destructive">*</span></th>
                                        <th class="px-4 py-3 text-right w-52">Target Rencana <span class="text-destructive">*</span></th>
                                        <th class="px-4 py-3 text-center w-14">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    <template x-for="(row, i) in rows" :key="row._uid">
                                        <tr
                                            class="hover:bg-muted/40 transition"
                                            :style="'position: relative; z-index: ' + (activeRow === i ? 200 : (100 - i))"
                                            @hs-dropdown-opened.window="if ($event.detail === `outputs[${i}][produk_id]`) activeRow = i"
                                            @hs-dropdown-closed.window="if ($event.detail === `outputs[${i}][produk_id]` && activeRow === i) activeRow = null"
                                            @product-selected="onProductPicked(row, $event.detail)"
                                        >
                                            <td class="px-4 py-3 align-top" :style="activeRow === i ? 'position: relative; z-index: 200;' : ''">
                                                <div
                                                    x-data="productSearchSelect({
                                                        name: `outputs[${i}][produk_id]`,
                                                        selectedId: row.produk_id,
                                                        selectedItem: row.selectedItem,
                                                        filterTipe: 'produk_jadi',
                                                        placeholder: '— Cari & Pilih Produk Jadi (BOM) —',
                                                        url: '{{ route('produk.select-data', [], false) }}'
                                                    })"
                                                    x-init="init()"
                                                    class="relative w-full"
                                                    :class="open ? 'z-[9999]' : 'z-10'"
                                                    style="position: relative;"
                                                    @click.outside="open = false; if (activeRow === i) activeRow = null"
                                                    @keydown.escape.window="open = false; if (activeRow === i) activeRow = null"
                                                    @hs-dropdown-opened.window="if ($event.detail && $event.detail !== `outputs[${i}][produk_id]`) open = false"
                                                >
                                                    <input type="hidden" :name="`outputs[${i}][produk_id]`" :value="selectedId">

                                                    <div
                                                        @click="activeRow = i; toggle()"
                                                        :id="`batch-output-trigger-${row._uid}`"
                                                        role="button"
                                                        tabindex="0"
                                                        :aria-expanded="open ? 'true' : 'false'"
                                                        :aria-invalid="hasRowProdukError(row) ? 'true' : 'false'"
                                                        :aria-describedby="hasRowProdukError(row) ? `batch-output-err-${row._uid}` : null"
                                                        @keydown.enter.prevent="activeRow = row._uid; toggle()"
                                                        @keydown.space.prevent="activeRow = row._uid; toggle()"
                                                        :class="hasRowProdukError(row) ? 'border-destructive ring-1 ring-destructive/30' : 'border-input hover:border-foreground/30'"
                                                        class="w-full flex items-center justify-between text-left rounded-lg border bg-background px-3 py-2 text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring transition cursor-pointer select-none"
                                                    >
                                                        <div class="flex items-center gap-1.5 truncate flex-1 min-w-0 pointer-events-none">
                                                            <template x-if="selectedItem">
                                                                <div class="flex items-center gap-1.5 truncate">
                                                                    <span class="font-mono text-foreground font-semibold bg-muted px-1.5 py-0.5 rounded-md border border-border text-[11px]" x-text="selectedItem.sku"></span>
                                                                    <span class="font-medium text-foreground truncate" x-text="selectedItem.nama"></span>
                                                                    <span class="text-[10px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="selectedItem.satuan"></span>
                                                                </div>
                                                            </template>
                                                            <template x-if="!selectedItem">
                                                                <span class="text-muted-foreground font-normal" x-text="placeholder"></span>
                                                            </template>
                                                        </div>
                                                        <div class="flex items-center gap-1 ml-2 shrink-0 pointer-events-none">
                                                            <svg class="w-3.5 h-3.5 text-muted-foreground transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                                        </div>
                                                    </div>

                                                    <p
                                                        :id="`batch-output-err-${row._uid}`"
                                                        x-show="hasRowProdukError(row)"
                                                        x-cloak
                                                        class="mt-1.5 text-[11px] font-medium text-destructive flex items-center gap-1"
                                                    >
                                                        <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        <span x-text="row.error || 'Pilih produk jadi yang sudah memiliki formula BOM.'"></span>
                                                    </p>

                                                    <div
                                                        x-show="open"
                                                        x-cloak
                                                        style="display: none; position: absolute; left: 0; top: 100%; z-index: 9999; width: 100%; box-sizing: border-box;"
                                                        class="absolute left-0 top-full z-[9999] mt-1.5 w-full min-w-64 sm:min-w-80 max-w-[calc(100vw-2rem)] bg-card rounded-xl border border-border shadow-xl text-xs overflow-hidden"
                                                    >
                                                        <div class="p-2.5 border-b border-border bg-muted/50 flex items-center gap-2">
                                                            <svg class="w-3.5 h-3.5 text-muted-foreground shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                            <input type="text" x-model="search" @input="onSearch($event)" @keydown.enter.prevent="fetchItems(1, false)" x-ref="searchInput" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" placeholder="Ketik SKU atau nama produk jadi..." class="w-full bg-background border border-input rounded-lg px-2.5 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring" />
                                                        </div>

                                                        <div class="max-h-52 overflow-y-auto divide-y divide-border">
                                                            <template x-for="item in items" :key="item.id">
                                                                <div
                                                                    @click="hasBom(item.id) ? select(item) : (clear(), rejectNoBom(row, item))"
                                                                    :class="!hasBom(item.id) ? 'bg-rose-500/5 text-muted-foreground hover:bg-rose-500/10 cursor-not-allowed' : (selectedId == item.id ? 'bg-muted text-foreground font-semibold cursor-pointer' : 'text-foreground hover:bg-muted/50 cursor-pointer')"
                                                                    class="px-3.5 py-2.5 transition flex items-center justify-between gap-2 select-none"
                                                                >
                                                                    <div class="flex items-center gap-2 truncate pointer-events-none">
                                                                        <span class="font-mono text-foreground font-medium bg-muted px-1.5 py-0.5 rounded-md border border-border text-[10px]" x-text="item.sku"></span>
                                                                        <span class="truncate font-medium" :class="hasBom(item.id) ? 'text-foreground' : 'text-muted-foreground'" x-text="item.nama"></span>
                                                                    </div>
                                                                    <div class="flex items-center gap-1.5 shrink-0 pointer-events-none">
                                                                        <template x-if="!hasBom(item.id)">
                                                                            <span class="text-[9px] font-semibold text-rose-700 dark:text-rose-400 bg-rose-500/10 px-1.5 py-0.5 rounded border border-rose-500/20">Belum Ada BOM</span>
                                                                        </template>
                                                                        <span class="text-[10px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="item.satuan"></span>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                            <div x-show="!loading && items.length === 0" class="py-4 text-center text-muted-foreground">Tidak ada produk yang cocok.</div>
                                                        </div>

                                                        <div class="p-2 border-t border-border bg-muted/40 flex items-center justify-between text-[11px] text-muted-foreground">
                                                            <span x-text="`Data: ${items.length}/${total}`"></span>
                                                            <button type="button" x-show="hasMore" @click="loadMore()" :disabled="loading" class="px-2.5 py-1 rounded-md bg-background border border-border hover:bg-muted text-foreground font-medium transition disabled:opacity-50 cursor-pointer shadow-xs">
                                                                <span x-text="loading ? '...' : '+10 Lagi'"></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 align-top">
                                                <div class="relative flex items-center">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        :name="`outputs[${i}][qty_rencana]`"
                                                        x-model="row.qty_rencana"
                                                        placeholder="misal: 100"
                                                        required
                                                        class="w-full rounded-lg border-input bg-background text-xs py-2 pr-16 font-mono text-right focus:border-ring focus:ring-1 focus:ring-ring shadow-xs"
                                                    />
                                                    <span class="absolute right-2.5 px-2 py-0.5 text-[10px] font-mono font-semibold text-muted-foreground bg-muted border border-border rounded-md uppercase tracking-wider pointer-events-none" x-text="row.satuan || 'Pcs'"></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center align-top">
                                                <button type="button" @click="removeRow(i)" :disabled="rows.length <= 1"
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

                        <div class="relative z-0 px-4 py-3 bg-muted/30 border-t border-border flex items-center justify-between">
                            <x-button type="button" @click="addRow()" variant="outline" size="sm">
                                <x-slot:icon>
                                    <svg class="w-3.5 h-3.5 text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </x-slot:icon>
                                + Tambah Produk Jadi Luaran
                            </x-button>

                            <div class="text-xs text-muted-foreground font-mono">
                                Total Varian: <span class="font-bold text-foreground" x-text="rows.length"></span>
                            </div>
                        </div>

                        <x-slot:footer>
                            <div class="flex items-center justify-end gap-2.5">
                                <x-button href="{{ route('batches.index') }}" variant="outline" size="sm">
                                    Batal
                                </x-button>
                                <x-button type="submit" variant="primary" size="sm">
                                    <x-slot:icon>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    </x-slot:icon>
                                    Simpan & Hitung Alokasi
                                </x-button>
                            </div>
                        </x-slot:footer>
                    </x-card>

                    {{-- Card 3: Pratinjau Agregasi Kebutuhan BOM --}}
                    <x-card title="Pratinjau Agregasi Kebutuhan BOM" subtitle="Kalkulasi dinamis total kebutuhan bahan baku yang akan dialokasikan untuk seluruh produk jadi di atas" :noPadding="true" class="relative z-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-muted/70 border-b border-border text-muted-foreground font-semibold uppercase text-[11px] tracking-wider">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left w-12">No</th>
                                        <th class="px-4 py-2.5 text-left w-28">SKU</th>
                                        <th class="px-4 py-2.5 text-left">Nama Bahan Baku</th>
                                        <th class="px-4 py-2.5 text-right w-36">Total Kebutuhan</th>
                                        <th class="px-4 py-2.5 text-left w-24">Satuan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border bg-card">
                                    <template x-for="(bomItem, idx) in getBomPreview()" :key="bomItem.bahan_id">
                                        <tr class="hover:bg-muted/30">
                                            <td class="px-4 py-2.5 text-center text-muted-foreground" x-text="idx + 1"></td>
                                            <td class="px-4 py-2.5 font-mono font-medium text-foreground" x-text="bomItem.sku"></td>
                                            <td class="px-4 py-2.5 font-semibold text-foreground" x-text="bomItem.nama"></td>
                                            <td class="px-4 py-2.5 text-right font-mono font-bold text-primary" x-text="Number(bomItem.total_kebutuhan).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 4})"></td>
                                            <td class="px-4 py-2.5 text-muted-foreground" x-text="bomItem.satuan"></td>
                                        </tr>
                                    </template>
                                    <tr x-show="getBomPreview().length === 0">
                                        <td colspan="5" class="px-4 py-6 text-center text-muted-foreground">
                                            Pilih produk jadi dan tentukan kuantitas rencana di atas untuk melihat pratinjau kebutuhan alokasi bahan baku (BOM).
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    function batchCreateForm() {
        const initialRows = @js($initialRows);
        return {
            activeRow: null,
            nextUid: initialRows.length + 1,
            bomMap: @js($bomMap ?? []),
            rows: initialRows,
            submittedAttempt: false,
            formError: '',
            hasBom(produkId) {
                if (!produkId) return false;
                const boms = this.bomMap[String(produkId)] ?? this.bomMap[Number(produkId)];
                return Array.isArray(boms) && boms.length > 0;
            },
            hasRowProdukError(row) {
                if (row.error) return true;
                if (row.produk_id && !this.hasBom(row.produk_id)) return true;
                return this.submittedAttempt && !row.produk_id;
            },
            addRow() {
                this.rows.push({
                    _uid: ++this.nextUid,
                    produk_id: '',
                    qty_rencana: '',
                    satuan: '',
                    error: '',
                    selectedItem: null,
                });
            },
            removeRow(index) {
                if (this.rows.length > 1) {
                    this.rows.splice(index, 1);
                    this.activeRow = null;
                    this.refreshFormErrorSummary();
                }
            },
            rejectNoBom(row, item) {
                row.produk_id = '';
                row.satuan = '';
                row.selectedItem = null;
                row.error = `Produk "${item.nama}" (${item.sku}) ditolak karena belum memiliki formula BOM. Buat resep BOM terlebih dahulu.`;
                this.formError = row.error;
            },
            onProductPicked(row, item) {
                if (!item) {
                    row.produk_id = '';
                    row.satuan = '';
                    row.selectedItem = null;
                    this.refreshFormErrorSummary();
                    return;
                }
                if (!this.hasBom(item.id)) {
                    row.produk_id = '';
                    row.satuan = '';
                    row.selectedItem = null;
                    row.error = `Produk "${item.nama}" (${item.sku}) ditolak karena belum memiliki formula BOM. Buat resep BOM terlebih dahulu.`;
                    this.formError = row.error;
                    return;
                }
                row.produk_id = item.id;
                row.satuan = item.satuan;
                row.selectedItem = item;
                row.error = '';
                this.refreshFormErrorSummary();
            },
            refreshFormErrorSummary() {
                const invalidRow = this.rows.find(r => r.error || (r.produk_id && !this.hasBom(r.produk_id)));
                this.formError = invalidRow ? (invalidRow.error || 'Terdapat produk jadi yang belum memiliki formula BOM.') : '';
            },
            onSubmit(event) {
                this.submittedAttempt = true;
                this.formError = '';

                let firstInvalidRow = null;
                for (const row of this.rows) {
                    if (!row.produk_id) {
                        row.error = 'Pilih produk jadi yang sudah memiliki formula BOM.';
                        firstInvalidRow = firstInvalidRow || row;
                    } else if (!this.hasBom(row.produk_id)) {
                        row.error = `Produk "${row.selectedItem?.nama || 'yang dipilih'}" ditolak karena belum memiliki formula BOM.`;
                        firstInvalidRow = firstInvalidRow || row;
                    }
                }

                if (firstInvalidRow) {
                    event.preventDefault();
                    this.formError = firstInvalidRow.error;
                    this.$nextTick(() => {
                        const trigger = document.getElementById(`batch-output-trigger-${firstInvalidRow._uid}`);
                        if (trigger) {
                            trigger.focus();
                        }
                    });
                    return;
                }
            },
            getBomPreview() {
                const aggregated = {};
                (this.rows || []).forEach(row => {
                    const pid = row.produk_id;
                    const qty = parseFloat(row.qty_rencana) || 0;
                    if (!pid || qty <= 0) return;
                    const boms = this.bomMap[pid] || [];
                    boms.forEach(b => {
                        if (!aggregated[b.bahan_id]) {
                            aggregated[b.bahan_id] = {
                                bahan_id: b.bahan_id,
                                sku: b.sku,
                                nama: b.nama,
                                satuan: b.satuan,
                                total_kebutuhan: 0
                            };
                        }
                        aggregated[b.bahan_id].total_kebutuhan += (b.qty_per_unit * qty);
                    });
                });
                return Object.values(aggregated);
            }
        };
    }
    </script>
    @endpush
</x-app-layout>

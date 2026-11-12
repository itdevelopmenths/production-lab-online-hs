<x-app-layout title="Impor & Grid Resep BOM (Excel & CSV)">
    <x-page-header
        title="Impor Formula Resep BOM (Excel & CSV)"
        subtitle="Kelola formula resep produk jadi dan kebutuhan bahan baku melalui berkas Excel/CSV atau grid interaktif (inputable)"
        :breadcrumbs="['Master Data' => null, 'BOM' => route('bom.index'), 'Impor Formula' => null]"
        :backUrl="route('bom.index')"
        backSize="xs"
    />

    {{-- Script SheetJS untuk Parsing & Generator Berkas Excel (.xlsx, .xls) & CSV --}}
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <div x-data="bomDualGridImporter()" x-init="init()" class="space-y-5">
        <!-- Panel Atas: Toolbar Dual-Format & Ringkasan -->
        <div class="bg-card rounded-xl border border-border p-4 shadow-xs">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-semibold text-foreground flex items-center gap-2">
                        <svg class="size-4 text-foreground shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Opsi Impor & Pengisian Data Dual-Format
                    </h2>
                    <p class="text-xs text-muted-foreground mt-0.5 leading-normal">
                        Dukung berkas <strong>Microsoft Excel (.xlsx, .xls)</strong> dan <strong>CSV (.csv, .txt)</strong>, atau ketik & tempel langsung (Ctrl+V) pada tabel grid di bawah.
                    </p>
                </div>

                <!-- Tombol-tombol Aksi Utama -->
                <div class="flex flex-wrap items-center lg:justify-end gap-2 shrink-0 max-w-full">
                    {{-- 1. Download Template Excel --}}
                    <button
                        type="button"
                        @click="downloadExcelTemplate()"
                        class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-medium shadow-xs transition cursor-pointer"
                        title="Unduh format spreadsheet resmi Microsoft Excel (.xlsx)"
                    >
                        <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Template Excel (.xlsx)
                    </button>

                    {{-- 2. Download Template CSV --}}
                    <a
                        href="{{ route('bom.template') }}"
                        class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap px-3 py-1.5 bg-background hover:bg-muted text-foreground border border-border rounded-lg text-xs font-medium transition cursor-pointer"
                        title="Unduh format teks standar CSV (.csv)"
                    >
                        <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Template CSV (.csv)
                    </a>

                    {{-- 3. Upload File Excel / CSV --}}
                    <label class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap px-3.5 py-1.5 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg text-xs font-semibold shadow-xs transition cursor-pointer">
                        <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        Unggah Berkas (Excel / CSV)
                        <input type="file" @change="handleFileUpload($event)" accept=".xlsx,.xls,.csv,.txt" class="hidden">
                    </label>
                </div>
            </div>

            <!-- Kartu Status & Petunjuk Cepat -->
            <div class="mt-4 pt-3 border-t border-border grid grid-cols-2 sm:grid-cols-5 gap-3">
                <div class="p-2.5 bg-muted/40 rounded-lg border border-border flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">Total Baris:</span>
                    <span class="font-mono text-sm font-bold text-foreground" x-text="rows.length"></span>
                </div>
                <div class="p-2.5 bg-emerald-500/10 rounded-lg border border-emerald-500/20 flex items-center justify-between">
                    <span class="text-xs text-emerald-700 dark:text-emerald-400">Baris Valid:</span>
                    <span class="font-mono text-sm font-bold text-emerald-700 dark:text-emerald-400" x-text="validRowsCount"></span>
                </div>
                <div class="p-2.5 rounded-lg border flex items-center justify-between transition" :class="duplicateRowsCount > 0 ? 'bg-purple-500/10 border-purple-500/30' : 'bg-muted/40 border-border'">
                    <span class="text-xs" :class="duplicateRowsCount > 0 ? 'text-purple-700 dark:text-purple-400 font-semibold' : 'text-muted-foreground'">Duplikat di Grid:</span>
                    <span class="font-mono text-sm font-bold" :class="duplicateRowsCount > 0 ? 'text-purple-700 dark:text-purple-400' : 'text-muted-foreground'" x-text="duplicateRowsCount"></span>
                </div>
                <div class="p-2.5 rounded-lg border flex items-center justify-between transition" :class="alreadyExistsCount > 0 ? 'bg-amber-500/10 border-amber-500/30' : 'bg-muted/40 border-border'">
                    <span class="text-xs" :class="alreadyExistsCount > 0 ? 'text-amber-700 dark:text-amber-400 font-semibold' : 'text-muted-foreground'">Sudah Terdaftar:</span>
                    <span class="font-mono text-sm font-bold" :class="alreadyExistsCount > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-muted-foreground'" x-text="alreadyExistsCount"></span>
                </div>
                <div class="p-2.5 bg-destructive/10 rounded-lg border border-destructive/20 flex items-center justify-between">
                    <span class="text-xs text-destructive">Perlu Diperiksa:</span>
                    <span class="font-mono text-sm font-bold text-destructive" x-text="invalidRowsCount"></span>
                </div>
            </div>
        </div>

        <!-- Area Grid Interactive Inputable (Spreadsheet-like) -->
        <x-card title="Grid Formula Resep (Column & Row Inputable)" subtitle="Pilih produk jadi dan bahan baku lewat pencarian dropdown, unggah Excel/CSV, atau tempel langsung (Ctrl+V)" :noPadding="true" class="relative z-10" overflow="visible">
            <x-slot:headerActions>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="removeExisting()"
                        x-show="alreadyExistsCount > 0"
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-400 border border-amber-500/20 rounded-lg text-xs font-medium cursor-pointer transition"
                        title="Hapus baris bahan yang sudah terdaftar sebelumnya dari tabel"
                    >
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Bersihkan Resep Terdaftar (<span x-text="alreadyExistsCount"></span>)
                    </button>
                    <button
                        type="button"
                        @click="removeDuplicates()"
                        x-show="duplicateRowsCount > 0"
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-purple-500/10 hover:bg-purple-500/20 text-purple-700 dark:text-purple-400 border border-purple-500/20 rounded-lg text-xs font-medium cursor-pointer transition"
                        title="Hapus baris duplikat secara otomatis dan pertahankan baris pertama"
                    >
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Bersihkan Duplikat (<span x-text="duplicateRowsCount"></span>)
                    </button>
                    <button
                        type="button"
                        @click="addRow()"
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-background hover:bg-muted text-foreground border border-border rounded-lg text-xs font-medium transition cursor-pointer"
                    >
                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        + Tambah Baris
                    </button>
                    <button
                        type="button"
                        @click="clearAllRows()"
                        x-show="rows.length > 0"
                        class="inline-flex items-center gap-1 px-2 py-1 text-destructive hover:underline text-xs font-medium cursor-pointer"
                    >
                        Hapus Semua
                    </button>
                </div>
            </x-slot:headerActions>

            <!-- Keyboard Paste Listener Box -->
            <div class="px-4 py-2.5 bg-blue-500/10 border-b border-blue-500/20 flex items-center justify-between text-xs text-blue-700 dark:text-blue-300">
                <div class="flex items-center gap-2">
                    <span class="size-2 rounded-full bg-blue-500 animate-pulse"></span>
                    <span>
                        <strong>Tips Efisiensi:</strong> Anda dapat memblok data tabel di Microsoft Excel / Google Sheets, tekan <strong>Ctrl + C</strong>, lalu klik di sini dan tekan <strong>Ctrl + V</strong> untuk mengisi otomatis seluruh baris!
                    </span>
                </div>
                <span class="text-[11px] font-mono font-medium hidden sm:inline">Ctrl + V didukung</span>
            </div>

            <div class="overflow-visible min-h-[280px] p-2" @paste="handlePaste($event)">
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="bg-muted/70 border-b border-border text-muted-foreground font-semibold uppercase text-[11px] tracking-wider">
                            <th class="px-3 py-2.5 text-center w-12">#</th>
                            <th class="px-3 py-2.5 text-left w-[33%]">Produk Jadi <span class="text-destructive">*</span></th>
                            <th class="px-3 py-2.5 text-left w-[33%]">Bahan Baku / Kemasan <span class="text-destructive">*</span></th>
                            <th class="px-3 py-2.5 text-right w-40">Kuantitas / Unit <span class="text-destructive">*</span></th>
                            <th class="px-3 py-2.5 text-center w-44">Status Validasi</th>
                            <th class="px-3 py-2.5 text-center w-14">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card">
                        <template x-for="(row, index) in rows" :key="row.id">
                            <tr
                                :style="'position: relative; z-index: ' + (activeDropdownKey && activeDropdownKey.startsWith(row.id + ':') ? 200 : (100 - index))"
                                :class="row.isExisting ? 'bg-amber-500/10' : (row.isDuplicate ? 'bg-purple-500/10' : (row.valid === false ? 'bg-destructive/10' : 'hover:bg-muted/40'))"
                                @hs-dropdown-opened.window="if ($event.detail && $event.detail.startsWith(row.id + ':')) activeDropdownKey = $event.detail"
                                @hs-dropdown-closed.window="if ($event.detail === activeDropdownKey) activeDropdownKey = null"
                            >
                                <td class="px-3 py-2.5 text-center font-mono text-muted-foreground text-xs" x-text="index + 1"></td>

                                {{-- Kolom 1: Produk Jadi (Dropdown Search Component) --}}
                                <td class="px-3 py-2" :style="activeDropdownKey === `${row.id}:pj` ? 'position: relative; z-index: 200;' : ''">
                                    <div
                                        x-data="productSearchSelect({
                                            name: `${row.id}:pj`,
                                            selectedId: row.produk_jadi_id,
                                            selectedItem: row.produk_jadi_item,
                                            filterTipe: 'produk_jadi',
                                            placeholder: '— Pilih Produk Jadi —',
                                            url: '{{ route('produk.select-data', [], false) }}'
                                        })"
                                        x-init="init()"
                                        @product-selected="onProdukJadiSelected(row, $event.detail)"
                                        class="relative w-full"
                                        :class="open ? 'z-[9999]' : 'z-10'"
                                        style="position: relative;"
                                        @click.outside="open = false; if (activeDropdownKey === `${row.id}:pj`) activeDropdownKey = null"
                                        @keydown.escape.window="open = false; if (activeDropdownKey === `${row.id}:pj`) activeDropdownKey = null"
                                        @hs-dropdown-opened.window="if ($event.detail && $event.detail !== `${row.id}:pj`) open = false"
                                    >
                                        <div
                                            @click="activeDropdownKey = `${row.id}:pj`; toggle()"
                                            role="button"
                                            tabindex="0"
                                            :aria-expanded="open ? 'true' : 'false'"
                                            @keydown.enter.prevent="activeDropdownKey = `${row.id}:pj`; toggle()"
                                            @keydown.space.prevent="activeDropdownKey = `${row.id}:pj`; toggle()"
                                            :class="(!row.produk_jadi_item && row.sku_produk_jadi) ? 'border-destructive/60 bg-destructive/5' : 'border-input bg-background hover:border-foreground/30'"
                                            class="w-full flex items-center justify-between text-left rounded-lg border px-3 py-2 text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring transition cursor-pointer select-none"
                                        >
                                            <div class="flex items-center gap-1.5 truncate flex-1 min-w-0 pointer-events-none">
                                                <template x-if="selectedItem">
                                                    <div class="flex items-center gap-1.5 truncate">
                                                        <span class="font-mono text-foreground font-semibold bg-muted px-1.5 py-0.5 rounded-md border border-border text-[10px]" x-text="selectedItem.sku"></span>
                                                        <span class="font-medium text-foreground truncate" x-text="selectedItem.nama"></span>
                                                        <span class="text-[9px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="selectedItem.satuan || 'pcs'"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!selectedItem && row.sku_produk_jadi">
                                                    <div class="flex items-center gap-1.5 truncate text-destructive">
                                                        <span class="font-mono font-semibold bg-destructive/10 px-1.5 py-0.5 rounded border border-destructive/20 text-[10px]" x-text="row.sku_produk_jadi"></span>
                                                        <span class="truncate text-[11px] font-medium">SKU tidak ditemukan</span>
                                                    </div>
                                                </template>
                                                <template x-if="!selectedItem && !row.sku_produk_jadi">
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
                                            style="display: none; position: absolute; left: 0; top: 100%; z-index: 9999; width: 100%; box-sizing: border-box;"
                                            class="absolute left-0 top-full z-[9999] mt-1.5 w-full min-w-64 sm:min-w-80 max-w-[calc(100vw-2rem)] bg-card rounded-xl border border-border shadow-xl text-xs overflow-hidden"
                                        >
                                            <div class="p-2.5 border-b border-border bg-muted/50 flex items-center gap-2">
                                                <svg class="w-3.5 h-3.5 text-muted-foreground shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                <input type="text" x-model="search" @input="onSearch($event)" @keydown.enter.prevent="fetchItems(1, false)" x-ref="searchInput" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" placeholder="Cari SKU / nama produk jadi..." class="w-full bg-background border border-input rounded-lg px-2.5 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring" />
                                            </div>

                                            <div class="max-h-48 overflow-y-auto divide-y divide-border">
                                                <template x-for="item in items" :key="item.id">
                                                    <div
                                                        @click="select(item)"
                                                        :class="selectedId == item.id ? 'bg-muted text-foreground font-semibold' : 'text-foreground hover:bg-muted/50'"
                                                        class="px-3.5 py-2.5 cursor-pointer transition flex items-center justify-between gap-2 select-none"
                                                    >
                                                        <div class="flex items-center gap-2 truncate pointer-events-none">
                                                            <span class="font-mono text-foreground font-medium bg-muted px-1.5 py-0.5 rounded-md border border-border text-[10px]" x-text="item.sku"></span>
                                                            <span class="truncate font-medium text-foreground" x-text="item.nama"></span>
                                                        </div>
                                                        <div class="flex items-center gap-1 shrink-0 pointer-events-none">
                                                            <span class="text-[9px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="item.satuan || 'pcs'"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                                <div x-show="!loading && items.length === 0" class="py-3 text-center text-muted-foreground">Tidak ada produk jadi cocok.</div>
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

                                {{-- Kolom 2: Bahan Baku / Kemasan (Dropdown Search Component) --}}
                                <td class="px-3 py-2" :style="activeDropdownKey === `${row.id}:bh` ? 'position: relative; z-index: 200;' : ''">
                                    <div
                                        x-data="productSearchSelect({
                                            name: `${row.id}:bh`,
                                            selectedId: row.bahan_id,
                                            selectedItem: row.bahan_item,
                                            filterTipe: ['bahan', 'kemas', 'bahan_baku', 'kemasan'],
                                            placeholder: '— Pilih Bahan / Kemasan —',
                                            url: '{{ route('produk.select-data', [], false) }}'
                                        })"
                                        x-init="init()"
                                        @product-selected="onBahanSelected(row, $event.detail)"
                                        class="relative w-full"
                                        :class="open ? 'z-[9999]' : 'z-10'"
                                        style="position: relative;"
                                        @click.outside="open = false; if (activeDropdownKey === `${row.id}:bh`) activeDropdownKey = null"
                                        @keydown.escape.window="open = false; if (activeDropdownKey === `${row.id}:bh`) activeDropdownKey = null"
                                        @hs-dropdown-opened.window="if ($event.detail && $event.detail !== `${row.id}:bh`) open = false"
                                    >
                                        <div
                                            @click="activeDropdownKey = `${row.id}:bh`; toggle()"
                                            role="button"
                                            tabindex="0"
                                            :aria-expanded="open ? 'true' : 'false'"
                                            @keydown.enter.prevent="activeDropdownKey = `${row.id}:bh`; toggle()"
                                            @keydown.space.prevent="activeDropdownKey = `${row.id}:bh`; toggle()"
                                            :class="(!row.bahan_item && row.sku_bahan) ? 'border-destructive/60 bg-destructive/5' : 'border-input bg-background hover:border-foreground/30'"
                                            class="w-full flex items-center justify-between text-left rounded-lg border px-3 py-2 text-xs shadow-xs focus:outline-none focus:ring-1 focus:ring-ring transition cursor-pointer select-none"
                                        >
                                            <div class="flex items-center gap-1.5 truncate flex-1 min-w-0 pointer-events-none">
                                                <template x-if="selectedItem">
                                                    <div class="flex items-center gap-1.5 truncate">
                                                        <span class="font-mono text-foreground font-semibold bg-muted px-1.5 py-0.5 rounded-md border border-border text-[10px]" x-text="selectedItem.sku"></span>
                                                        <span class="font-medium text-foreground truncate" x-text="selectedItem.nama"></span>
                                                        <span class="text-[9px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="selectedItem.satuan || '-'"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!selectedItem && row.sku_bahan">
                                                    <div class="flex items-center gap-1.5 truncate text-destructive">
                                                        <span class="font-mono font-semibold bg-destructive/10 px-1.5 py-0.5 rounded border border-destructive/20 text-[10px]" x-text="row.sku_bahan"></span>
                                                        <span class="truncate text-[11px] font-medium">SKU tidak ditemukan</span>
                                                    </div>
                                                </template>
                                                <template x-if="!selectedItem && !row.sku_bahan">
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
                                            style="display: none; position: absolute; left: 0; top: 100%; z-index: 9999; width: 100%; box-sizing: border-box;"
                                            class="absolute left-0 top-full z-[9999] mt-1.5 w-full min-w-64 sm:min-w-80 max-w-[calc(100vw-2rem)] bg-card rounded-xl border border-border shadow-xl text-xs overflow-hidden"
                                        >
                                            <div class="p-2.5 border-b border-border bg-muted/50 flex items-center gap-2">
                                                <svg class="w-3.5 h-3.5 text-muted-foreground shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                <input type="text" x-model="search" @input="onSearch($event)" @keydown.enter.prevent="fetchItems(1, false)" x-ref="searchInput" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" placeholder="Cari nama / SKU bahan..." class="w-full bg-background border border-input rounded-lg px-2.5 py-1.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring" />
                                            </div>

                                            <div class="max-h-48 overflow-y-auto divide-y divide-border">
                                                <template x-for="item in items" :key="item.id">
                                                    <div
                                                        @click="isPairDuplicateInOtherRow(row, item.sku) ? alertDuplicatePair(row, item) : select(item)"
                                                        :class="isPairDuplicateInOtherRow(row, item.sku) ? 'bg-muted/30 text-muted-foreground/60 cursor-not-allowed opacity-60' : (selectedId == item.id ? 'bg-muted text-foreground font-semibold' : 'text-foreground hover:bg-muted/50 cursor-pointer')"
                                                        class="px-3.5 py-2.5 transition flex items-center justify-between gap-2 select-none"
                                                    >
                                                        <div class="flex items-center gap-2 truncate pointer-events-none">
                                                            <span class="font-mono text-foreground font-medium bg-muted px-1.5 py-0.5 rounded-md border border-border text-[10px]" x-text="item.sku"></span>
                                                            <span class="truncate font-medium" :class="isPairDuplicateInOtherRow(row, item.sku) ? 'text-muted-foreground' : 'text-foreground'" x-text="item.nama"></span>
                                                            <template x-if="isPairDuplicateInOtherRow(row, item.sku)">
                                                                <span class="text-[9px] font-semibold text-rose-600 dark:text-rose-400 bg-rose-500/10 px-1.5 py-0.5 rounded border border-rose-500/20">Sudah di Tabel</span>
                                                            </template>
                                                            <template x-if="!isPairDuplicateInOtherRow(row, item.sku) && isPairAlreadyRegistered(row, item.sku)">
                                                                <span class="text-[9px] font-semibold text-amber-700 dark:text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/20">Terdaftar BOM</span>
                                                            </template>
                                                        </div>
                                                        <div class="flex items-center gap-1.5 shrink-0 pointer-events-none">
                                                            <span class="text-[9px] font-mono text-muted-foreground bg-muted px-1.5 py-0.5 rounded-md border border-border" x-text="item.satuan"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                                <div x-show="!loading && items.length === 0" class="py-3 text-center text-muted-foreground">Tidak ada bahan cocok.</div>
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

                                {{-- Kolom 3: Qty per Unit --}}
                                <td class="px-3 py-2 text-right">
                                    <div class="relative flex items-center justify-end">
                                        <input
                                            type="number"
                                            step="0.0001"
                                            min="0.0001"
                                            x-model="row.qty_per_unit"
                                            @input="validateRow(row)"
                                            class="w-36 text-xs py-2 pl-2.5 pr-14 text-right rounded-lg border border-input bg-background text-foreground focus:outline-none focus:ring-1 focus:ring-ring font-mono shadow-xs"
                                        >
                                        <span class="absolute right-2 px-1.5 py-0.5 text-[9px] font-mono font-semibold text-muted-foreground bg-muted border border-border rounded pointer-events-none" x-text="row.bahan_item?.satuan || '-'"></span>
                                    </div>
                                </td>

                                {{-- Kolom 4: Status Validasi --}}
                                <td class="px-3 py-1.5 text-center">
                                    <template x-if="row.valid === true">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                            &check; Siap Disimpan
                                        </span>
                                    </template>
                                    <template x-if="row.isExisting === true">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20" :title="row.error">
                                            ⚠️ Resep Sudah Terdaftar
                                        </span>
                                    </template>
                                    <template x-if="row.valid === false && !row.isExisting">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-destructive/10 text-destructive border border-destructive/20" :title="row.error">
                                            &times; <span x-text="row.error || 'Data Tidak Valid'"></span>
                                        </span>
                                    </template>
                                    <template x-if="row.valid === null">
                                        <span class="text-[10px] text-muted-foreground">Belum diisi</span>
                                    </template>
                                </td>

                                {{-- Kolom 5: Aksi Hapus Baris --}}
                                <td class="px-3 py-1.5 text-center">
                                    <button
                                        type="button"
                                        @click="removeRow(index)"
                                        class="text-muted-foreground hover:text-destructive p-1 transition cursor-pointer font-bold"
                                        title="Hapus baris ini"
                                    >
                                        &times;
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="rows.length === 0">
                            <td colspan="6" class="py-12 text-center text-muted-foreground text-xs">
                                <svg class="mx-auto size-8 text-muted-foreground/60 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="font-medium text-foreground">Belum ada baris formula resep.</p>
                                <p class="text-[11px] text-muted-foreground mt-1">Unggah berkas Excel/CSV di atas, tempel data dengan <strong>Ctrl + V</strong>, atau klik <strong>+ Tambah Baris</strong>.</p>
                                <button
                                    type="button"
                                    @click="addRow()"
                                    class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 bg-primary text-primary-foreground rounded-lg text-xs font-semibold hover:bg-primary/90 transition cursor-pointer shadow-xs"
                                >
                                    + Tambah Baris Pertama
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Grid: Action Submit Batch -->
            <div class="p-3 bg-muted/30 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="text-xs text-muted-foreground">
                    Menampilkan <strong class="text-foreground" x-text="rows.length"></strong> baris resep.
                    <span x-show="invalidRowsCount > 0" class="text-destructive font-semibold ml-2">
                        * Ada <span x-text="invalidRowsCount"></span> baris yang perlu diperbaiki sebelum dapat disimpan.
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="addRow()"
                        class="px-3 py-1.5 bg-background hover:bg-muted text-foreground border border-border rounded-lg text-xs font-medium transition cursor-pointer"
                    >
                        + Tambah Baris
                    </button>
                    <button
                        type="button"
                        @click="submitBulk()"
                        :disabled="rows.length === 0 || invalidRowsCount > 0 || isSubmitting"
                        class="px-4 py-1.5 bg-primary hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed text-primary-foreground rounded-lg text-xs font-semibold shadow-xs transition cursor-pointer flex items-center gap-1.5"
                    >
                        <svg x-show="isSubmitting" class="animate-spin -ml-1 mr-1.5 size-3.5 text-primary-foreground" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="isSubmitting ? 'Menyimpan...' : 'Simpan Semua Formula BOM (' + validRowsCount + ' Baris)'"></span>
                    </button>
                </div>
            </div>
        </x-card>
    </div>

    @push('scripts')
    <script>
    function bomDualGridImporter() {
        return {
            produkJadiMap: {},
            produkJadiObjMap: {},
            bahanMap: {},
            bahanObjMap: {},
            existingBomMap: {},
            rows: [],
            activeDropdownKey: null,
            nextRowSeq: 0,
            isSubmitting: false,

            init() {
                const pjList = @js($produkJadiList);
                const bhList = @js($bahanList);
                const existingList = @js($existingBoms ?? []);

                pjList.forEach(item => {
                    const cleanSku = (item.sku || '').trim().toUpperCase();
                    this.produkJadiMap[cleanSku] = item.nama;
                    this.produkJadiObjMap[cleanSku] = {
                        id: item.id,
                        sku: item.sku,
                        nama: item.nama,
                        satuan: item.satuan || 'pcs',
                    };
                });

                bhList.forEach(item => {
                    const cleanSku = (item.sku || '').trim().toUpperCase();
                    this.bahanMap[cleanSku] = `${item.nama} (${item.satuan})`;
                    this.bahanObjMap[cleanSku] = {
                        id: item.id,
                        sku: item.sku,
                        nama: item.nama,
                        satuan: item.satuan || '-',
                    };
                });

                this.existingBomMap = existingList || {};

                // Tambahkan 3 baris awal sebagai template inputable
                this.addRow();
                this.addRow();
                this.addRow();
            },

            buildRow(skuJadi = '', skuBahan = '', qty = 1.0) {
                const cleanJadi = (skuJadi || '').trim().toUpperCase();
                const cleanBahan = (skuBahan || '').trim().toUpperCase();
                const pjObj = cleanJadi ? (this.produkJadiObjMap[cleanJadi] || null) : null;
                const bhObj = cleanBahan ? (this.bahanObjMap[cleanBahan] || null) : null;

                return {
                    id: `${Date.now()}-${++this.nextRowSeq}`,
                    sku_produk_jadi: pjObj ? pjObj.sku : skuJadi,
                    produk_jadi_id: pjObj ? pjObj.id : '',
                    produk_jadi_item: pjObj ? { ...pjObj } : null,
                    sku_bahan: bhObj ? bhObj.sku : skuBahan,
                    bahan_id: bhObj ? bhObj.id : '',
                    bahan_item: bhObj ? { ...bhObj } : null,
                    qty_per_unit: qty,
                    valid: null,
                    isDuplicate: false,
                    isExisting: false,
                    error: ''
                };
            },

            onProdukJadiSelected(row, item) {
                if (item) {
                    const cleanSku = (item.sku || '').trim().toUpperCase();
                    this.produkJadiMap[cleanSku] = item.nama;
                    this.produkJadiObjMap[cleanSku] = {
                        id: item.id,
                        sku: item.sku,
                        nama: item.nama,
                        satuan: item.satuan || 'pcs',
                    };
                    row.sku_produk_jadi = item.sku || '';
                    row.produk_jadi_id = item.id || '';
                    row.produk_jadi_item = { ...item };
                } else {
                    row.sku_produk_jadi = '';
                    row.produk_jadi_id = '';
                    row.produk_jadi_item = null;
                }
                this.validateRow(row);
            },

            onBahanSelected(row, item) {
                if (item) {
                    const cleanSku = (item.sku || '').trim().toUpperCase();
                    this.bahanMap[cleanSku] = `${item.nama} (${item.satuan || '-'})`;
                    this.bahanObjMap[cleanSku] = {
                        id: item.id,
                        sku: item.sku,
                        nama: item.nama,
                        satuan: item.satuan || '-',
                    };
                    row.sku_bahan = item.sku || '';
                    row.bahan_id = item.id || '';
                    row.bahan_item = { ...item };
                } else {
                    row.sku_bahan = '';
                    row.bahan_id = '';
                    row.bahan_item = null;
                }
                this.validateRow(row);
            },

            isPairDuplicateInOtherRow(currentRow, candidateBahanSku) {
                const cleanJadi = (currentRow.sku_produk_jadi || '').trim().toUpperCase();
                const cleanBahan = (candidateBahanSku || '').trim().toUpperCase();
                if (!cleanJadi || !cleanBahan) return false;

                return this.rows.some(r => {
                    if (r.id === currentRow.id) return false;
                    return (r.sku_produk_jadi || '').trim().toUpperCase() === cleanJadi
                        && (r.sku_bahan || '').trim().toUpperCase() === cleanBahan;
                });
            },

            isPairAlreadyRegistered(currentRow, candidateBahanSku) {
                const cleanJadi = (currentRow.sku_produk_jadi || '').trim().toUpperCase();
                const cleanBahan = (candidateBahanSku || '').trim().toUpperCase();
                if (!cleanJadi || !cleanBahan) return false;

                return this.existingBomMap && this.existingBomMap[`${cleanJadi}|${cleanBahan}`] !== undefined;
            },

            alertDuplicatePair(currentRow, item) {
                const msg = `Kombinasi "${currentRow.produk_jadi_item?.nama || currentRow.sku_produk_jadi}" dan "${item.nama}" sudah ada pada baris lain di grid.`;
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Kombinasi Sudah Ada di Tabel',
                        text: msg,
                        confirmButtonColor: '#0284c7',
                        confirmButtonText: 'Mengerti'
                    });
                } else {
                    alert(msg);
                }
            },

            get validRowsCount() {
                return this.rows.filter(r => r.valid === true).length;
            },

            get invalidRowsCount() {
                return this.rows.filter(r => r.valid === false).length;
            },

            get duplicateRowsCount() {
                return this.rows.filter(r => r.isDuplicate === true).length;
            },

            get alreadyExistsCount() {
                return this.rows.filter(r => r.isExisting === true).length;
            },

            addRow(skuJadi = '', skuBahan = '', qty = 1.0) {
                this.rows.push(this.buildRow(skuJadi, skuBahan, qty));
                this.validateAll();
            },

            removeRow(index) {
                const removed = this.rows[index];
                this.rows.splice(index, 1);
                if (removed && this.activeDropdownKey && this.activeDropdownKey.startsWith(removed.id + ':')) {
                    this.activeDropdownKey = null;
                }
                this.validateAll();
            },

            clearAllRows() {
                if (confirm('Kosongkan semua baris pada grid tabel?')) {
                    this.rows = [];
                    this.activeDropdownKey = null;
                }
            },

            removeDuplicates() {
                const seen = new Set();
                const uniqueRows = [];
                let removed = 0;

                this.rows.forEach(r => {
                    const sJadi = (r.sku_produk_jadi || '').trim().toUpperCase();
                    const sBahan = (r.sku_bahan || '').trim().toUpperCase();
                    if (sJadi && sBahan) {
                        const key = `${sJadi}|${sBahan}`;
                        if (seen.has(key)) {
                            removed++;
                            return;
                        }
                        seen.add(key);
                    }
                    uniqueRows.push(r);
                });

                this.rows = uniqueRows;
                this.validateAll();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Duplikat Dibersihkan',
                        text: `${removed} baris duplikat berhasil dibersihkan dari grid.`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            },

            removeExisting() {
                let removed = 0;
                this.rows = this.rows.filter(r => {
                    const sJadi = (r.sku_produk_jadi || '').trim().toUpperCase();
                    const sBahan = (r.sku_bahan || '').trim().toUpperCase();
                    const key = `${sJadi}|${sBahan}`;
                    if (this.existingBomMap && this.existingBomMap[key] !== undefined) {
                        removed++;
                        return false;
                    }
                    return true;
                });

                this.validateAll();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Resep Terdaftar Dilewati',
                        text: `${removed} baris bahan yang sudah terdaftar berhasil dilewati.`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            },

            validateRow(row) {
                this.validateAll();
            },

            validateAll() {
                const pairCounts = {};
                this.rows.forEach(r => {
                    const sJadi = (r.sku_produk_jadi || '').trim().toUpperCase();
                    const sBahan = (r.sku_bahan || '').trim().toUpperCase();
                    if (sJadi && sBahan) {
                        const key = `${sJadi}|${sBahan}`;
                        pairCounts[key] = (pairCounts[key] || 0) + 1;
                    }
                });

                this.rows.forEach(r => {
                    r.isDuplicate = false;
                    r.isExisting = false;
                    if (!r.sku_produk_jadi && !r.sku_bahan) {
                        r.valid = null;
                        r.error = '';
                        return;
                    }

                    const sJadi = (r.sku_produk_jadi || '').trim().toUpperCase();
                    const sBahan = (r.sku_bahan || '').trim().toUpperCase();
                    const qty = parseFloat(r.qty_per_unit);

                    if (!sJadi || !this.produkJadiMap[sJadi]) {
                        r.valid = false;
                        r.error = 'SKU Produk Jadi tidak valid';
                        return;
                    }

                    if (!sBahan || !this.bahanMap[sBahan]) {
                        r.valid = false;
                        r.error = 'SKU Bahan tidak valid';
                        return;
                    }

                    if (isNaN(qty) || qty <= 0) {
                        r.valid = false;
                        r.error = 'Qty harus > 0';
                        return;
                    }

                    const key = `${sJadi}|${sBahan}`;
                    if (pairCounts[key] > 1) {
                        r.valid = false;
                        r.isDuplicate = true;
                        r.error = 'Duplikat di tabel: kombinasi produk dan bahan ini ada lebih dari 1 kali';
                        return;
                    }

                    if (this.existingBomMap && this.existingBomMap[key] !== undefined) {
                        r.valid = false;
                        r.isExisting = true;
                        r.error = `Bahan ini sudah terdaftar dalam formula produk ini (Kebutuhan saat ini: ${this.existingBomMap[key]})`;
                        return;
                    }

                    r.valid = true;
                    r.error = '';
                });
            },

            downloadExcelTemplate() {
                if (typeof XLSX === 'undefined') {
                    alert('Library Excel belum siap.');
                    return;
                }

                const wb = XLSX.utils.book_new();
                const sampleData = [
                    ['sku_produk_jadi', 'sku_bahan', 'qty_per_unit'],
                    ['PRD-SAMPLE-01', 'BAH-PARFUM-01', 15.5],
                    ['PRD-SAMPLE-01', 'BAH-ALKOHOL-01', 35.0],
                    ['PRD-SAMPLE-02', 'BAH-PARFUM-01', 20.0],
                    ['PRD-SAMPLE-02', 'KEM-BOTOL-50', 1.0]
                ];
                const ws = XLSX.utils.aoa_to_sheet(sampleData);
                ws['!cols'] = [{ wch: 22 }, { wch: 22 }, { wch: 16 }];
                XLSX.utils.book_append_sheet(wb, ws, 'Formula Resep BOM');
                XLSX.writeFile(wb, 'template_resep_bom.xlsx');
            },

            handleFileUpload(event) {
                const file = event.target.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = (e) => {
                    try {
                        const data = new Uint8Array(e.target.result);
                        const workbook = XLSX.read(data, { type: 'array' });
                        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                        const json = XLSX.utils.sheet_to_json(firstSheet, { header: 1, defval: '' });

                        if (!json || json.length < 2) {
                            alert('Berkas kosong atau tidak memiliki baris data.');
                            return;
                        }

                        // Deteksi indeks kolom
                        const header = json[0].map(h => String(h).toLowerCase().trim());
                        let jadiIdx = header.findIndex(h => h.includes('sku_produk_jadi') || h.includes('item name') || h.includes('produk'));
                        let bahanIdx = header.findIndex(h => h.includes('sku_bahan') || h.includes('ingredient') || h.includes('bahan'));
                        let qtyIdx = header.findIndex(h => h.includes('qty') || h.includes('quantity') || h.includes('kuantitas'));

                        if (jadiIdx === -1) jadiIdx = 0;
                        if (bahanIdx === -1) bahanIdx = 1;
                        if (qtyIdx === -1) qtyIdx = 2;

                        const newRows = [];
                        for (let i = 1; i < json.length; i++) {
                            const row = json[i];
                            const sJadi = String(row[jadiIdx] || '').trim();
                            const sBahan = String(row[bahanIdx] || '').trim();
                            const rawQty = String(row[qtyIdx] || '1').replace(',', '.');
                            const qty = parseFloat(rawQty) || 1.0;

                            if (sJadi || sBahan) {
                                newRows.push(this.buildRow(sJadi, sBahan, qty));
                            }
                        }

                        if (newRows.length > 0) {
                            this.rows = newRows;
                            this.validateAll();
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berkas Berhasil Dimuat',
                                    text: `${newRows.length} baris resep berhasil dimuat ke dalam grid tabel. Silakan tinjau dan edit sebelum menyimpan.`,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            }
                        }
                    } catch (err) {
                        alert('Gagal membaca berkas: ' + err.message);
                    }
                    event.target.value = '';
                };
                reader.readAsArrayBuffer(file);
            },

            handlePaste(event) {
                const clipboardData = event.clipboardData || window.clipboardData;
                if (!clipboardData) return;
                const pastedText = clipboardData.getData('Text');
                if (!pastedText || !pastedText.includes('\t') && !pastedText.includes(';') && !pastedText.includes('\n')) {
                    return; // Biarkan default paste jika hanya teks pendek biasa
                }

                event.preventDefault();
                const lines = pastedText.trim().split(/\r\n|\n|\r/);
                const newRows = [];

                lines.forEach((line, idx) => {
                    const delimiter = line.includes('\t') ? '\t' : (line.includes(';') ? ';' : ',');
                    const parts = line.split(delimiter);
                    if (parts.length >= 2) {
                        const sJadi = (parts[0] || '').trim();
                        const sBahan = (parts[1] || '').trim();
                        const rawQty = (parts[2] || '1').replace(',', '.');
                        const qty = parseFloat(rawQty) || 1.0;

                        // Abaikan baris jika itu header
                        if (sJadi.toLowerCase().includes('sku') && sBahan.toLowerCase().includes('sku')) {
                            return;
                        }

                        newRows.push(this.buildRow(sJadi, sBahan, qty));
                    }
                });

                if (newRows.length > 0) {
                    // Jika baris saat ini hanya 3 baris kosong default, gantikan saja
                    const isEmptyDefault = this.rows.length <= 3 && this.rows.every(r => !r.sku_produk_jadi && !r.sku_bahan);
                    if (isEmptyDefault) {
                        this.rows = newRows;
                    } else {
                        this.rows.push(...newRows);
                    }
                    this.validateAll();
                }
            },

            submitBulk() {
                if (this.duplicateRowsCount > 0) {
                    if (confirm(`Terdapat ${this.duplicateRowsCount} baris formula duplikat di dalam tabel grid. Bersihkan baris duplikat secara otomatis sebelum menyimpan?`)) {
                        this.removeDuplicates();
                    } else {
                        return;
                    }
                }

                if (this.alreadyExistsCount > 0) {
                    if (confirm(`Terdapat ${this.alreadyExistsCount} baris bahan yang sudah terdaftar dalam formula produk sebelumnya. Lewati bahan yang sudah terdaftar dan simpan resep yang baru?`)) {
                        this.removeExisting();
                    } else {
                        return;
                    }
                }

                const validRows = this.rows.filter(r => r.valid === true);
                if (validRows.length === 0) {
                    alert('Tidak ada baris resep baru yang dapat disimpan.');
                    return;
                }

                if (this.invalidRowsCount > 0) {
                    if (!confirm(`Ada ${this.invalidRowsCount} baris tidak valid yang akan dilewati. Lanjutkan menyimpan ${validRows.length} baris valid?`)) {
                        return;
                    }
                }

                this.isSubmitting = true;

                fetch('{{ route("bom.import-bulk") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        items: validRows.map(r => ({
                            sku_produk_jadi: r.sku_produk_jadi,
                            sku_bahan: r.sku_bahan,
                            qty_per_unit: r.qty_per_unit
                        }))
                    })
                })
                .then(res => res.json())
                .then(data => {
                    this.isSubmitting = false;
                    if (data.success) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil Disimpan!',
                                text: data.message,
                                confirmButtonText: 'Ke Katalog BOM'
                            }).then(() => {
                                window.location.href = '{{ route("bom.index") }}';
                            });
                        } else {
                            alert(data.message);
                            window.location.href = '{{ route("bom.index") }}';
                        }
                    } else {
                        alert(data.message || 'Gagal menyimpan formula BOM.');
                    }
                })
                .catch(err => {
                    this.isSubmitting = false;
                    alert('Terjadi kesalahan koneksi server.');
                });
            }
        };
    }
    </script>
    @endpush
</x-app-layout>

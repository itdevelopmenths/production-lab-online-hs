<x-app-layout title="Impor & Grid Resep BOM (Excel & CSV)">
    <x-page-header
        title="Impor Formula Resep BOM (Excel & CSV)"
        subtitle="Kelola formula resep produk jadi dan kebutuhan bahan baku melalui berkas Excel/CSV atau grid interaktif (inputable)"
        :breadcrumbs="['Master Data' => null, 'BOM' => route('bom.index'), 'Impor Formula' => null]"
    >
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <x-button href="{{ route('bom.index') }}" variant="secondary" size="xs">
                    &larr; Kembali ke BOM
                </x-button>
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- Script SheetJS untuk Parsing & Generator Berkas Excel (.xlsx, .xls) & CSV --}}
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <div x-data="bomDualGridImporter()" x-init="init()" class="space-y-5">
        <!-- Panel Atas: Toolbar Dual-Format & Ringkasan -->
        <div class="bg-white rounded-sm border border-gray-200 p-4 shadow-xs">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Opsi Impor & Pengisian Data Dual-Format
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Dukung berkas <strong>Microsoft Excel (.xlsx, .xls)</strong> dan <strong>CSV (.csv, .txt)</strong>, atau ketik & tempel langsung (Ctrl+V) pada tabel grid di bawah.
                    </p>
                </div>

                <!-- Tombol-tombol Aksi Utama -->
                <div class="flex flex-wrap items-center gap-2">
                    {{-- 1. Download Template Excel --}}
                    <button
                        type="button"
                        @click="downloadExcelTemplate()"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-sm text-xs font-semibold shadow-2xs transition cursor-pointer"
                        title="Unduh format spreadsheet resmi Microsoft Excel (.xlsx)"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Template Excel (.xlsx)
                    </button>

                    {{-- 2. Download Template CSV --}}
                    <a
                        href="{{ route('bom.template') }}"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300 rounded-sm text-xs font-medium transition cursor-pointer"
                        title="Unduh format teks standar CSV (.csv)"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Template CSV (.csv)
                    </a>

                    {{-- 3. Upload File Excel / CSV --}}
                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-sm text-xs font-semibold shadow-2xs transition cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        Unggah Berkas (Excel / CSV)
                        <input type="file" @change="handleFileUpload($event)" accept=".xlsx,.xls,.csv,.txt" class="hidden">
                    </label>
                </div>
            </div>

            <!-- Kartu Status & Petunjuk Cepat -->
            <div class="mt-4 pt-3 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="p-2.5 bg-gray-50 rounded-sm border border-gray-200 flex items-center justify-between">
                    <span class="text-xs text-gray-600">Total Baris Resep:</span>
                    <span class="font-mono text-sm font-bold text-gray-900" x-text="rows.length"></span>
                </div>
                <div class="p-2.5 bg-emerald-50 rounded-sm border border-emerald-200 flex items-center justify-between">
                    <span class="text-xs text-emerald-800">Baris Terverifikasi Valid:</span>
                    <span class="font-mono text-sm font-bold text-emerald-700" x-text="validRowsCount"></span>
                </div>
                <div class="p-2.5 bg-amber-50 rounded-sm border border-amber-200 flex items-center justify-between">
                    <span class="text-xs text-amber-800">Perlu Diperiksa (Invalid):</span>
                    <span class="font-mono text-sm font-bold text-amber-700" x-text="invalidRowsCount"></span>
                </div>
            </div>
        </div>

        <!-- Datalist Autocomplete SKU Produk Jadi & Bahan -->
        <datalist id="list-produk-jadi">
            @foreach($produkJadiList as $pj)
                <option value="{{ $pj->sku }}">{{ $pj->nama }}</option>
            @endforeach
        </datalist>

        <datalist id="list-bahan-baku">
            @foreach($bahanList as $bh)
                <option value="{{ $bh->sku }}">{{ $bh->nama }} ({{ $bh->satuan }})</option>
            @endforeach
        </datalist>

        <!-- Area Grid Interactive Inputable (Spreadsheet-like) -->
        <x-card title="Grid Formula Resep (Column & Row Inputable)" subtitle="Dapat diketik langsung, diubah nilainya, ditambah baris, atau ditempel langsung (Ctrl+V) dari spreadsheet" :noPadding="true">
            <x-slot:headerActions>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="addRow()"
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 rounded-sm text-xs font-semibold transition cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        + Tambah Baris
                    </button>
                    <button
                        type="button"
                        @click="clearAllRows()"
                        x-show="rows.length > 0"
                        class="inline-flex items-center gap-1 px-2 py-1 text-rose-600 hover:text-rose-800 text-xs font-medium cursor-pointer"
                    >
                        Hapus Semua
                    </button>
                </div>
            </x-slot:headerActions>

            <!-- Keyboard Paste Listener Box -->
            <div class="px-4 py-2.5 bg-blue-50/60 border-b border-blue-100 flex items-center justify-between text-xs text-blue-900">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                    <span>
                        <strong>Tips Efisiensi:</strong> Anda dapat memblok data tabel di Microsoft Excel / Google Sheets, tekan <strong>Ctrl + C</strong>, lalu klik di sini dan tekan <strong>Ctrl + V</strong> untuk mengisi otomatis seluruh baris!
                    </span>
                </div>
                <span class="text-[11px] text-blue-700 font-mono font-medium hidden sm:inline">Ctrl + V didukung</span>
            </div>

            <div class="overflow-x-auto p-2" @paste="handlePaste($event)">
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-[11px] uppercase tracking-wider">
                            <th class="px-3 py-2.5 text-center w-12">#</th>
                            <th class="px-3 py-2.5 text-left w-72">SKU Produk Jadi <span class="text-rose-500">*</span></th>
                            <th class="px-3 py-2.5 text-left w-72">SKU Bahan Baku / Kemasan <span class="text-rose-500">*</span></th>
                            <th class="px-3 py-2.5 text-right w-44">Kuantitas / Unit <span class="text-rose-500">*</span></th>
                            <th class="px-3 py-2.5 text-center w-40">Status Validasi</th>
                            <th class="px-3 py-2.5 text-center w-16">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template x-for="(row, index) in rows" :key="row.id">
                            <tr :class="row.valid === false ? 'bg-rose-50/50' : 'hover:bg-gray-50/80'">
                                <td class="px-3 py-2 text-center font-mono text-gray-500 text-xs" x-text="index + 1"></td>
                                
                                {{-- Kolom 1: SKU Produk Jadi (Inputable) --}}
                                <td class="px-3 py-1.5">
                                    <input
                                        type="text"
                                        list="list-produk-jadi"
                                        x-model="row.sku_produk_jadi"
                                        @input="validateRow(row)"
                                        placeholder="Ketik SKU atau pilih..."
                                        class="w-full text-xs py-1 px-2 rounded-sm border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono uppercase"
                                    >
                                    <span class="block text-[10px] text-gray-500 mt-0.5 truncate" x-text="getProdukJadiName(row.sku_produk_jadi)"></span>
                                </td>

                                {{-- Kolom 2: SKU Bahan (Inputable) --}}
                                <td class="px-3 py-1.5">
                                    <input
                                        type="text"
                                        list="list-bahan-baku"
                                        x-model="row.sku_bahan"
                                        @input="validateRow(row)"
                                        placeholder="Ketik SKU bahan atau pilih..."
                                        class="w-full text-xs py-1 px-2 rounded-sm border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono uppercase"
                                    >
                                    <span class="block text-[10px] text-gray-500 mt-0.5 truncate" x-text="getBahanName(row.sku_bahan)"></span>
                                </td>

                                {{-- Kolom 3: Qty per Unit (Inputable) --}}
                                <td class="px-3 py-1.5 text-right">
                                    <input
                                        type="number"
                                        step="0.0001"
                                        min="0.0001"
                                        x-model="row.qty_per_unit"
                                        @input="validateRow(row)"
                                        class="w-36 text-xs py-1 px-2 text-right rounded-sm border-gray-300 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 font-mono"
                                    >
                                </td>

                                {{-- Kolom 4: Status Validasi --}}
                                <td class="px-3 py-1.5 text-center">
                                    <template x-if="row.valid === true">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            &check; Siap Disimpan
                                        </span>
                                    </template>
                                    <template x-if="row.valid === false">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300" :title="row.error">
                                            &times; <span x-text="row.error || 'Data Tidak Valid'"></span>
                                        </span>
                                    </template>
                                    <template x-if="row.valid === null">
                                        <span class="text-[10px] text-gray-400">Belum diisi</span>
                                    </template>
                                </td>

                                {{-- Kolom 5: Aksi Hapus Baris --}}
                                <td class="px-3 py-1.5 text-center">
                                    <button
                                        type="button"
                                        @click="removeRow(index)"
                                        class="text-gray-400 hover:text-rose-600 p-1 transition cursor-pointer font-bold"
                                        title="Hapus baris ini"
                                    >
                                        &times;
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="rows.length === 0">
                            <td colspan="6" class="py-12 text-center text-gray-400 text-xs">
                                <svg class="mx-auto h-8 w-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="font-medium text-gray-600">Belum ada baris formula resep.</p>
                                <p class="text-[11px] text-gray-400 mt-1">Unggah berkas Excel/CSV di atas, tempel data dengan <strong>Ctrl + V</strong>, atau klik <strong>+ Tambah Baris</strong>.</p>
                                <button
                                    type="button"
                                    @click="addRow()"
                                    class="mt-3 inline-flex items-center gap-1 px-3 py-1 bg-primary-50 text-primary-700 border border-primary-200 rounded-sm text-xs font-semibold hover:bg-primary-100 transition cursor-pointer"
                                >
                                    + Tambah Baris Pertama
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Grid: Action Submit Batch -->
            <div class="p-3 bg-gray-50 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="text-xs text-gray-600">
                    Menampilkan <strong x-text="rows.length"></strong> baris resep.
                    <span x-show="invalidRowsCount > 0" class="text-rose-600 font-semibold ml-2">
                        * Ada <span x-text="invalidRowsCount"></span> baris yang perlu diperbaiki sebelum dapat disimpan.
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="addRow()"
                        class="px-3 py-1.5 bg-white hover:bg-gray-100 text-gray-700 border border-gray-300 rounded-sm text-xs font-semibold transition cursor-pointer"
                    >
                        + Tambah Baris
                    </button>
                    <button
                        type="button"
                        @click="submitBulk()"
                        :disabled="rows.length === 0 || invalidRowsCount > 0 || isSubmitting"
                        class="px-4 py-1.5 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-sm text-xs font-bold shadow-xs transition cursor-pointer flex items-center gap-1.5"
                    >
                        <svg x-show="isSubmitting" class="animate-spin -ml-1 mr-1.5 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
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
            bahanMap: {},
            rows: [],
            isSubmitting: false,

            init() {
                const pjList = @json($produkJadiList);
                const bhList = @json($bahanList);

                pjList.forEach(item => {
                    this.produkJadiMap[item.sku.trim().toUpperCase()] = item.nama;
                });

                bhList.forEach(item => {
                    this.bahanMap[item.sku.trim().toUpperCase()] = `${item.nama} (${item.satuan})`;
                });

                // Tambahkan 3 baris awal sebagai template inputable
                this.addRow();
                this.addRow();
                this.addRow();
            },

            get validRowsCount() {
                return this.rows.filter(r => r.valid === true).length;
            },

            get invalidRowsCount() {
                return this.rows.filter(r => r.valid === false).length;
            },

            addRow(skuJadi = '', skuBahan = '', qty = 1.0) {
                const newRow = {
                    id: Date.now() + Math.random(),
                    sku_produk_jadi: skuJadi,
                    sku_bahan: skuBahan,
                    qty_per_unit: qty,
                    valid: null,
                    error: ''
                };
                this.validateRow(newRow);
                this.rows.push(newRow);
            },

            removeRow(index) {
                this.rows.splice(index, 1);
            },

            clearAllRows() {
                if (confirm('Kosongkan semua baris pada grid tabel?')) {
                    this.rows = [];
                }
            },

            getProdukJadiName(sku) {
                if (!sku) return '';
                const clean = sku.trim().toUpperCase();
                return this.produkJadiMap[clean] ? `✓ ${this.produkJadiMap[clean]}` : '— SKU tidak ditemukan';
            },

            getBahanName(sku) {
                if (!sku) return '';
                const clean = sku.trim().toUpperCase();
                return this.bahanMap[clean] ? `✓ ${this.bahanMap[clean]}` : '— SKU tidak ditemukan';
            },

            validateRow(row) {
                if (!row.sku_produk_jadi && !row.sku_bahan) {
                    row.valid = null;
                    row.error = '';
                    return;
                }

                const sJadi = (row.sku_produk_jadi || '').trim().toUpperCase();
                const sBahan = (row.sku_bahan || '').trim().toUpperCase();
                const qty = parseFloat(row.qty_per_unit);

                if (!sJadi || !this.produkJadiMap[sJadi]) {
                    row.valid = false;
                    row.error = 'SKU Produk Jadi tidak valid';
                    return;
                }

                if (!sBahan || !this.bahanMap[sBahan]) {
                    row.valid = false;
                    row.error = 'SKU Bahan tidak valid';
                    return;
                }

                if (isNaN(qty) || qty <= 0) {
                    row.valid = false;
                    row.error = 'Qty harus > 0';
                    return;
                }

                row.valid = true;
                row.error = '';
            },

            validateAll() {
                this.rows.forEach(r => this.validateRow(r));
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
                                const rObj = {
                                    id: Date.now() + Math.random() + i,
                                    sku_produk_jadi: sJadi,
                                    sku_bahan: sBahan,
                                    qty_per_unit: qty,
                                    valid: null,
                                    error: ''
                                };
                                this.validateRow(rObj);
                                newRows.push(rObj);
                            }
                        }

                        if (newRows.length > 0) {
                            this.rows = newRows;
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

                        const rObj = {
                            id: Date.now() + Math.random() + idx,
                            sku_produk_jadi: sJadi,
                            sku_bahan: sBahan,
                            qty_per_unit: qty,
                            valid: null,
                            error: ''
                        };
                        this.validateRow(rObj);
                        newRows.push(rObj);
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
                }
            },

            submitBulk() {
                const validRows = this.rows.filter(r => r.valid === true);
                if (validRows.length === 0) {
                    alert('Tidak ada baris data valid yang siap disimpan.');
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

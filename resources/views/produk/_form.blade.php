@php
    $p = $produk ?? null;
    $categories = $kategoriList ?? \App\Models\Kategori::with(['varians' => fn($q) => $q->orderBy('nama')])->orderBy('nama')->get();
    $supplierListAll = $suppliers ?? \App\Models\Supplier::active()->orderBy('nama')->get(['id', 'nama', 'kategori']);
    $abcOptions = $klasifikasiAbcList ?? \App\Models\KlasifikasiAbc::where('is_active', true)->orderByDesc('tambahan_buffer_hari')->get(['id', 'kode', 'nama', 'tambahan_buffer_hari', 'warna_badge']);

    $initialProfil = old('profil_analisa', $p?->profil_analisa ?? '');
    if ($initialProfil === 'bahan_impor') {
        $initialProfil = 'impor';
    }
    $initialAbcId = old('klasifikasi_abc_id', $p?->klasifikasi_abc_id ?? '');

    $rawMoq = old('satuan_order_moq', $p?->satuan_order_moq ?? 1);
    $floatMoq = ($rawMoq !== null && $rawMoq !== '') ? (float) $rawMoq : 1;
    $displayMoq = ($floatMoq == (int) $floatMoq) ? (int) $floatMoq : rtrim(rtrim(number_format($floatMoq, 2, '.', ''), '0'), '.');

    // Build initial assigned suppliers
    $initialSuppliers = [];
    $oldSuppliers = old('supplier_assignments', old('suppliers'));
    if ($oldSuppliers !== null) {
        if (is_array($oldSuppliers)) {
            foreach ($oldSuppliers as $k => $v) {
                if (is_array($v) && isset($v['supplier_id'])) {
                    $sObj = $supplierListAll->firstWhere('id', (int) $v['supplier_id']);
                    $initialSuppliers[] = [
                        'supplier_id' => (int) $v['supplier_id'],
                        'nama' => $sObj?->nama ?? ('Supplier #' . $v['supplier_id']),
                        'kategori' => $sObj?->kategori ?? 'lokal',
                        'status' => ($v['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif',
                    ];
                } elseif (is_numeric($k) && is_array($v)) {
                    $sObj = $supplierListAll->firstWhere('id', (int) $k);
                    $initialSuppliers[] = [
                        'supplier_id' => (int) $k,
                        'nama' => $sObj?->nama ?? ('Supplier #' . $k),
                        'kategori' => $sObj?->kategori ?? 'lokal',
                        'status' => ($v['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif',
                    ];
                }
            }
        }
    } elseif ($p) {
        $loadedSuppliers = $p->relationLoaded('suppliers') ? $p->suppliers : $p->suppliers()->get();
        foreach ($loadedSuppliers as $sup) {
            $initialSuppliers[] = [
                'supplier_id' => $sup->id,
                'nama' => $sup->nama,
                'kategori' => $sup->kategori,
                'status' => $sup->pivot->status ?? 'aktif',
            ];
        }
        if (empty($initialSuppliers) && $p->supplier_id) {
            $sObj = $supplierListAll->firstWhere('id', $p->supplier_id);
            if ($sObj) {
                $initialSuppliers[] = [
                    'supplier_id' => $sObj->id,
                    'nama' => $sObj->nama,
                    'kategori' => $sObj->kategori,
                    'status' => 'aktif',
                ];
            }
        }
    }
@endphp

<script>
function produkFormState(categories, initialKategoriId, initialVarianId, initialNamaProduk, initialTipe, initialProfilAnalisa, initialKlasifikasiAbcId, allSuppliers, initialAssignedSuppliers) {
    return {
        categories: categories || [],
        selectedKategoriId: initialKategoriId || '',
        selectedVarianId: initialVarianId || '',
        namaProduk: initialNamaProduk || '',
        tipe: initialTipe || 'bahan',
        profilAnalisa: initialProfilAnalisa || '',
        selectedKlasifikasiAbcId: initialKlasifikasiAbcId ? String(initialKlasifikasiAbcId) : '',
        allSuppliers: allSuppliers || [],
        assignedSuppliers: initialAssignedSuppliers || [],
        selectedSupplierToAdd: '',

        get showKlasifikasiAbc() {
            return this.tipe !== 'produk_jadi' && (this.profilAnalisa === 'impor' || this.profilAnalisa === 'bahan_impor');
        },

        get currentVarians() {
            var catId = String(this.selectedKategoriId);
            var cat = this.categories.find(function(c) {
                return String(c.id) === catId;
            });
            return (cat && cat.varians) ? cat.varians : [];
        },

        get currentVarianName() {
            var varId = String(this.selectedVarianId);
            var v = this.currentVarians.find(function(item) {
                return String(item.id) === varId;
            });
            return v ? v.nama : '';
        },

        get fullNamePreview() {
            var base = (this.namaProduk || '').trim();
            var varian = (this.currentVarianName || '').trim();
            if (!base) return '— Belum diisi —';
            return varian ? (base + ' ' + varian) : base;
        },

        onKategoriChange() {
            var varId = String(this.selectedVarianId);
            var exists = this.currentVarians.some(function(v) {
                return String(v.id) === varId;
            });
            if (!exists) {
                this.selectedVarianId = '';
            }
        },

        addSupplier() {
            if (!this.selectedSupplierToAdd) return;
            var sId = parseInt(this.selectedSupplierToAdd, 10);
            if (this.assignedSuppliers.some(function(s) { return parseInt(s.supplier_id, 10) === sId; })) {
                this.selectedSupplierToAdd = '';
                return;
            }
            var found = this.allSuppliers.find(function(s) { return parseInt(s.id, 10) === sId; });
            if (found) {
                this.assignedSuppliers.push({
                    supplier_id: found.id,
                    nama: found.nama,
                    kategori: found.kategori || 'lokal',
                    status: 'aktif'
                });
            }
            this.selectedSupplierToAdd = '';
        },

        removeSupplier(index) {
            this.assignedSuppliers.splice(index, 1);
        },

        toggleSupplierStatus(index) {
            var current = this.assignedSuppliers[index].status;
            this.assignedSuppliers[index].status = (current === 'aktif') ? 'nonaktif' : 'aktif';
        }
    };
}
</script>

<div x-data="produkFormState(
    {{ Js::from($categories) }},
    '{{ addslashes(old('kategori_id', $p?->kategori_id ?? '')) }}',
    '{{ addslashes(old('varian_id', $p?->varian_id ?? '')) }}',
    '{{ addslashes(old('nama_produk', $p?->nama_produk ?? $p?->nama ?? '')) }}',
    '{{ addslashes(old('tipe', $p?->tipe ?? 'bahan')) }}',
    '{{ addslashes((string) $initialProfil) }}',
    '{{ addslashes((string) $initialAbcId) }}',
    {{ Js::from($supplierListAll) }},
    {{ Js::from($initialSuppliers) }}
)" class="space-y-4">

    <!-- Preview Box -->
    <div class="p-4 bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-gray-800 rounded-[12px] flex flex-wrap items-center justify-between gap-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div>
            <span class="text-[11px] font-bold text-[#667085] dark:text-gray-400 uppercase tracking-wider block">Pratinjau Nama Lengkap Produk:</span>
            <span class="text-sm font-semibold text-[#101828] dark:text-white font-mono" x-text="fullNamePreview"></span>
        </div>
        <div class="text-right">
            <span class="text-[11px] text-[#667085] dark:text-gray-400 block">Format: Kategori + Nama Dasar + Varian</span>
        </div>
    </div>

    <x-form-section title="Identitas & Klasifikasi Produk" subtitle="Kategori, varian, SKU, tipe barang, dan satuan operasional">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Kategori Dropdown --}}
            <x-form-field name="kategori_id" label="Kategori Produk" :required="true" help="Pilih kategori induk produk">
                <select
                    name="kategori_id"
                    id="kategori_id"
                    x-model="selectedKategoriId"
                    @change="onKategoriChange()"
                    required
                    class="w-full h-9 text-xs border-0 bg-transparent px-0 py-1.5 text-[#101828] dark:text-white focus:outline-none focus:ring-0 cursor-pointer"
                >
                    <option value="">— Pilih Kategori Produk —</option>
                    <template x-for="c in categories" :key="c.id">
                        <option :value="c.id" x-text="c.nama" :selected="String(c.id) === String(selectedKategoriId)"></option>
                    </template>
                </select>
            </x-form-field>

            {{-- Varian Dropdown --}}
            <x-form-field name="varian_id" label="Varian Produk" help="Varian disaring otomatis sesuai Kategori terpilih">
                <select
                    name="varian_id"
                    id="varian_id"
                    x-model="selectedVarianId"
                    class="w-full h-9 text-xs border-0 bg-transparent px-0 py-1.5 text-[#101828] dark:text-white focus:outline-none focus:ring-0 cursor-pointer"
                >
                    <option value="">— Tanpa Varian (Non-Varian) —</option>
                    <template x-for="v in currentVarians" :key="v.id">
                        <option :value="v.id" x-text="v.nama" :selected="String(v.id) === String(selectedVarianId)"></option>
                    </template>
                </select>
            </x-form-field>

            {{-- Base Name Produk --}}
            <x-form-field name="nama_produk" label="Nama Dasar Produk" :required="true" help="Nama produk tanpa imbuhan varian (misal: Travel Perfume, Botol Spray, Alkohol 96%)">
                <input
                    type="text"
                    name="nama_produk"
                    id="nama_produk"
                    x-model="namaProduk"
                    value="{{ old('nama_produk', $p?->nama_produk ?? $p?->nama) }}"
                    placeholder="misal: Travel Perfume"
                    required
                    maxlength="150"
                    class="w-full h-9 text-xs border-0 bg-transparent px-0 py-1.5 text-[#101828] dark:text-white focus:outline-none focus:ring-0"
                />
            </x-form-field>

            {{-- SKU Kode Barang --}}
            <x-form-field name="sku" label="SKU / Kode Barang" :required="true" help="Kode unik identifikasi stok barang">
                <x-input name="sku" variant="seamless" value="{{ old('sku', $p?->sku) }}" placeholder="misal: BAH-ALK-96" :required="true" />
            </x-form-field>

            {{-- Tipe Produk --}}
            <x-form-field name="tipe" label="Tipe Produk" :required="true">
                <select
                    name="tipe"
                    id="tipe"
                    x-model="tipe"
                    required
                    class="w-full h-9 text-xs border-0 bg-transparent px-0 py-1.5 text-[#101828] dark:text-white focus:outline-none focus:ring-0 cursor-pointer"
                >
                    @foreach(['bahan' => 'Bahan Baku', 'kemas' => 'Kemasan / Botol', 'produk_jadi' => 'Produk Jadi (Output BOM)'] as $v => $l)
                        <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </select>
            </x-form-field>

            {{-- Satuan Dasar UOM --}}
            <x-form-field name="satuan" label="Satuan Dasar UOM" :required="true" help="Satuan standar operasional (ml, gr, pcs) untuk stok & HPP">
                <x-select name="satuan" variant="seamless" placeholder="— Pilih Satuan UOM —" :required="true">
                    @php($uoms = $uomList ?? \App\Models\Uom::active()->orderBy('nama')->get())
                    @foreach($uoms as $uom)
                        <option value="{{ $uom->kode }}" @selected(old('satuan', $p?->satuan) === $uom->kode)>
                            {{ $uom->nama }} ({{ $uom->kode }})
                        </option>
                    @endforeach
                </x-select>
            </x-form-field>

            {{-- Faktor Konversi Bawaan Sistem (Default 1) --}}
            <input type="hidden" name="faktor_konversi" value="{{ old('faktor_konversi', $p?->faktor_konversi ?? 1) }}">

            {{-- MOQ --}}
            <x-form-field name="satuan_order_moq" label="Minimum Order Quantity (MOQ)" :required="true" help="Batas minimum kelipatan pemesanan">
                <x-input type="number" step="any" min="0.01" name="satuan_order_moq" variant="seamless" value="{{ $displayMoq }}" :required="true" />
            </x-form-field>

            {{-- Profil Analisa --}}
            <div x-show="tipe !== 'produk_jadi'" x-transition>
                <x-form-field name="profil_analisa" label="Profil Analisa Stok" help="Kategori perhitungan batas aman stok (Lokal atau Impor)">
                    <x-select name="profil_analisa" variant="seamless" x-model="profilAnalisa" placeholder="— Pilih Profil Analisa —">
                        <option value="lokal" @selected($initialProfil === 'lokal')>Lokal</option>
                        <option value="impor" @selected($initialProfil === 'impor')>Impor</option>
                    </x-select>
                </x-form-field>
            </div>

            {{-- Klasifikasi ABC (khusus profil Impor) --}}
            <div x-show="showKlasifikasiAbc" x-transition>
                <x-form-field name="klasifikasi_abc_id" label="Klasifikasi ABC (Impor)" help="Menentukan tambahan hari buffer lead time di Analisa Stok Impor">
                    <x-select name="klasifikasi_abc_id" variant="seamless" x-model="selectedKlasifikasiAbcId" ::disabled="!showKlasifikasiAbc" placeholder="— Belum Ditentukan (Default Buffer +0 Hari) —">
                        @foreach($abcOptions as $abc)
                            <option value="{{ $abc->id }}" @selected((string) $initialAbcId === (string) $abc->id)>
                                {{ $abc->nama }} (+{{ $abc->tambahan_buffer_hari }} Hari)
                            </option>
                        @endforeach
                    </x-select>
                </x-form-field>
            </div>
        </div>
    </x-form-section>

    {{-- Panel Daftar Supplier Pemasok (PRODUK_SUPPLIER) --}}
    <div x-show="tipe !== 'produk_jadi'" x-transition class="border border-[#E8E8E8] dark:border-gray-800 bg-white dark:bg-gray-900 rounded-[12px] p-4 sm:p-5 space-y-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#E8E8E8] dark:border-gray-800 pb-3">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-[#101828] dark:text-white flex items-center gap-2">
                    <svg class="size-4 text-[#111111] dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Daftar Supplier Pemasok (<span class="font-mono text-[#111111] dark:text-white">PRODUK_SUPPLIER</span>)
                </h3>
                <p class="text-[11px] text-[#667085] dark:text-gray-400 mt-0.5">Hubungkan vendor penyedia bahan baku/kemasan ini beserta status aktifnya.</p>
            </div>
            
            {{-- Dropdown tambah supplier --}}
            <div class="flex items-center gap-2">
                <select x-model="selectedSupplierToAdd" class="h-9 text-xs rounded-lg border border-[#E8E8E8] dark:border-gray-700 bg-[#F5F5F5] dark:bg-gray-950 px-2.5 py-1.5 text-[#101828] dark:text-white focus:border-[#111111] dark:focus:border-white outline-none">
                    <option value="">— Pilih Supplier —</option>
                    <template x-for="s in allSuppliers" :key="s.id">
                        <option :value="s.id" x-text="s.nama + ' (' + (s.kategori || 'lokal').toUpperCase() + ')'" :disabled="assignedSuppliers.some(item => parseInt(item.supplier_id, 10) === parseInt(s.id, 10))"></option>
                    </template>
                </select>
                <button type="button" @click="addSupplier()" :disabled="!selectedSupplierToAdd" class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-xs font-semibold bg-[#111111] dark:bg-white text-white dark:text-[#111111] hover:bg-[#292929] dark:hover:bg-gray-100 disabled:opacity-50 transition-colors cursor-pointer">
                    <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tambah
                </button>
            </div>
        </div>

        {{-- Hidden input indicator --}}
        <input type="hidden" name="supplier_assignments_present" value="1">

        {{-- Interactive Table --}}
        <div class="overflow-x-auto rounded-[10px] border border-[#E8E8E8] dark:border-gray-800">
            <table class="w-full text-xs">
                <thead class="bg-[#f8fafb] dark:bg-gray-800/95 border-b border-[#E8E8E8] dark:border-gray-800">
                    <tr>
                        <th class="px-3 py-2.5 text-left font-medium text-[#667085] dark:text-gray-400">Supplier</th>
                        <th class="px-3 py-2.5 text-left font-medium text-[#667085] dark:text-gray-400 w-28">Kategori</th>
                        <th class="px-3 py-2.5 text-center font-medium text-[#667085] dark:text-gray-400 w-32">Status Pemasok</th>
                        <th class="px-3 py-2.5 text-center font-medium text-[#667085] dark:text-gray-400 w-20">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E8E8E8] dark:divide-gray-800 bg-white dark:bg-gray-900">
                    <template x-for="(item, idx) in assignedSuppliers" :key="item.supplier_id">
                        <tr class="hover:bg-[#fafafa] dark:hover:bg-gray-800/40 transition-colors">
                            <td class="px-3 py-2.5 font-medium text-[#101828] dark:text-white">
                                <span x-text="item.nama"></span>
                                <input type="hidden" :name="'supplier_assignments[' + idx + '][supplier_id]'" :value="item.supplier_id">
                                <input type="hidden" :name="'supplier_assignments[' + idx + '][status]'" :value="item.status">
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase border"
                                      :class="item.kategori === 'impor' ? 'bg-purple-50 dark:bg-purple-950/30 text-purple-700 dark:text-purple-400 border-purple-200 dark:border-purple-800/50' : 'bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800/50'"
                                      x-text="item.kategori || 'lokal'">
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <button type="button" @click="toggleSupplierStatus(idx)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium border transition-colors cursor-pointer"
                                        :class="item.status === 'aktif' ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/50' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700'">
                                    <span class="size-1.5 rounded-full" :class="item.status === 'aktif' ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                                    <span x-text="item.status === 'aktif' ? 'Aktif' : 'Nonaktif'"></span>
                                </button>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <button type="button" @click="removeSupplier(idx)" class="p-1 rounded text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors cursor-pointer" title="Hapus supplier">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="assignedSuppliers.length === 0">
                        <td colspan="4" class="px-3 py-6 text-center text-[#667085] dark:text-gray-400 text-xs italic">
                            Belum ada supplier yang ditugaskan untuk produk ini. Pilih supplier dari daftar di atas lalu klik Tambah.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Status Aktif --}}
    <x-form-section title="Status Aktivasi Katalog">
        <x-checkbox name="is_active" label="Status Aktif Katalog" :checked="old('is_active', $p?->is_active ?? true)" help="Hanya produk aktif yang dapat dipilih dalam pembuatan PO dan Batch Produksi" />
    </x-form-section>
</div>

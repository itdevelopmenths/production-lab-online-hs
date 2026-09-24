<x-app-layout title="Buat Draft Purchase Order">
    <div class="max-w-6xl mx-auto space-y-5" x-data="draftPoForm({{ Js::from($prefilledItems ?? []) }})">
        <x-page-header
            title="Buat Draft Purchase Order"
            subtitle="Penerbitan Draft PO otomatis dari hasil kalkulasi Analisa & Rekomendasi Stok Bahan Baku"
            :breadcrumbs="['Inventori' => null, 'Analisa Stok' => route('analisa.index'), 'Buat Draft PO' => null]"
        >
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <x-button href="{{ route('analisa.index') }}" variant="secondary" size="xs">
                        &larr; Kembali ke Analisa Stok
                    </x-button>
                </div>
            </x-slot:actions>
        </x-page-header>

        {{-- Banner Panduan Rekomendasi --}}
        <div class="bg-gradient-to-r from-primary-50 via-sky-50 to-white p-4 rounded-sm border border-primary-200 flex items-start justify-between gap-4 shadow-2xs">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-sm bg-primary-700 text-white flex items-center justify-center shrink-0 shadow-xs mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="text-xs font-bold text-gray-900">Formulir Pengadaan dari Analisa Stok</h4>
                    </div>
                    <p class="text-[11px] text-gray-600 mt-0.5">
                        Daftar bahan baku di bawah ini dimuat berdasarkan rekomendasi perhitungan batas minimum & rumus MOQ. Anda dapat memilih supplier rekanan, menyesuaikan kuantitas beli, atau menghapus item yang tidak ingin diproses.
                    </p>
                </div>
            </div>
            <div class="text-right shrink-0">
                <span class="text-[11px] text-gray-500 block">Total Item Rekomendasi</span>
                <span class="text-base font-extrabold text-primary-800 font-mono" x-text="items.length + ' Bahan'"></span>
            </div>
        </div>

        <form method="POST" action="{{ route('analisa.create-po.store') }}" @submit="if(submitting) return false; submitting = true">
            @csrf

            <div class="space-y-5">
                {{-- Card 1: Informasi Header PO & Lokasi --}}
                <x-card title="Informasi Purchase Order" subtitle="Identitas pengadaan, supplier rekanan, gudang tujuan, dan nomor invoice" variant="primary">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-form-group name="supplier_id" label="Pemasok / Supplier" :required="true" help="Daftar rekanan aktif terverifikasi">
                            <x-select name="supplier_id" placeholder="— Pilih Supplier Rekanan —" :required="true">
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}" @selected(old('supplier_id', $defaultSupplierId ?? '') == $s->id)>
                                        {{ $s->nama }} ({{ ucfirst($s->kategori) }})
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form-group>

                        <x-form-group name="gudang_id" label="Lokasi PO (Gudang Tujuan)" help="Gudang penerima fisik saat barang datang">
                            <x-select name="gudang_id" placeholder="— Pilih Gudang Penerima —">
                                @foreach($gudang as $g)
                                    <option value="{{ $g->id }}" @selected(old('gudang_id') == $g->id)>
                                        {{ $g->nama }} ({{ ucwords(str_replace('_', ' ', $g->tipe)) }})
                                    </option>
                                @endforeach
                            </x-select>
                        </x-form-group>

                        <x-form-group name="no_invoice" label="No Invoice / Vendor Ref" help="Kosongkan jika ingin dibuat otomatis oleh sistem">
                            <x-input type="text" name="no_invoice" value="{{ old('no_invoice') }}" placeholder="Otomatis (cth: INV/PO/202609/0001)" />
                        </x-form-group>

                        <x-form-group name="tanggal" label="Tanggal PO" :required="true">
                            <x-input type="date" name="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" :required="true" />
                        </x-form-group>

                        <x-form-group name="eta" label="Estimasi Kedatangan (ETA)" help="Perkiraan pesanan tiba di gudang">
                            <x-input type="date" name="eta" value="{{ old('eta') }}" />
                        </x-form-group>

                        <x-form-group name="sumber_dana" label="Sumber Dana / Rekening">
                            <x-input type="text" name="sumber_dana" value="{{ old('sumber_dana', 'BCA Operasional') }}" placeholder="Contoh: BCA Operasional" />
                        </x-form-group>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-form-group name="skema_bayar" label="Skema Pembayaran" :required="true">
                            <select name="skema_bayar" x-model="skemaBayar" class="w-full rounded-sm border-gray-300 text-xs py-1.5 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                                <option value="cash">Tunai (Cash / Transfer Penuh)</option>
                                <option value="tempo">Tempo (Jatuh Tempo Tunggal)</option>
                                <option value="termin">Termin (Pembayaran Bertahap / Cicil)</option>
                            </select>
                        </x-form-group>

                        <div x-show="skemaBayar === 'tempo'" x-cloak>
                            <x-form-group name="tanggal_tempo" label="Jatuh Tempo Pembayaran" :required="true" help="Batas akhir pelunasan tagihan tempo">
                                <x-input type="date" name="tanggal_tempo" x-bind:required="skemaBayar === 'tempo'" value="{{ old('tanggal_tempo') }}" />
                            </x-form-group>
                        </div>
                    </div>
                </x-card>

                {{-- Card 2: Rincian Bahan yang Dipesan --}}
                <x-card title="Daftar Bahan Baku yang Dipesan" subtitle="Item rekomendasi order hasil kalkulasi engine stok. Anda dapat menyesuaikan kuantitas dan harga satuan." :noPadding="true" class="relative z-20">
                    <x-slot:actions>
                        <div class="flex items-center gap-3">
                            <div class="relative w-48 sm:w-64">
                                <input
                                    type="text"
                                    x-model="searchQuery"
                                    placeholder="Cari item dalam daftar..."
                                    class="w-full pl-7 pr-2 py-1 text-xs rounded-sm border border-gray-300 focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                >
                                <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <span class="text-xs text-gray-500 font-mono">
                                <span class="font-bold text-gray-800" x-text="filteredItems().length"></span> / <span x-text="items.length"></span> item
                            </span>
                        </div>
                    </x-slot:actions>

                    {{-- Hidden inputs container for POST submit --}}
                    <div class="hidden">
                        <template x-for="(item, idx) in items" :key="'hidden-' + item.produk_id">
                            <div>
                                <input type="hidden" :name="`items[${idx}][produk_id]`" :value="item.produk_id">
                                <input type="hidden" :name="`items[${idx}][qty]`" :value="item.qty">
                                <input type="hidden" :name="`items[${idx}][qty_satuan_beli]`" :value="item.qty">
                                <input type="hidden" :name="`items[${idx}][satuan_beli]`" :value="item.satuan_dasar">
                                <input type="hidden" :name="`items[${idx}][faktor_konversi]`" value="1">
                                <input type="hidden" :name="`items[${idx}][harga_satuan]`" :value="item.harga_satuan">
                                <input type="hidden" :name="`items[${idx}][harga_total]`" :value="item.harga_total">
                            </div>
                        </template>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-700 font-semibold uppercase text-[11px] tracking-wider">
                                <tr>
                                    <th class="px-3 py-2.5 text-center w-12">No</th>
                                    <th class="px-4 py-2.5 text-left min-w-[240px]">Bahan Baku</th>
                                    <th class="px-4 py-2.5 text-left w-56">Jumlah Dipesan</th>
                                    <th class="px-4 py-2.5 text-right w-44">Estimasi Harga Satuan</th>
                                    <th class="px-4 py-2.5 text-right w-48 bg-primary-50/50 text-primary-900">Subtotal Estimasi</th>
                                    <th class="px-2 py-2.5 text-center w-14">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <template x-for="(item, idx) in filteredItems()" :key="item.produk_id">
                                    <tr class="hover:bg-gray-50/60 transition">
                                        <td class="px-3 py-2.5 text-center text-gray-400 font-mono" x-text="idx + 1"></td>
                                        
                                        {{-- Kolom Bahan Baku --}}
                                        <td class="px-4 py-2.5">
                                            <div class="font-bold text-gray-900" x-text="item.nama"></div>
                                            <div class="text-[11px] text-gray-400 font-mono flex items-center gap-1.5 mt-0.5">
                                                <span x-text="item.sku"></span>
                                                <span>&bull;</span>
                                                <span>Satuan: <strong class="text-gray-600" x-text="item.satuan_dasar"></strong></span>
                                            </div>
                                        </td>

                                        {{-- Kolom Jumlah Dipesan --}}
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-1.5">
                                                <input
                                                    type="number"
                                                    step="any"
                                                    min="0.01"
                                                    x-model.number="item.qty"
                                                    @input="recalculateItem(item)"
                                                    class="w-32 px-2.5 py-1 text-xs rounded border border-gray-300 font-mono font-semibold text-right focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                                >
                                                <span class="px-2 py-1 rounded bg-gray-100 text-gray-700 font-mono text-xs border border-gray-200 shrink-0" x-text="item.satuan_dasar"></span>
                                            </div>
                                        </td>

                                        {{-- Kolom Harga Satuan --}}
                                        <td class="px-4 py-2.5 text-right font-mono">
                                            <div class="flex items-center justify-end gap-1">
                                                <span class="text-[10px] text-gray-400">Rp</span>
                                                <input
                                                    type="number"
                                                    step="any"
                                                    min="0"
                                                    x-model.number="item.harga_satuan"
                                                    @input="recalculateItem(item)"
                                                    class="w-32 px-2 py-1 text-xs text-right rounded border border-gray-300 font-mono focus:ring-1 focus:ring-primary-500"
                                                >
                                            </div>
                                        </td>

                                        {{-- Kolom Subtotal Estimasi --}}
                                        <td class="px-4 py-2.5 text-right font-mono font-bold text-gray-900 bg-primary-50/20">
                                            <span x-text="'Rp ' + formatThousand(item.harga_total)"></span>
                                        </td>

                                        {{-- Kolom Aksi Hapus --}}
                                        <td class="px-2 py-2.5 text-center">
                                            <button
                                                type="button"
                                                @click="removeItem(item.produk_id)"
                                                title="Hapus dari daftar PO"
                                                class="text-gray-400 hover:text-rose-600 p-1 rounded hover:bg-rose-50 transition cursor-pointer"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>

                                <template x-if="filteredItems().length === 0">
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-gray-400 text-xs">
                                            <div class="flex flex-col items-center justify-center gap-1">
                                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                                <span x-text="items.length === 0 ? 'Semua item telah dihapus dari daftar.' : 'Tidak ada item yang cocok dengan pencarian.'"></span>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Footer Table Bar --}}
                    <div class="p-3.5 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600">
                        <div class="flex items-center gap-2">
                            <span>Total Item yang Dipesan: <strong class="text-gray-900" x-text="items.length"></strong> Bahan</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-medium text-gray-700">Subtotal Estimasi Bahan:</span>
                            <span class="text-sm font-extrabold text-primary-800 font-mono" x-text="'Rp ' + formatThousand(grandTotal())"></span>
                        </div>
                    </div>
                </x-card>

                {{-- Card 3: Catatan & Tombol Simpan --}}
                <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-start">
                    <div class="md:col-span-7">
                        <x-card title="Catatan / Instruksi Khusus PO" subtitle="Tambahkan catatan internal atau arahan pengiriman untuk supplier" :noPadding="true">
                            <div class="p-4">
                                <textarea
                                    name="catatan"
                                    rows="3"
                                    placeholder="Contoh: Pesanan mendesak dari hasil analisa stok mingguan. Mohon kirimkan COA bersamaan dengan barang fisik."
                                    class="w-full text-xs rounded-sm border-gray-300 focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                >{{ old('catatan') }}</textarea>
                            </div>
                        </x-card>
                    </div>

                    <div class="md:col-span-5">
                        <div class="bg-white rounded-md border border-gray-200 shadow-xs p-5 space-y-4">
                            <div>
                                <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Ringkasan Konfirmasi PO</h4>
                                <p class="text-[11px] text-gray-500 mt-0.5">Draft PO akan dibuat dan dapat diedit kembali di modul Purchasing.</p>
                            </div>

                            <div class="space-y-2 border-t border-gray-100 pt-3 text-xs">
                                <div class="flex items-center justify-between text-gray-600">
                                    <span>Status Awal</span>
                                    <span class="bg-amber-100 text-amber-800 font-semibold px-2 py-0.5 rounded text-[11px]">Draft</span>
                                </div>
                                <div class="flex items-center justify-between text-gray-600">
                                    <span>Asal Pengadaan</span>
                                    <span class="text-primary-700 font-semibold">Rekomendasi Analisa</span>
                                </div>
                                <div class="flex items-center justify-between text-gray-600">
                                    <span>Jumlah Bahan</span>
                                    <span class="font-mono font-bold text-gray-800" x-text="items.length + ' Item'"></span>
                                </div>
                                <div class="flex items-center justify-between border-t border-gray-200 pt-2 text-sm">
                                    <span class="font-bold text-gray-900">Total Estimasi PO</span>
                                    <span class="font-extrabold text-primary-800 font-mono" x-text="'Rp ' + formatThousand(grandTotal())"></span>
                                </div>
                            </div>

                            <div class="pt-2 flex items-center justify-end gap-2.5 border-t border-gray-100">
                                <x-button href="{{ route('analisa.index') }}" variant="secondary" size="md">
                                    Batal
                                </x-button>

                                <button
                                    type="submit"
                                    :disabled="items.length === 0 || submitting"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white text-xs font-bold rounded-sm shadow-xs transition cursor-pointer"
                                >
                                    <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <svg x-show="submitting" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span x-text="submitting ? 'Menyimpan Draft...' : 'Konfirmasi & Buat Draft PO'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function draftPoForm(initialItems) {
            return {
                items: initialItems || [],
                searchQuery: '',
                skemaBayar: 'cash',
                submitting: false,

                filteredItems() {
                    if (!this.searchQuery || !this.searchQuery.trim()) {
                        return this.items;
                    }
                    const q = this.searchQuery.toLowerCase().trim();
                    return this.items.filter(i => 
                        (i.nama && i.nama.toLowerCase().includes(q)) || 
                        (i.sku && i.sku.toLowerCase().includes(q))
                    );
                },

                removeItem(produkId) {
                    this.items = this.items.filter(i => i.produk_id !== produkId);
                },

                recalculateItem(item) {
                    const qty = Number(item.qty) > 0 ? Number(item.qty) : 0;
                    item.qty_satuan_beli = qty;
                    item.faktor_konversi = 1;
                    item.satuan_beli = item.satuan_dasar;
                    
                    const hargaSatuan = Number(item.harga_satuan) || 0;
                    item.harga_total = Math.round(qty * hargaSatuan * 100) / 100;
                },

                grandTotal() {
                    return this.items.reduce((sum, i) => sum + (Number(i.harga_total) || 0), 0);
                },

                formatThousand(n) {
                    const num = Number(n) || 0;
                    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(num);
                }
            }
        }
    </script>
    @endpush
</x-app-layout>

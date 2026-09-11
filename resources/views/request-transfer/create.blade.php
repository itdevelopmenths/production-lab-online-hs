<x-app-layout title="Buat Request/Transfer">
    <x-page-header
        title="Buat Request & Transfer"
        subtitle="Formulir mutasi stok internal antar gudang atau permintaan bahan baku produksi"
        :breadcrumbs="['Request & Transfer' => route('rt.index'), 'Buat Dokumen' => null]"
    >
        <x-slot:actions>
            <x-button href="{{ route('rt.index') }}" variant="secondary" size="xs">
                &larr; Kembali ke Daftar
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="max-w-5xl" x-data="rtForm()">
        <form method="POST" action="{{ route('rt.store') }}">
            @csrf
            <div class="space-y-5">
                {{-- Card 1: Parameter Transfer & Lokasi --}}
                <x-card title="Parameter Dokumen Mutasi" subtitle="Tentukan jenis transfer logistik dan alur gudang asal ke gudang tujuan" variant="primary">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <x-form-group name="jenis" label="Jenis Mutasi" :required="true">
                            <x-select name="jenis" x-model="jenis" :required="true">
                                <option value="req_bahan">Request Bahan (Approval 5-Tahap)</option>
                                <option value="retur_bahan">Retur Bahan ke Gudang Asal</option>
                                <option value="kirim_produk_jadi">Kirim Produk Jadi ke FF</option>
                                <option value="antar_fulfillment">Antar Fulfillment</option>
                                <option value="retur_produk_jadi">Retur Produk Jadi</option>
                            </x-select>
                            <x-slot:help>
                                <span class="inline-flex items-center gap-1 font-mono text-[10px] text-primary-700 bg-primary-50 px-1.5 py-0.5 rounded-sm border border-primary-100 mt-1"
                                      x-text="jenis === 'req_bahan' ? '⚡ Alur 5 Tahap: Draft ➔ Disetujui ➔ Diproses ➔ Dikirim ➔ Selesai' : '⚡ Alur 3 Tahap Langsung: Draft ➔ Dikirim ➔ Selesai'"></span>
                            </x-slot:help>
                        </x-form-group>

                        <x-form-group name="gudang_asal_id" label="Gudang Asal" help="Gudang pengirim / sumber stok">
                            <x-select name="gudang_asal_id" placeholder="— Pilih Gudang Asal —">
                                @foreach($gudang as $g)
                                    <option value="{{ $g->id }}" @selected(old('gudang_asal_id') == $g->id)>{{ $g->nama }} ({{ ucfirst($g->tipe) }})</option>
                                @endforeach
                            </x-select>
                        </x-form-group>

                        <x-form-group name="gudang_tujuan_id" label="Gudang Tujuan" help="Gudang penerima alokasi fisik">
                            <x-select name="gudang_tujuan_id" placeholder="— Pilih Gudang Tujuan —">
                                @foreach($gudang as $g)
                                    <option value="{{ $g->id }}" @selected(old('gudang_tujuan_id') == $g->id)>{{ $g->nama }} ({{ ucfirst($g->tipe) }})</option>
                                @endforeach
                            </x-select>
                        </x-form-group>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <x-form-group name="catatan" label="Catatan / Referensi Dokumen" help="Informasi tambahan untuk tim logistik / gudang operasional">
                            <x-textarea name="catatan" rows="2" placeholder="Tuliskan catatan khusus atau nomor referensi kerja jika ada..." />
                        </x-form-group>
                    </div>
                </x-card>

                {{-- Card 2: Rincian Item Mutasi --}}
                <x-card title="Daftar Barang & Kuantitas Diminta" subtitle="Tentukan SKU produk/bahan dan kuantitas unit yang akan ditransfer" :noPadding="true">
                    <div class="overflow-visible">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-700 font-semibold uppercase text-[11px] tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left w-[62%]">Produk / Bahan Baku <span class="text-rose-500">*</span></th>
                                    <th class="px-4 py-3 text-right w-52">Qty Diminta <span class="text-rose-500">*</span></th>
                                    <th class="px-4 py-3 text-center w-14">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <template x-for="(row, i) in rows" :key="i">
                                    <tr
                                        class="hover:bg-gray-50/60 transition"
                                        :style="'position: relative; z-index: ' + (activeRow === i ? 200 : (100 - i))"
                                        @hs-dropdown-opened.window="if ($event.detail === `items[${i}][produk_id]`) activeRow = i"
                                        @hs-dropdown-closed.window="if ($event.detail === `items[${i}][produk_id]` && activeRow === i) activeRow = null"
                                        @product-selected="if($event.detail) { row.produk_id = $event.detail.id; row.satuan = $event.detail.satuan; row.selectedItem = $event.detail; } else { row.produk_id = ''; row.satuan = ''; row.selectedItem = null; }"
                                    >
                                        <td class="px-4 py-2.5">
                                            <div
                                                x-data="productSearchSelect({
                                                    name: `items[${i}][produk_id]`,
                                                    selectedId: row.produk_id,
                                                    selectedItem: row.selectedItem,
                                                    placeholder: '— Cari & Pilih Produk / Bahan (Paginasi 10) —',
                                                    url: '{{ route('produk.select-data', [], false) }}'
                                                })"
                                                x-init="init()"
                                                class="relative w-full"
                                                :class="open ? 'z-[100]' : 'z-10'"
                                                style="position: relative;"
                                                @click.outside="open = false; if (activeRow === i) activeRow = null"
                                                @keydown.escape.window="open = false; if (activeRow === i) activeRow = null"
                                                @hs-dropdown-opened.window="if ($event.detail && $event.detail !== `items[${i}][produk_id]`) open = false"
                                            >
                                                <input type="hidden" :name="`items[${i}][produk_id]`" :value="selectedId" required>

                                                <div
                                                    @click="toggle()"
                                                    role="button"
                                                    tabindex="0"
                                                    @keydown.enter.prevent="toggle()"
                                                    @keydown.space.prevent="toggle()"
                                                    class="w-full flex items-center justify-between text-left rounded-sm border border-gray-300 bg-white px-3 py-1.5 text-xs shadow-xs hover:border-gray-400 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 transition cursor-pointer select-none"
                                                >
                                                    <div class="flex items-center gap-1.5 truncate flex-1 min-w-0 pointer-events-none">
                                                        <template x-if="selectedItem">
                                                            <div class="flex items-center gap-1.5 truncate">
                                                                <span class="font-mono text-primary-700 font-semibold bg-primary-50 px-1 py-0.5 rounded-xs border border-primary-100 text-[11px]" x-text="selectedItem.sku"></span>
                                                                <span class="font-medium text-gray-900 truncate" x-text="selectedItem.nama"></span>
                                                                <span class="text-[10px] font-mono text-gray-500 bg-gray-100 px-1 py-0.5 rounded-xs border border-gray-200" x-text="selectedItem.satuan"></span>
                                                            </div>
                                                        </template>
                                                        <template x-if="!selectedItem">
                                                            <span class="text-gray-400 font-normal" x-text="placeholder"></span>
                                                        </template>
                                                    </div>
                                                    <div class="flex items-center gap-1 ml-2 shrink-0 pointer-events-none">
                                                        <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                                    </div>
                                                </div>

                                                <div
                                                    x-show="open"
                                                    x-cloak
                                                    style="display: none; position: absolute; left: 0; top: 100%; z-index: 9999; width: 100%; min-width: 320px; box-sizing: border-box;"
                                                    class="absolute left-0 top-full z-[100] mt-1 w-full min-w-[320px] bg-white rounded-sm border border-gray-200 shadow-2xl text-xs overflow-hidden"
                                                >
                                                    <div class="p-2 border-b border-gray-100 bg-gray-50 flex items-center gap-1.5">
                                                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                                        <input type="text" x-model="search" @input="onSearch()" x-ref="searchInput" placeholder="Ketik SKU atau nama..." class="w-full bg-white border border-gray-200 rounded-xs px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500" />
                                                    </div>

                                                    <div class="max-h-52 overflow-y-auto divide-y divide-gray-50">
                                                        <template x-for="item in items" :key="item.id">
                                                            <div @click="select(item)" :class="selectedId == item.id ? 'bg-primary-50 text-primary-900 font-semibold' : 'text-gray-700 hover:bg-gray-50'" class="px-3 py-2 cursor-pointer transition flex items-center justify-between gap-2 select-none">
                                                                <div class="flex items-center gap-1.5 truncate pointer-events-none">
                                                                    <span class="font-mono text-primary-700 font-medium bg-primary-50 px-1 py-0.5 rounded-xs border border-primary-100 text-[10px]" x-text="item.sku"></span>
                                                                    <span class="truncate font-medium text-gray-900" x-text="item.nama"></span>
                                                                </div>
                                                                <div class="flex items-center gap-1 shrink-0 pointer-events-none">
                                                                    <span class="text-[10px] font-mono text-gray-500 bg-gray-100 px-1 py-0.5 rounded-xs border border-gray-200" x-text="item.satuan"></span>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        <div x-show="!loading && items.length === 0" class="py-4 text-center text-gray-400">Tidak ada produk yang cocok.</div>
                                                    </div>

                                                    <div class="p-1.5 border-t border-gray-100 bg-gray-50 flex items-center justify-between text-[11px] text-gray-500">
                                                        <span x-text="`Data: ${items.length}/${total}`"></span>
                                                        <button type="button" x-show="hasMore" @click="loadMore()" :disabled="loading" class="px-2 py-0.5 rounded-xs bg-white border border-gray-200 hover:bg-gray-100 text-primary-700 font-medium transition disabled:opacity-50 cursor-pointer">
                                                            <span x-text="loading ? '...' : '+10 Lagi'"></span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <div class="relative flex items-center">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    :name="`items[${i}][qty_diminta]`"
                                                    x-model="row.qty"
                                                    placeholder="0.00"
                                                    required
                                                    class="w-full rounded-sm border-gray-300 text-xs py-1.5 pr-16 font-mono text-right focus:border-primary-500 focus:ring-1 focus:ring-primary-500 shadow-xs"
                                                />
                                                <span class="absolute right-2 px-1.5 py-0.5 text-[10px] font-mono font-bold text-primary-700 bg-primary-50 border border-primary-200 rounded-xs uppercase tracking-wider pointer-events-none" x-text="row.satuan || 'Unit'"></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <button type="button" @click="if(rows.length > 1) rows.splice(i,1)" :disabled="rows.length <= 1"
                                                    class="p-1.5 text-gray-400 hover:text-rose-600 disabled:opacity-30 disabled:cursor-not-allowed transition rounded-sm"
                                                    title="Hapus baris">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 bg-gray-50/50 border-t border-gray-200 flex items-center justify-between">
                        <x-button type="button" @click="rows.push({produk_id:'',qty:'',satuan:'',selectedItem:null})" variant="secondary" size="xs">
                            <x-slot:icon>
                                <svg class="w-3.5 h-3.5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </x-slot:icon>
                            Tambah Baris Barang
                        </x-button>

                        <div class="text-xs text-gray-500 font-mono">
                            Total Item: <span class="font-bold text-gray-800" x-text="rows.length"></span>
                        </div>
                    </div>

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2.5">
                            <x-button href="{{ route('rt.index') }}" variant="secondary" size="sm">
                                Batal
                            </x-button>
                            <x-button type="submit" variant="primary" size="sm">
                                <x-slot:icon>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </x-slot:icon>
                                Simpan Draft Dokumen
                            </x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function rtForm() {
            return {
                jenis: '{{ old("jenis", "req_bahan") }}',
                rows: [{ produk_id: '', qty: '', satuan: '', selectedItem: null }],
                activeRow: null
            };
        }
    </script>
    @endpush
</x-app-layout>

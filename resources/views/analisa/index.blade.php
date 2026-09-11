<x-app-layout title="Analisa Stok">
    <div x-data="analisa()" x-init="load()">
        <x-page-header
            title="Analisa & Rekomendasi Stok"
            subtitle="Kalkulasi otomatis Buffer Stock, batas minimum, dan rekomendasi order bahan lokal, impor, & fulfillment"
            :breadcrumbs="['Inventori' => null, 'Analisa Stok' => null]"
        >
            <x-slot:actions>
                @can('analisa.snapshot')
                <button type="button" @click="snapshot()" x-show="tab !== 'riwayat'" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-800 hover:bg-gray-900 text-white text-xs font-medium rounded-sm shadow-sm transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                    Simpan Snapshot Riwayat
                </button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <!-- Segmented Tab Navigation & Status -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-3 mb-4">
            <div class="inline-flex rounded-sm bg-gray-100 p-0.5 border border-gray-200">
                <template x-for="t in tabs" :key="t.key">
                    <button type="button"
                        @click="tab = t.key; load()"
                        :class="tab === t.key ? 'bg-white text-primary-700 shadow-sm font-semibold' : 'text-gray-600 hover:text-gray-900 font-medium'"
                        class="px-3.5 py-1.5 text-xs rounded-sm transition flex items-center gap-1.5"
                    >
                        <span x-text="t.label"></span>
                    </button>
                </template>
            </div>
            <div class="text-xs text-gray-500 flex items-center gap-1.5">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Data Real-time Sistem</span>
            </div>
        </div>

        <!-- Quick KPI Summary Strip -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Item</p>
                <p class="text-lg font-bold font-mono text-gray-900 mt-0.5" x-text="rows.length"></p>
            </div>
            <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Perlu Tindakan / Order</p>
                <p class="text-lg font-bold font-mono text-rose-600 mt-0.5" x-text="kpiOrderCount()"></p>
            </div>
            <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Status Aman</p>
                <p class="text-lg font-bold font-mono text-emerald-600 mt-0.5" x-text="kpiAmanCount()"></p>
            </div>
            <div class="bg-white rounded-sm border border-gray-200 p-3 shadow-sm">
                <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Qty Rekomendasi</p>
                <p class="text-lg font-bold font-mono text-primary-700 mt-0.5" x-text="kpiTotalOrderQty()"></p>
            </div>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-sm border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr id="ahead"></tr>
                    </thead>
                    <tbody id="abody" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>

            <!-- Loading Spinner -->
            <div x-show="loading" class="py-12 flex flex-col items-center justify-center text-gray-500">
                <svg class="animate-spin h-6 w-6 text-primary-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-xs">Memuat kalkulasi data analisa...</span>
            </div>

            <!-- Empty State -->
            <div x-show="!loading && rows.length === 0" class="py-12 text-center">
                <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="mt-2 text-xs font-medium text-gray-500">Tidak ada data analisa untuk kategori ini.</p>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function analisa(){
      return {
        tab: 'lokal',
        rows: [],
        loading: false,
        tabs: [
          { key: 'lokal', label: 'Bahan Baku Lokal' },
          { key: 'impor', label: 'Bahan Baku Impor' },
          { key: 'fulfillment', label: 'Produk Jadi (Fulfillment)' },
          { key: 'riwayat', label: 'Log Riwayat Snapshot' }
        ],
        cols: {
          lokal: [
            ['sku', 'SKU', 'text-left'],
            ['nama', 'Nama Bahan', 'text-left'],
            ['adu', 'ADU', 'text-right'],
            ['batas_minimum', 'Batas Min', 'text-right'],
            ['target_stock', 'Target', 'text-right'],
            ['tersedia', 'Tersedia', 'text-right'],
            ['selisih', 'Selisih', 'text-right'],
            ['status', 'Status', 'text-center'],
            ['qty_order', 'Qty Order', 'text-right']
          ],
          impor: [
            ['sku', 'SKU', 'text-left'],
            ['nama', 'Nama Bahan', 'text-left'],
            ['klasifikasi_abc', 'ABC', 'text-center'],
            ['buffer_days', 'Buffer Hari', 'text-right'],
            ['total_selisih', 'Selisih', 'text-right'],
            ['status', 'Status', 'text-center'],
            ['total_qty_order', 'Qty Order', 'text-right'],
            ['total_nominal_order', 'Nominal Order', 'text-right']
          ],
          fulfillment: [
            ['sku', 'SKU', 'text-left'],
            ['nama', 'Nama Produk', 'text-left'],
            ['batas_minimum_total', 'Batas Min', 'text-right'],
            ['target_stock_total', 'Target', 'text-right'],
            ['stok_total', 'Stok Saat Ini', 'text-right'],
            ['akan_datang', 'Akan Datang', 'text-right'],
            ['stok_all', 'Stok Total', 'text-right'],
            ['selisih', 'Selisih', 'text-right'],
            ['status', 'Status', 'text-center'],
            ['qty_order', 'Qty Order', 'text-right']
          ],
          riwayat: [
            ['tanggal', 'Tanggal', 'text-left'],
            ['tipe', 'Tipe Analisa', 'text-left'],
            ['item_label', 'Item / SKU', 'text-left'],
            ['batas_minimum', 'Batas Min', 'text-right'],
            ['target_stock', 'Target', 'text-right'],
            ['status', 'Status', 'text-center'],
            ['qty_order', 'Qty Order', 'text-right'],
            ['pencatat', 'Oleh', 'text-left']
          ]
        },
        urls: {
          lokal: '{{ route('analisa.lokal.data') }}',
          impor: '{{ route('analisa.impor.data') }}',
          fulfillment: '{{ route('analisa.fulfillment.data') }}',
          riwayat: '{{ route('analisa.riwayat.data') }}'
        },
        fmt(v, colName){
          if (typeof v === 'number') {
            if (colName === 'total_nominal_order') {
              return 'Rp ' + v.toLocaleString('id-ID', { maximumFractionDigits: 0 });
            }
            return v.toLocaleString('id-ID', { maximumFractionDigits: 2 });
          }
          return v ?? '-';
        },
        badge(s){
          const st = String(s || '').toLowerCase();
          if (st === 'order' || st === 'po') {
            return '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200 uppercase tracking-wide">ORDER</span>';
          }
          if (st === 'aman') {
            return '<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase tracking-wide">AMAN</span>';
          }
          return `<span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-gray-100 text-gray-700 border border-gray-200 uppercase tracking-wide">${st}</span>`;
        },
        kpiOrderCount(){
          return this.rows.filter(r => {
            const st = String(r.status || '').toLowerCase();
            return st === 'order' || st === 'po';
          }).length;
        },
        kpiAmanCount(){
          return this.rows.filter(r => String(r.status || '').toLowerCase() === 'aman').length;
        },
        kpiTotalOrderQty(){
          const sum = this.rows.reduce((acc, r) => {
            const q = Number(r.qty_order || r.total_qty_order || 0);
            return acc + (isNaN(q) ? 0 : q);
          }, 0);
          return sum.toLocaleString('id-ID', { maximumFractionDigits: 2 });
        },
        async load(){
          this.loading = true;
          try {
            const res = await fetch(this.urls[this.tab]);
            const j = await res.json();
            this.rows = j.data || [];
            this.renderTable();
          } catch(e) {
            console.error(e);
            this.rows = [];
          } finally {
            this.loading = false;
          }
        },
        renderTable(){
          const cols = this.cols[this.tab];
          const ahead = document.getElementById('ahead');
          const abody = document.getElementById('abody');

          if (ahead) {
            ahead.innerHTML = cols.map(c => `
              <th class="px-3 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-50 border-b border-gray-200 ${c[2]}">
                ${c[1]}
              </th>
            `).join('');
          }

          if (abody) {
            if (this.rows.length === 0) {
              abody.innerHTML = '';
              return;
            }

            abody.innerHTML = this.rows.map((r, idx) => {
              const bg = idx % 2 === 0 ? 'bg-white' : 'bg-gray-50/40';
              return `<tr class="${bg} hover:bg-primary-50/20 transition-colors">` + cols.map(c => {
                const val = r[c[0]];
                const colKey = c[0];
                const align = c[2];

                if (colKey === 'status') {
                  return `<td class="px-3 py-2 text-xs ${align}">${this.badge(val)}</td>`;
                }

                if (colKey === 'sku') {
                  return `<td class="px-3 py-2 text-xs font-mono font-medium text-primary-800 ${align}">${val ?? '-'}</td>`;
                }

                if (colKey === 'nama' || colKey === 'item_label') {
                  return `<td class="px-3 py-2 text-xs font-medium text-gray-900 ${align}">${val ?? '-'}</td>`;
                }

                if (align === 'text-right') {
                  return `<td class="px-3 py-2 text-xs font-mono text-gray-800 ${align}">${this.fmt(val, colKey)}</td>`;
                }

                return `<td class="px-3 py-2 text-xs text-gray-700 ${align}">${this.fmt(val, colKey)}</td>`;
              }).join('') + '</tr>';
            }).join('');
          }
        },
        async snapshot(){
          const map = {
            lokal: 'bahan_lokal',
            impor: 'bahan_impor',
            fulfillment: 'produk_jadi_fulfillment'
          };
          const fd = new FormData();
          fd.append('tipe', map[this.tab]);
          fd.append('_token', '{{ csrf_token() }}');

          try {
            const res = await fetch('{{ route('analisa.snapshot') }}', {
              method: 'POST',
              body: fd,
              headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
              Swal.fire({
                icon: 'success',
                title: 'Snapshot Tersimpan',
                text: 'Hasil kalkulasi analisa telah diarsipkan ke tab Riwayat.',
                confirmButtonColor: '#0284c7'
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan',
                text: 'Tidak dapat menyimpan snapshot analisa.',
                confirmButtonColor: '#0284c7'
              });
            }
          } catch(e) {
            Swal.fire({
              icon: 'error',
              title: 'Terjadi Kesalahan',
              text: 'Koneksi ke server terganggu.',
              confirmButtonColor: '#0284c7'
            });
          }
        }
      };
    }
    </script>
    @endpush
</x-app-layout>

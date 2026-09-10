<x-app-layout title="Analisa Stok">
    <div x-data="analisa()" x-init="load()">
        <div class="flex items-center justify-between mb-6">
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
                <template x-for="t in tabs" :key="t.key">
                    <button @click="tab=t.key; load()" :class="tab===t.key ? 'bg-white shadow text-primary-600' : 'text-gray-500'" class="px-4 py-2 text-sm font-medium rounded-lg" x-text="t.label"></button>
                </template>
            </div>
            @can('analisa.snapshot')
            <button @click="snapshot()" x-show="tab!=='riwayat'" class="px-4 py-2 bg-gray-700 text-white text-sm font-medium rounded-xl hover:bg-gray-800">Simpan Snapshot</button>
            @endcan
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200"><tr id="ahead"></tr></thead>
                <tbody id="abody"></tbody>
            </table>
            <p x-show="rows.length===0" class="p-6 text-center text-sm text-gray-400">Tidak ada data.</p>
        </div>
    </div>

    @push('scripts')
    <script>
    function analisa(){
      return {
        tab:'lokal', rows:[],
        tabs:[{key:'lokal',label:'Bahan Lokal'},{key:'impor',label:'Bahan Impor'},{key:'fulfillment',label:'Produk Jadi'},{key:'riwayat',label:'Riwayat'}],
        cols:{
          lokal:[['sku','SKU'],['nama','Nama'],['adu','ADU'],['batas_minimum','Batas Min'],['target_stock','Target'],['tersedia','Tersedia'],['selisih','Selisih'],['status','Status'],['qty_order','Qty Order']],
          impor:[['sku','SKU'],['nama','Nama'],['klasifikasi_abc','ABC'],['buffer_days','Buffer Hari'],['total_selisih','Selisih'],['status','Status'],['total_qty_order','Qty Order'],['total_nominal_order','Nominal']],
          fulfillment:[['sku','SKU'],['nama','Nama'],['batas_minimum_total','Batas Min'],['target_stock_total','Target'],['stok_total','Stok'],['akan_datang','Akan Datang'],['stok_all','Stok All'],['selisih','Selisih'],['status','Status'],['qty_order','Qty Order']],
          riwayat:[['tanggal','Tanggal'],['tipe','Tipe'],['item_label','Item'],['batas_minimum','Batas Min'],['target_stock','Target'],['status','Status'],['qty_order','Qty Order'],['pencatat','Oleh']],
        },
        urls:{lokal:'{{ route('analisa.lokal.data') }}',impor:'{{ route('analisa.impor.data') }}',fulfillment:'{{ route('analisa.fulfillment.data') }}',riwayat:'{{ route('analisa.riwayat.data') }}'},
        fmt(v){ return typeof v==='number' ? v.toLocaleString('id-ID',{maximumFractionDigits:2}) : (v??'-'); },
        badge(s){ const c = (s==='order'||s==='po')?'bg-red-50 text-red-600':'bg-emerald-50 text-emerald-600'; return `<span class="px-2 py-0.5 rounded-full text-xs ${c}">${s}</span>`; },
        async load(){
          const res = await fetch(this.urls[this.tab]); const j = await res.json(); this.rows = j.data||[];
          const cols = this.cols[this.tab];
          document.getElementById('ahead').innerHTML = cols.map(c=>`<th class="px-4 py-3 text-left font-medium text-gray-600">${c[1]}</th>`).join('');
          document.getElementById('abody').innerHTML = this.rows.map(r=>'<tr class="border-b border-gray-100">'+cols.map(c=>{
            let v=r[c[0]]; if(c[0]==='status') return `<td class="px-4 py-2">${this.badge(v)}</td>`;
            return `<td class="px-4 py-2">${this.fmt(v)}</td>`;
          }).join('')+'</tr>').join('');
        },
        async snapshot(){
          const map={lokal:'bahan_lokal',impor:'bahan_impor',fulfillment:'produk_jadi_fulfillment'};
          const fd=new FormData(); fd.append('tipe',map[this.tab]); fd.append('_token','{{ csrf_token() }}');
          const res=await fetch('{{ route('analisa.snapshot') }}',{method:'POST',body:fd,headers:{'Accept':'application/json'}});
          if(res.ok){ Swal.fire('Tersimpan','Snapshot analisa disimpan ke Riwayat.','success'); } else { Swal.fire('Gagal','Tidak dapat menyimpan snapshot.','error'); }
        }
      };
    }
    </script>
    @endpush
</x-app-layout>

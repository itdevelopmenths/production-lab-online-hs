<x-app-layout title="Laporan">
    <h3 class="text-lg font-semibold text-gray-900 mb-6">Laporan</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach([
            ['reports.production','Produksi','Ringkasan batch selesai & yield'],
            ['reports.material','Pemakaian Bahan','Total bahan terpakai dari batch'],
            ['reports.defect','Defect','Batch dengan unit rusak'],
            ['reports.low-stock','Variance / Opname','Selisih pemakaian teoritis vs aktual'],
            ['reports.purchasing','Purchasing / Hutang','Nilai PO & sisa tagihan'],
            ['reports.fulfillment','Pergerakan Stok Fulfillment','Mutasi produk jadi antar cabang'],
        ] as [$route,$title,$desc])
        <a href="{{ route($route) }}" class="block bg-white rounded-xl border border-gray-200 p-5 hover:border-primary-300 hover:shadow-sm transition">
            <p class="font-semibold text-gray-900">{{ $title }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $desc }}</p>
        </a>
        @endforeach
    </div>
</x-app-layout>

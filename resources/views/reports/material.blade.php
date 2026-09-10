<x-app-layout title="Pemakaian Bahan">
    <a href="{{ route('reports.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Laporan</a>
    <h3 class="text-lg font-semibold text-gray-900 mt-4 mb-6">Pemakaian Bahan</h3>
    <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">SKU</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Bahan</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Total Keluar (Produksi)</th>
            </tr></thead>
            <tbody>
                @forelse($rows as $r)
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-2">{{ $r->produk->sku ?? '-' }}</td>
                    <td class="px-4 py-2">{{ $r->produk->nama ?? '-' }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format($r->total_keluar,2,',','.') }} {{ $r->produk->satuan ?? '' }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">Belum ada pemakaian bahan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>

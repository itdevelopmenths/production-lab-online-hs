<x-app-layout title="Laporan Defect">
    <a href="{{ route('reports.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Laporan</a>
    <h3 class="text-lg font-semibold text-gray-900 mt-4 mb-6">Laporan Defect</h3>
    <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">No Batch</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Produk</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Baik</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Rusak</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Defect Rate</th>
            </tr></thead>
            <tbody>
                @forelse($batches as $b)
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-2">{{ $b->no_batch }}</td>
                    <td class="px-4 py-2">{{ $b->produk->nama }}</td>
                    <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($b->qty_baik,2),'0'),'.') }}</td>
                    <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($b->qty_rusak,2),'0'),'.') }}</td>
                    <td class="px-4 py-2 text-right">{{ $b->defectRate() !== null ? number_format($b->defectRate(),1).'%' : '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Tidak ada defect.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>

<x-app-layout title="Purchasing / Hutang">
    <a href="{{ route('reports.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Laporan</a>
    <h3 class="text-lg font-semibold text-gray-900 mt-4 mb-6">Purchasing / Hutang</h3>
    <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">No PO</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Supplier</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Nilai</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Dibayar</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Sisa</th>
            </tr></thead>
            <tbody>
                @forelse($pos as $po)
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-2">{{ $po->no_po }}</td>
                    <td class="px-4 py-2">{{ $po->supplier->nama }}</td>
                    <td class="px-4 py-2">{{ ucwords(str_replace('_',' ',$po->status)) }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format($po->total_nilai,0,',','.') }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format($po->total_dibayar ?? 0,0,',','.') }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format(($po->total_nilai ?? 0) - ($po->total_dibayar ?? 0),0,',','.') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Belum ada PO.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>

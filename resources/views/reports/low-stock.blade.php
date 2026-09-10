<x-app-layout title="Variance / Opname">
    <a href="{{ route('reports.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Laporan</a>
    <h3 class="text-lg font-semibold text-gray-900 mt-4 mb-6">Variance Pemakaian (Stock Opname)</h3>
    <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Batch</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Bahan</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Teoritis</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Aktual</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Variance</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Keterangan</th>
            </tr></thead>
            <tbody>
                @forelse($opname as $o)
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-2">{{ $o->batch->no_batch ?? '-' }}</td>
                    <td class="px-4 py-2">{{ $o->bahan->nama ?? '-' }}</td>
                    <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($o->pemakaian_teoritis,2),'0'),'.') }}</td>
                    <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($o->pemakaian_aktual,2),'0'),'.') }}</td>
                    <td class="px-4 py-2 text-right {{ $o->variance() > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($o->variance(),2,',','.') }}</td>
                    <td class="px-4 py-2 text-gray-500">{{ $o->keterangan ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Belum ada data opname.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>

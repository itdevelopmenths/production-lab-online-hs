<x-app-layout title="Pergerakan Stok Fulfillment">
    <a href="{{ route('reports.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Laporan</a>
    <h3 class="text-lg font-semibold text-gray-900 mt-4 mb-6">Pergerakan Stok Fulfillment</h3>
    <div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200"><tr>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tanggal</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Gudang</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Produk</th>
                <th class="px-4 py-3 text-left font-medium text-gray-600">Tipe</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Qty</th>
                <th class="px-4 py-3 text-right font-medium text-gray-600">Saldo</th>
            </tr></thead>
            <tbody>
                @forelse($rows as $r)
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-2">{{ $r->tanggal->format('d/m/Y') }}</td>
                    <td class="px-4 py-2">{{ $r->gudang->nama ?? '-' }}</td>
                    <td class="px-4 py-2">{{ $r->produk->nama ?? '-' }}</td>
                    <td class="px-4 py-2">{{ strtoupper($r->tipe) }}</td>
                    <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($r->qty,2),'0'),'.') }}</td>
                    <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($r->saldo_setelah,2),'0'),'.') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Belum ada pergerakan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>

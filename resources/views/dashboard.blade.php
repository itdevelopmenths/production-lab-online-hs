<x-app-layout title="Dashboard">
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-900">Halo, {{ $user->name }}</h3>
        <p class="text-sm text-gray-500">Role: {{ ucfirst($user->roleName() ?? '-') }}</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach([
            ['PO Perlu Tindakan', $stats['po_perlu_tindakan'], 'text-amber-600'],
            ['Request/Transfer Aktif', $stats['rt_perlu_tindakan'], 'text-indigo-600'],
            ['Batch Aktif', $stats['batch_aktif'], 'text-primary-600'],
            ['Batch Selesai', $stats['batch_selesai'], 'text-emerald-600'],
        ] as [$label,$val,$color])
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs text-gray-400 uppercase tracking-wide">{{ $label }}</p>
            <p class="text-2xl font-bold mt-2 {{ $color }}">{{ $val }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h4 class="text-sm font-semibold text-gray-800 mb-4">PO Perlu Tindakan</h4>
            <div class="space-y-2">
                @forelse($poTerbaru as $po)
                <a href="{{ route('purchasing.show', $po) }}" class="flex items-center justify-between text-sm py-2 border-b border-gray-100 hover:text-primary-600">
                    <span>{{ $po->no_po }} · {{ $po->supplier->nama }}</span>
                    <span class="text-xs text-gray-400">{{ ucwords(str_replace('_',' ',$po->status)) }}</span>
                </a>
                @empty
                <p class="text-sm text-gray-400">Tidak ada.</p>
                @endforelse
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h4 class="text-sm font-semibold text-gray-800 mb-4">Request &amp; Transfer Aktif</h4>
            <div class="space-y-2">
                @forelse($rtTerbaru as $rt)
                <a href="{{ route('rt.show', $rt) }}" class="flex items-center justify-between text-sm py-2 border-b border-gray-100 hover:text-primary-600">
                    <span>{{ $rt->no_transaksi }} · {{ ucwords(str_replace('_',' ',$rt->jenis)) }}</span>
                    <span class="text-xs text-gray-400">{{ ucwords(str_replace('_',' ',$rt->status)) }}</span>
                </a>
                @empty
                <p class="text-sm text-gray-400">Tidak ada.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>

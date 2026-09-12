@props(['po'])

@php
    $steps = [
        'draft' => ['label' => 'Draft PO', 'desc' => 'Dibuat Staf Purchasing', 'step' => 1],
        'diajukan' => ['label' => 'Diajukan', 'desc' => 'Menunggu Approval Manager', 'step' => 2],
        'disetujui' => ['label' => 'Disetujui', 'desc' => 'Disetujui Manager', 'step' => 3],
        'dikirim_ke_gudang' => ['label' => 'Kirim Gudang', 'desc' => 'Ekspedisi / Inbound', 'step' => 4],
        'diterima' => ['label' => 'Diterima Gudang', 'desc' => 'Barang Datang & Cek QC', 'step' => 5],
        'selesai' => ['label' => 'Selesai / Lunas', 'desc' => 'Barang & AP Lengkap', 'step' => 6],
    ];

    $isCancelled = $po->status === 'dibatalkan';
    $isReceived = $po->barangDatang->count() > 0;
    
    // Tentukan current step level (1 s/d 6)
    $currentStep = match($po->status) {
        'draft' => 1,
        'diajukan' => 2,
        'disetujui' => 3,
        'dikirim_ke_gudang' => $isReceived ? 5 : 4,
        'selesai' => 6,
        default => 1,
    };
@endphp

<div class="bg-white rounded-sm border border-gray-200 p-4 shadow-xs">
    @if($isCancelled)
        <div class="flex items-center justify-between p-3 bg-rose-50 border border-rose-200 rounded-sm">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 font-bold">
                    ✕
                </div>
                <div>
                    <h4 class="text-xs font-bold text-rose-900">Purchase Order Dibatalkan</h4>
                    <p class="text-[11px] text-rose-600">Dokumen ini telah dibatalkan dan tidak lagi diproses lebih lanjut.</p>
                </div>
            </div>
            <x-badge variant="danger" size="sm">Dibatalkan</x-badge>
        </div>
    @else
        <div class="relative">
            <div class="hidden sm:block absolute top-1/2 left-0 w-full h-0.5 bg-gray-200 -translate-y-1/2 z-0"></div>
            <div class="grid grid-cols-2 sm:grid-cols-6 gap-2 sm:gap-0 relative z-10">
                @foreach($steps as $key => $st)
                    @php
                        $isCompleted = $st['step'] < $currentStep || ($st['step'] == 6 && $po->status === 'selesai');
                        $isActive = $st['step'] == $currentStep && $po->status !== 'selesai';
                    @endphp
                    <div class="flex flex-col items-center text-center group">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition shadow-xs {{ $isCompleted ? 'bg-emerald-600 text-white ring-2 ring-emerald-100' : ($isActive ? 'bg-primary-600 text-white ring-4 ring-primary-100' : 'bg-white border border-gray-300 text-gray-400') }}">
                            @if($isCompleted)
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            @else
                                {{ $st['step'] }}
                            @endif
                        </div>
                        <div class="mt-1.5">
                            <span class="block text-[11px] font-bold {{ $isActive ? 'text-primary-700' : ($isCompleted ? 'text-gray-900' : 'text-gray-400') }}">
                                {{ $st['label'] }}
                            </span>
                            <span class="hidden sm:block text-[9px] text-gray-400 truncate max-w-[90px]">
                                {{ $st['desc'] }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

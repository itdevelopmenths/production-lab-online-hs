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
    
    $currentStep = match($po->status) {
        'draft' => 1,
        'diajukan' => 2,
        'disetujui' => 3,
        'dikirim_ke_gudang' => $isReceived ? 5 : 4,
        'selesai' => 6,
        default => 1,
    };
@endphp

<div class="bg-card rounded-xl border border-border p-6 shadow-xs">
    @if($isCancelled)
        <div class="flex items-center justify-between p-4 bg-red-50/80 border border-red-200 rounded-xl">
            <div class="flex items-center gap-3">
                <div class="size-9 rounded-full bg-red-100 flex items-center justify-center text-red-600 font-bold text-sm">
                    ✕
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-red-900">Purchase Order Dibatalkan</h4>
                    <p class="text-xs text-red-600 mt-0.5">Dokumen ini telah dibatalkan dan tidak lagi diproses lebih lanjut.</p>
                </div>
            </div>
            <x-badge variant="danger" size="sm">Dibatalkan</x-badge>
        </div>
    @else
        <div class="relative">
            <div class="hidden sm:block absolute top-1/2 left-0 w-full h-0.5 bg-border -translate-y-1/2 z-0"></div>
            <div class="grid grid-cols-2 sm:grid-cols-6 gap-3 sm:gap-0 relative z-10">
                @foreach($steps as $key => $st)
                    @php
                        $isCompleted = $st['step'] < $currentStep || ($st['step'] == 6 && $po->status === 'selesai');
                        $isActive = $st['step'] == $currentStep && $po->status !== 'selesai';
                    @endphp
                    <div class="flex flex-col items-center text-center group">
                        <div class="size-8 rounded-full flex items-center justify-center text-xs font-bold transition shadow-xs {{ $isCompleted ? 'bg-emerald-600 text-white ring-4 ring-emerald-50' : ($isActive ? 'bg-primary text-primary-foreground ring-4 ring-primary/10' : 'bg-card border border-border text-muted-foreground') }}">
                            @if($isCompleted)
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            @else
                                {{ $st['step'] }}
                            @endif
                        </div>
                        <div class="mt-2">
                            <span class="block text-xs font-semibold {{ $isActive ? 'text-foreground' : ($isCompleted ? 'text-foreground' : 'text-muted-foreground') }}">
                                {{ $st['label'] }}
                            </span>
                            <span class="hidden sm:block text-[10px] text-muted-foreground truncate max-w-[95px] mt-0.5">
                                {{ $st['desc'] }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

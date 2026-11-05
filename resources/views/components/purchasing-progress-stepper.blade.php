@props(['po', 'stageBenchmarks' => null])

@php
    $hasStages = $po->relationLoaded('stages') && $po->stages->isNotEmpty();
    $isCancelled = $po->status === 'dibatalkan';
@endphp

@if(!$hasStages)
    {{-- =========================================================================
         Graceful Fallback: Legacy 6-Step Stepper (PO Warisan / Belum Punya 9 Stage)
         ========================================================================= --}}
    @php
        $legacySteps = [
            'draft' => ['label' => 'Draft PO', 'desc' => 'Dibuat Staf Purchasing', 'step' => 1],
            'diajukan' => ['label' => 'Diajukan', 'desc' => 'Menunggu Approval Manager', 'step' => 2],
            'disetujui' => ['label' => 'Disetujui', 'desc' => 'Disetujui Manager', 'step' => 3],
            'dikirim_ke_gudang' => ['label' => 'Kirim Gudang', 'desc' => 'Ekspedisi / Inbound', 'step' => 4],
            'diterima' => ['label' => 'Diterima Gudang', 'desc' => 'Barang Datang & Cek QC', 'step' => 5],
            'selesai' => ['label' => 'Selesai / Lunas', 'desc' => 'Barang & AP Lengkap', 'step' => 6],
        ];

        $isReceived = $po->barangDatang ? $po->barangDatang->count() > 0 : false;
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
                        <p class="text-xs text-red-600 mt-0.5">{{ $po->alasan_batal ?? 'Dokumen ini telah dibatalkan.' }}</p>
                    </div>
                </div>
                <x-badge variant="danger" size="sm">Dibatalkan</x-badge>
            </div>
        @else
            <nav aria-label="Tahapan Purchase Order Legacy">
                <ol class="grid grid-cols-2 sm:grid-cols-6 gap-3 sm:gap-0 relative z-10 list-none m-0 p-0">
                    @foreach($legacySteps as $key => $st)
                        @php
                            $isCompleted = $st['step'] < $currentStep || ($st['step'] == 6 && $po->status === 'selesai');
                            $isActive = $st['step'] == $currentStep && $po->status !== 'selesai';
                        @endphp
                        <li class="flex flex-col items-center text-center group" @if($isActive) aria-current="step" @endif>
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
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
    </div>
@else
    {{-- =========================================================================
         PRD v2.4 9-Stage Progress Stepper & Lead Time Summary
         ========================================================================= --}}
    @php
        $stageDefs = [
            1 => ['key' => \App\Models\PoStage::STAGE_1, 'label' => '1. Perencanaan', 'cat' => 'internal'],
            2 => ['key' => \App\Models\PoStage::STAGE_2, 'label' => '2. Approval', 'cat' => 'internal'],
            3 => ['key' => \App\Models\PoStage::STAGE_3, 'label' => '3. Konfirmasi Supplier', 'cat' => 'supplier'],
            4 => ['key' => \App\Models\PoStage::STAGE_4, 'label' => '4. Pembayaran / DP', 'cat' => 'internal'],
            5 => ['key' => \App\Models\PoStage::STAGE_5, 'label' => '5. PO Terbit', 'cat' => 'internal'],
            6 => ['key' => \App\Models\PoStage::STAGE_6, 'label' => '6. Pengemasan', 'cat' => 'supplier'],
            7 => ['key' => \App\Models\PoStage::STAGE_7, 'label' => '7. Pengiriman', 'cat' => 'logistik'],
            8 => ['key' => \App\Models\PoStage::STAGE_8, 'label' => '8. Unloading', 'cat' => 'internal'],
            9 => ['key' => \App\Models\PoStage::STAGE_9, 'label' => '9. Input Sistem', 'cat' => 'internal'],
        ];

        // Hitung Subtotal Durasi per Kategori
        $internalMenit = 0;
        $supplierMenit = 0;
        $logistikMenit = 0;

        foreach ($stageDefs as $num => $def) {
            $sumMenit = (int) $po->stages->where('stage', $def['key'])->sum('durasi_menit');
            if ($def['cat'] === 'internal') {
                $internalMenit += $sumMenit;
            } elseif ($def['cat'] === 'supplier') {
                $supplierMenit += $sumMenit;
            } elseif ($def['cat'] === 'logistik') {
                $logistikMenit += $sumMenit;
            }
        }
        $totalLtMenit = $internalMenit + $supplierMenit + $logistikMenit;

        // Ambil benchmark supplier jika ada
        $benchmarks = $stageBenchmarks ?? ($po->supplier?->stageSummaries?->keyBy('stage') ?? collect());
    @endphp

    <div class="space-y-4">
        {{-- Card Stepper 9 Stage --}}
        <div class="bg-card rounded-xl border border-border p-4 sm:p-5 shadow-xs">
            @if($isCancelled)
                <div class="mb-4 flex items-center justify-between p-3.5 bg-rose-50/90 border border-rose-200 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="size-8 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 font-bold text-xs">✕</div>
                        <div>
                            <h4 class="text-xs font-bold text-rose-900">Purchase Order Dibatalkan</h4>
                            <p class="text-[11px] text-rose-700 mt-0.5">{{ $po->alasan_batal ?? 'Dibatalkan oleh manajemen.' }}</p>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-rose-200 text-rose-900">Dibatalkan</span>
                </div>
            @endif

            <nav aria-label="Tahapan Purchase Order">
                <ol class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-9 gap-3 list-none m-0 p-0">
                    @foreach($stageDefs as $num => $def)
                        @php
                            $stageKey = $def['key'];
                            $records = $po->stages->where('stage', $stageKey)->sortBy('putaran');
                            $latestRecord = $records->last();
                            $putaran = $records->max('putaran') ?? 1;
                            $durasiMenit = (int) $records->sum('durasi_menit');

                            // Tentukan State & Skema Warna
                            // 1. Abu-abu (belum mulai)
                            // 2. Biru (berjalan)
                            // 3. Merah (terlambat)
                            // 4. Oranye (dikembalikan)
                            // 5. Hijau (selesai)
                            $state = 'belum_mulai';
                            if ($latestRecord) {
                                if ($latestRecord->status === 'selesai') {
                                    $state = 'selesai';
                                } elseif ($latestRecord->status === 'dikembalikan') {
                                    $state = 'dikembalikan';
                                } elseif ($latestRecord->status === 'berjalan') {
                                    $state = 'berjalan';
                                    // Cek indikator terlambat terhadap average stage supplier
                                    if ($latestRecord->waktu_mulai) {
                                        $bm = $benchmarks->get($stageKey);
                                        if ($bm && $bm->jumlah_transaksi > 0 && $bm->total_menit > 0) {
                                            $avgMenit = $bm->total_menit / $bm->jumlah_transaksi;
                                            $elapsed = $latestRecord->waktu_mulai->diffInMinutes(now());
                                            if ($elapsed > $avgMenit) {
                                                $state = 'terlambat';
                                            }
                                        }
                                    }
                                }
                            }

                            $isCurrent = ($po->stage_aktif === $stageKey) && !$isCancelled && $po->status !== 'selesai';

                            // Tampilan Durasi
                            $displayJam = null;
                            $displayHari = null;
                            if ($durasiMenit > 0) {
                                $displayJam = \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($durasiMenit);
                                $displayHari = \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($durasiMenit);
                            } elseif ($latestRecord && $latestRecord->status === 'berjalan' && $latestRecord->waktu_mulai) {
                                $runMin = $latestRecord->waktu_mulai->diffInMinutes(now());
                                $displayJam = \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($runMin);
                                $displayHari = \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($runMin);
                            }

                            // PIC & Waktu
                            $picName = $latestRecord?->picUser?->name ?? $latestRecord?->recordedBy?->name;
                            $waktuMulai = $latestRecord?->waktu_mulai?->format('d/m H:i');
                            $waktuSelesai = $latestRecord?->waktu_selesai?->format('d/m H:i');

                            // Styling badge & box
                            $boxStyles = match($state) {
                                'selesai' => 'bg-emerald-50/60 border-emerald-300 text-emerald-950',
                                'berjalan' => 'bg-blue-50/80 border-blue-400 text-blue-950 ring-2 ring-blue-400/20',
                                'terlambat' => 'bg-rose-50/90 border-rose-400 text-rose-950 ring-2 ring-rose-400/20',
                                'dikembalikan' => 'bg-amber-50/80 border-amber-400 text-amber-950',
                                default => 'bg-muted/40 border-border/80 text-muted-foreground',
                            };

                            $circleStyles = match($state) {
                                'selesai' => 'bg-emerald-600 text-white font-bold',
                                'berjalan' => 'bg-blue-600 text-white font-bold animate-pulse',
                                'terlambat' => 'bg-rose-600 text-white font-bold animate-bounce',
                                'dikembalikan' => 'bg-amber-500 text-white font-bold',
                                default => 'bg-muted border border-border text-muted-foreground',
                            };
                        @endphp

                        <li class="flex flex-col p-2.5 rounded-xl border {{ $boxStyles }} transition-all text-xs"
                            @if($isCurrent) aria-current="step" @endif>
                            
                            {{-- Header Step: Nomor & Badge Putaran --}}
                            <div class="flex items-center justify-between gap-1 mb-1.5">
                                <div class="size-6 rounded-full flex items-center justify-center text-[11px] shrink-0 {{ $circleStyles }}">
                                    @if($state === 'selesai')
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        {{ $num }}
                                    @endif
                                </div>

                                @if($putaran > 1)
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-200 text-amber-900 border border-amber-300" title="Melewati {{ $putaran }} putaran">
                                        P-{{ $putaran }}
                                    </span>
                                @endif
                            </div>

                            {{-- Nama Tahap --}}
                            <div class="font-bold text-[11px] leading-tight mb-1 truncate" title="{{ $def['label'] }}">
                                {{ $def['label'] }}
                            </div>

                            {{-- Waktu & Status Badge --}}
                            <div class="space-y-0.5 text-[10px] opacity-90 mt-auto pt-1 border-t border-border/40">
                                @if($state === 'selesai')
                                    <span class="text-emerald-700 font-semibold block">✓ Selesai</span>
                                    @if($waktuSelesai)
                                        <span class="font-mono text-muted-foreground block">{{ $waktuSelesai }}</span>
                                    @endif
                                @elseif($state === 'terlambat')
                                    <span class="text-rose-700 font-bold block">⚠️ Terlambat</span>
                                    @if($waktuMulai)
                                        <span class="font-mono text-rose-600 block">Mulai: {{ $waktuMulai }}</span>
                                    @endif
                                @elseif($state === 'berjalan')
                                    <span class="text-blue-700 font-semibold block">● Berjalan</span>
                                    @if($waktuMulai)
                                        <span class="font-mono text-muted-foreground block">Mulai: {{ $waktuMulai }}</span>
                                    @endif
                                @elseif($state === 'dikembalikan')
                                    <span class="text-amber-700 font-semibold block">↩ Dikembalikan</span>
                                @else
                                    <span class="text-muted-foreground italic block">Belum mulai</span>
                                @endif

                                {{-- PIC --}}
                                @if($picName)
                                    <span class="truncate block text-muted-foreground text-[9px]" title="PIC: {{ $picName }}">
                                        PIC: {{ Str::limit($picName, 12) }}
                                    </span>
                                @endif

                                {{-- Durasi (Jam 2 desimal, Hari 1 desimal) --}}
                                @if($displayHari !== null)
                                    <div class="font-mono text-[9px] font-semibold text-foreground/80 pt-0.5">
                                        {{ $displayHari }} hr <span class="text-muted-foreground font-normal">({{ $displayJam }} j)</span>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </nav>
        </div>

        {{-- Bar Ringkasan Subtotal Lead Time --}}
        <div class="bg-card rounded-xl border border-border p-3.5 shadow-xs grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div class="p-2.5 rounded-lg bg-muted/40 border border-border">
                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground block">LT Internal (1,2,4,5,8,9)</span>
                <p class="font-mono text-sm font-bold text-foreground mt-0.5">
                    {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($internalMenit) }} hari
                    <span class="text-[11px] font-normal text-muted-foreground font-mono">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($internalMenit) }} jam)</span>
                </p>
            </div>

            <div class="p-2.5 rounded-lg bg-muted/40 border border-border">
                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground block">LT Supplier (3, 6)</span>
                <p class="font-mono text-sm font-bold text-foreground mt-0.5">
                    {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($supplierMenit) }} hari
                    <span class="text-[11px] font-normal text-muted-foreground font-mono">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($supplierMenit) }} jam)</span>
                </p>
            </div>

            <div class="p-2.5 rounded-lg bg-muted/40 border border-border">
                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground block">LT Logistik (7)</span>
                <p class="font-mono text-sm font-bold text-foreground mt-0.5">
                    {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($logistikMenit) }} hari
                    <span class="text-[11px] font-normal text-muted-foreground font-mono">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($logistikMenit) }} jam)</span>
                </p>
            </div>

            <div class="p-2.5 rounded-lg bg-primary-50/50 border border-primary-200">
                <span class="text-[10px] font-bold uppercase tracking-wider text-primary-900 block">Total Lead Time</span>
                <p class="font-mono text-sm font-bold text-primary-700 mt-0.5">
                    {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($totalLtMenit) }} hari
                    <span class="text-[11px] font-normal text-primary-600 font-mono">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($totalLtMenit) }} jam)</span>
                </p>
            </div>
        </div>
    </div>
@endif

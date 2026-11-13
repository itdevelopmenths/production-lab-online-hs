@props(['po', 'stageBenchmarks' => null])

@php
    $hasStages = $po->relationLoaded('stages') && $po->stages->isNotEmpty();
    $isCancelled = $po->status === 'dibatalkan';
@endphp

@if(!$hasStages)
    {{-- =========================================================================
         Graceful Fallback: Legacy 6-Step Stepper (PO Warisan)
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

    <div class="bg-card rounded-2xl border border-border/80 p-5 shadow-xs">
        @if($isCancelled)
            <div class="flex items-center justify-between p-3.5 bg-rose-50/80 border border-rose-200 rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="size-8 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 font-bold text-xs">✕</div>
                    <div>
                        <h4 class="text-xs font-bold text-rose-900">Purchase Order Dibatalkan</h4>
                        <p class="text-[11px] text-rose-600 mt-0.5">{{ $po->alasan_batal ?? 'Dokumen ini telah dibatalkan.' }}</p>
                    </div>
                </div>
                <x-badge variant="danger" size="xs">Dibatalkan</x-badge>
            </div>
        @else
            <nav aria-label="Tahapan Purchase Order Legacy">
                <ol class="grid grid-cols-2 sm:grid-cols-6 gap-3 sm:gap-0 relative z-10 list-none m-0 p-0 pt-2">
                    @foreach($legacySteps as $key => $st)
                        @php
                            $isCompleted = $st['step'] < $currentStep || ($st['step'] == 6 && $po->status === 'selesai');
                            $isActive = $st['step'] == $currentStep && $po->status !== 'selesai';
                        @endphp
                        <li class="flex flex-col items-center text-center group" @if($isActive) aria-current="step" @endif>
                            <div class="size-8 rounded-full flex items-center justify-center text-xs font-bold transition shadow-xs {{ $isCompleted ? 'bg-emerald-600 text-white ring-4 ring-emerald-50' : ($isActive ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 ring-4 ring-primary/20' : 'bg-card border border-border text-muted-foreground') }}">
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
         PRD v2.4 9-Stage Progress Stepper (Redesigned: Clean, Simpel & Elegan)
         ========================================================================= --}}
    @php
        $stageDefs = [
            1 => ['key' => \App\Models\PoStage::STAGE_1, 'short' => 'Perencanaan', 'sub' => 'Draft PO', 'cat' => 'internal'],
            2 => ['key' => \App\Models\PoStage::STAGE_2, 'short' => 'Approval', 'sub' => 'Manager', 'cat' => 'internal'],
            3 => ['key' => \App\Models\PoStage::STAGE_3, 'short' => 'Supplier Confirm', 'sub' => 'Konfirmasi Vendor', 'cat' => 'internal'],
            4 => ['key' => \App\Models\PoStage::STAGE_4, 'short' => 'Payment', 'sub' => 'Keuangan / DP', 'cat' => 'internal'],
            5 => ['key' => \App\Models\PoStage::STAGE_5, 'short' => 'PO', 'sub' => 'Penerbitan Resmi', 'cat' => 'supplier'],
            6 => ['key' => \App\Models\PoStage::STAGE_6, 'short' => 'Pengemasan', 'sub' => 'Packing Vendor', 'cat' => 'supplier'],
            7 => ['key' => \App\Models\PoStage::STAGE_7, 'short' => 'Pengiriman', 'sub' => 'Ekspedisi / Transit', 'cat' => 'transportation'],
            8 => ['key' => \App\Models\PoStage::STAGE_8, 'short' => 'Unloading', 'sub' => 'Fisik Gudang', 'cat' => 'receiving'],
            9 => ['key' => \App\Models\PoStage::STAGE_9, 'short' => 'Input', 'sub' => 'Posting Stok', 'cat' => 'receiving'],
        ];

        // Durasi Subtotal per Kategori (Klasifikasi Sesuai Lampiran 1)
        $internalMenit = 0;
        $supplierMenit = 0;
        $transportationMenit = 0;
        $receivingMenit = 0;

        foreach ($stageDefs as $num => $def) {
            $sumMenit = (int) $po->stages->where('stage', $def['key'])->sum('durasi_menit');
            if ($def['cat'] === 'internal') {
                $internalMenit += $sumMenit;
            } elseif ($def['cat'] === 'supplier') {
                $supplierMenit += $sumMenit;
            } elseif ($def['cat'] === 'transportation') {
                $transportationMenit += $sumMenit;
            } elseif ($def['cat'] === 'receiving') {
                $receivingMenit += $sumMenit;
            }
        }
        $totalLtMenit = $internalMenit + $supplierMenit + $transportationMenit + $receivingMenit;
        $benchmarks = $stageBenchmarks ?? ($po->supplier?->stageSummaries?->keyBy('stage') ?? collect());

        $activeStageNum = null;
        $activeStageLabel = null;
        $activeStageState = null;
        $activePic = null;
        $activeStartTime = null;

        foreach ($stageDefs as $num => $def) {
            if ($po->stage_aktif === $def['key']) {
                $activeStageNum = $num;
                $activeStageLabel = $def['short'];
                $rec = $po->stages->where('stage', $def['key'])->last();
                $activePic = $rec?->picUser?->name ?? $rec?->recordedBy?->name;
                $activeStartTime = $rec?->waktu_mulai;
                break;
            }
        }
    @endphp

    <div class="bg-card rounded-2xl border border-border/80 p-5 shadow-xs space-y-4">
        {{-- Banner jika Dibatalkan --}}
        @if($isCancelled)
            <div class="flex items-center justify-between p-3.5 bg-rose-50/90 border border-rose-200 rounded-xl">
                <div class="flex items-center gap-3">
                    <div class="size-8 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 font-bold text-xs">✕</div>
                    <div>
                        <h4 class="text-xs font-bold text-rose-900">Purchase Order Dibatalkan</h4>
                        <p class="text-[11px] text-rose-700 mt-0.5">{{ $po->alasan_batal ?? 'Dibatalkan oleh manajemen.' }}</p>
                    </div>
                </div>
                <x-badge variant="danger" size="xs">Dibatalkan</x-badge>
            </div>
        @endif

        {{-- Progress Timeline 9-Stage --}}
        <div class="overflow-x-auto pt-3 pb-2 px-3 scrollbar-none" tabindex="0" role="region" aria-label="Scroll Tahapan Purchase Order">
            <nav aria-label="Tahapan Purchase Order" class="min-w-[44rem] lg:min-w-0">
                {{-- Group Header Klasifikasi Lead Time (Sesuai Lampiran 1) --}}
                <div class="grid grid-cols-9 gap-2 mb-3.5 select-none" role="group" aria-label="Klasifikasi Fase Purchase Order">
                    {{-- 1. Internal Processing (Stage 1-4) --}}
                    <div class="col-span-4 rounded-xl bg-card border border-border px-3 py-1.5 flex items-center justify-center gap-2 shadow-2xs min-w-0"
                         title="Internal Processing: Tahap 1–4 (Perencanaan, Approval, Supplier Confirm, Payment)">
                        <span class="size-2 rounded-full bg-blue-600 shrink-0"></span>
                        <span class="text-xs font-bold text-foreground tracking-tight truncate">Internal Processing</span>
                    </div>

                    {{-- 2. Supplier Processing (Stage 5-6) --}}
                    <div class="col-span-2 rounded-xl bg-card border border-border px-2.5 py-1.5 flex items-center justify-center gap-2 shadow-2xs min-w-0"
                         title="Supplier Processing: Tahap 5–6 (PO, Pengemasan)">
                        <span class="size-2 rounded-full bg-amber-500 shrink-0"></span>
                        <span class="text-xs font-bold text-foreground tracking-tight truncate">Supplier Processing</span>
                    </div>

                    {{-- 3. Transportation Time (Stage 7) --}}
                    <div class="col-span-1 rounded-xl bg-card border border-border px-1 py-1.5 flex items-center justify-center gap-1.5 shadow-2xs min-w-0"
                         title="Transportation Time: Tahap 7 (Pengiriman)">
                        <span class="size-2 rounded-full bg-purple-600 shrink-0"></span>
                        <span class="text-xs font-bold text-foreground truncate" title="Transportation Time">Transport</span>
                    </div>

                    {{-- 4. Receiving (Stage 8-9) --}}
                    <div class="col-span-2 rounded-xl bg-card border border-border px-2.5 py-1.5 flex items-center justify-center gap-2 shadow-2xs min-w-0"
                         title="Receiving: Tahap 8–9 (Unloading, Input)">
                        <span class="size-2 rounded-full bg-emerald-600 shrink-0"></span>
                        <span class="text-xs font-bold text-foreground tracking-tight truncate">Receiving</span>
                    </div>
                </div>

                <ol class="flex items-start justify-between relative list-none m-0 p-0 pt-2">
                    {{-- Continuous Connecting Line behind nodes --}}
                    <div class="absolute top-6 left-6 right-6 h-0.5 bg-border -z-0"></div>

                    @foreach($stageDefs as $num => $def)
                        @php
                            $stageKey = $def['key'];
                            $records = $po->stages->where('stage', $stageKey)->sortBy('putaran');
                            $latestRecord = $records->last();
                            $putaran = $records->max('putaran') ?? 1;
                            $durasiMenit = (int) $records->sum('durasi_menit');

                            $state = 'belum_mulai';
                            if ($latestRecord) {
                                if ($latestRecord->status === 'selesai') {
                                    $state = 'selesai';
                                } elseif ($latestRecord->status === 'dikembalikan') {
                                    $state = 'dikembalikan';
                                } elseif ($latestRecord->status === 'berjalan') {
                                    $state = 'berjalan';
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
                            if ($isCurrent) {
                                $activeStageState = $state;
                            }

                            // Realisasi Lead Time Stage (termasuk stage berjalan)
                            if ($latestRecord && in_array($latestRecord->status, ['berjalan', 'terlambat']) && $latestRecord->waktu_mulai) {
                                $elapsedRunning = (int) $latestRecord->waktu_mulai->diffInMinutes(now());
                                $stageLtMenit = $durasiMenit + $elapsedRunning;
                            } else {
                                $stageLtMenit = $durasiMenit;
                            }
                            $stageLtHari = \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($stageLtMenit);
                            $stageLtJam = \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($stageLtMenit);

                            $nodeClass = match($state) {
                                'selesai' => 'bg-emerald-600 text-white ring-4 ring-emerald-50 dark:ring-emerald-950/40 shadow-xs',
                                'berjalan' => 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 ring-4 ring-primary/20 shadow-md animate-pulse',
                                'terlambat' => 'bg-rose-600 text-white ring-4 ring-rose-200 dark:ring-rose-900/50 shadow-md animate-pulse',
                                'dikembalikan' => 'bg-amber-500 text-white ring-4 ring-amber-100 dark:ring-amber-950/40 shadow-xs',
                                default => 'bg-card border-2 border-border text-muted-foreground',
                            };

                            $textClass = match($state) {
                                'selesai' => 'text-foreground font-semibold',
                                'berjalan' => 'text-primary font-bold',
                                'terlambat' => 'text-rose-600 font-bold',
                                'dikembalikan' => 'text-amber-600 font-bold',
                                default => 'text-muted-foreground font-normal',
                            };
                        @endphp

                        <li class="flex flex-col items-center text-center group flex-1 min-w-0 px-1 relative z-10"
                            @if($isCurrent) aria-current="step" @endif>
                            
                            {{-- Step Node Circle --}}
                            <div class="size-8 rounded-full flex items-center justify-center text-xs font-bold transition {{ $nodeClass }}"
                                 title="Tahap {{ $num }}: {{ $def['short'] }} ({{ ucfirst(str_replace('_',' ',$state)) }})">
                                @if($state === 'selesai')
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                @elseif($state === 'dikembalikan')
                                    <span class="text-xs">↩</span>
                                @elseif($state === 'terlambat')
                                    <span class="text-xs font-black">!</span>
                                @else
                                    {{ $num }}
                                @endif
                            </div>

                            {{-- Step Title & Subtitle --}}
                            <div class="mt-2 space-y-0.5">
                                <span class="block text-[11px] leading-tight {{ $textClass }} text-balance">
                                    {{ $def['short'] }}
                                </span>
                                <span class="hidden sm:block text-[10px] leading-tight text-muted-foreground">
                                    @if($state === 'selesai')
                                        ✓ Selesai
                                    @elseif($state === 'berjalan')
                                        ● Berjalan
                                    @elseif($state === 'terlambat')
                                        ⚠️ Terlambat
                                    @elseif($state === 'dikembalikan')
                                        ↩ Ditolak
                                    @else
                                        {{ $def['sub'] }}
                                    @endif
                                </span>
                            </div>

                            {{-- Hasil Lead Time per Stage --}}
                            <div class="mt-1.5">
                                @if($state === 'selesai')
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md font-mono text-[10px] font-semibold bg-muted/60 text-foreground border border-border/80"
                                          title="Lead Time Selesai: {{ $stageLtHari }} hari / {{ $stageLtJam }} jam ({{ $stageLtMenit }} menit)">
                                        <span>{{ $stageLtHari }} hr</span>
                                        @if($stageLtMenit > 0 && $stageLtMenit < 1440)
                                            <span class="text-[9px] text-muted-foreground font-normal">({{ $stageLtJam }}j)</span>
                                        @endif
                                    </span>
                                @elseif($state === 'berjalan' || $state === 'terlambat')
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md font-mono text-[10px] font-bold bg-card text-foreground border {{ $state === 'terlambat' ? 'border-rose-500 ring-1 ring-rose-500/30' : 'border-primary ring-1 ring-primary/30' }} shadow-2xs"
                                          title="Lead Time Berjalan: {{ $stageLtHari }} hari / {{ $stageLtJam }} jam ({{ $stageLtMenit }} menit)">
                                        <span class="size-1.5 rounded-full {{ $state === 'terlambat' ? 'bg-rose-600 animate-ping' : 'bg-primary animate-pulse' }}"></span>
                                        <span>{{ $stageLtHari }} hr</span>
                                        @if($stageLtMenit > 0 && $stageLtMenit < 1440)
                                            <span class="text-[9px] text-muted-foreground font-normal">({{ $stageLtJam }}j)</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-md font-mono text-[10px] text-muted-foreground/50 bg-muted/20 border border-border/40"
                                          title="Lead Time: Belum Dimulai (0 menit)">
                                        0 hr
                                    </span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </nav>
        </div>

        {{-- Strip Ringkasan Terpadu: Tahap Aktif & Subtotal Lead Time --}}
        <div class="border-t border-border/60 pt-3 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
            {{-- Kiri: Highlight Tahap Aktif --}}
            <div class="flex items-center gap-2">
                @if($po->status === 'selesai')
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-300">
                        <svg class="size-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Seluruh 9 Tahap Selesai (PO Closed)
                    </span>
                @elseif($isCancelled)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300">
                        PO Dibatalkan
                    </span>
                @elseif($activeStageNum)
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground text-[11px]">Tahap Aktif:</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold {{ $activeStageState === 'terlambat' ? 'bg-rose-100 text-rose-900 border border-rose-300' : 'bg-primary/10 text-primary border border-primary/20' }}">
                            <span class="size-2 rounded-full {{ $activeStageState === 'terlambat' ? 'bg-rose-600 animate-ping' : 'bg-primary animate-pulse' }}"></span>
                            Tahap {{ $activeStageNum }}: {{ $activeStageLabel }}
                        </span>
                        @if($activePic)
                            <span class="text-[11px] text-muted-foreground hidden sm:inline">
                                · PIC: <strong>{{ $activePic }}</strong>
                            </span>
                        @endif
                        @if($activeStartTime)
                            <span class="text-[11px] text-muted-foreground hidden sm:inline">
                                · Mulai: {{ $activeStartTime->format('d/m H:i') }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Kanan: Metrik Lead Time Bersih (Internal, Supplier, Transportation, Receiving, Total LT) Sesuai Lampiran 1 & 2 --}}
            <div class="flex flex-wrap items-center gap-2 text-[11px]">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-muted/50 border border-border/80 font-mono text-muted-foreground" title="Internal Processing (Stage 1-4: Perencanaan, Approval, Supplier Confirm, Payment)">
                    <span class="size-1.5 rounded-full bg-sky-500 shrink-0"></span>
                    <span>Internal<span class="hidden xl:inline"> Processing</span>:</span>
                    <strong class="text-foreground font-bold">{{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($internalMenit) }} hr</strong>
                </div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-muted/50 border border-border/80 font-mono text-muted-foreground" title="Supplier Processing (Stage 5-6: PO, Pengemasan)">
                    <span class="size-1.5 rounded-full bg-amber-500 shrink-0"></span>
                    <span>Supplier<span class="hidden xl:inline"> Processing</span>:</span>
                    <strong class="text-foreground font-bold">{{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($supplierMenit) }} hr</strong>
                </div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-muted/50 border border-border/80 font-mono text-muted-foreground" title="Transportation Time (Stage 7: Pengiriman)">
                    <span class="size-1.5 rounded-full bg-violet-500 shrink-0"></span>
                    <span>Transportation<span class="hidden xl:inline"> Time</span>:</span>
                    <strong class="text-foreground font-bold">{{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($transportationMenit) }} hr</strong>
                </div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-muted/50 border border-border/80 font-mono text-muted-foreground" title="Receiving (Stage 8-9: Unloading, Input)">
                    <span class="size-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                    <span>Receiving:</span>
                    <strong class="text-foreground font-bold">{{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($receivingMenit) }} hr</strong>
                </div>
                <div class="px-2.5 py-1 rounded-md bg-primary-50/70 dark:bg-primary-950/30 border border-primary-200 dark:border-primary-800/40 font-mono font-bold text-primary-700 dark:text-primary-300">
                    Total LT: {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($totalLtMenit) }} Hari
                    <span class="font-normal text-[10px] text-muted-foreground">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($totalLtMenit) }} j)</span>
                </div>
            </div>
        </div>
    </div>
@endif

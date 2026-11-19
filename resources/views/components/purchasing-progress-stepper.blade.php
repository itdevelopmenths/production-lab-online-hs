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

    <div class="rounded-[12px] bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-border p-4 sm:p-5 shadow-theme-xs">
        @if($isCancelled)
            <div class="flex items-center justify-between p-3.5 bg-red-50/90 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-[10px]">
                <div class="flex items-center gap-3">
                    <div class="size-8 rounded-full bg-red-100 dark:bg-red-500/20 flex items-center justify-center text-red-600 dark:text-red-400 font-medium text-xs shrink-0">✕</div>
                    <div>
                        <h4 class="text-xs font-semibold text-red-900 dark:text-red-200">Purchase Order Dibatalkan</h4>
                        <p class="text-[11px] text-red-700 dark:text-red-300 mt-0.5">{{ $po->alasan_batal ?? 'Dokumen ini telah dibatalkan.' }}</p>
                    </div>
                </div>
                <x-badge variant="danger" size="xs" :pill="true">Dibatalkan</x-badge>
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
                            <div class="size-8 rounded-full flex items-center justify-center text-xs font-medium transition {{ $isCompleted ? 'bg-[#00bc7d] text-white shadow-[0px_0px_0px_4px_rgba(0,188,125,0.18)]' : ($isActive ? 'bg-brand-500 dark:bg-white text-white dark:text-gray-900 shadow-lg ring-4 ring-brand-500/15 dark:ring-white/20' : 'bg-white dark:bg-gray-800 border border-[#E8E8E8] dark:border-gray-700 text-text-muted') }}">
                                @if($isCompleted)
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    {{ $st['step'] }}
                                @endif
                            </div>
                            <div class="mt-2">
                                <span class="block text-xs font-medium {{ $isActive || $isCompleted ? 'text-text-active' : 'text-text-muted' }}">
                                    {{ $st['label'] }}
                                </span>
                                <span class="hidden sm:block text-[10px] text-text-muted truncate max-w-[95px] mt-0.5">
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
         PRD v2.4 8-Stage Progress Stepper (Konsolidasi Pengemasan ke Pengiriman)
         ========================================================================= --}}
    @php
        $stageDefs = [
            1 => ['key' => \App\Models\PoStage::STAGE_1, 'short' => 'Perencanaan', 'sub' => 'Draft PO', 'cat' => 'internal'],
            2 => ['key' => \App\Models\PoStage::STAGE_2, 'short' => 'Approval', 'sub' => 'Manager', 'cat' => 'internal'],
            3 => ['key' => \App\Models\PoStage::STAGE_3, 'short' => 'Supplier Confirm', 'sub' => 'Konfirmasi Vendor', 'cat' => 'internal'],
            4 => ['key' => \App\Models\PoStage::STAGE_4, 'short' => 'Payment', 'sub' => 'Keuangan / DP', 'cat' => 'internal'],
            5 => ['key' => \App\Models\PoStage::STAGE_5, 'short' => 'PO', 'sub' => 'Penerbitan Resmi', 'cat' => 'supplier'],
            6 => ['key' => \App\Models\PoStage::STAGE_6, 'short' => 'Pengiriman', 'sub' => 'Ekspedisi / Transit', 'cat' => 'transportation'],
            7 => ['key' => \App\Models\PoStage::STAGE_7, 'short' => 'Unloading', 'sub' => 'Fisik Gudang', 'cat' => 'receiving'],
            8 => ['key' => \App\Models\PoStage::STAGE_8, 'short' => 'Input', 'sub' => 'Posting Stok', 'cat' => 'receiving'],
        ];

        $internalMenit = 0;
        $supplierMenit = 0;
        $transportationMenit = 0;
        $receivingMenit = 0;

        $legacyPengemasanMenit = (int) $po->stages->where('stage', \App\Models\PoStage::LEGACY_STAGE_PENGEMASAN)->sum('durasi_menit');

        foreach ($stageDefs as $num => $def) {
            $sumMenit = (int) $po->stages->where('stage', $def['key'])->sum('durasi_menit');
            if ($def['cat'] === 'internal') {
                $internalMenit += $sumMenit;
            } elseif ($def['cat'] === 'supplier') {
                $supplierMenit += ($sumMenit + $legacyPengemasanMenit);
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

    <div class="rounded-[12px] bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-border p-4 sm:p-5 shadow-theme-xs space-y-4">
        {{-- Banner jika Dibatalkan --}}
        @if($isCancelled)
            <div class="flex items-center justify-between p-3.5 bg-red-50/90 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-[10px]">
                <div class="flex items-center gap-3">
                    <div class="size-8 rounded-full bg-red-100 dark:bg-red-500/20 flex items-center justify-center text-red-600 dark:text-red-400 font-medium text-xs shrink-0">✕</div>
                    <div>
                        <h4 class="text-xs font-semibold text-red-900 dark:text-red-200">Purchase Order Dibatalkan</h4>
                        <p class="text-[11px] text-red-700 dark:text-red-300 mt-0.5">{{ $po->alasan_batal ?? 'Dibatalkan oleh manajemen.' }}</p>
                    </div>
                </div>
                <x-badge variant="danger" size="xs" :pill="true">Dibatalkan</x-badge>
            </div>
        @endif

        {{-- Progress Timeline 8-Stage --}}
        <div class="overflow-x-auto pt-2 pb-2 px-2 no-scrollbar" tabindex="0" role="region" aria-label="Scroll Tahapan Purchase Order">
            <nav aria-label="Tahapan Purchase Order" class="min-w-[44rem] lg:min-w-0">
                {{-- Group Header Klasifikasi Lead Time --}}
                <div class="grid grid-cols-8 gap-2 mb-4 select-none" role="group" aria-label="Klasifikasi Fase Purchase Order">
                    <div class="col-span-4 rounded-[8px] bg-[#FBFBFB] dark:bg-gray-800/60 border border-[#E8E8E8] dark:border-border px-3 py-1.5 flex items-center justify-center gap-2 shadow-theme-xs min-w-0"
                         title="Internal Processing: Tahap 1–4 (Perencanaan, Approval, Supplier Confirm, Payment)">
                        <span class="size-2 rounded-full bg-gray-400 shrink-0"></span>
                        <span class="text-xs font-medium text-text-active tracking-tight truncate">Internal Processing</span>
                    </div>

                    <div class="col-span-1 rounded-[8px] bg-[#FBFBFB] dark:bg-gray-800/60 border border-[#E8E8E8] dark:border-border px-1.5 py-1.5 flex items-center justify-center gap-1.5 shadow-theme-xs min-w-0"
                         title="Supplier Processing: Tahap 5 (Penerbitan PO)">
                        <span class="size-2 rounded-full bg-gray-400 shrink-0"></span>
                        <span class="text-xs font-medium text-text-active tracking-tight truncate">Supplier</span>
                    </div>

                    <div class="col-span-1 rounded-[8px] bg-[#FBFBFB] dark:bg-gray-800/60 border border-[#E8E8E8] dark:border-border px-1.5 py-1.5 flex items-center justify-center gap-1.5 shadow-theme-xs min-w-0"
                         title="Transportation Time: Tahap 6 (Pengiriman)">
                        <span class="size-2 rounded-full bg-gray-400 shrink-0"></span>
                        <span class="text-xs font-medium text-text-active truncate" title="Transportation Time">Transport</span>
                    </div>

                    <div class="col-span-2 rounded-[8px] bg-[#FBFBFB] dark:bg-gray-800/60 border border-[#E8E8E8] dark:border-border px-2.5 py-1.5 flex items-center justify-center gap-2 shadow-theme-xs min-w-0"
                         title="Receiving: Tahap 7–8 (Unloading, Input)">
                        <span class="size-2 rounded-full bg-gray-400 shrink-0"></span>
                        <span class="text-xs font-medium text-text-active tracking-tight truncate">Receiving</span>
                    </div>
                </div>

                <ol class="flex items-start justify-between relative list-none m-0 p-0 pt-2">
                    {{-- Continuous Connecting Line behind nodes --}}
                    <div class="absolute top-6 left-6 right-6 h-0.5 bg-gray-100 dark:bg-gray-800 -z-0"></div>

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

                            if ($latestRecord && in_array($latestRecord->status, ['berjalan', 'terlambat']) && $latestRecord->waktu_mulai) {
                                $elapsedRunning = (int) $latestRecord->waktu_mulai->diffInMinutes(now());
                                $stageLtMenit = $durasiMenit + $elapsedRunning;
                            } else {
                                $stageLtMenit = $durasiMenit;
                            }
                            $stageLtHari = \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($stageLtMenit);
                            $stageLtJam = \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($stageLtMenit);

                            $nodeClass = match($state) {
                                'selesai' => 'bg-[#00bc7d] text-white shadow-[0px_0px_0px_4px_rgba(0,188,125,0.18)]',
                                'berjalan' => 'bg-brand-500 dark:bg-white text-white dark:text-gray-900 shadow-lg ring-4 ring-brand-500/15 dark:ring-white/20 animate-pulse',
                                'terlambat' => 'bg-[#F04438] text-white ring-4 ring-red-500/20 shadow-md animate-pulse',
                                'dikembalikan' => 'bg-gray-900 dark:bg-white text-white dark:text-gray-900 ring-4 ring-border shadow-xs',
                                default => 'bg-white dark:bg-gray-800 border-2 border-[#E8E8E8] dark:border-gray-700 text-text-muted',
                            };

                            $textClass = match($state) {
                                'selesai' => 'text-text-active font-medium',
                                'berjalan' => 'text-text-active font-medium',
                                'terlambat' => 'text-red-600 dark:text-red-400 font-medium',
                                'dikembalikan' => 'text-foreground dark:text-foreground font-medium',
                                default => 'text-text-muted font-normal',
                            };
                        @endphp

                        <li class="flex flex-col items-center text-center group flex-1 min-w-0 px-1 relative z-10"
                            @if($isCurrent) aria-current="step" @endif>

                            {{-- Step Node Circle --}}
                            <div class="size-8 rounded-full flex items-center justify-center text-xs font-medium transition {{ $nodeClass }}"
                                 title="Tahap {{ $num }}: {{ $def['short'] }} ({{ ucfirst(str_replace('_',' ',$state)) }})">
                                @if($state === 'selesai')
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                @elseif($state === 'dikembalikan')
                                    <span class="text-xs">↩</span>
                                @elseif($state === 'terlambat')
                                    <span class="text-xs font-medium">!</span>
                                @else
                                    {{ $num }}
                                @endif
                            </div>

                            {{-- Step Title & Subtitle --}}
                            <div class="mt-2 space-y-0.5">
                                <span class="block text-[11px] leading-tight {{ $textClass }} text-balance">
                                    {{ $def['short'] }}
                                </span>
                                <span class="hidden sm:block text-[10px] leading-tight text-text-muted">
                                    @if($state === 'selesai')
                                        ✓ Selesai
                                    @elseif($state === 'berjalan')
                                        ● Berjalan
                                    @elseif($state === 'terlambat')
                                        ⚠ Terlambat
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
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-[5px] tabular-nums text-[10px] font-medium bg-[#F5F5F5] dark:bg-gray-800 text-text-active border border-[#E8E8E8] dark:border-border"
                                          title="Lead Time Selesai: {{ $stageLtHari }} hari / {{ $stageLtJam }} jam ({{ $stageLtMenit }} menit)">
                                        <span>{{ $stageLtHari }} hr</span>
                                        @if($stageLtMenit > 0 && $stageLtMenit < 1440)
                                            <span class="text-[9px] text-text-muted font-normal">({{ $stageLtJam }}j)</span>
                                        @endif
                                    </span>
                                @elseif($state === 'berjalan' || $state === 'terlambat')
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-[5px] tabular-nums text-[10px] font-medium bg-white dark:bg-gray-900 text-text-active border {{ $state === 'terlambat' ? 'border-red-500 ring-1 ring-red-500/25' : 'border-black dark:border-white ring-1 ring-black/10 dark:ring-white/20' }} shadow-theme-xs"
                                          title="Lead Time Berjalan: {{ $stageLtHari }} hari / {{ $stageLtJam }} jam ({{ $stageLtMenit }} menit)">
                                        <span class="size-1.5 rounded-full {{ $state === 'terlambat' ? 'bg-red-500 animate-ping' : 'bg-black dark:bg-white animate-pulse' }}"></span>
                                        <span>{{ $stageLtHari }} hr</span>
                                        @if($stageLtMenit > 0 && $stageLtMenit < 1440)
                                            <span class="text-[9px] text-text-muted font-normal">({{ $stageLtJam }}j)</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-[5px] tabular-nums text-[10px] text-text-muted/60 bg-[#FBFBFB] dark:bg-gray-800/40 border border-[#E8E8E8]/70 dark:border-gray-800"
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
        <div class="border-t border-[#E8E8E8] dark:border-border pt-3 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
            {{-- Kiri: Highlight Tahap Aktif --}}
            <div class="flex items-center gap-2 flex-wrap">
                @if($po->status === 'selesai')
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-card dark:bg-card text-foreground dark:text-foreground border border-border dark:border-border">
                        <svg class="size-3.5 text-foreground dark:text-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Seluruh 8 Tahap Selesai (PO Closed)
                    </span>
                @elseif($isCancelled)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-500/20">
                        PO Dibatalkan
                    </span>
                @elseif($activeStageNum)
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-text-muted text-[11px]">Tahap Aktif:</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[8px] text-xs font-medium {{ $activeStageState === 'terlambat' ? 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-500/20' : 'bg-gray-100 dark:bg-gray-800 text-[#101828] dark:text-white border border-[#E8E8E8] dark:border-border' }}">
                            <span class="size-2 rounded-full {{ $activeStageState === 'terlambat' ? 'bg-red-600 animate-ping' : 'bg-[#627EF2] animate-pulse' }}"></span>
                            Tahap {{ $activeStageNum }}: {{ $activeStageLabel }}
                        </span>
                        @if($activePic)
                            <span class="text-[11px] text-text-muted hidden sm:inline">
                                · PIC: <strong class="text-text-active">{{ $activePic }}</strong>
                            </span>
                        @endif
                        @if($activeStartTime)
                            <span class="text-[11px] text-text-muted hidden sm:inline">
                                · Mulai: {{ $activeStartTime->format('d/m H:i') }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Kanan: Metrik Lead Time Bersih --}}
            <div class="flex flex-wrap items-center gap-2 text-[11px]">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#FBFBFB] dark:bg-gray-800/60 border border-[#E8E8E8] dark:border-border tabular-nums text-text-muted" title="Internal Processing (Stage 1-4: Perencanaan, Approval, Supplier Confirm, Payment)">
                    <span class="size-1.5 rounded-full bg-gray-400 shrink-0"></span>
                    <span>Internal<span class="hidden xl:inline"> Processing</span>:</span>
                    <strong class="text-text-active font-medium">{{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($internalMenit) }} hr</strong>
                </div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#FBFBFB] dark:bg-gray-800/60 border border-[#E8E8E8] dark:border-border tabular-nums text-text-muted" title="Supplier Processing (Stage 5: PO)">
                    <span class="size-1.5 rounded-full bg-gray-400 shrink-0"></span>
                    <span>Supplier<span class="hidden xl:inline"> Processing</span>:</span>
                    <strong class="text-text-active font-medium">{{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($supplierMenit) }} hr</strong>
                </div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#FBFBFB] dark:bg-gray-800/60 border border-[#E8E8E8] dark:border-border tabular-nums text-text-muted" title="Transportation Time (Stage 6: Pengiriman)">
                    <span class="size-1.5 rounded-full bg-gray-400 shrink-0"></span>
                    <span>Transportation<span class="hidden xl:inline"> Time</span>:</span>
                    <strong class="text-text-active font-medium">{{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($transportationMenit) }} hr</strong>
                </div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] bg-[#FBFBFB] dark:bg-gray-800/60 border border-[#E8E8E8] dark:border-border tabular-nums text-text-muted" title="Receiving (Stage 7-8: Unloading, Input)">
                    <span class="size-1.5 rounded-full bg-gray-400 shrink-0"></span>
                    <span>Receiving:</span>
                    <strong class="text-text-active font-medium">{{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($receivingMenit) }} hr</strong>
                </div>
                <div class="px-2.5 py-1 rounded-[6px] bg-black dark:bg-white text-white dark:text-[#0A0A0A] tabular-nums font-medium shadow-theme-xs">
                    Total LT: {{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToDays($totalLtMenit) }} Hari
                    <span class="font-normal text-[10px] opacity-80">({{ \App\Services\Purchasing\SupplierLeadTimeService::minutesToHours($totalLtMenit) }} j)</span>
                </div>
            </div>
        </div>
    </div>
@endif

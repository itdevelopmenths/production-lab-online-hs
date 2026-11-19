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

    <div class="rounded-[12px] bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-border p-4 sm:p-5">
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

    @php
        $ltSvc = \App\Services\Purchasing\SupplierLeadTimeService::class;
        $phases = [
            ['label' => 'Internal', 'full' => 'Internal Processing', 'span' => 4, 'menit' => $internalMenit, 'bar' => 'bg-[#111111] dark:bg-white', 'dot' => 'bg-[#111111] dark:bg-white', 'hint' => 'Tahap 1–4'],
            ['label' => 'Supplier', 'full' => 'Supplier Processing', 'span' => 1, 'menit' => $supplierMenit, 'bar' => 'bg-[#627EF2]', 'dot' => 'bg-[#627EF2]', 'hint' => 'Tahap 5'],
            ['label' => 'Transport', 'full' => 'Transportation Time', 'span' => 1, 'menit' => $transportationMenit, 'bar' => 'bg-[#98A2B3]', 'dot' => 'bg-[#98A2B3]', 'hint' => 'Tahap 6'],
            ['label' => 'Receiving', 'full' => 'Receiving', 'span' => 2, 'menit' => $receivingMenit, 'bar' => 'bg-[#D0D5DD] dark:bg-gray-600', 'dot' => 'bg-[#D0D5DD] dark:bg-gray-600', 'hint' => 'Tahap 7–8'],
        ];
        $spanClass = [1 => 'col-span-1', 2 => 'col-span-2', 4 => 'col-span-4'];
        $stageIcons = [
            1 => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
            2 => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
            3 => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
            4 => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
            5 => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            6 => 'M8 17a2 2 0 11-4 0 2 2 0 014 0zm12 0a2 2 0 11-4 0 2 2 0 014 0zM13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10m10 0H8m5 0h2m4 0h1V12l-3-4h-4v8',
            7 => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
            8 => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4',
        ];
        $doneCount = 0;
        foreach ($stageDefs as $n => $d) {
            if (optional($po->stages->where('stage', $d['key'])->sortBy('putaran')->last())->status === 'selesai') {
                $doneCount++;
            }
        }
        $progressPct = (int) round($doneCount / count($stageDefs) * 100);
    @endphp

    <section data-stepper="po" class="card-elevated rounded-[14px] bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-border overflow-hidden" aria-label="Progres Purchase Order">
        @if($isCancelled)
            <div class="flex items-center justify-between gap-3 px-5 py-3.5 bg-red-50/90 dark:bg-red-500/10 border-b border-red-200 dark:border-red-500/20">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-8 rounded-full bg-red-100 dark:bg-red-500/20 flex items-center justify-center text-red-600 dark:text-red-400 font-medium text-xs shrink-0">✕</div>
                    <div class="min-w-0">
                        <h4 class="text-xs font-semibold text-red-900 dark:text-red-200">Purchase Order Dibatalkan</h4>
                        <p class="text-[11px] text-red-700 dark:text-red-300 mt-0.5">{{ $po->alasan_batal ?? 'Dibatalkan oleh manajemen.' }}</p>
                    </div>
                </div>
                <x-badge variant="danger" size="xs" :pill="true">Dibatalkan</x-badge>
            </div>
        @endif

        {{-- Header --}}
        <div class="px-5 pt-5 pb-4 flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-2.5 flex-wrap min-w-0">
                <h3 class="text-[15px] font-semibold text-text-active tracking-[-0.01em]">Progres Pengadaan</h3>
                @if($po->status === 'selesai')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#111111] text-white dark:bg-white dark:text-[#0A0A0A]">PO Closed</span>
                @elseif($activeStageNum && !$isCancelled)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $activeStageState === 'terlambat' ? 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-300 border-red-200 dark:border-red-500/30' : 'bg-[#F4F6FE] dark:bg-[#627EF2]/10 text-[#2B3FA3] dark:text-[#AEBBF9] border-[#D7DEFB] dark:border-[#627EF2]/30' }}">
                        <span class="size-1.5 rounded-full {{ $activeStageState === 'terlambat' ? 'bg-red-600 animate-ping' : 'bg-[#627EF2] animate-pulse' }}"></span>
                        Tahap {{ $activeStageNum }}: {{ $activeStageLabel }}
                    </span>
                @endif
            </div>
            <div class="tabular-nums text-[12px] text-text-muted">
                Total Lead Time
                <strong class="ml-1 text-[15px] font-semibold text-text-active">{{ $ltSvc::minutesToDays($totalLtMenit) }} hari</strong>
                <span class="text-[11px]">({{ $ltSvc::minutesToHours($totalLtMenit) }} j)</span>
            </div>
        </div>

        <div class="px-4 sm:px-5 pb-5" tabindex="0" role="region" aria-label="Scroll Tahapan Purchase Order">
            <nav aria-label="Tahapan Purchase Order">
                {{-- Fase (hanya layar lebar): batang berwarna = legenda lead time --}}
                <div class="hidden xl:grid grid-cols-8 gap-3 mb-3 select-none" role="group" aria-label="Fase Purchase Order">
                    @foreach($phases as $ph)
                        <div class="{{ $spanClass[$ph['span']] }} min-w-0" title="{{ $ph['full'] }}: {{ $ph['hint'] }}">
                            <div class="h-1 rounded-full {{ $ph['bar'] }}"></div>
                            <div class="mt-1.5 flex items-baseline gap-1.5 min-w-0">
                                <span class="text-[12px] font-medium text-text-active truncate">{{ $ph['label'] }}</span>
                                <span class="text-[11px] text-text-muted tabular-nums truncate">{{ $ltSvc::minutesToDays($ph['menit']) }} hr</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <ol class="grid grid-cols-1 xl:grid-cols-8 gap-0 xl:gap-2 list-none m-0 p-0">
                    @foreach($stageDefs as $num => $def)
                        @php
                            $stageKey = $def['key'];
                            $records = $po->stages->where('stage', $stageKey)->sortBy('putaran');
                            $latestRecord = $records->last();
                            $putaran = (int) ($records->max('putaran') ?? 1);
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
                                        $bmChk = $benchmarks->get($stageKey);
                                        if ($bmChk && $bmChk->jumlah_transaksi > 0 && $bmChk->total_menit > 0) {
                                            $avgChk = $bmChk->total_menit / $bmChk->jumlah_transaksi;
                                            if ($latestRecord->waktu_mulai->diffInMinutes(now()) > $avgChk) {
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

                            $stageLtMenit = ($latestRecord && in_array($latestRecord->status, ['berjalan', 'terlambat']) && $latestRecord->waktu_mulai)
                                ? $durasiMenit + (int) $latestRecord->waktu_mulai->diffInMinutes(now())
                                : $durasiMenit;
                            $stageLtHari = $ltSvc::minutesToDays($stageLtMenit);
                            $stageLtJam = $ltSvc::minutesToHours($stageLtMenit);

                            $bm = $benchmarks->get($stageKey);
                            $avgMenit = ($bm && $bm->jumlah_transaksi > 0 && $bm->total_menit > 0) ? $bm->total_menit / $bm->jumlah_transaksi : null;
                            $ratio = ($avgMenit && $state !== 'belum_mulai') ? min(100, (int) round($stageLtMenit / $avgMenit * 100)) : null;

                            $picName = $latestRecord?->picUser?->name ?? $latestRecord?->recordedBy?->name;
                            $picInitials = $picName ? collect(preg_split('/\s+/', trim(preg_replace('/\(.*?\)/', '', $picName))))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') : null;
                            $stamp = $state === 'selesai' ? ($latestRecord?->waktu_selesai ?? $latestRecord?->waktu_mulai) : $latestRecord?->waktu_mulai;

                            $nodeClass = match($state) {
                                'selesai' => 'bg-[#111111] text-white dark:bg-white dark:text-[#0A0A0A] shadow-[0_1px_2px_rgba(16,24,40,0.25)]',
                                'berjalan' => 'bg-white dark:bg-gray-900 text-[#111111] dark:text-white border-2 border-[#111111] dark:border-white shadow-[0_0_0_5px_rgba(98,126,242,0.18)]',
                                'terlambat' => 'bg-[#F04438] text-white shadow-[0_0_0_5px_rgba(240,68,56,0.2)]',
                                'dikembalikan' => 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-600',
                                default => 'bg-white dark:bg-gray-900 border border-dashed border-[#CFD4DC] dark:border-gray-700 text-text-muted',
                            };
                            $tileClass = match(true) {
                                $state === 'terlambat' => 'border-red-300 dark:border-red-500/40 bg-red-50/60 dark:bg-red-500/5',
                                $isCurrent => 'border-[#111111]/80 dark:border-white/70 bg-gradient-to-b from-white to-[#F6F7FB] dark:from-gray-800 dark:to-gray-900 shadow-[0_8px_24px_-12px_rgba(98,126,242,0.55)]',
                                $state === 'selesai' => 'border-[#E4E7EC] dark:border-gray-700 bg-white dark:bg-gray-900',
                                $state === 'dikembalikan' => 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/60',
                                default => 'border-dashed border-[#DDE1E8] dark:border-gray-800 bg-[#FBFBFC] dark:bg-gray-900/40',
                            };
                            $pillClass = match($state) {
                                'selesai' => 'bg-[#111111] text-white dark:bg-white dark:text-[#0A0A0A]',
                                'berjalan' => 'bg-[#F4F6FE] dark:bg-[#627EF2]/15 text-[#2B3FA3] dark:text-[#AEBBF9] border border-[#D7DEFB] dark:border-[#627EF2]/30',
                                'terlambat' => 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-500/30',
                                'dikembalikan' => 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-100',
                                default => 'bg-transparent text-text-muted border border-[#E4E7EC] dark:border-gray-700',
                            };
                            $statusText = match($state) {
                                'selesai' => 'Selesai',
                                'berjalan' => 'Berjalan',
                                'terlambat' => 'Terlambat',
                                'dikembalikan' => 'Ditolak',
                                default => 'Menunggu',
                            };
                            $prevDone = $num > 1 && optional($po->stages->where('stage', $stageDefs[$num - 1]['key'])->sortBy('putaran')->last())->status === 'selesai';
                            $thisDone = $state === 'selesai';
                        @endphp

                        <li class="relative flex gap-3 pb-4 last:pb-0 xl:pb-0 xl:flex-col xl:gap-0 min-w-0" @if($isCurrent) aria-current="step" @endif>
                            {{-- Konektor horizontal (xl) --}}
                            @if($num > 1)
                                <span aria-hidden="true" class="hidden xl:block absolute top-[18px] right-1/2 w-[calc(100%+0.5rem)] h-[2px] rounded-full {{ $prevDone ? 'bg-[#111111] dark:bg-white' : 'bg-[#E4E7EC] dark:bg-gray-800' }}"></span>
                            @endif
                            {{-- Konektor vertikal (di bawah xl) --}}
                            @if($num < count($stageDefs))
                                <span aria-hidden="true" class="xl:hidden absolute left-[17px] top-9 -bottom-0 w-[2px] rounded-full {{ $thisDone ? 'bg-[#111111] dark:bg-white' : 'bg-[#E4E7EC] dark:bg-gray-800' }}"></span>
                            @endif

                            <div class="relative z-10 shrink-0 xl:flex xl:justify-center">
                                <div class="size-9 rounded-full flex items-center justify-center transition {{ $nodeClass }}"
                                     title="Tahap {{ $num }}: {{ $def['short'] }} ({{ ucfirst(str_replace('_',' ',$state)) }})">
                                    @if($state === 'selesai')
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    @elseif($state === 'terlambat')
                                        <span class="text-sm font-semibold">!</span>
                                    @else
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $stageIcons[$num] }}"/></svg>
                                    @endif
                                </div>
                            </div>

                            <div class="flex-1 min-w-0 xl:mt-3 xl:text-center pt-1 xl:pt-0">
                                <span class="block text-[13px] leading-tight {{ $state === 'belum_mulai' ? 'font-medium text-text-muted' : 'font-semibold text-text-active' }}">{{ $def['short'] }}</span>
                                @if(in_array($state, ['berjalan', 'terlambat', 'dikembalikan']) || $putaran > 1)
                                    <span class="block mt-0.5 text-[11px] leading-snug {{ $state === 'terlambat' ? 'text-red-600 dark:text-red-400 font-medium' : 'text-text-muted' }}">
                                        {{ $statusText }}@if($putaran > 1) · Putaran {{ $putaran }}@endif
                                    </span>
                                @endif
                                @if($state !== 'belum_mulai')
                                    <span class="mt-1.5 inline-flex items-center gap-1 rounded-[6px] px-1.5 py-0.5 text-[11px] font-medium tabular-nums border {{ $state === 'terlambat' ? 'bg-red-50 dark:bg-red-500/10 border-red-300 dark:border-red-500/40 text-red-700 dark:text-red-300' : 'bg-white dark:bg-gray-900 border-[#E4E7EC] dark:border-gray-700 text-text-active' }}"
                                          title="Lead time {{ $state === 'selesai' ? 'selesai' : 'berjalan' }}: {{ $stageLtHari }} hari / {{ $stageLtJam }} jam ({{ $stageLtMenit }} menit)@if($avgMenit) · rata-rata supplier {{ $ltSvc::minutesToDays($avgMenit) }} hari @endif">
                                        @if(in_array($state, ['berjalan','terlambat']))
                                            <span class="size-1.5 rounded-full {{ $state === 'terlambat' ? 'bg-red-500 animate-ping' : 'bg-[#627EF2] animate-pulse' }}"></span>
                                        @endif
                                        {{ $stageLtHari }} hr
                                    </span>
                                @endif
                                @if($isCurrent && ($picName || $stamp))
                                    <span class="block mt-1.5 text-[11px] leading-snug text-text-muted">
                                        @if($picName){{ $picName }}@endif
                                        @if($stamp)<span class="block tabular-nums">sejak {{ $stamp->format('d/m H:i') }}</span>@endif
                                    </span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </nav>
        </div>

    </section>
@endif

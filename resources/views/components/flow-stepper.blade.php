@props([
    'steps' => [],      // [['label' => 'Draft', 'hint' => 'Disusun'], ...]
    'current' => 1,     // posisi langkah aktif (1-based)
    'completed' => false, // true bila seluruh langkah selesai
    'terminal' => null, // teks bila dokumen berhenti (ditolak/dibatalkan)
    'title' => 'Alur Dokumen',
])

@php
    $count = max(count($steps), 1);
@endphp

<section data-stepper="flow" class="card-elevated rounded-[14px] bg-white dark:bg-gray-900 border border-[#E8E8E8] dark:border-border overflow-hidden" aria-label="{{ $title }}">
    <div class="px-5 pt-4 pb-3 flex items-center justify-between gap-3 flex-wrap">
        <h3 class="text-[15px] font-semibold text-text-active tracking-[-0.01em]">{{ $title }}</h3>
        @if($terminal)
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-500/30">{{ $terminal }}</span>
        @elseif($completed)
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#111111] text-white dark:bg-white dark:text-[#0A0A0A]">Selesai</span>
        @elseif(isset($steps[$current - 1]))
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border bg-[#F4F6FE] dark:bg-[#627EF2]/10 text-[#2B3FA3] dark:text-[#AEBBF9] border-[#D7DEFB] dark:border-[#627EF2]/30">
                <span class="size-1.5 rounded-full bg-[#627EF2] animate-pulse"></span>
                {{ $steps[$current - 1]['label'] }}
            </span>
        @endif
    </div>

    <div class="px-4 sm:px-5 pb-5">
        <ol class="grid list-none m-0 p-0" style="grid-template-columns: repeat({{ $count }}, minmax(0, 1fr));" aria-label="{{ $title }}">
            @foreach($steps as $i => $st)
                @php
                    $n = $i + 1;
                    $state = $completed || $n < $current ? 'selesai' : ($n === $current && !$terminal ? 'berjalan' : ($n === $current && $terminal ? 'berhenti' : 'belum'));
                    $nodeClass = match($state) {
                        'selesai' => 'bg-[#111111] text-white dark:bg-white dark:text-[#0A0A0A] shadow-[0_1px_2px_rgba(16,24,40,0.25)]',
                        'berjalan' => 'bg-white dark:bg-gray-900 text-[#111111] dark:text-white border-2 border-[#111111] dark:border-white shadow-[0_0_0_5px_rgba(98,126,242,0.18)]',
                        'berhenti' => 'bg-[#F04438] text-white shadow-[0_0_0_5px_rgba(240,68,56,0.2)]',
                        default => 'bg-white dark:bg-gray-900 border border-dashed border-[#CFD4DC] dark:border-gray-700 text-text-muted',
                    };
                    $prevDone = $n > 1 && ($completed || ($n - 1) < $current);
                @endphp
                <li class="relative flex flex-col items-center text-center min-w-0 px-1" @if($state === 'berjalan') aria-current="step" @endif>
                    @if($n > 1)
                        <span aria-hidden="true" class="absolute top-[18px] right-1/2 w-full h-[2px] rounded-full {{ $prevDone ? 'bg-[#111111] dark:bg-white' : 'bg-[#E4E7EC] dark:bg-gray-800' }}"></span>
                    @endif
                    <div class="relative z-10 size-9 rounded-full flex items-center justify-center text-[13px] font-semibold tabular-nums {{ $nodeClass }}">
                        @if($state === 'selesai')
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        @elseif($state === 'berhenti')
                            <span>✕</span>
                        @else
                            {{ $n }}
                        @endif
                    </div>
                    <div class="mt-2.5 w-full">
                        <span class="block text-[13px] leading-tight {{ $state === 'belum' ? 'font-medium text-text-muted' : 'font-semibold text-text-active' }}">{{ $st['label'] }}</span>
                        @if(!empty($st['hint']))
                            <span class="block mt-0.5 text-[11px] leading-snug text-text-muted">{{ $st['hint'] }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>

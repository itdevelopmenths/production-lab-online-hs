@props(['title', 'meta' => null])

{{-- Header dokumen: ikon + nomor + status + aksi. Dipakai halaman detail request/transfer/batch. --}}
<section {{ $attributes->merge(['class' => 'po-hero card-elevated rounded-[14px] border border-border p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4']) }}>
    <div class="flex items-center gap-4 min-w-0">
        @isset($icon)
            <span class="size-12 rounded-[12px] bg-[#111111] text-white dark:bg-white dark:text-[#0A0A0A] flex items-center justify-center shrink-0 [&>svg]:size-6" aria-hidden="true">{{ $icon }}</span>
        @endisset
        <div class="min-w-0">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h2 class="text-xl font-semibold tabular-nums tracking-[-0.01em] text-text-active break-words">{{ $title }}</h2>
                @isset($badge){{ $badge }}@endisset
            </div>
            @if($meta)
                <p class="text-[12px] text-text-muted mt-1">{!! $meta !!}</p>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 lg:justify-end shrink-0">{{ $actions }}</div>
    @endisset
</section>

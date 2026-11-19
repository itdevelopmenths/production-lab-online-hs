@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'count' => null,
    'variant' => 'default',
    'bodyClass' => 'p-4 sm:p-5',
    'noPadding' => false,
    'tools' => null,
    'footer' => null,
    'overflow' => null,
])

@php
    $classAttr = $attributes->get('class', '');
    $hasExplicitOverflow = (bool) preg_match('/\boverflow-(visible|hidden|auto|scroll)\b/', $classAttr);
    $elevated = $variant === 'elevated';
    $overflowClass = $overflow ? "overflow-{$overflow}" : ($hasExplicitOverflow ? '' : 'overflow-hidden');
@endphp

<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-900 text-[#101828] dark:text-white ' . ($elevated ? 'rounded-[14px] card-elevated' : 'rounded-[12px] shadow-theme-xs') . ' border border-[#e8e8e8] dark:border-border flex flex-col ' . $overflowClass . ' transition-all']) }}>
    @if ($title || isset($header) || isset($tools))
        <div class="px-5 py-4 border-b border-[#e8e8e8] dark:border-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 {{ $elevated ? 'card-elevated-head rounded-t-[14px]' : 'bg-white dark:bg-gray-900 rounded-t-[12px]' }}">
            @if (isset($header))
                {{ $header }}
            @else
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    @if ($icon)
                        <span class="size-[42px] rounded-[8px] bg-[#f9fafb] dark:bg-gray-800 border border-[#e8e8e8] dark:border-border flex items-center justify-center shrink-0 [&>svg]:size-[20px] text-[#101828] dark:text-white">
                            {!! $icon !!}
                        </span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 min-w-0">
                            <h3 class="text-[16px] leading-[1.4] font-semibold tracking-[-0.176px] text-[#101828] dark:text-white truncate">{!! $title !!}</h3>
                            @if ($count !== null)
                                <span class="bg-[#f3f4f6] dark:bg-gray-800 px-[7px] py-px rounded-full text-[12px] font-medium text-[#6a7282] dark:text-gray-400 tabular-nums shrink-0">
                                    {{ $count }}
                                </span>
                            @endif
                        </div>
                        @if ($subtitle)
                            <p class="text-[12px] leading-[18px] tracking-[-0.132px] text-[#6a7282] dark:text-gray-400 mt-0.5">{!! $subtitle !!}</p>
                        @endif
                    </div>
                </div>
            @endif

            @if (isset($tools))
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    {{ $tools }}
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $noPadding ? 'p-0' : $bodyClass }} flex-1">
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="px-5 py-3 bg-[#fbfbfb] dark:bg-gray-900/60 border-t border-[#e8e8e8] dark:border-border text-xs text-[#6a7282] dark:text-gray-400 rounded-b-[12px]">
            {{ $footer }}
        </div>
    @endif
</div>

@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'variant' => 'default', // default, primary, success, danger, warning, info, gold
    'bodyClass' => 'p-4',
    'noPadding' => false,
    'tools' => null,
    'footer' => null,
])

@php
    $topBorderClass = match ($variant) {
        'primary' => 'border-t-2 border-t-primary-600',
        'success' => 'border-t-2 border-t-emerald-600',
        'danger' => 'border-t-2 border-t-rose-600',
        'warning' => 'border-t-2 border-t-amber-500',
        'info' => 'border-t-2 border-t-blue-500',
        'gold' => 'border-t-2 border-t-gold-500',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-sm border border-gray-200 shadow-xs flex flex-col ' . $topBorderClass]) }}>
    @if ($title || isset($header) || isset($tools))
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between gap-3 bg-white">
            @if (isset($header))
                {{ $header }}
            @else
                <div class="flex items-center gap-2 min-w-0">
                    @if ($icon)
                        <span class="text-gray-500 shrink-0">{!! $icon !!}</span>
                    @endif
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 truncate leading-tight">{!! $title !!}</h3>
                        @if ($subtitle)
                            <p class="text-xs text-gray-500 mt-0.5">{!! $subtitle !!}</p>
                        @endif
                    </div>
                </div>
            @endif

            @if (isset($tools))
                <div class="flex items-center gap-2 shrink-0">
                    {{ $tools }}
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $noPadding ? 'p-0' : $bodyClass }} flex-1">
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 text-xs text-gray-600 rounded-b-sm">
            {{ $footer }}
        </div>
    @endif
</div>

@props([
    'items' => [],
    'active' => null,
    'alpineModel' => null,
    'onClick' => null,
])

@php
    $activeTabClasses = 'bg-white dark:bg-gray-800 text-[#101828] dark:text-white border border-[#e8e8e8] dark:border-gray-700 shadow-[0px_1px_2px_0px_rgba(16,24,40,0.05)] font-medium';
    $inactiveTabClasses = 'bg-transparent text-[#6a7282] dark:text-gray-400 border border-transparent hover:text-[#101828] dark:hover:text-white hover:bg-black/[0.03] dark:hover:bg-white/[0.03] font-medium';
    $baseTabClasses = 'px-[10px] sm:px-3 py-[4px] text-[13px] sm:text-[14px] leading-[20px] tracking-[-0.154px] gap-[6px] rounded-[6px] h-full inline-flex items-center whitespace-nowrap transition-all cursor-pointer select-none';
@endphp

<div {{ $attributes->merge(['class' => 'bg-[#f3f4f6] dark:bg-gray-900 dark:border dark:border-gray-800 p-[2px] gap-[2px] rounded-[8px] h-9 sm:h-10 inline-flex items-center w-fit max-w-full overflow-x-auto no-scrollbar']) }}
     role="tablist">
    @foreach ($items as $item)
        @php
            $key = $item['key'] ?? (string) $loop->index;
            $label = $item['label'] ?? $key;
            $href = $item['href'] ?? null;
            $count = $item['count'] ?? null;
            $dot = $item['dot'] ?? null;
            $itemOnClick = $item['onclick'] ?? null;
            $isActive = (bool) ($item['active'] ?? ($active !== null && (string) $active === (string) $key));
            $staticStateClass = $alpineModel ? '' : ($isActive ? $activeTabClasses : $inactiveTabClasses);
        @endphp

        @if ($href)
            <a href="{{ $href }}"
               role="tab"
               aria-selected="{{ $isActive ? 'true' : 'false' }}"
               class="{{ $baseTabClasses }} {{ $isActive ? $activeTabClasses : $inactiveTabClasses }}">
                @if ($dot)
                    <span class="size-[6px] rounded-full shrink-0 {{ $dot }}"></span>
                @endif
                <span>{{ $label }}</span>
                @if ($count !== null)
                    <span class="bg-[#f3f4f6] dark:bg-gray-700 text-[#364153] dark:text-gray-200 px-[7px] py-px rounded-full text-[12px] font-medium tabular-nums">
                        {{ $count }}
                    </span>
                @endif
            </a>
        @else
            <button type="button"
                    role="tab"
                    @if ($alpineModel)
                        @click="{{ $alpineModel }} = @js($key); {{ $onClick ? $onClick : '' }}"
                        :class="{{ $alpineModel }} === @js($key) ? @js($activeTabClasses) : @js($inactiveTabClasses)"
                        :aria-selected="{{ $alpineModel }} === @js($key) ? 'true' : 'false'"
                    @else
                        aria-selected="{{ $isActive ? 'true' : 'false' }}"
                        @if ($itemOnClick)
                            onclick="{{ $itemOnClick }}"
                        @elseif ($onClick)
                            onclick="{{ $onClick }}"
                        @endif
                    @endif
                    class="{{ $baseTabClasses }} {{ $staticStateClass }}">
                @if ($dot)
                    <span class="size-[6px] rounded-full shrink-0 {{ $dot }}"></span>
                @endif
                <span>{{ $label }}</span>
                @if ($count !== null)
                    <span class="bg-[#f3f4f6] dark:bg-gray-700 text-[#364153] dark:text-gray-200 px-[7px] py-px rounded-full text-[12px] font-medium tabular-nums">
                        {{ $count }}
                    </span>
                @endif
            </button>
        @endif
    @endforeach
</div>

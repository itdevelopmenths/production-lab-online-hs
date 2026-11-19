@props([
    'title',
    'active' => false,
    'keywords' => '',
])

@php
    $normalizedKeywords = strtolower((string) $keywords);
@endphp

<div class="w-full"
     x-data="{ open: @js((bool) $active) }"
     x-show="!menuQuery || @js($normalizedKeywords).includes(menuQuery.toLowerCase().trim())"
     x-effect="if (menuQuery && menuQuery.trim().length > 0 && @js($normalizedKeywords).includes(menuQuery.toLowerCase().trim())) { open = true; }">
    <button type="button"
            @click="if (collapsed) { toggleCollapsed(); open = true; } else { open = !open; }"
            :title="collapsed ? @js($title) : undefined"
            :aria-expanded="open && !collapsed"
            :class="collapsed ? 'px-0 justify-center' : 'px-3 justify-between gap-2.5'"
            class="w-full h-[44px] rounded-lg transition-all duration-200 flex items-center relative border overflow-hidden cursor-pointer select-none {{ $active ? 'sidebar-active-tab lg:text-white' : 'border-transparent text-text-muted hover:bg-[#FBFBFB] hover:text-black dark:hover:bg-white/[0.02] dark:hover:text-white' }}">
        @if ($active)
            <div class="-translate-x-1/2 absolute h-[28px] left-1/2 top-[29px] w-[232px] pointer-events-none opacity-40 bg-radial-[at_50%_50%] from-stone-400 to-transparent rounded-full blur-[2px]"></div>
        @endif

        <span class="relative z-10 flex items-center min-w-0" :class="collapsed ? 'justify-center' : 'gap-2.5'">
            @if (isset($icon))
                <span class="size-[18px] shrink-0 flex items-center justify-center [&>svg]:size-[18px]">
                    {!! $icon !!}
                </span>
            @endif
            <span x-show="!collapsed" class="truncate text-[14px] leading-5 font-medium tracking-[-0.16px] whitespace-nowrap">
                {{ $title }}
            </span>
        </span>

        <svg x-show="!collapsed"
             class="relative z-10 size-4 shrink-0 transition-transform duration-200"
             :class="open ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open && !collapsed"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="mt-1 space-y-0.5 ml-5 border-l-[3px] border-[#E8E8E8] dark:border-border pl-4 py-0.5">
        {{ $slot }}
    </div>
</div>

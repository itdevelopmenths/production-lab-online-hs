@props([
    'href',
    'active' => false,
    'badge' => null,
    'badgeVariant' => 'neutral',
    'isChild' => false,
])

@if ($isChild)
<a href="{{ $href }}"
   data-nav-item
   data-nav-active="{{ $active ? 'true' : 'false' }}"
   @if($active)
       @click="if (window.location.pathname === new URL('{{ $href }}', window.location.origin).pathname && window.location.search === new URL('{{ $href }}', window.location.origin).search) { $event.preventDefault(); }"
   @endif
   onclick="try { sessionStorage.setItem('hs_sidebar_scroll', this.closest('.sidebar-scroll')?.scrollTop || 0); } catch(e) {}"
   {{ $attributes->merge(['class' => 'relative flex items-center justify-between gap-2 h-9 rounded-lg px-2.5 text-[13px] transition-colors cursor-pointer ' . ($active ? 'text-[#111111] dark:text-white font-medium bg-[#F2F2F2]/75 dark:bg-white/[0.05]' : 'text-text-muted hover:text-[#111111] dark:hover:text-white hover:bg-[#FBFBFB] dark:hover:bg-white/[0.02] font-medium')]) }}>
    @if ($active)
        <div class="absolute left-[-19px] top-1/2 -translate-y-1/2 w-[3px] h-6 bg-[#627EF2] rounded-full"></div>
    @endif

    <span class="flex items-center gap-2 min-w-0 flex-1">
        @if (isset($icon))
            <span class="size-[16px] shrink-0 flex items-center justify-center [&>svg]:size-[16px]">
                {!! $icon !!}
            </span>
        @endif
        <span class="truncate leading-tight">{{ $slot }}</span>
    </span>

    @if ($badge !== null && $badge > 0)
        <span class="min-w-5 px-1.5 py-0.5 text-[11px] leading-tight inline-flex items-center justify-center font-medium rounded-full {{ $active ? 'bg-[#111111] text-white dark:bg-white dark:text-[#111111]' : 'bg-[#F3F4F6] dark:bg-gray-800 text-[#6A7282] dark:text-gray-300' }}">
            {{ $badge > 99 ? '99+' : $badge }}
        </span>
    @endif
</a>
@else
<a href="{{ $href }}"
   data-nav-item
   data-nav-active="{{ $active ? 'true' : 'false' }}"
   :title="collapsed ? '{{ trim(strip_tags($slot)) }}' : undefined"
   @if($active)
       @click="if (window.location.pathname === new URL('{{ $href }}', window.location.origin).pathname && window.location.search === new URL('{{ $href }}', window.location.origin).search) { $event.preventDefault(); }"
   @endif
   onclick="try { sessionStorage.setItem('hs_sidebar_scroll', this.closest('.sidebar-scroll')?.scrollTop || 0); } catch(e) {}"
   :class="collapsed ? 'px-0 justify-center' : 'px-3 gap-2.5'"
   {{ $attributes->merge(['class' => 'w-full h-[44px] rounded-lg transition-all duration-200 flex items-center relative border overflow-hidden group cursor-pointer ' . ($active ? 'sidebar-active-tab' : 'border-transparent text-text-muted hover:bg-[#FBFBFB] hover:text-black dark:hover:bg-white/[0.02] dark:hover:text-white')]) }}>
    @if ($active)
        <div class="-translate-x-1/2 absolute h-[28px] left-1/2 top-[29px] w-[232px] pointer-events-none opacity-40 bg-radial-[at_50%_50%] from-stone-400 to-transparent rounded-full blur-[2px]"></div>
    @endif

    @if (isset($icon))
        <span class="relative z-10 size-[18px] shrink-0 flex items-center justify-center [&>svg]:size-[18px]">
            {!! $icon !!}
        </span>
    @endif

    <span x-show="!collapsed" class="relative z-10 flex-1 truncate text-[14px] leading-5 font-medium tracking-[-0.16px] whitespace-nowrap">
        {{ $slot }}
    </span>

    @if ($badge !== null && $badge > 0)
        <span
            :class="collapsed 
                ? 'absolute top-1 right-1 size-4 text-[9px] ring-2 ring-white dark:ring-[#0A0A0A]' 
                : 'min-w-5 px-1.5 py-0.5 text-[11px] leading-tight'"
            class="relative z-10 inline-flex items-center justify-center font-medium rounded-full {{ $active ? 'bg-white text-[#111111]' : 'bg-[#F3F4F6] dark:bg-gray-800 text-[#101828] dark:text-gray-200' }}"
        >
            {{ $badge > 99 ? '99+' : $badge }}
        </span>
    @endif
</a>
@endif

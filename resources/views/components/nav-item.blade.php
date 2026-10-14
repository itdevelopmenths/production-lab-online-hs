@props([
    'href',
    'active' => false,
    'badge' => null,
    'badgeVariant' => 'neutral',
])

@php
    $activeClasses = $active
        ? 'bg-primary text-primary-foreground font-semibold shadow-md'
        : 'text-foreground/80 hover:bg-accent hover:text-foreground';
@endphp

<a href="{{ $href }}"
   data-nav-item
   data-nav-active="{{ $active ? 'true' : 'false' }}"
   :title="collapsed ? '{{ trim(strip_tags($slot)) }}' : undefined"
   @if($active)
       @click="if (window.location.pathname === new URL('{{ $href }}', window.location.origin).pathname && window.location.search === new URL('{{ $href }}', window.location.origin).search) { $event.preventDefault(); }"
   @endif
   onclick="try { sessionStorage.setItem('hs_sidebar_scroll', this.closest('.sidebar-scroll')?.scrollTop || 0); } catch(e) {}"
   :class="collapsed ? 'justify-center h-10 w-10 mx-auto rounded-xl' : 'h-11 gap-3 rounded-lg px-3 text-[14px]'"
   {{ $attributes->merge(['class' => 'flex items-center transition-colors group relative cursor-pointer ' . $activeClasses]) }}>
    
    @if (isset($icon))
        <span class="size-[18px] shrink-0 flex items-center justify-center">
            {!! $icon !!}
        </span>
    @endif

    <span x-show="!collapsed" class="flex-1 truncate leading-tight font-medium">
        {{ $slot }}
    </span>

    @if ($badge !== null && $badge > 0)
        <span
            :class="collapsed 
                ? 'absolute -top-1 -right-1 size-4 text-[9px] ring-2 ring-card border shadow-xs' 
                : 'min-w-5 px-1.5 py-0.5 text-[11px] leading-tight'"
            class="inline-flex items-center justify-center font-bold rounded-full {{ $active ? 'bg-primary-foreground text-primary' : 'bg-muted text-foreground' }}"
        >
            {{ $badge > 99 ? '99+' : $badge }}
        </span>
    @endif
</a>

@props([
    'href',
    'active' => false,
    'badge' => null,
    'badgeVariant' => 'primary',
])

@php
    $activeClasses = $active
        ? 'bg-[#374151] text-white font-medium'
        : 'text-gray-300 hover:bg-[#2d3748] hover:text-white';

    $badgeClasses = match ($badgeVariant) {
        'danger' => 'bg-rose-600 text-white',
        'warning' => 'bg-amber-500 text-white',
        'success' => 'bg-emerald-600 text-white',
        default => 'bg-primary-600 text-white',
    };
@endphp

<a href="{{ $href }}"
   data-nav-item
   data-nav-active="{{ $active ? 'true' : 'false' }}"
   @if($active)
       @click="if (window.location.pathname === new URL('{{ $href }}', window.location.origin).pathname && window.location.search === new URL('{{ $href }}', window.location.origin).search) { $event.preventDefault(); }"
   @endif
   onclick="try { sessionStorage.setItem('hs_sidebar_scroll', this.closest('.sidebar-scroll')?.scrollTop || 0); } catch(e) {}"
   {{ $attributes->merge(['class' => 'flex items-center justify-between px-3 py-2 text-xs rounded-sm transition-colors group ' . $activeClasses]) }}>
    <div class="flex items-center gap-2.5 min-w-0">
        @if (isset($icon))
            <span class="w-4 h-4 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity">
                {{ $icon }}
            </span>
        @endif
        <span class="truncate">{{ $slot }}</span>
    </div>

    @if ($badge !== null && $badge > 0)
        <span class="ml-2 px-1.5 py-0.25 text-[10px] font-bold rounded-sm {{ $badgeClasses }} shrink-0">
            {{ $badge }}
        </span>
    @endif
</a>

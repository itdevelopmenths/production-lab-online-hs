@props([
    'name' => 'open',
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'variant' => 'default',
    'maxWidth' => 'md',
    'noPadding' => false,
    'bodyClass' => 'p-6 text-sm text-foreground',
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl',
        '4xl' => 'sm:max-w-4xl',
        '5xl' => 'sm:max-w-5xl',
        '6xl' => 'sm:max-w-6xl',
        'full' => 'sm:max-w-full sm:mx-4',
        default => 'sm:max-w-lg',
    };
@endphp

<div x-show="{{ $name }}"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
     role="dialog"
     aria-modal="true"
     style="display: none;">
    
    {{-- Backdrop --}}
    <div x-show="{{ $name }}"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="{{ $name }} = false"
         class="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity"
         aria-hidden="true"></div>

    {{-- Dialog Box (DevScore Modal) --}}
    <div x-show="{{ $name }}"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="relative bg-card rounded-2xl border border-border shadow-2xl w-full {{ $maxWidthClass }} z-10 overflow-hidden sm:my-8 text-left transition-all">
        
        @if (isset($header))
            {{ $header }}
        @elseif ($title)
            <div class="px-6 py-5 border-b border-border/60 bg-card flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    @if ($icon)
                        <span class="text-muted-foreground shrink-0">{!! $icon !!}</span>
                    @endif
                    <div>
                        <h3 class="text-lg font-semibold text-foreground tracking-tight leading-tight">{!! $title !!}</h3>
                        @if ($subtitle)
                            <p class="text-xs text-muted-foreground mt-0.5">{!! $subtitle !!}</p>
                        @endif
                    </div>
                </div>
                <button type="button" @click="{{ $name }} = false" class="text-muted-foreground hover:text-foreground transition cursor-pointer p-1.5 rounded-lg hover:bg-accent">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <div class="{{ $noPadding ? 'p-0' : $bodyClass }}">
            {{ $slot }}
        </div>

        @if (isset($footer))
            <div class="px-6 py-4 bg-muted/30 border-t border-border/60 flex items-center justify-end gap-2.5 rounded-b-2xl">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>

@props([
    'name' => 'open',
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'variant' => 'primary', // primary, info, warning, success, danger, dark, default, none
    'maxWidth' => 'md',
    'noPadding' => false,
    'bodyClass' => 'p-4 text-xs text-gray-700',
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
        default => 'sm:max-w-md',
    };

    $topBorderClass = match ($variant) {
        'primary' => 'border-t-4 border-t-primary-600',
        'info' => 'border-t-4 border-t-blue-500',
        'warning' => 'border-t-4 border-t-amber-500',
        'success' => 'border-t-4 border-t-emerald-600',
        'danger' => 'border-t-4 border-t-rose-600',
        'dark' => 'border-t-4 border-t-gray-800',
        'none' => '',
        default => 'border-t-4 border-t-primary-600',
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
         class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
         aria-hidden="true"></div>

    {{-- Dialog Box (AdminLTE Sharp Corners & Top Accent) --}}
    <div x-show="{{ $name }}"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="relative bg-white rounded-sm border border-gray-300 shadow-xl w-full {{ $maxWidthClass }} z-10 overflow-hidden {{ $topBorderClass }} sm:my-8 text-left">
        
        @if (isset($header))
            {{ $header }}
        @elseif ($title)
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                <div class="flex items-center gap-2 min-w-0">
                    @if ($icon)
                        <span class="text-gray-500 shrink-0">{!! $icon !!}</span>
                    @endif
                    <div>
                        <h3 class="text-sm font-bold text-gray-800 tracking-tight leading-tight">{!! $title !!}</h3>
                        @if ($subtitle)
                            <p class="text-xs text-gray-500 mt-0.5">{!! $subtitle !!}</p>
                        @endif
                    </div>
                </div>
                <button type="button" @click="{{ $name }} = false" class="text-gray-400 hover:text-gray-600 transition cursor-pointer p-1 rounded-sm hover:bg-gray-200/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <div class="{{ $noPadding ? 'p-0' : $bodyClass }}">
            {{ $slot }}
        </div>

        @if (isset($footer))
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-end gap-2 rounded-b-sm">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>


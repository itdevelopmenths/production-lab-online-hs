@props([
    'name' => 'open',
    'title' => null,
    'maxWidth' => 'md',
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        default => 'max-w-md',
    };
@endphp

<div x-show="{{ $name }}"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0 flex items-center justify-center"
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
         class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"></div>

    {{-- Dialog Box (Sharp Corners) --}}
    <div x-show="{{ $name }}"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="relative bg-white rounded-sm border border-gray-300 shadow-lg w-full {{ $maxWidthClass }} z-10 overflow-visible">
        
        @if ($title)
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-800 tracking-tight">{{ $title }}</h3>
                <button type="button" @click="{{ $name }} = false" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <div class="p-4 text-xs text-gray-700">
            {{ $slot }}
        </div>

        @if (isset($footer))
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-end gap-2">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>

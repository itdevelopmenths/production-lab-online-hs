@props([
    'name' => 'open',
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'variant' => 'default',
    'maxWidth' => 'md',
    'noPadding' => false,
    'bodyClass' => 'bg-[#F5F5F5] dark:bg-gray-950 p-3 sm:p-4 md:p-5 flex flex-col gap-2.5 sm:gap-3.5 overflow-y-auto custom-scrollbar flex-1 text-sm text-text-base',
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
     class="fixed inset-0 z-[999] p-2 sm:p-4 md:p-6 flex items-center justify-center overflow-y-auto"
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
         class="fixed inset-0 bg-black/50 backdrop-blur-xs transition-opacity"
         aria-hidden="true"></div>

    {{-- AppForm Dialog Box --}}
    <div x-show="{{ $name }}"
         x-transition:enter="ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="relative bg-white dark:bg-gray-900 rounded-[12px] border border-[#E8E8E8] dark:border-border shadow-2xl w-full {{ $maxWidthClass }} z-10 overflow-hidden flex flex-col max-h-[calc(100dvh-2rem)] text-left transition-all">

        @if (isset($header))
            {{ $header }}
        @elseif ($title)
            <div class="px-3.5 py-2.5 sm:px-4 sm:py-3 bg-white dark:bg-gray-900 border-b border-[#E8E8E8] dark:border-border flex items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    @if ($icon)
                        <span class="size-8 rounded-[8px] bg-[#F9FAFB] dark:bg-gray-800 border border-[#E8E8E8] dark:border-border text-text-active flex items-center justify-center shrink-0 [&>svg]:size-4">{!! $icon !!}</span>
                    @endif
                    <div class="min-w-0">
                        <h3 class="font-bold text-base text-text-active tracking-tight leading-snug truncate">{!! $title !!}</h3>
                        @if ($subtitle)
                            <p class="text-xs text-text-muted mt-0.5 leading-normal">{!! $subtitle !!}</p>
                        @endif
                    </div>
                </div>
                <button type="button" @click="{{ $name }} = false" class="text-text-muted hover:text-text-active transition cursor-pointer p-1.5 rounded-[6px] hover:bg-gray-100 dark:hover:bg-gray-800 shrink-0" aria-label="Tutup modal">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <div class="{{ $noPadding ? 'p-0 bg-[#F5F5F5] dark:bg-gray-950 overflow-y-auto custom-scrollbar flex-1' : $bodyClass }}">
            {{ $slot }}
        </div>

        @if (isset($footer))
            <div class="bg-white dark:bg-gray-900 border-t border-[#E8E8E8] dark:border-border px-3.5 py-2.5 sm:px-4 sm:py-3 flex items-center justify-end gap-2 shrink-0">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>

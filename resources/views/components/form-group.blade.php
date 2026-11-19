@props([
    'name' => null,
    'label' => null,
    'required' => false,
    'help' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <div class="flex items-center justify-between gap-2">
            <label @if($name) for="{{ $name }}" @endif class="block text-[13px] sm:text-[14px] font-medium text-text-active leading-snug">
                {!! $label !!}
                @if ($required)
                    <span class="text-red-500 font-bold ml-0.5">*</span>
                @endif
            </label>
            @if (isset($labelRight))
                <div class="text-xs text-text-muted">
                    {{ $labelRight }}
                </div>
            @endif
        </div>
    @endif

    <div>
        {{ $slot }}
    </div>

    @if ($help)
        <p class="text-[11px] text-text-muted leading-tight">{!! $help !!}</p>
    @endif

    @if ($name && isset($errors) && $errors->has($name))
        <p class="text-xs text-red-600 dark:text-red-400 font-medium flex items-center gap-1.5 mt-1">
            <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ $errors->first($name) }}</span>
        </p>
    @endif
</div>

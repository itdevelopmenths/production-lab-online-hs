@props([
    'name' => null,
    'label' => null,
    'required' => false,
    'help' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @if ($label)
        <div class="flex items-center justify-between">
            <label @if($name) for="{{ $name }}" @endif class="block text-sm font-medium text-foreground">
                {!! $label !!}
                @if ($required)
                    <span class="text-destructive font-bold ml-0.5">*</span>
                @endif
            </label>
            @if (isset($labelRight))
                <div class="text-xs text-muted-foreground">
                    {{ $labelRight }}
                </div>
            @endif
        </div>
    @endif

    <div>
        {{ $slot }}
    </div>

    @if ($help)
        <p class="text-xs text-muted-foreground leading-tight">{!! $help !!}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="text-xs text-destructive font-medium flex items-center gap-1.5 mt-1">
                <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ $message }}</span>
            </p>
        @enderror
    @endif
</div>

@props([
    'name' => null,
    'label' => null,
    'required' => false,
    'help' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <div class="flex items-center justify-between">
            <label @if($name) for="{{ $name }}" @endif class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">
                {!! $label !!}
                @if ($required)
                    <span class="text-rose-500 font-bold ml-0.5">*</span>
                @endif
            </label>
            @if (isset($labelRight))
                <div class="text-[11px] text-gray-500">
                    {{ $labelRight }}
                </div>
            @endif
        </div>
    @endif

    <div>
        {{ $slot }}
    </div>

    @if ($help)
        <p class="text-[11px] text-gray-500 leading-tight">{!! $help !!}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="text-[11px] text-rose-600 font-medium flex items-center gap-1 mt-1">
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ $message }}</span>
            </p>
        @enderror
    @endif
</div>

@props([
    'name' => null,
    'label' => null,
    'value' => '1',
    'checked' => false,
    'help' => null,
    'disabled' => false,
])

<div class="flex items-start gap-2">
    @if ($name)
        <input type="hidden" name="{{ $name }}" value="0" />
    @endif
    <div class="flex items-center h-5">
        <input
            type="checkbox"
            @if($name) name="{{ $name }}" id="{{ $name }}" @endif
            value="{{ $value }}"
            @if($checked) checked @endif
            @if($disabled) disabled @endif
            {{ $attributes->merge([
                'class' => 'w-4 h-4 rounded-sm border-gray-300 text-primary-600 focus:ring-primary-500 shadow-xs cursor-pointer disabled:cursor-not-allowed'
            ]) }}
        />
    </div>
    @if ($label || $slot->isNotEmpty())
        <div class="text-xs">
            <label @if($name) for="{{ $name }}" @endif class="font-medium text-gray-700 cursor-pointer select-none">
                {{ $label ?? $slot }}
            </label>
            @if ($help)
                <p class="text-[11px] text-gray-500 mt-0.5 leading-tight">{{ $help }}</p>
            @endif
        </div>
    @endif
</div>

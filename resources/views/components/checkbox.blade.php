@props([
    'name' => null,
    'label' => null,
    'value' => '1',
    'checked' => false,
    'help' => null,
    'disabled' => false,
])

<div class="flex items-start gap-2.5">
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
                'class' => 'size-4 rounded border-border text-primary focus:ring-primary/20 shadow-xs cursor-pointer disabled:cursor-not-allowed transition'
            ]) }}
        />
    </div>
    @if ($label || $slot->isNotEmpty())
        <div class="text-sm">
            <label @if($name) for="{{ $name }}" @endif class="font-medium text-foreground cursor-pointer select-none">
                {{ $label ?? $slot }}
            </label>
            @if ($help)
                <p class="text-xs text-muted-foreground mt-0.5 leading-tight">{{ $help }}</p>
            @endif
        </div>
    @endif
</div>

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
                'class' => 'size-4 rounded-[4px] border-[#E8E8E8] dark:border-border bg-white dark:bg-gray-900 text-black dark:text-white accent-black dark:accent-white focus:ring-black/20 dark:focus:ring-white/20 shadow-theme-xs cursor-pointer disabled:cursor-not-allowed transition'
            ]) }}
        />
    </div>
    @if ($label || $slot->isNotEmpty())
        <div class="text-sm">
            <label @if($name) for="{{ $name }}" @endif class="font-medium text-text-active cursor-pointer select-none">
                {{ $label ?? $slot }}
            </label>
            @if ($help)
                <p class="text-xs text-text-muted mt-0.5 leading-tight">{{ $help }}</p>
            @endif
        </div>
    @endif
</div>

@props([
    'name' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'icon' => null,
    'addonRight' => null,
    'size' => 'default',
])

@php
    $hasError = $name && $errors->has($name);
    
    $borderClass = $hasError 
        ? 'border-destructive focus:ring-destructive/20 text-destructive bg-destructive/5' 
        : 'border-border focus:ring-primary/20 bg-card text-foreground';

    $sizeClasses = match($size) {
        'xs' => 'text-xs px-2.5 py-1 rounded-md h-7',
        'sm' => 'text-xs px-2.5 py-1 rounded-md h-8',
        'lg' => 'text-sm px-3.5 py-2 rounded-lg h-10',
        default => 'text-xs px-3 py-1.5 rounded-md h-8.5', // default / h-8.5
    };

    $paddingLeft = $icon ? 'pl-9' : '';
    $paddingRight = $addonRight ? 'pr-10' : '';
@endphp

<div class="relative w-full">
    @if ($icon)
        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-muted-foreground [&_svg]:size-3.5">
            {!! $icon !!}
        </span>
    @endif

    <input
        type="{{ $type }}"
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        value="{{ $value ?? ($name ? old($name) : null) }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
        {{ $attributes->merge([
            'class' => "w-full border shadow-2xs outline-none transition placeholder:text-muted-foreground disabled:bg-muted disabled:text-muted-foreground disabled:cursor-not-allowed focus:ring-1 $borderClass $sizeClasses $paddingLeft $paddingRight"
        ]) }}
    />

    @if ($addonRight)
        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-xs text-muted-foreground font-medium">
            {{ $addonRight }}
        </span>
    @endif

    {{ $slot }}
</div>

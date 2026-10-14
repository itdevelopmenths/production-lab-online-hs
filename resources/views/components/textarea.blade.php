@props([
    'name' => null,
    'rows' => 3,
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
])

@php
    $hasError = $name && $errors->has($name);
    
    $borderClass = $hasError 
        ? 'border-destructive focus:ring-destructive/20 text-destructive bg-destructive/5' 
        : 'border-border focus:ring-primary/20 bg-card text-foreground';
@endphp

<div class="relative w-full">
    <textarea
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        rows="{{ $rows }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
        {{ $attributes->merge([
            'class' => "w-full rounded-lg border shadow-xs outline-none transition placeholder:text-muted-foreground disabled:bg-muted disabled:text-muted-foreground disabled:cursor-not-allowed focus:ring-2 text-sm px-3.5 py-2.5 $borderClass"
        ]) }}
    >{{ $value ?? ($name ? old($name) : null) }}{{ $slot }}</textarea>
</div>

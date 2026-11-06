@props([
    'name' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
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
        default => 'text-xs px-3 py-1.5 rounded-md h-8.5', // default
    };
@endphp

<div class="relative w-full">
    <select
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        {{ $attributes->merge([
            'class' => "w-full border shadow-2xs outline-none transition disabled:bg-muted disabled:text-muted-foreground disabled:cursor-not-allowed focus:ring-1 cursor-pointer pr-8 $borderClass $sizeClasses"
        ]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>
</div>

@props([
    'variant' => 'neutral', // neutral, info, progress, warning, success, danger, muted, primary, secondary
    'dot' => false,
    'size' => 'default', // default, sm
])

@php
    $tone = match ($variant) {
        'primary', 'indigo', 'progress' => 'progress',
        'info', 'blue', 'sky' => 'info',
        'warning', 'amber', 'gold' => 'warning',
        'success', 'emerald', 'green' => 'success',
        'danger', 'rose', 'red' => 'danger',
        'muted' => 'muted',
        default => 'neutral',
    };

    $classes = match ($tone) {
        'info' => 'bg-sky-50 text-sky-700 border border-sky-200/60',
        'progress' => 'bg-indigo-50 text-indigo-700 border border-indigo-200/60',
        'warning' => 'bg-amber-50 text-amber-600 border border-amber-200/60',
        'success' => 'bg-emerald-50 text-emerald-700 border border-emerald-200/60',
        'danger' => 'bg-red-50 text-red-600 border border-red-200/60',
        'muted' => 'bg-zinc-50 text-zinc-400 border border-zinc-200/60',
        default => 'bg-zinc-100 text-zinc-700 border border-zinc-200/60',
    };

    $dotClasses = match ($tone) {
        'info' => 'bg-sky-500',
        'progress' => 'bg-indigo-500',
        'warning' => 'bg-amber-500',
        'success' => 'bg-emerald-500',
        'danger' => 'bg-red-500',
        'muted' => 'bg-zinc-400',
        default => 'bg-zinc-500',
    };

    $sizeClasses = match ($size) {
        'sm' => 'text-[10px] px-2 py-0.5',
        default => 'text-[11px] px-2.5 py-1',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-md font-semibold tracking-wide whitespace-nowrap uppercase ' . $classes . ' ' . $sizeClasses]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $dotClasses }}"></span>
    @endif
    <span>{{ $slot }}</span>
</span>

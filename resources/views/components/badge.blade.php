@props([
    'variant' => 'neutral', // neutral, info, progress, warning, success, danger, muted, primary, secondary
    'dot' => false,
    'size' => 'default', // default, sm
])

@php
    $tone = match ($variant) {
        'primary' => 'primary',
        'info', 'blue', 'sky' => 'info',
        'progress', 'indigo' => 'progress',
        'warning', 'amber', 'gold' => 'warning',
        'success', 'emerald', 'green' => 'success',
        'danger', 'rose', 'red' => 'danger',
        'muted' => 'muted',
        default => 'neutral',
    };

    $classes = match ($tone) {
        'primary' => 'bg-zinc-900 text-white border border-zinc-900',
        'info' => 'bg-sky-50 text-sky-700 border border-sky-200/80',
        'progress' => 'bg-zinc-100 text-zinc-800 border border-zinc-300',
        'warning' => 'bg-amber-50 text-amber-700 border border-amber-200/80',
        'success' => 'bg-emerald-50 text-emerald-700 border border-emerald-200/80',
        'danger' => 'bg-red-50 text-red-600 border border-red-200/80',
        'muted' => 'bg-zinc-50 text-zinc-500 border border-zinc-200/60',
        default => 'bg-zinc-100 text-zinc-700 border border-zinc-200/80',
    };

    $dotClasses = match ($tone) {
        'primary' => 'bg-white',
        'info' => 'bg-sky-500',
        'progress' => 'bg-zinc-700',
        'warning' => 'bg-amber-500',
        'success' => 'bg-emerald-500',
        'danger' => 'bg-red-500',
        'muted' => 'bg-zinc-400',
        default => 'bg-zinc-500',
    };

    $sizeClasses = match ($size) {
        'sm' => 'text-[9px] px-1.5 py-0.5 rounded',
        default => 'text-[10px] px-2 py-0.5 rounded-md',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-md font-semibold tracking-wide whitespace-nowrap uppercase ' . $classes . ' ' . $sizeClasses]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $dotClasses }}"></span>
    @endif
    <span>{{ $slot }}</span>
</span>

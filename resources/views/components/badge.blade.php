@props([
    'variant' => 'secondary', // primary, success, danger, warning, info, secondary, gold, gray
    'dot' => false,
    'size' => 'xs', // xs, sm
])

@php
    $classes = match ($variant) {
        'primary' => 'bg-primary-50 text-primary-700 border border-primary-200',
        'success' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        'danger' => 'bg-rose-50 text-rose-700 border border-rose-200',
        'warning' => 'bg-amber-50 text-amber-800 border border-amber-200',
        'info' => 'bg-blue-50 text-blue-700 border border-blue-200',
        'gold' => 'bg-gold-50 text-gold-800 border border-gold-300',
        'gray', 'secondary' => 'bg-gray-100 text-gray-700 border border-gray-200',
        default => 'bg-gray-100 text-gray-700 border border-gray-200',
    };

    $dotClasses = match ($variant) {
        'primary' => 'bg-primary-600',
        'success' => 'bg-emerald-600',
        'danger' => 'bg-rose-600',
        'warning' => 'bg-amber-600',
        'info' => 'bg-blue-600',
        'gold' => 'bg-gold-600',
        default => 'bg-gray-500',
    };

    $sizeClasses = match ($size) {
        'sm' => 'text-xs px-2 py-0.5',
        default => 'text-[11px] px-1.5 py-0.25',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 font-semibold rounded-sm tracking-wide ' . $classes . ' ' . $sizeClasses]) }}>
    @if ($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses }}"></span>
    @endif
    {{ $slot }}
</span>

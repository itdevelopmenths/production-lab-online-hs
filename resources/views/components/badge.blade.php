@props([
    'variant' => 'neutral', // neutral, primary, info, progress, warning, success, danger, muted, plus transaction status keys
    'dot' => false,
    'pill' => false,
    'size' => 'default', // default, sm, xs
])

@php
    $tone = match ($variant) {
        'primary' => 'primary',
        'info', 'blue', 'sky', 'in_transit', 'diproses', 'dikirim_ke_gudang', 'dikirim' => 'info',
        'teal', 'received', 'diterima', 'parsial' => 'teal',
        'progress', 'indigo' => 'progress',
        'warning', 'amber', 'gold', 'pending', 'diajukan' => 'warning',
        'success', 'emerald', 'green', 'completed', 'selesai', 'disetujui', 'aktif' => 'success',
        'danger', 'rose', 'red', 'cancelled', 'ditolak', 'dibatalkan', 'nonaktif' => 'danger',
        'muted', 'draft' => 'muted',
        default => 'neutral',
    };

    $classes = match ($tone) {
        'primary' => 'bg-black text-white border border-black dark:bg-white dark:text-[#0A0A0A] dark:border-white',
        'info' => 'bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20',
        'teal' => 'bg-teal-50 text-teal-700 border border-teal-200 dark:bg-teal-500/10 dark:text-teal-400 dark:border-teal-500/20',
        'progress' => 'bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20',
        'warning' => 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
        'success' => 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
        'danger' => 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20',
        'muted' => 'bg-gray-100 text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
        default => 'bg-gray-100 text-gray-800 border border-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700',
    };

    $dotClasses = match ($tone) {
        'primary' => 'bg-white dark:bg-[#0A0A0A]',
        'info' => 'bg-blue-500 dark:bg-blue-400',
        'teal' => 'bg-teal-500 dark:bg-teal-400',
        'progress' => 'bg-indigo-500 dark:bg-indigo-400',
        'warning' => 'bg-amber-500 dark:bg-amber-400',
        'success' => 'bg-emerald-500 dark:bg-emerald-400',
        'danger' => 'bg-red-500 dark:bg-red-400',
        'muted' => 'bg-gray-400 dark:bg-gray-500',
        default => 'bg-gray-500 dark:bg-gray-400',
    };

    $shapeClass = $pill ? 'rounded-full' : 'rounded-md';

    $sizeClasses = match ($size) {
        'xs' => 'text-[9px] px-1.5 py-0.5',
        'sm' => 'text-[10px] px-1.5 py-0.5',
        default => 'text-[11px] px-2.5 py-0.5',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 font-semibold tracking-wide whitespace-nowrap uppercase ' . $shapeClass . ' ' . $classes . ' ' . $sizeClasses]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $dotClasses }}"></span>
    @endif
    <span>{{ $slot }}</span>
</span>

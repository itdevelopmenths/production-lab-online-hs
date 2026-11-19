@props([
    'variant' => 'neutral', // neutral, primary, info, progress, warning, success, danger, muted, plus transaction status keys
    'dot' => false,
    'pill' => false,
    'size' => 'default', // default, sm, xs
])

{{--
    Badge minimalis: satu bentuk (pill putih + garis tipis) untuk semua status.
    Warna hanya muncul sebagai titik kecil (dot) dan hanya merah untuk danger,
    sehingga status tetap terbaca dari label + titik tanpa blok warna-warni.
--}}
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
        'primary' => 'bg-gray-900 text-white border border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white',
        'danger' => 'bg-white text-red-700 border border-red-200 dark:bg-transparent dark:text-red-400 dark:border-red-500/30',
        'muted' => 'bg-white text-gray-500 border border-gray-200 dark:bg-transparent dark:text-gray-400 dark:border-gray-700',
        default => 'bg-white text-gray-700 border border-gray-200 dark:bg-transparent dark:text-gray-200 dark:border-gray-700',
    };

    $dotClasses = match ($tone) {
        'primary' => 'bg-white dark:bg-gray-900',
        'info' => 'bg-sky-500',
        'teal' => 'bg-teal-500',
        'progress' => 'bg-indigo-500',
        'warning' => 'bg-amber-500',
        'success' => 'bg-emerald-500',
        'danger' => 'bg-red-500',
        default => 'bg-gray-400',
    };

    // Status berwarna selalu diberi titik agar tetap terbaca tanpa latar warna.
    $showDot = $dot || in_array($tone, ['info', 'teal', 'progress', 'warning', 'success', 'danger'], true);

    $shapeClass = $pill ? 'rounded-full' : 'rounded-md';

    $sizeClasses = match ($size) {
        'xs', 'sm' => 'text-[11px] px-2 py-0.5',
        default => 'text-xs px-2.5 py-0.5',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 font-medium whitespace-nowrap ' . $shapeClass . ' ' . $classes . ' ' . $sizeClasses]) }}>
    @if ($showDot)
        <span class="size-1.5 rounded-full shrink-0 {{ $dotClasses }}"></span>
    @endif
    <span>{{ $slot }}</span>
</span>

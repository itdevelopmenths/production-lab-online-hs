<?php

namespace App\Support;

class UiTableHelper
{
    public static function actionLink(
        string $href,
        string $label,
        string $icon = 'eye',
        string $tone = 'default',
        array $attrs = []
    ): string {
        $extraClass = (string) ($attrs['class'] ?? '');
        unset($attrs['class']);
        $classes = self::buttonClasses($tone, $extraClass);
        $extraAttrs = self::renderAttributes($attrs);
        $svg = self::iconSvg($icon);
        $escapedLabel = e($label);

        return '<a href="' . e($href) . '" title="' . $escapedLabel . '" aria-label="' . $escapedLabel . '" class="' . $classes . '"' . $extraAttrs . '>'
            . $svg
            . '<span class="sr-only">' . $escapedLabel . '</span>'
            . '</a>';
    }

    public static function actionButton(
        string $onclick,
        string $label,
        string $icon = 'trash',
        string $tone = 'danger',
        array $attrs = []
    ): string {
        $extraClass = (string) ($attrs['class'] ?? '');
        unset($attrs['class']);
        $classes = self::buttonClasses($tone, $extraClass);
        $type = e($attrs['type'] ?? 'button');
        unset($attrs['type']);
        $extraAttrs = self::renderAttributes($attrs);
        $svg = self::iconSvg($icon);
        $escapedLabel = e($label);
        $onclickAttr = $onclick !== '' ? ' onclick="' . e($onclick) . '"' : '';

        return '<button type="' . $type . '"' . $onclickAttr . ' title="' . $escapedLabel . '" aria-label="' . $escapedLabel . '" class="' . $classes . '"' . $extraAttrs . '>'
            . $svg
            . '<span class="sr-only">' . $escapedLabel . '</span>'
            . '</button>';
    }

    public static function actionGroup(array $buttonsHtml, string $align = 'center'): string
    {
        $items = array_filter($buttonsHtml, fn ($item) => trim((string) $item) !== '');
        if ($items === []) {
            return '<span class="text-xs text-[#6a7282] dark:text-gray-500">—</span>';
        }

        $justify = match ($align) {
            'start', 'left' => 'justify-start',
            'end', 'right' => 'justify-end',
            default => 'justify-center',
        };

        return '<div class="flex items-center ' . $justify . ' gap-1.5">' . implode('', $items) . '</div>';
    }

    /** Status modern: titik warna + label (untuk tabel `tbl-modern`). */
    public static function statusDot(string $label, string $tone = 'neutral'): string
    {
        $dot = match ($tone) {
            'ok', 'success', 'aktif', 'selesai' => 'tbl-dot--ok',
            'warn', 'warning', 'pending' => 'tbl-dot--warn',
            'bad', 'danger', 'nonaktif', 'dibatalkan' => 'tbl-dot--bad',
            'info' => 'tbl-dot--info',
            'ink' => 'tbl-dot--ink',
            default => '',
        };

        return '<span class="tbl-status"><i class="tbl-dot ' . $dot . '" aria-hidden="true"></i>' . e($label) . '</span>';
    }

    /** Label kecil netral, opsional dengan titik warna. */
    public static function tag(string $label, ?string $dotTone = null): string
    {
        $dot = $dotTone ? '<i class="tbl-dot tbl-dot--' . e($dotTone) . '" aria-hidden="true"></i>' : '';

        return '<span class="tbl-tag">' . $dot . e($label) . '</span>';
    }

    public static function statusPill(string $label, string $tone = 'draft'): string
    {
        $toneClasses = match ($tone) {
            'pending', 'diajukan', 'warning', 'amber' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
            'in_transit', 'diproses', 'dikirim', 'dikirim_ke_gudang', 'info', 'blue' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20',
            'received', 'diterima', 'parsial', 'teal' => 'bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-500/10 dark:text-teal-400 dark:border-teal-500/20',
            'completed', 'selesai', 'disetujui', 'aktif', 'success', 'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
            'cancelled', 'ditolak', 'dibatalkan', 'nonaktif', 'danger', 'red' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20',
            'progress', 'indigo' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20',
            default => 'bg-gray-100 border-gray-200 text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300',
        };

        return '<span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold tracking-wide whitespace-nowrap ' . $toneClasses . '">'
            . e($label)
            . '</span>';
    }

    private static function buttonClasses(string $tone, string $extraClass = ''): string
    {
        $base = 'size-[32px] rounded-[6px] bg-white dark:bg-gray-900 inline-flex items-center justify-center transition-colors border shrink-0 cursor-pointer';

        $toneClass = match ($tone) {
            'danger' => 'border-[#ffc9c9] dark:border-red-500/30 hover:bg-red-50/70 dark:hover:bg-red-950/50 text-[#e7000b] dark:text-red-400',
            'success' => 'border-[#a4f4cf] dark:border-emerald-500/30 hover:bg-emerald-50/70 dark:hover:bg-emerald-950/50 text-[#007a55] dark:text-emerald-400',
            'warning' => 'border-amber-200 dark:border-amber-500/30 hover:bg-amber-50/70 dark:hover:bg-amber-950/50 text-amber-700 dark:text-amber-400',
            'info' => 'border-blue-200 dark:border-blue-500/30 hover:bg-blue-50/70 dark:hover:bg-blue-950/50 text-blue-700 dark:text-blue-400',
            default => 'border-[#e8e8e8] dark:border-border hover:bg-gray-50 dark:hover:bg-gray-800 text-[#101828] dark:text-white',
        };

        $extra = trim($extraClass);

        return $base . ' ' . $toneClass . ($extra !== '' ? ' ' . e($extra) : '');
    }

    private static function renderAttributes(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $key => $val) {
            if ($val === null || $val === false) {
                continue;
            }
            $k = preg_replace('/[^a-zA-Z0-9\-_:@.]/', '', (string) $key);
            if ($k === '') {
                continue;
            }
            if ($val === true) {
                $html .= ' ' . $k;
            } else {
                $html .= ' ' . $k . '="' . e((string) $val) . '"';
            }
        }

        return $html;
    }

    private static function iconSvg(string $icon): string
    {
        $path = match ($icon) {
            'pencil', 'edit' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>',
            'trash', 'delete' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>',
            'check' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
            'x', 'close' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
            'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
            'book', 'ledger' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
            'sliders' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>',
            'layers' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>',
            'printer' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>',
            'send' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>',
            'plus' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>',
            default => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>',
        };

        return '<svg class="size-[14px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">' . $path . '</svg>';
    }
}

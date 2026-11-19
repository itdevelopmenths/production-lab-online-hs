@props(['label', 'tone' => 'default'])

<div {{ $attributes->merge(['class' => 'card-elevated rounded-[14px] border bg-card p-4 flex flex-col gap-2.5 min-w-0 ' . ($tone === 'danger' ? 'border-destructive/30' : 'border-border')]) }}>
    <div class="flex items-center justify-between gap-2">
        <span class="text-[12px] text-muted-foreground">{{ $label }}</span>
        @isset($icon)
            <span class="size-8 rounded-[9px] bg-[#F4F6FE] dark:bg-[#627EF2]/15 text-[#3C55C9] dark:text-[#AEBBF9] flex items-center justify-center shrink-0 [&>svg]:size-4" aria-hidden="true">{{ $icon }}</span>
        @endisset
    </div>
    <div class="min-w-0">{{ $slot }}</div>
</div>

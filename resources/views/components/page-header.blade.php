@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
    'backUrl' => null,
    'backLabel' => 'Kembali',
    'backSize' => 'sm',
])

<div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-border">
    <div class="min-w-0">
        <h1 class="text-lg font-bold text-foreground tracking-tight leading-tight">{!! $title !!}</h1>
        @if ($subtitle)
            <p class="text-xs text-muted-foreground mt-0.5 leading-normal">{!! $subtitle !!}</p>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        @if (!empty($breadcrumbs))
            <nav class="flex items-center text-xs text-muted-foreground" aria-label="Breadcrumb">
                <ol class="inline-flex flex-wrap items-center space-x-1 sm:space-x-1.5">
                    <li class="inline-flex items-center">
                        <a href="{{ route('dashboard') }}" class="text-muted-foreground hover:text-foreground transition-colors inline-flex items-center">
                            <svg class="size-3.5 mr-1" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            Home
                        </a>
                    </li>
                    @foreach ($breadcrumbs as $label => $url)
                        <li>
                            <div class="flex items-center">
                                <span class="mx-1 text-muted-foreground/40" aria-hidden="true">/</span>
                                @if ($url && !$loop->last)
                                    <a href="{{ $url }}" class="text-muted-foreground hover:text-foreground transition-colors">{!! $label !!}</a>
                                @else
                                    <span class="text-foreground font-medium" @if($loop->last) aria-current="page" @endif>{!! $label !!}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        @if ($backUrl || isset($actions))
            <div class="flex flex-wrap items-center gap-2">
                {{ $actions ?? '' }}

                @if ($backUrl)
                    <x-button :href="$backUrl" variant="outline" :size="$backSize" data-back-button="true" class="pointer-coarse:min-h-11">
                        <x-slot:icon>
                            <svg class="size-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        </x-slot:icon>
                        {{ $backLabel }}
                    </x-button>
                @endif
            </div>
        @endif
    </div>
</div>

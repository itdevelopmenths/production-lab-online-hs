@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
])

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border/60">
    <div>
        <h1 class="text-xl font-bold text-foreground tracking-tight leading-tight">{!! $title !!}</h1>
        @if ($subtitle)
            <p class="text-sm text-muted-foreground mt-1 leading-normal">{!! $subtitle !!}</p>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-3">
        @if (!empty($breadcrumbs))
            <nav class="flex items-center text-xs text-muted-foreground" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 sm:space-x-1.5">
                    <li class="inline-flex items-center">
                        <a href="{{ route('dashboard') }}" class="text-muted-foreground hover:text-foreground transition-colors inline-flex items-center">
                            <svg class="size-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            Home
                        </a>
                    </li>
                    @foreach ($breadcrumbs as $label => $url)
                        <li>
                            <div class="flex items-center">
                                <span class="mx-1 text-muted-foreground/40">/</span>
                                @if ($url && !$loop->last)
                                    <a href="{{ $url }}" class="text-muted-foreground hover:text-foreground transition-colors">{!! $label !!}</a>
                                @else
                                    <span class="text-foreground font-medium">{!! $label !!}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        @if (isset($actions))
            <div class="flex items-center gap-2.5">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>

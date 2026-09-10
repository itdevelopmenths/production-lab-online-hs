@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
])

<div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-gray-200/80">
    <div>
        <h1 class="text-xl font-bold text-gray-900 tracking-tight leading-none">{!! $title !!}</h1>
        @if ($subtitle)
            <p class="text-xs text-gray-500 mt-1.5">{!! $subtitle !!}</p>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-3">
        @if (!empty($breadcrumbs))
            <nav class="flex items-center text-xs text-gray-500" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 sm:space-x-1.5">
                    <li class="inline-flex items-center">
                        <a href="{{ route('dashboard') }}" class="text-gray-500 hover:text-primary-600 inline-flex items-center">
                            <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path></svg>
                            Home
                        </a>
                    </li>
                    @foreach ($breadcrumbs as $label => $url)
                        <li>
                            <div class="flex items-center">
                                <span class="mx-1 text-gray-400">/</span>
                                @if ($url && !$loop->last)
                                    <a href="{{ $url }}" class="text-gray-500 hover:text-primary-600">{!! $label !!}</a>
                                @else
                                    <span class="text-gray-700 font-medium">{!! $label !!}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        @if (isset($actions))
            <div class="flex items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>

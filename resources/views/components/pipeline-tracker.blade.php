@props([
    'steps' => [],
    'currentStep' => 1,
    'isCancelled' => false,
])

<div class="py-3 px-4 bg-muted/40 border border-border rounded-xl">
    <div class="flex items-center justify-between w-full relative">
        @foreach ($steps as $index => $step)
            @php
                $stepNum = $index + 1;
                $isPassed = $stepNum < $currentStep;
                $isCurrent = $stepNum === $currentStep;
                $isPending = $stepNum > $currentStep;
            @endphp

            <div class="flex-1 flex flex-col items-center relative group">
                {{-- Connector line --}}
                @if (!$loop->first)
                    <div class="absolute top-3.5 right-1/2 w-full h-0.5 -translate-y-1/2 {{ $isPassed || $isCurrent ? 'bg-primary' : 'bg-border' }} -z-0"></div>
                @endif

                {{-- Step Indicator --}}
                <div class="size-7 rounded-full flex items-center justify-center text-xs font-bold z-10 border transition-all {{
                    $isCancelled && $isCurrent
                        ? 'bg-destructive text-destructive-foreground border-destructive ring-2 ring-destructive/20'
                        : ($isPassed
                            ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                            : ($isCurrent
                                ? 'bg-primary text-primary-foreground border-primary ring-4 ring-primary/10 shadow-xs'
                                : 'bg-card text-muted-foreground border-border'))
                }}">
                    @if ($isCancelled && $isCurrent)
                        ✕
                    @elseif ($isPassed)
                        ✓
                    @else
                        {{ $stepNum }}
                    @endif
                </div>

                {{-- Label --}}
                <span class="text-[11px] font-medium tracking-tight mt-1.5 text-center truncate max-w-[85px] sm:max-w-none {{
                    $isCurrent ? 'text-foreground font-semibold' : ($isPassed ? 'text-foreground' : 'text-muted-foreground')
                }}">
                    {{ $step }}
                </span>
            </div>
        @endforeach
    </div>
</div>

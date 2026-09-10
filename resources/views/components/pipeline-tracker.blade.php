@props([
    'steps' => [],
    'currentStep' => 1,
    'isCancelled' => false,
])

<div class="py-2.5 px-3 bg-gray-50 border border-gray-200 rounded-sm">
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
                    <div class="absolute top-3 right-1/2 w-full h-0.5 -translate-y-1/2 {{ $isPassed || $isCurrent ? 'bg-primary-600' : 'bg-gray-300' }} -z-0"></div>
                @endif

                {{-- Step Indicator --}}
                <div class="w-6 h-6 rounded-sm flex items-center justify-center text-[10px] font-bold z-10 border transition-all duration-200 {{
                    $isCancelled && $isCurrent
                        ? 'bg-rose-600 text-white border-rose-600 ring-2 ring-rose-200'
                        : ($isPassed
                            ? 'bg-primary-600 text-white border-primary-600'
                            : ($isCurrent
                                ? 'bg-white text-primary-700 border-primary-600 ring-2 ring-primary-100 font-extrabold'
                                : 'bg-gray-100 text-gray-500 border-gray-300'))
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
                <span class="text-[10px] font-medium tracking-tight mt-1 text-center truncate max-w-[80px] sm:max-w-none {{
                    $isCurrent ? 'text-gray-900 font-bold' : ($isPassed ? 'text-gray-700' : 'text-gray-400')
                }}">
                    {{ $step }}
                </span>
            </div>
        @endforeach
    </div>
</div>

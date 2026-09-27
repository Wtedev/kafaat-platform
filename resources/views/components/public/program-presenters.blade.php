@props([
    'presenters' => null,
])

@php
    $items = \App\Support\TrainingProgramExtrasSupport::normalizeProgramPresenters(
        is_array($presenters) ? $presenters : null,
    );
@endphp

@if ($items !== [])
<section {{ $attributes->class(['program-presenters']) }} aria-labelledby="program-presenters-heading">
    <div class="mb-5 flex items-start gap-3">
        <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#335483]/10 text-[#335483]" aria-hidden="true">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-4-4h-1m-4 6H3v-2a4 4 0 014-4h4m0-4a4 4 0 11-8 0 4 4 0 018 0zm8 0a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
        </span>
        <div class="min-w-0">
            <h3 id="program-presenters-heading" class="text-base font-semibold tracking-tight text-brand sm:text-lg">
                مقدمو البرنامج
            </h3>
        </div>
    </div>

    <ul class="space-y-3 sm:space-y-3.5">
        @foreach ($items as $presenter)
            <li class="flex gap-3 rounded-2xl bg-[#F7FAFC] p-3.5 ring-1 ring-[#c5d4e4]/60 sm:gap-4 sm:p-4">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#335483] text-sm font-semibold text-white shadow-sm sm:size-10 sm:text-base" aria-hidden="true">
                    {{ \App\Support\TrainingProgramExtrasSupport::presenterInitials($presenter['name']) }}
                </span>
                <div class="min-w-0 flex-1 pt-0.5">
                    <p class="text-[15px] font-semibold leading-7 text-gray-900 sm:text-base">
                        {{ $presenter['name'] }}
                    </p>
                    @if ($presenter['role'] !== '')
                        <p class="mt-1 text-sm leading-6 text-gray-600">
                            {{ $presenter['role'] }}
                        </p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>
@endif

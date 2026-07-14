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
    <div class="mb-4 flex items-start gap-3">
        <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#335483]/10 text-[#335483]" aria-hidden="true">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
        </span>
        <div class="min-w-0">
            <h3 id="program-presenters-heading" class="text-base font-semibold tracking-tight text-[#335483] sm:text-lg">
                مقدمو البرنامج
            </h3>
            <p class="mt-1 text-sm leading-relaxed text-gray-500">
                الخبراء الذين يقدّمون محاور البرنامج للمستفيدين.
            </p>
        </div>
    </div>

    <ul class="divide-y divide-[#c5d4e4]/55 overflow-hidden rounded-xl ring-1 ring-[#c5d4e4]/55" role="list">
        @foreach ($items as $presenter)
            @php
                $initials = \App\Support\TrainingProgramExtrasSupport::presenterInitials($presenter['name']);
                $roleLabel = $presenter['role'] !== '' ? $presenter['role'] : 'مقدّم البرنامج';
            @endphp
            <li class="group flex items-center gap-3 bg-[#F7FAFC]/50 px-3.5 py-2.5 transition-colors duration-200 hover:bg-[#F7FAFC] sm:gap-3.5 sm:px-4 sm:py-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#335483] text-[10px] font-semibold tracking-wide text-white transition duration-200 group-hover:bg-[#2a466e] sm:size-10 sm:text-[11px]" aria-hidden="true">
                    {{ $initials }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-medium leading-5 text-gray-800 sm:text-xs">
                        {{ $presenter['name'] }}
                    </p>
                    <p class="mt-0.5 text-[10px] leading-4 text-[#335483]/75 sm:text-[11px]">
                        {{ $roleLabel }}
                    </p>
                </div>
            </li>
        @endforeach
    </ul>
</section>
@endif

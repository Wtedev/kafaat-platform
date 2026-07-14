@props([
'backHref',
'backLabel',
'title',
'description' => null,
'descriptionHeading' => 'النبذة',
'hasImage' => false,
'imageUrl' => '',
'mediaContext' => 'program',
'programKind' => null,
'objectFit' => 'cover',
])

@php
$hasSidebar = isset($sidebar) && ! $sidebar->isEmpty();
$hasAction = isset($action) && ! $action->isEmpty();
$hasExtra = isset($extra) && ! $extra->isEmpty();
$hasMediaBadges = isset($mediaBadges) && ! $mediaBadges->isEmpty();
@endphp

<nav class="mb-5" aria-label="مسار التنقل">
    <a href="{{ $backHref }}" class="inline-flex items-center gap-2 rounded-lg text-sm font-medium transition-colors hover:text-[#1e3a5f]" style="color:#335483">
        <svg class="h-4 w-4 shrink-0 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        {{ $backLabel }}
    </a>
</nav>

<header class="mb-6 max-w-4xl">
    <h1 class="text-2xl font-bold leading-tight tracking-tight text-gray-900 sm:text-3xl lg:text-[2rem]">{{ $title }}</h1>
</header>

<div class="relative mb-8 overflow-hidden rounded-3xl">
    <x-public.card-media variant="hero" :mediaContext="$mediaContext" :programKind="$programKind" :hasImage="$hasImage" :imageUrl="$imageUrl" :objectFit="$objectFit" :alt="$title" />
    @if ($hasMediaBadges)
    <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/55 via-black/25 to-transparent px-4 pb-4 pt-16 sm:px-5 sm:pb-5">
        <div class="pointer-events-auto flex flex-wrap items-center gap-2">
            {{ $mediaBadges }}
        </div>
    </div>
    @endif
</div>

<div @class([ 'grid gap-6' , 'grid-cols-1' , $hasSidebar ? 'md:grid-cols-[minmax(0,1fr)_minmax(0,3fr)]' : '' , ])>
    @if ($hasSidebar)
    <div class="order-2 md:order-none md:col-start-1 md:row-start-1 md:self-start">
        {{ $sidebar }}
    </div>
    @endif

    <div @class([ 'order-1 min-w-0 md:order-none' , $hasSidebar ? 'md:col-start-2 md:row-start-1' : '' , ])>
        <article class="overflow-hidden rounded-2xl bg-white">
            <div class="p-6 sm:p-8">
                @if (filled($descriptionHeading))
                <h2 class="mb-5 text-lg font-medium text-gray-900">{{ $descriptionHeading }}</h2>
                @endif

                @if (filled($description))
                @php
                    $descriptionBody = (string) $description;
                    $isRichHtml = $descriptionBody !== '' && preg_match('/<[a-z][\s\S]*>/i', $descriptionBody);
                @endphp
                <div class="max-w-none text-[15px] leading-8 text-gray-600 sm:text-base text-right font-sans {{ $isRichHtml ? 'prose prose-lg prose-headings:text-[#111827] prose-a:text-[#335483] prose-strong:text-[#111827]' : 'whitespace-pre-line' }}" style="direction: rtl">
                    @if ($isRichHtml)
                        {!! clean($descriptionBody) !!}
                    @else
                        {!! nl2br(e($descriptionBody)) !!}
                    @endif
                </div>
                @else
                <div class="max-w-none text-[15px] leading-8 text-gray-600 sm:text-base">
                    {{ $descriptionSlot ?? '' }}
                </div>
                @endif
            </div>

            @if ($hasAction)
            <div class="px-6 pb-6 sm:px-8 sm:pb-8">
                {{ $action }}
            </div>
            @endif
        </article>
    </div>
</div>

@if ($hasExtra)
<div class="mt-6 lg:mt-8">
    {{ $extra }}
</div>
@endif

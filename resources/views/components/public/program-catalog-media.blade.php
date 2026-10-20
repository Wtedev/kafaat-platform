@props([
'program',
'index' => 0,
])

@php
$ended = $program->publicRegistrationUxState() === 'ended';
$compactLogo = str_contains((string) ($program->image ?? ''), 'tatawaa-waei');
@endphp

<div class="relative overflow-hidden">
    <div @class(['h-full w-full', 'grayscale' => $ended])>
        <x-public.card-media variant="catalog" mediaContext="program" :programKind="$program->program_kind" :hasImage="filled($program->image)" :imageUrl="$program->imagePublicUrl()" :objectFit="$compactLogo ? 'contain' : 'cover'" :alt="$program->title" :index="$index" />
    </div>
    <div class="pointer-events-none absolute inset-x-0 top-0 flex justify-start p-3">
        <x-public.program-registration-status-badge :program="$program" on-media />
    </div>
</div>

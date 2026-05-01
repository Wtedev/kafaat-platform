{{-- Expects: $visible (bool), $toggle (string), $cvLocale (optional) --}}
@php
    $loc = $cvLocale ?? 'ar';
    $showLabel = $loc === 'en' ? 'Show in CV' : 'إظهار في السيرة';
    $hideLabel = $loc === 'en' ? 'Hide from CV' : 'إخفاء من السيرة';
    $label = $visible ? $hideLabel : $showLabel;
@endphp
<form method="POST" action="{{ route('portal.competency.update') }}" class="inline-flex shrink-0">
    @csrf
    @method('PATCH')
    <input type="hidden" name="section" value="visibility" />
    <input type="hidden" name="toggle" value="{{ $toggle }}" />
    <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-[#253B5B]/25" title="{{ $label }}" aria-label="{{ $label }}">
        @if ($visible)
        @include('portal.competency.partials.icon-eye')
        @else
        @include('portal.competency.partials.icon-eye-off')
        @endif
    </button>
</form>

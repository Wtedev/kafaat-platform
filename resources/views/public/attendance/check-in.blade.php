@extends('layouts.public')
@section('title', 'التحضير')
@section('head')
    <style>
        .survey-shell { max-width: 40rem; overflow: hidden; }
        .survey-body { padding: 1.5rem 1.5rem 2rem; }
        .survey-hero {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef2f6;
        }
        .survey-hero img {
            display: block;
            width: 100%;
            height: 12rem;
            object-fit: cover;
        }
        .survey-hero.is-contain img {
            height: 13rem;
            object-fit: contain;
            padding: 1.25rem 1.5rem;
        }
        .survey-kicker {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #e8eef5;
            color: #335483;
            padding: 0.2rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 700;
        }
    </style>
@endsection
@section('content')
    @php
        $program = $link->program;
        $programTitle = $program?->title;
        $headerImage = $program && filled($program->image) ? $program->imagePublicUrl() : null;
        $headerContain = $program?->imageUsesContainFit() ?? false;
        $headerSurface = $headerContain ? ($program->imageHeroSurfaceColor() ?: '#eef2f6') : null;
    @endphp
    <div class="survey-shell mx-auto rounded-2xl border border-gray-100 bg-white">
        @if ($headerImage)
            <div class="survey-hero {{ $headerContain ? 'is-contain' : '' }}" @if ($headerSurface) style="background: {{ $headerSurface }}" @endif>
                <img src="{{ $headerImage }}" alt="{{ $programTitle }}">
            </div>
        @endif
        <div class="survey-body">
            <div class="mb-5">
                <span class="survey-kicker">{{ $link->name }}</span>
                @if ($programTitle)
                    <p class="mt-3 text-sm text-gray-500">{{ $programTitle }}</p>
                @endif
            </div>

            @if ($step === 'unavailable')
                <h1 class="text-xl font-semibold text-[#335483]">{{ \App\Services\Attendance\ProgramAttendanceLinkService::UNAVAILABLE_MESSAGE }}</h1>
            @elseif ($step === 'already')
                <h1 class="text-xl font-semibold text-[#335483]">{{ \App\Services\Attendance\ProgramAttendanceLinkService::ALREADY_PUBLIC_MESSAGE }}</h1>
                @if ($attendedAt)
                    <p class="mt-3 text-sm text-gray-600" dir="ltr">{{ $attendedAt }}</p>
                @endif
            @elseif ($step === 'done')
                <h1 class="text-xl font-semibold text-[#335483]">{{ \App\Services\Attendance\ProgramAttendanceLinkService::RECORDED_MESSAGE }}</h1>
                <p class="mt-3 text-sm text-gray-600" dir="ltr">{{ $attendedAt }}</p>
            @elseif ($step === 'confirm')
                <h1 class="text-xl font-semibold text-[#335483]">تأكيد الحضور</h1>
                <p class="mt-4 text-right text-2xl font-semibold text-[#335483]" dir="rtl">{{ $shortName }}</p>
                <form method="POST" action="{{ route('public.attendance.confirm', $link->token) }}" class="mt-6">
                    @csrf
                    <button type="submit" class="w-full rounded-xl bg-[#335483] px-4 py-3 text-sm font-semibold text-white">تأكيد الحضور</button>
                </form>
            @else
                <h1 class="text-xl font-semibold text-[#335483]">أدخل رقم الهوية</h1>
                <p class="mt-2 text-sm text-gray-500">نطابق الرقم مع تسجيلك المقبول في البرنامج، ثم نعرض اسمًا مختصرًا للتأكيد.</p>
                @if ($errors->any())
                    <p class="mt-4 text-sm text-red-700">{{ $errors->first() }}</p>
                @endif
                <form method="POST" action="{{ route('public.attendance.identify', $link->token) }}" class="mt-6 space-y-4">
                    @csrf
                    <label class="block text-sm font-semibold" for="national_id">رقم الهوية</label>
                    <input id="national_id" name="national_id" inputmode="numeric" autocomplete="off" maxlength="10" class="w-full rounded-xl border border-gray-300 px-3 py-3 text-base" dir="ltr">
                    @if ($turnstileSiteKey)
                        <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}"></div>
                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                    @elseif (app()->environment('testing') && config('services.turnstile.fake') === true)
                        <input type="hidden" name="turnstile_token" value="test-turnstile">
                    @endif
                    <button type="submit" class="w-full rounded-xl bg-[#335483] px-4 py-3 text-sm font-semibold text-white">متابعة</button>
                </form>
            @endif
        </div>
    </div>
@endsection

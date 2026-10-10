@extends('layouts.public')
@section('title', 'استبيان')
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
        .survey-question {
            border: 1px solid #e6edf4;
            border-radius: 1rem;
            background: #fff;
            padding: 1rem 1rem 0.9rem;
        }
        .survey-question + .survey-question { margin-top: 0.85rem; }
        .survey-index {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.6rem;
            height: 1.6rem;
            border-radius: 999px;
            background: #335483;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .survey-scale {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            margin-top: 0.85rem;
        }
        .survey-stars {
            display: inline-flex;
            flex-direction: row;
            direction: ltr;
            gap: 0.15rem;
        }
        .survey-stars input {
            position: absolute;
            inline-size: 1px;
            block-size: 1px;
            overflow: hidden;
            clip: rect(0 0 0 0);
        }
        .survey-stars label {
            cursor: pointer;
            font-size: 2rem;
            line-height: 1;
            color: #d5dde6;
            padding: 0 0.05rem;
        }
        .survey-stars label:hover,
        .survey-stars label:hover ~ label,
        .survey-stars input:checked ~ label { color: #fbbb2e; }
        .survey-stars input:focus-visible + label {
            outline: 2px solid #335483;
            outline-offset: 2px;
            border-radius: 4px;
        }
        .survey-choice {
            display: flex;
            gap: 0.6rem;
            align-items: flex-start;
            border: 1px solid #e6edf4;
            border-radius: 0.85rem;
            padding: 0.65rem 0.8rem;
            background: #fff;
        }
        .survey-choice + .survey-choice { margin-top: 0.45rem; }
    </style>
@endsection
@section('content')
    @php
        $program = $survey?->program;
        $typeLabel = $survey?->type?->label();
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
        @if ($typeLabel || $programTitle)
            <div class="mb-5">
                @if ($typeLabel)
                    <span class="survey-kicker">استبيان {{ $typeLabel }}</span>
                @endif
                @if ($programTitle)
                    <p class="mt-3 text-sm text-gray-500">{{ $programTitle }}</p>
                @endif
            </div>
        @endif

        @if ($step === 'unavailable')
            <h1 class="text-xl font-semibold text-[#335483]">{{ \App\Services\Surveys\ProgramSurveyService::UNAVAILABLE_MESSAGE }}</h1>
        @elseif ($step === 'already')
            <h1 class="text-xl font-semibold text-[#335483]">{{ \App\Services\Surveys\ProgramSurveyService::ALREADY_MESSAGE }}</h1>
        @elseif ($step === 'thanks')
            <h1 class="text-xl font-semibold text-[#335483]">{{ \App\Services\Surveys\ProgramSurveyService::THANKS_MESSAGE }}</h1>
        @elseif ($step === 'confirm')
            <h1 class="text-xl font-semibold text-[#335483]">تأكيد الهوية</h1>
            <p class="mt-4 text-right text-2xl font-semibold text-[#335483]" dir="rtl">{{ $shortName }}</p>
            <form method="POST" action="{{ route('public.surveys.confirm', $survey->public_token) }}" class="mt-6 flex flex-wrap gap-3">
                @csrf
                <button type="submit" name="choice" value="yes" class="rounded-xl bg-[#335483] px-4 py-2 text-sm font-semibold text-white">نعم، هذا أنا</button>
                <button type="submit" name="choice" value="no" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold">ليس أنا</button>
            </form>
        @elseif ($step === 'form')
            <h1 class="text-xl font-semibold text-[#335483]">الأسئلة</h1>
            <p class="mt-2 text-sm text-gray-500">النجمة على اليمين هي واحدة، ويزداد العدد كلما اتجهت يسارًا حتى خمس نجوم.</p>
            @if ($errors->any())
                <p class="mt-4 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif
            <form method="POST" action="{{ route('public.surveys.submit', $survey->public_token) }}" class="mt-6">
                @csrf
                @foreach ($survey->questions as $question)
                    <fieldset class="survey-question">
                        <legend class="flex items-start gap-3 font-semibold">
                            <span class="survey-index">{{ $loop->iteration }}</span>
                            <span>{{ $question->prompt }}</span>
                        </legend>
                        @if ($question->type === \App\Enums\SurveyQuestionType::Scale)
                            <div class="survey-scale" dir="ltr">
                                @if (filled($question->scale_min_label))
                                    <span class="text-sm text-gray-600">{{ $question->scale_min_label }}</span>
                                @endif
                                <div class="survey-stars">
                                    @for ($score = 5; $score >= 1; $score--)
                                        <input id="survey-q-{{ $question->id }}-{{ $score }}" type="radio" name="answers[{{ $question->id }}]" value="{{ $score }}" @checked((string) old('answers.'.$question->id) === (string) $score)>
                                        <label for="survey-q-{{ $question->id }}-{{ $score }}" aria-label="{{ $score }} من 5">★</label>
                                    @endfor
                                </div>
                                @if (filled($question->scale_max_label))
                                    <span class="text-sm text-gray-600">{{ $question->scale_max_label }}</span>
                                @endif
                            </div>
                        @elseif ($question->type === \App\Enums\SurveyQuestionType::Single)
                            <div class="mt-3">
                                @foreach ($question->options ?? [] as $option)
                                    <label class="survey-choice text-sm">
                                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option }}">
                                        {{ $option }}
                                    </label>
                                @endforeach
                            </div>
                        @elseif ($question->type === \App\Enums\SurveyQuestionType::Multiple)
                            <div class="mt-3">
                                @foreach ($question->options ?? [] as $option)
                                    <label class="survey-choice text-sm">
                                        <input type="checkbox" name="answers[{{ $question->id }}][]" value="{{ $option }}">
                                        {{ $option }}
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <textarea name="answers[{{ $question->id }}]" rows="3" class="mt-3 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></textarea>
                        @endif
                    </fieldset>
                @endforeach
                <button type="submit" class="mt-6 w-full rounded-xl bg-[#335483] px-4 py-3 text-sm font-semibold text-white sm:w-auto">إرسال</button>
            </form>
        @else
            <h1 class="text-xl font-semibold text-[#335483]">أدخل رقم الهوية</h1>
            <p class="mt-2 text-sm text-gray-500">نطابق الرقم مع تسجيلك المقبول في البرنامج، ثم نعرض اسمًا مختصرًا للتأكيد.</p>
            @if ($errors->any())
                <p class="mt-4 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif
            <form method="POST" action="{{ route('public.surveys.identify', $survey->public_token) }}" class="mt-6 space-y-4">
                @csrf
                <label class="block text-sm font-semibold" for="national_id">رقم الهوية</label>
                <input id="national_id" name="national_id" inputmode="numeric" autocomplete="off" maxlength="10" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" dir="ltr">
                @if ($turnstileSiteKey)
                    <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}"></div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                @elseif (app()->environment('testing') && config('services.turnstile.fake') === true)
                    <input type="hidden" name="turnstile_token" value="test-turnstile">
                @endif
                <button type="submit" class="rounded-xl bg-[#335483] px-4 py-2 text-sm font-semibold text-white">متابعة</button>
            </form>
        @endif
        </div>
    </div>
@endsection

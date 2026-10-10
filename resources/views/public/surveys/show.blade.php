@extends('layouts.public')
@section('title', 'استبيان')
@section('content')
    <div class="mx-auto max-w-xl rounded-xl border border-gray-100 bg-white p-6">
        @if ($step === 'unavailable')
            <h1 class="text-xl font-semibold text-[#335483]">{{ \App\Services\Surveys\ProgramSurveyService::UNAVAILABLE_MESSAGE }}</h1>
        @elseif ($step === 'already')
            <h1 class="text-xl font-semibold text-[#335483]">{{ \App\Services\Surveys\ProgramSurveyService::ALREADY_MESSAGE }}</h1>
        @elseif ($step === 'thanks')
            <h1 class="text-xl font-semibold text-[#335483]">{{ \App\Services\Surveys\ProgramSurveyService::THANKS_MESSAGE }}</h1>
        @elseif ($step === 'confirm')
            <h1 class="text-xl font-semibold text-[#335483]">تأكيد الهوية</h1>
            <p class="mt-4 text-lg">{{ $shortName }}</p>
            <form method="POST" action="{{ route('public.surveys.confirm', $survey->public_token) }}" class="mt-6 flex gap-3">
                @csrf
                <button type="submit" name="choice" value="yes" class="rounded-xl bg-[#335483] px-4 py-2 text-sm font-semibold text-white">نعم، هذا أنا</button>
                <button type="submit" name="choice" value="no" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold">ليس أنا</button>
            </form>
        @elseif ($step === 'form')
            <h1 class="text-xl font-semibold text-[#335483]">الاستبيان</h1>
            @if ($errors->any())
                <p class="mt-4 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif
            <form method="POST" action="{{ route('public.surveys.submit', $survey->public_token) }}" class="mt-6 space-y-6">
                @csrf
                @foreach ($survey->questions as $question)
                    <fieldset class="space-y-2">
                        <legend class="font-semibold">{{ $question->prompt }}</legend>
                        @if ($question->type === \App\Enums\SurveyQuestionType::Scale)
                            <div class="flex flex-wrap items-center gap-3">
                                @if (filled($question->scale_min_label))
                                    <span class="text-sm text-gray-600">{{ $question->scale_min_label }}</span>
                                @endif
                                @for ($score = 1; $score <= 5; $score++)
                                    <label class="text-sm">
                                        <input type="radio" name="answers[{{ $question->id }}]" value="{{ $score }}" @checked((string) old('answers.'.$question->id) === (string) $score)>
                                        {{ $score }}
                                    </label>
                                @endfor
                                @if (filled($question->scale_max_label))
                                    <span class="text-sm text-gray-600">{{ $question->scale_max_label }}</span>
                                @endif
                            </div>
                        @elseif ($question->type === \App\Enums\SurveyQuestionType::Single)
                            @foreach ($question->options ?? [] as $option)
                                <label class="block text-sm">
                                    <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option }}">
                                    {{ $option }}
                                </label>
                            @endforeach
                        @elseif ($question->type === \App\Enums\SurveyQuestionType::Multiple)
                            @foreach ($question->options ?? [] as $option)
                                <label class="block text-sm">
                                    <input type="checkbox" name="answers[{{ $question->id }}][]" value="{{ $option }}">
                                    {{ $option }}
                                </label>
                            @endforeach
                        @else
                            <textarea name="answers[{{ $question->id }}]" rows="3" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm"></textarea>
                        @endif
                    </fieldset>
                @endforeach
                <button type="submit" class="rounded-xl bg-[#335483] px-4 py-2 text-sm font-semibold text-white">إرسال</button>
            </form>
        @else
            <h1 class="text-xl font-semibold text-[#335483]">أدخل رقم الهوية</h1>
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
@endsection

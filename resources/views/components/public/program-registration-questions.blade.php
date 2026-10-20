@props(['questions' => []])

@php
    $questions = \App\Support\ProgramRegistrationQuestions::normalize($questions ?? []);
    $count = count($questions);
    $initialStep = 0;
    foreach ($questions as $index => $question) {
        if ($errors->has('answers.'.$question['key'])) {
            $initialStep = $index;
            break;
        }
    }
@endphp

@if ($count > 0)
<div class="rounded-2xl border border-[#c5d4e4]/80 bg-[#F7FAFC] p-4" data-registration-questions data-initial-step="{{ $initialStep }}">
    <p class="text-sm font-bold text-[#335483]">{{ $count }} أسئلة</p>
    <p class="mt-1 text-xs text-gray-500" data-registration-progress>سؤال {{ $initialStep + 1 }} من {{ $count }}</p>

    @foreach ($questions as $index => $question)
        <div class="mt-4 space-y-3" data-registration-step="{{ $index }}" @if ($index !== $initialStep) hidden @endif>
            <p class="text-sm font-medium leading-relaxed text-gray-800">
                {{ $question['label'] }}
                @if ($question['required'])
                    <span class="text-red-600">*</span>
                @endif
            </p>

            @if ($question['type'] === 'choice')
                <div class="space-y-2">
                    @foreach ($question['options'] as $optionIndex => $option)
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                            <input
                                type="radio"
                                name="answers[{{ $question['key'] }}]"
                                value="{{ $option }}"
                                class="size-4 border-gray-300 text-[#335483] focus:ring-[#335483]"
                                @checked(old('answers.'.$question['key']) === $option)
                                @if ($question['required'] && $optionIndex === 0) required @endif
                            >
                            <span>{{ $option }}</span>
                        </label>
                    @endforeach
                </div>
            @elseif ($question['type'] === 'textarea' || $question['type'] === 'pledge')
                <textarea
                    name="answers[{{ $question['key'] }}]"
                    rows="3"
                    @if ($question['required']) required @endif
                    @if ($question['type'] === 'pledge') pattern="^\s*\S+(?:\s+\S+){2,}\s+\d{10}\s*$" title="اكتب الاسم الثلاثي ثم السجل المدني المكوّن من 10 أرقام" @endif
                    placeholder="{{ $question['placeholder'] }}"
                    class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-[#335483] focus:outline-none focus:ring-2 focus:ring-[#335483]/20"
                >{{ old('answers.'.$question['key']) }}</textarea>
            @else
                <input
                    type="{{ $question['key'] === 'linkedin_url' ? 'url' : 'text' }}"
                    name="answers[{{ $question['key'] }}]"
                    value="{{ old('answers.'.$question['key']) }}"
                    @if ($question['required']) required @endif
                    @if ($question['key'] === 'linkedin_url') dir="ltr" @endif
                    placeholder="{{ $question['placeholder'] }}"
                    class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-[#335483] focus:outline-none focus:ring-2 focus:ring-[#335483]/20"
                >
            @endif

            @error('answers.'.$question['key'])
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endforeach

    <div class="mt-4 flex items-center justify-between gap-3">
        <button type="button" data-registration-prev class="rounded-xl border border-[#335483] px-4 py-2 text-sm font-semibold text-[#335483]" @if ($initialStep === 0) hidden @endif>رجوع</button>
        <button type="button" data-registration-next class="rounded-xl bg-[#335483] px-4 py-2 text-sm font-semibold text-white" @if ($initialStep === $count - 1) hidden @endif>التالي</button>
    </div>
</div>
<script>
(function () {
    var root = document.querySelector('[data-registration-questions]');
    if (!root) return;
    var steps = Array.prototype.slice.call(root.querySelectorAll('[data-registration-step]'));
    var progress = root.querySelector('[data-registration-progress]');
    var prev = root.querySelector('[data-registration-prev]');
    var next = root.querySelector('[data-registration-next]');
    var index = Number(root.getAttribute('data-initial-step') || 0);

    function show(nextIndex) {
        index = nextIndex;
        steps.forEach(function (step, stepIndex) {
            step.hidden = stepIndex !== index;
        });
        if (progress) progress.textContent = 'سؤال ' + (index + 1) + ' من ' + steps.length;
        if (prev) prev.hidden = index === 0;
        if (next) next.hidden = index === steps.length - 1;
    }

    function currentIsValid() {
        var fields = steps[index].querySelectorAll('input, textarea');
        for (var i = 0; i < fields.length; i++) {
            if (!fields[i].checkValidity()) {
                fields[i].reportValidity();
                return false;
            }
        }
        return true;
    }

    if (next) {
        next.addEventListener('click', function () {
            if (!currentIsValid() || index >= steps.length - 1) return;
            show(index + 1);
        });
    }
    if (prev) {
        prev.addEventListener('click', function () {
            if (index === 0) return;
            show(index - 1);
        });
    }
})();
</script>
@endif

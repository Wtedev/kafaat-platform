@props([
    'questions' => [],
    'action' => '',
    'ackLabel' => '',
    'title' => '',
    'open' => false,
])

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
    if ($errors->has('attendance_acknowledgement')) {
        $initialStep = max(0, $count - 1);
    }
@endphp

@if ($count > 0)
<dialog
    id="program-register-dialog"
    class="m-auto w-[min(36rem,calc(100vw-1.5rem))] rounded-3xl border-0 bg-white p-0 text-start shadow-2xl backdrop:bg-[#121c33]/60"
    data-registration-questions
    data-initial-step="{{ $initialStep }}"
    @if ($open) data-open-on-load @endif
    aria-labelledby="program-register-count"
>
    <form method="POST" action="{{ $action }}" class="flex max-h-[min(42rem,calc(100dvh-2rem))] flex-col">
        @csrf
        <div class="border-b border-[#e6edf5] px-5 pb-4 pt-5 sm:px-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-[#1FA4AE]">{{ $title }}</p>
                    <p id="program-register-count" class="mt-1 text-2xl font-extrabold text-[#173F73]">{{ $count }} أسئلة</p>
                    <p class="mt-1 text-sm text-gray-500" data-registration-progress>سؤال {{ $initialStep + 1 }} من {{ $count }}</p>
                </div>
                <button type="button" data-close-program-register class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#f4f7fb] text-lg leading-none text-[#335483]" aria-label="إغلاق">×</button>
            </div>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-[#e6edf5]">
                <div data-registration-bar class="h-full rounded-full bg-[#335483]" style="width: {{ round((($initialStep + 1) / $count) * 100) }}%"></div>
            </div>
        </div>

        <div class="overflow-y-auto px-5 py-5 sm:px-6">
            @foreach ($questions as $index => $question)
                <div data-registration-step="{{ $index }}" @if ($index !== $initialStep) hidden @endif>
                    <p class="text-base font-semibold leading-relaxed text-[#173F73]">
                        {{ $question['label'] }}
                        @if ($question['required'])
                            <span class="text-red-600">*</span>
                        @else
                            <span class="text-sm font-normal text-gray-400">اختياري</span>
                        @endif
                    </p>

                    <div class="mt-4">
                        @if ($question['type'] === 'choice')
                            <div class="space-y-2">
                                @foreach ($question['options'] as $optionIndex => $option)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-[#d5e0ec] bg-white px-4 py-3 text-sm text-gray-700 transition has-[:checked]:border-[#335483] has-[:checked]:bg-[#f4f7fb]">
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
                                rows="4"
                                @if ($question['required']) required @endif
                                @if ($question['type'] === 'pledge') pattern="^\s*\S+(?:\s+\S+){2,}\s+\d{10}\s*$" title="اكتب الاسم الثلاثي ثم السجل المدني المكوّن من 10 أرقام" @endif
                                placeholder="{{ $question['placeholder'] }}"
                                class="w-full rounded-2xl border border-[#d5e0ec] bg-white px-4 py-3 text-sm text-gray-800 focus:border-[#335483] focus:outline-none focus:ring-2 focus:ring-[#335483]/20"
                            >{{ old('answers.'.$question['key']) }}</textarea>
                        @else
                            <input
                                type="{{ $question['key'] === 'linkedin_url' ? 'url' : 'text' }}"
                                name="answers[{{ $question['key'] }}]"
                                value="{{ old('answers.'.$question['key']) }}"
                                @if ($question['required']) required @endif
                                @if ($question['key'] === 'linkedin_url') dir="ltr" @endif
                                placeholder="{{ $question['placeholder'] }}"
                                class="w-full rounded-2xl border border-[#d5e0ec] bg-white px-4 py-3 text-sm text-gray-800 focus:border-[#335483] focus:outline-none focus:ring-2 focus:ring-[#335483]/20"
                            >
                        @endif
                    </div>

                    @error('answers.'.$question['key'])
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="mt-5" data-registration-ack @if ($initialStep !== $count - 1) hidden @endif>
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-[#c5d4e4]/80 bg-[#F7FAFC] p-4">
                    <input type="checkbox" name="attendance_acknowledgement" value="1" required class="mt-1 size-4 shrink-0 rounded border-gray-300 text-[#335483] focus:ring-[#335483]" @checked(old('attendance_acknowledgement'))>
                    <span class="text-sm leading-relaxed text-gray-700">{{ $ackLabel }}</span>
                </label>
                @error('attendance_acknowledgement')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex items-center gap-3 border-t border-[#e6edf5] px-5 py-4 sm:px-6">
            <button type="button" data-registration-prev class="rounded-2xl border border-[#335483] px-5 py-2.5 text-sm font-semibold text-[#335483]" @if ($initialStep === 0) hidden @endif>رجوع</button>
            <button type="button" data-registration-next class="ms-auto rounded-2xl bg-[#335483] px-6 py-2.5 text-sm font-semibold text-white" @if ($initialStep === $count - 1) hidden @endif>التالي</button>
            <button type="submit" data-registration-submit class="ms-auto rounded-2xl bg-[#335483] px-6 py-2.5 text-sm font-semibold text-white" @if ($initialStep !== $count - 1) hidden @endif>سجّل في البرنامج</button>
        </div>
    </form>
</dialog>
<style>
#program-register-dialog::backdrop { background: rgba(18, 28, 51, 0.55); }
</style>
<script>
(function () {
    var dialog = document.getElementById('program-register-dialog');
    if (!dialog) return;
    var steps = Array.prototype.slice.call(dialog.querySelectorAll('[data-registration-step]'));
    var progress = dialog.querySelector('[data-registration-progress]');
    var bar = dialog.querySelector('[data-registration-bar]');
    var prev = dialog.querySelector('[data-registration-prev]');
    var next = dialog.querySelector('[data-registration-next]');
    var submit = dialog.querySelector('[data-registration-submit]');
    var ack = dialog.querySelector('[data-registration-ack]');
    var index = Number(dialog.getAttribute('data-initial-step') || 0);

    function show(nextIndex) {
        index = nextIndex;
        var last = index === steps.length - 1;
        steps.forEach(function (step, stepIndex) {
            step.hidden = stepIndex !== index;
        });
        if (progress) progress.textContent = 'سؤال ' + (index + 1) + ' من ' + steps.length;
        if (bar) bar.style.width = Math.round(((index + 1) / steps.length) * 100) + '%';
        if (prev) prev.hidden = index === 0;
        if (next) next.hidden = last;
        if (submit) submit.hidden = !last;
        if (ack) ack.hidden = !last;
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

    document.querySelectorAll('[data-open-program-register]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (typeof dialog.showModal === 'function') dialog.showModal();
        });
    });
    var closeButton = dialog.querySelector('[data-close-program-register]');
    if (closeButton) closeButton.addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) dialog.close();
    });
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
    if (dialog.hasAttribute('data-open-on-load') && typeof dialog.showModal === 'function') {
        dialog.showModal();
    }
})();
</script>
@endif

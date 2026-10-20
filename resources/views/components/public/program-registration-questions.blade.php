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
    <form method="POST" action="{{ $action }}" class="flex max-h-[min(40rem,calc(100dvh-2rem))] flex-col">
        @csrf
        <div class="border-b border-[#e6edf5] px-5 pb-4 pt-4 sm:px-6">
            <div class="flex items-center justify-between gap-3">
                <p class="truncate text-sm font-semibold text-[#1FA4AE]">{{ $title }}</p>
                <button type="button" data-close-program-register class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#f4f7fb] text-lg leading-none text-[#335483]" aria-label="إغلاق">×</button>
            </div>
            <div class="mt-3 flex items-baseline justify-between gap-3">
                <p class="text-sm font-semibold text-[#173F73]" data-registration-progress>سؤال {{ $initialStep + 1 }} من {{ $count }}</p>
                <p id="program-register-count" class="text-sm text-gray-500">{{ $count }} أسئلة</p>
            </div>
            <div class="mt-3 flex gap-1.5" data-registration-dots aria-hidden="true">
                @for ($dot = 0; $dot < $count; $dot++)
                    <span data-registration-dot class="h-1.5 flex-1 rounded-full {{ $dot <= $initialStep ? 'bg-[#335483]' : 'bg-gray-200' }}"></span>
                @endfor
            </div>
        </div>

        <div class="min-h-56 overflow-y-auto px-5 py-5 sm:px-6" data-registration-body>
            @foreach ($questions as $index => $question)
                <div data-registration-step="{{ $index }}" @if ($index !== $initialStep) hidden @endif>
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-lg font-bold leading-8 text-[#173F73]">
                            {{ $question['label'] }}
                            @if ($question['required'])
                                <span class="text-red-600">*</span>
                            @endif
                        </p>
                        @unless ($question['required'])
                            <span class="mt-1 shrink-0 rounded-full bg-[#f4f7fb] px-2.5 py-1 text-xs font-medium text-gray-500">اختياري</span>
                        @endunless
                    </div>

                    <div class="mt-4">
                        @if ($question['type'] === 'choice')
                            <div class="space-y-2">
                                @foreach ($question['options'] as $optionIndex => $option)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-[#d5e0ec] bg-white px-4 py-3.5 text-sm text-gray-800 transition has-[:checked]:border-[#335483] has-[:checked]:bg-[#f4f7fb] has-[:checked]:font-semibold">
                                        <input
                                            type="radio"
                                            name="answers[{{ $question['key'] }}]"
                                            value="{{ $option }}"
                                            class="size-4 shrink-0 border-gray-300 text-[#335483] focus:ring-[#335483]"
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
                                rows="5"
                                @if ($question['required']) required @endif
                                @if ($question['type'] === 'pledge') pattern="^\s*\S+(?:\s+\S+){2,}\s+\d{10}\s*$" title="اكتب الاسم الثلاثي ثم السجل المدني المكوّن من 10 أرقام" @endif
                                placeholder="{{ $question['placeholder'] }}"
                                class="w-full resize-none rounded-2xl border border-[#d5e0ec] bg-gray-50 px-4 py-3 text-base text-gray-800 focus:border-[#335483] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#335483]/20"
                            >{{ old('answers.'.$question['key']) }}</textarea>
                        @else
                            <input
                                type="{{ $question['key'] === 'linkedin_url' ? 'url' : 'text' }}"
                                name="answers[{{ $question['key'] }}]"
                                value="{{ old('answers.'.$question['key']) }}"
                                @if ($question['required']) required @endif
                                @if ($question['key'] === 'linkedin_url') dir="ltr" @endif
                                placeholder="{{ $question['placeholder'] }}"
                                class="w-full rounded-2xl border border-[#d5e0ec] bg-gray-50 px-4 py-3.5 text-base text-gray-800 focus:border-[#335483] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#335483]/20"
                            >
                            @if ($question['key'] === 'linkedin_url')
                                <p class="mt-2 text-xs leading-5 text-gray-500">يمكنك المتابعة بدون رابط.</p>
                            @endif
                        @endif
                    </div>

                    @error('answers.'.$question['key'])
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="mt-4" data-registration-ack @if ($initialStep !== $count - 1) hidden @endif>
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-[#c5d4e4] bg-[#F7FAFC] p-4 has-[:checked]:border-[#335483]">
                    <input type="checkbox" name="attendance_acknowledgement" value="1" required class="mt-0.5 size-4 shrink-0 rounded border-gray-300 text-[#335483] focus:ring-[#335483]" @checked(old('attendance_acknowledgement'))>
                    <span class="text-sm font-medium leading-relaxed text-[#173F73]">{{ $ackLabel }}</span>
                </label>
                @error('attendance_acknowledgement')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex items-center gap-3 border-t border-[#e6edf5] px-5 py-4 sm:px-6">
            <button type="button" data-registration-prev class="rounded-2xl px-4 py-3 text-sm font-semibold text-[#335483]" @if ($initialStep === 0) hidden @endif>رجوع</button>
            <button type="button" data-registration-next class="flex-1 rounded-2xl bg-[#335483] px-6 py-3 text-sm font-semibold text-white" @if ($initialStep === $count - 1) hidden @endif>التالي</button>
            <button type="submit" data-registration-submit class="flex-1 rounded-2xl bg-[#335483] px-6 py-3 text-sm font-semibold text-white" @if ($initialStep !== $count - 1) hidden @endif>سجّل في البرنامج</button>
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
    var dots = Array.prototype.slice.call(dialog.querySelectorAll('[data-registration-dot]'));
    var body = dialog.querySelector('[data-registration-body]');
    var prev = dialog.querySelector('[data-registration-prev]');
    var next = dialog.querySelector('[data-registration-next]');
    var submit = dialog.querySelector('[data-registration-submit]');
    var ack = dialog.querySelector('[data-registration-ack]');
    var index = Number(dialog.getAttribute('data-initial-step') || 0);

    function show(nextIndex, shouldFocus) {
        index = nextIndex;
        var last = index === steps.length - 1;
        steps.forEach(function (step, stepIndex) {
            step.hidden = stepIndex !== index;
        });
        if (progress) progress.textContent = 'سؤال ' + (index + 1) + ' من ' + steps.length;
        dots.forEach(function (dot, dotIndex) {
            dot.classList.toggle('bg-[#335483]', dotIndex <= index);
            dot.classList.toggle('bg-gray-200', dotIndex > index);
        });
        if (prev) prev.hidden = index === 0;
        if (next) next.hidden = last;
        if (submit) submit.hidden = !last;
        if (ack) ack.hidden = !last;
        if (body) body.scrollTop = 0;
        if (shouldFocus && dialog.open) {
            var field = steps[index].querySelector('input, textarea');
            if (field) field.focus();
        }
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
            show(index, true);
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
            show(index + 1, true);
        });
    }
    if (prev) {
        prev.addEventListener('click', function () {
            if (index === 0) return;
            show(index - 1, true);
        });
    }
    dialog.querySelector('form').addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || event.target.tagName === 'TEXTAREA') return;
        if (index >= steps.length - 1) return;
        event.preventDefault();
        if (next) next.click();
    });
    if (dialog.hasAttribute('data-open-on-load') && typeof dialog.showModal === 'function') {
        dialog.showModal();
    }
})();
</script>
@endif

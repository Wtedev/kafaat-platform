@php
    $type = (string) ($q['type'] ?? 'scale');
    $n = is_numeric($i) ? $i + 1 : '';
@endphp
<li class="sui-survey-q" data-survey-item>
    <div class="sui-survey-q__head">
        <span class="sui-survey-q__num" data-survey-num>{{ $n }}</span>
        <div class="sui-survey-q__tools">
            <button type="button" class="sui-icon-btn" data-survey-move="up" aria-label="لأعلى"><i data-lucide="arrow-up" class="sui-icon sui-icon--sm"></i></button>
            <button type="button" class="sui-icon-btn" data-survey-move="down" aria-label="لأسفل"><i data-lucide="arrow-down" class="sui-icon sui-icon--sm"></i></button>
            <button type="button" class="sui-icon-btn" data-survey-remove aria-label="حذف السؤال"><i data-lucide="trash-2" class="sui-icon sui-icon--sm"></i></button>
        </div>
    </div>
    <div class="sui-survey-q__body">
        <div class="sui-field sui-survey-q__prompt">
            <label class="sui-field__label">نص السؤال</label>
            <input class="sui-control" type="text" name="questions[{{ $i }}][prompt]" value="{{ $q['prompt'] ?? '' }}" maxlength="500" required>
        </div>
        <div class="sui-field">
            <label class="sui-field__label">نوع الإجابة</label>
            <select class="sui-control" name="questions[{{ $i }}][type]" data-survey-type>
                @foreach (\App\Enums\SurveyQuestionType::cases() as $option)
                    <option value="{{ $option->value }}" @selected($type === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="sui-survey-q__scale" data-survey-scale>
            <div class="sui-field">
                <label class="sui-field__label">نص 1 (اختياري)</label>
                <input class="sui-control" type="text" name="questions[{{ $i }}][scale_min_label]" value="{{ $q['scale_min_label'] ?? '' }}" maxlength="80" placeholder="غير موافق إطلاقاً">
            </div>
            <div class="sui-field">
                <label class="sui-field__label">نص 5 (اختياري)</label>
                <input class="sui-control" type="text" name="questions[{{ $i }}][scale_max_label]" value="{{ $q['scale_max_label'] ?? '' }}" maxlength="80" placeholder="موافق تماماً">
            </div>
        </div>
        <div class="sui-field sui-survey-q__options" data-survey-options>
            <label class="sui-field__label">الخيارات، كل خيار في سطر</label>
            <textarea class="sui-control" name="questions[{{ $i }}][options]" rows="3">{{ $q['options'] ?? '' }}</textarea>
        </div>
        <label class="sui-checkline">
            <input type="hidden" name="questions[{{ $i }}][required]" value="0">
            <input class="sui-check" type="checkbox" name="questions[{{ $i }}][required]" value="1" @checked((bool) ($q['required'] ?? true))>
            <span>إلزامي</span>
        </label>
    </div>
</li>

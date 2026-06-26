@php
    $fieldWrapperView = $getFieldWrapperView();
    $id = $getId();
    $showRegistration = $getShowRegistrationRange();
    $programHasEnd = $getProgramHasEndDate();
    $showWeekdayPicker = $getShowWeekdayPicker();
    $showPublishSchedule = $getShowPublishSchedule();
    $registrationStartPath = $getRegistrationStartPath();
    $registrationEndPath = $getRegistrationEndPath();
    $programStartPath = $getProgramStartPath();
    $programEndPath = $getProgramEndPath();
    $weekdaysPath = $getWeekdaysPath();
    $publishImmediatelyPath = $getPublishImmediatelyPath();
    $publishedAtPath = $getPublishedAtPath();
    $notifyAudiencePath = $getNotifyAudiencePath();
    $months = [
        'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
    ];
    $dayLabels = ['أحد', 'اثن', 'ثل', 'أرب', 'خم', 'جم', 'سب'];
@endphp

<x-dynamic-component :component="$fieldWrapperView" :field="$field">
    <div
        wire:key="training-schedule-{{ $id }}-{{ $showRegistration ? '1' : '0' }}-{{ $programHasEnd ? '1' : '0' }}-{{ $showWeekdayPicker ? '1' : '0' }}-{{ $showPublishSchedule ? '1' : '0' }}"
        x-data="trainingScheduleCalendar({
            showRegistration: @js($showRegistration),
            programHasEnd: @js($programHasEnd),
            showWeekdayPicker: @js($showWeekdayPicker),
            showPublishSchedule: @js($showPublishSchedule),
            months: @js($months),
            dayLabels: @js($dayLabels),
            paths: {
                registrationStart: @js($registrationStartPath),
                registrationEnd: @js($registrationEndPath),
                programStart: @js($programStartPath),
                programEnd: @js($programEndPath),
                weekdays: @js($weekdaysPath),
                publishImmediately: @js($publishImmediatelyPath),
                publishedAt: @js($publishedAtPath),
                notifyAudience: @js($notifyAudiencePath),
            },
        })"
        class="fi-training-schedule"
        dir="rtl"
    >
        <div class="fi-training-schedule__toolbar">
            <div class="fi-training-schedule__mode-switch" role="tablist" aria-label="الفترة النشطة">
                @if ($showRegistration)
                    <button
                        type="button"
                        role="tab"
                        class="fi-training-schedule__mode-btn"
                        x-bind:aria-selected="activeRange === 'registration'"
                        x-bind:class="{ 'is-active is-registration': activeRange === 'registration' }"
                        x-on:click="setActiveRange('registration')"
                    >
                        <span class="fi-training-schedule__mode-dot fi-training-schedule__mode-dot--registration"></span>
                        التسجيل
                    </button>
                @endif
                <button
                    type="button"
                    role="tab"
                    class="fi-training-schedule__mode-btn"
                    x-bind:aria-selected="activeRange === 'program'"
                    x-bind:class="{ 'is-active is-program': activeRange === 'program' }"
                    x-on:click="setActiveRange('program')"
                >
                    <span class="fi-training-schedule__mode-dot fi-training-schedule__mode-dot--program"></span>
                    البرنامج
                </button>
                <button
                    type="button"
                    role="tab"
                    class="fi-training-schedule__mode-btn"
                    x-show="showPublishSchedule && ! publishImmediately"
                    x-cloak
                    x-bind:aria-selected="activeRange === 'publish'"
                    x-bind:class="{ 'is-active is-publish': activeRange === 'publish' }"
                    x-on:click="setActiveRange('publish')"
                >
                    <span class="fi-training-schedule__mode-dot fi-training-schedule__mode-dot--publish"></span>
                    النشر
                </button>
            </div>

            <div class="fi-training-schedule__legend">
                @if ($showRegistration)
                    <span class="fi-training-schedule__legend-item"><span class="fi-training-schedule__legend-chip fi-training-schedule__legend-chip--registration"></span>تسجيل</span>
                @endif
                <span class="fi-training-schedule__legend-item"><span class="fi-training-schedule__legend-chip fi-training-schedule__legend-chip--program"></span>برنامج</span>
                @if ($showPublishSchedule)
                    <span class="fi-training-schedule__legend-item" x-show="! publishImmediately" x-cloak><span class="fi-training-schedule__legend-chip fi-training-schedule__legend-chip--publish"></span>نشر</span>
                @endif
            </div>
        </div>

        <div class="fi-training-schedule__publish" x-show="showPublishSchedule" x-cloak>
            <label class="fi-training-schedule__publish-check">
                <input type="checkbox" x-model="publishImmediately" x-on:change="onPublishImmediatelyChange()">
                <span>نشر فوراً</span>
            </label>
            <label class="fi-training-schedule__publish-check">
                <input type="checkbox" x-model="notifyAudience">
                <span>إرسال إشعارات البرنامج للمستفيدين</span>
            </label>
        </div>

        <p class="fi-training-schedule__pending" x-show="pendingStart && activeRange !== 'publish'" x-cloak x-text="pendingHint()"></p>

        <div class="fi-training-schedule__errors" x-show="scheduleErrors.length > 0" x-cloak>
            <template x-for="(error, index) in scheduleErrors" x-bind:key="'err-' + index">
                <p class="fi-training-schedule__error" x-text="error"></p>
            </template>
        </div>

        <div class="fi-training-schedule__month-bar">
            <button type="button" class="fi-training-schedule__nav-btn fi-training-schedule__nav-btn--prev" x-on:click="prevMonth()" aria-label="الشهر السابق">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
            </button>
            <div class="fi-training-schedule__month-label">
                <span x-text="months[month]"></span>
                <span class="fi-training-schedule__month-year" x-text="year"></span>
            </div>
            <button type="button" class="fi-training-schedule__nav-btn fi-training-schedule__nav-btn--next" x-on:click="nextMonth()" aria-label="الشهر التالي">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
        </div>

        <div class="fi-training-schedule__calendar">
            <div class="fi-training-schedule__weekdays">
                <template x-for="(label, index) in dayLabels" x-bind:key="'wd-' + index">
                    <div
                        class="fi-training-schedule__weekday"
                        x-text="label"
                        x-bind:class="weekdayHeaderClass(index)"
                    ></div>
                </template>
            </div>
            <div class="fi-training-schedule__grid" role="grid">
                <template x-for="blank in leadingBlanks" x-bind:key="'b-' + blank">
                    <div class="fi-training-schedule__blank" aria-hidden="true"></div>
                </template>
                <template x-for="day in daysInMonth" x-bind:key="day">
                    <button
                        type="button"
                        role="gridcell"
                        x-on:click="selectDay(day)"
                        x-bind:class="dayClasses(day)"
                        x-bind:aria-label="dayAriaLabel(day)"
                        class="fi-training-schedule__day"
                    >
                        <span class="fi-training-schedule__day-g" x-text="day"></span>
                        <span class="fi-training-schedule__day-h" x-text="hijriLabel(day)"></span>
                    </button>
                </template>
            </div>
        </div>

        <div class="fi-training-schedule__weekday-picker" x-show="showWeekdayPicker" x-cloak>
            <span class="fi-training-schedule__weekday-picker-label">أيام البرنامج</span>
            <div class="fi-training-schedule__weekday-pills">
                <template x-for="(label, index) in dayLabels" x-bind:key="'pill-' + index">
                    <button
                        type="button"
                        class="fi-training-schedule__weekday-pill"
                        x-text="label"
                        x-on:click="toggleWeekday(index)"
                        x-bind:class="{ 'is-selected': weekdaySelected(index) }"
                        x-bind:aria-pressed="weekdaySelected(index)"
                    ></button>
                </template>
            </div>
        </div>

        <div class="fi-training-schedule__summary">
            <template x-if="showRegistration">
                <div class="fi-training-schedule__summary-card fi-training-schedule__summary-card--registration">
                    <div class="fi-training-schedule__summary-accent"></div>
                    <div class="fi-training-schedule__summary-inner">
                        <div class="fi-training-schedule__summary-head">
                            <div class="fi-training-schedule__summary-title-row">
                                <span class="fi-training-schedule__summary-dot"></span>
                                <span class="fi-training-schedule__summary-badge">التسجيل</span>
                            </div>
                            <button type="button" class="fi-training-schedule__clear-btn" x-show="registrationStart" x-on:click="clearRange('registration')">مسح</button>
                        </div>
                        <p class="fi-training-schedule__summary-dates" x-text="rangeSummary(registrationStart, registrationEnd)"></p>
                        <span class="fi-training-schedule__summary-pill" x-show="registrationStart" x-text="rangeMeta(registrationStart, registrationEnd)"></span>
                    </div>
                </div>
            </template>
            <div class="fi-training-schedule__summary-card fi-training-schedule__summary-card--program">
                <div class="fi-training-schedule__summary-accent"></div>
                <div class="fi-training-schedule__summary-inner">
                    <div class="fi-training-schedule__summary-head">
                        <div class="fi-training-schedule__summary-title-row">
                            <span class="fi-training-schedule__summary-dot"></span>
                            <span class="fi-training-schedule__summary-badge">البرنامج</span>
                        </div>
                        <button type="button" class="fi-training-schedule__clear-btn" x-show="programStart" x-on:click="clearRange('program')">مسح</button>
                    </div>
                    <p class="fi-training-schedule__summary-dates" x-text="programSummary()"></p>
                    <span class="fi-training-schedule__summary-pill" x-show="programStart" x-text="programMeta()"></span>
                </div>
            </div>
            <template x-if="showPublishSchedule && ! publishImmediately">
                <div class="fi-training-schedule__summary-card fi-training-schedule__summary-card--publish">
                    <div class="fi-training-schedule__summary-accent"></div>
                    <div class="fi-training-schedule__summary-inner">
                        <div class="fi-training-schedule__summary-head">
                            <div class="fi-training-schedule__summary-title-row">
                                <span class="fi-training-schedule__summary-dot"></span>
                                <span class="fi-training-schedule__summary-badge">النشر</span>
                            </div>
                            <button type="button" class="fi-training-schedule__clear-btn" x-show="publishedAt" x-on:click="clearPublishDate()">مسح</button>
                        </div>
                        <p class="fi-training-schedule__summary-dates" x-text="publishSummary()"></p>
                        <span class="fi-training-schedule__summary-pill" x-show="publishedAt" x-text="publishMeta()"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <style>
        .fi-training-schedule {
            --tsc-reg: #3b82f6;
            --tsc-reg-soft: rgba(59, 130, 246, 0.18);
            --tsc-reg-strong: rgba(59, 130, 246, 0.32);
            --tsc-prog: #22c55e;
            --tsc-prog-soft: rgba(34, 197, 94, 0.18);
            --tsc-prog-strong: rgba(34, 197, 94, 0.32);
            --tsc-pub: #f59e0b;
            --tsc-pub-soft: rgba(245, 158, 11, 0.18);
            --tsc-pub-strong: rgba(245, 158, 11, 0.34);
            --tsc-brand: #335483;
            --tsc-border: rgba(255, 255, 255, 0.08);
            --tsc-surface: rgba(255, 255, 255, 0.03);
            --tsc-text: #f4f4f5;
            --tsc-muted: #a1a1aa;
            border-radius: 1rem;
            border: 1px solid var(--tsc-border);
            background: linear-gradient(180deg, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.02) 100%);
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .fi-training-schedule__toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .fi-training-schedule__pending {
            margin: 0;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            color: #fde68a;
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.28);
        }

        .fi-training-schedule__errors {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            padding: 0.55rem 0.75rem;
            border-radius: 0.5rem;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.35);
        }

        .fi-training-schedule__error {
            margin: 0;
            font-size: 0.75rem;
            line-height: 1.45;
            color: #fecaca;
        }

        .fi-training-schedule__mode-switch {
            display: inline-flex;
            padding: 0.2rem;
            gap: 0.2rem;
            border-radius: 0.75rem;
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--tsc-border);
        }

        .fi-training-schedule__mode-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.85rem;
            border-radius: 0.55rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--tsc-muted);
            background: transparent;
            border: none;
            transition: all 0.18s ease;
            cursor: pointer;
        }

        .fi-training-schedule__mode-btn:hover {
            color: var(--tsc-text);
            background: rgba(255, 255, 255, 0.05);
        }

        .fi-training-schedule__mode-btn.is-active.is-registration {
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.2);
            box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.45);
        }

        .fi-training-schedule__mode-btn.is-active.is-program {
            color: #86efac;
            background: rgba(34, 197, 94, 0.2);
            box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.45);
        }

        .fi-training-schedule__mode-btn.is-active.is-publish {
            color: #fcd34d;
            background: rgba(245, 158, 11, 0.2);
            box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.45);
        }

        .fi-training-schedule__mode-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 9999px;
        }
        .fi-training-schedule__mode-dot--registration { background: var(--tsc-reg); }
        .fi-training-schedule__mode-dot--program { background: var(--tsc-prog); }
        .fi-training-schedule__mode-dot--publish { background: var(--tsc-pub); }

        .fi-training-schedule__publish {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem 1.5rem;
            padding: 0.15rem 0;
        }

        .fi-training-schedule__publish-check {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--tsc-text);
            cursor: pointer;
            user-select: none;
        }

        .fi-training-schedule__publish-check input {
            width: 1rem;
            height: 1rem;
            accent-color: var(--tsc-prog);
            cursor: pointer;
        }

        .fi-training-schedule__hint,
        .fi-training-schedule__hint-icon { display: none; }

        .fi-training-schedule__month-bar {
            display: grid;
            grid-template-columns: 2.25rem 1fr 2.25rem;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.65rem;
            border-radius: 0.75rem;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--tsc-border);
        }

        .fi-training-schedule__month-bar-start,
        .fi-training-schedule__month-bar-end { display: none; }

        .fi-training-schedule__nav-btn--prev { justify-self: start; }
        .fi-training-schedule__nav-btn--next { justify-self: end; }

        .fi-training-schedule__month-label {
            grid-column: 2;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--tsc-text);
        }

        .fi-training-schedule__month-year {
            margin-inline-start: 0.35rem;
            font-weight: 500;
            color: var(--tsc-muted);
        }

        .fi-training-schedule__nav-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.1rem;
            height: 2.1rem;
            border-radius: 0.55rem;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.06);
            color: #e4e4e7;
            transition: all 0.15s;
            cursor: pointer;
            flex-shrink: 0;
        }

        .fi-training-schedule__nav-btn:hover {
            color: #fff;
            border-color: rgba(255, 255, 255, 0.28);
            background: rgba(255, 255, 255, 0.12);
        }

        .fi-training-schedule__nav-btn svg {
            display: block;
        }

        .fi-training-schedule__legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.25rem;
            padding: 0 0.15rem;
        }

        .fi-training-schedule__legend-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.72rem;
            color: var(--tsc-muted);
        }

        .fi-training-schedule__legend-chip {
            width: 1.25rem;
            height: 0.45rem;
            border-radius: 9999px;
        }
        .fi-training-schedule__legend-chip--registration { background: var(--tsc-reg); }
        .fi-training-schedule__legend-chip--program { background: var(--tsc-prog); }
        .fi-training-schedule__legend-chip--overlap {
            background: linear-gradient(90deg, var(--tsc-reg) 50%, var(--tsc-prog) 50%);
        }
        .fi-training-schedule__legend-chip--publish { background: var(--tsc-pub); }

        .fi-training-schedule__weekday.is-selected {
            color: var(--tsc-text);
            font-weight: 700;
        }

        .fi-training-schedule__weekday.is-off {
            opacity: 0.3;
            color: #71717a;
        }

        .fi-training-schedule__calendar {
            padding: 0.75rem;
            border-radius: 0.85rem;
            background: rgba(0, 0, 0, 0.18);
            border: 1px solid var(--tsc-border);
        }

        .fi-training-schedule__weekdays,
        .fi-training-schedule__grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.3rem;
        }

        .fi-training-schedule__weekday {
            text-align: center;
            font-size: 0.68rem;
            font-weight: 600;
            color: var(--tsc-muted);
            padding: 0.35rem 0;
        }

        .fi-training-schedule__day {
            position: relative;
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.05rem;
            border-radius: 0.55rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--tsc-text);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: transform 0.12s, background 0.12s, border-color 0.12s, box-shadow 0.12s;
            cursor: pointer;
            padding: 0.15rem 0;
            min-height: 2.75rem;
        }

        .fi-training-schedule__day-g {
            line-height: 1.1;
            font-size: 0.9rem;
        }

        .fi-training-schedule__day-h {
            line-height: 1;
            font-size: 0.58rem;
            font-weight: 500;
            color: var(--tsc-muted);
            opacity: 0.9;
        }

        .fi-training-schedule__day:hover {
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.05);
        }

        .fi-training-schedule__day--registration {
            background: var(--tsc-reg-soft);
            color: #bfdbfe;
        }
        .fi-training-schedule__day--registration.is-start,
        .fi-training-schedule__day--registration.is-end {
            background: var(--tsc-reg-strong);
            font-weight: 700;
            box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.5);
        }

        .fi-training-schedule__day--program {
            background: var(--tsc-prog-soft);
            color: #bbf7d0;
        }
        .fi-training-schedule__day--program.is-start,
        .fi-training-schedule__day--program.is-end {
            background: var(--tsc-prog-strong);
            font-weight: 700;
            box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.5);
        }

        .fi-training-schedule__day--overlap {
            background: linear-gradient(160deg, var(--tsc-reg-soft) 0%, var(--tsc-prog-soft) 100%);
            color: #ecfccb;
            box-shadow: inset 0 -3px 0 0 linear-gradient(90deg, var(--tsc-reg), var(--tsc-prog));
        }
        .fi-training-schedule__day--overlap::after {
            content: '';
            position: absolute;
            bottom: 4px;
            left: 18%;
            right: 18%;
            height: 3px;
            border-radius: 9999px;
            background: linear-gradient(90deg, var(--tsc-reg) 50%, var(--tsc-prog) 50%);
        }

        .fi-training-schedule__day--weekday-off {
            opacity: 0.38;
            color: #71717a !important;
            background: rgba(0, 0, 0, 0.22) !important;
            border-color: rgba(255, 255, 255, 0.04) !important;
            box-shadow: none !important;
        }

        .fi-training-schedule__day--weekday-off.fi-training-schedule__day--program-inactive {
            opacity: 0.32;
            background: rgba(0, 0, 0, 0.28) !important;
        }

        .fi-training-schedule__day--weekday-off .fi-training-schedule__day-h {
            opacity: 0.45;
        }

        .fi-training-schedule__day--weekday-off:hover {
            transform: none;
        }

        .fi-training-schedule__day--publish {
            background: var(--tsc-pub-soft);
            color: #fde68a;
            font-weight: 700;
            box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.55);
        }

        .fi-training-schedule__day--past {
            opacity: 0.25;
            cursor: not-allowed;
            pointer-events: none;
        }

        .fi-training-schedule__day--pending {
            border-color: #fbbf24 !important;
            box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.25);
            animation: fi-tsc-pulse 1.2s ease-in-out infinite;
        }

        .fi-training-schedule__day--today:not(.fi-training-schedule__day--registration):not(.fi-training-schedule__day--program):not(.fi-training-schedule__day--overlap) {
            border-color: var(--tsc-brand);
            color: #93c5fd;
        }

        .fi-training-schedule__day--today::before {
            content: '';
            position: absolute;
            top: 4px;
            width: 4px;
            height: 4px;
            border-radius: 9999px;
            background: currentColor;
            opacity: 0.85;
        }

        @keyframes fi-tsc-pulse {
            0%, 100% { box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.2); }
            50% { box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.35); }
        }

        .fi-training-schedule__summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
            gap: 0.75rem;
        }

        .fi-training-schedule__summary-card {
            position: relative;
            display: flex;
            overflow: hidden;
            border-radius: 0.75rem;
            border: 1px solid var(--tsc-border);
            background: var(--tsc-surface);
        }

        .fi-training-schedule__summary-accent {
            width: 4px;
            flex-shrink: 0;
        }

        .fi-training-schedule__summary-card--registration .fi-training-schedule__summary-accent { background: var(--tsc-reg); }
        .fi-training-schedule__summary-card--program .fi-training-schedule__summary-accent { background: var(--tsc-prog); }
        .fi-training-schedule__summary-card--publish .fi-training-schedule__summary-accent { background: var(--tsc-pub); }

        .fi-training-schedule__summary-inner {
            flex: 1;
            padding: 0.75rem 0.9rem;
            min-width: 0;
        }

        .fi-training-schedule__summary-card--registration {
            border-color: rgba(59, 130, 246, 0.22);
            background: rgba(59, 130, 246, 0.05);
        }

        .fi-training-schedule__summary-card--program {
            border-color: rgba(34, 197, 94, 0.22);
            background: rgba(34, 197, 94, 0.05);
        }

        .fi-training-schedule__summary-card--publish {
            border-color: rgba(245, 158, 11, 0.22);
            background: rgba(245, 158, 11, 0.05);
        }

        .fi-training-schedule__summary-title-row {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .fi-training-schedule__summary-dot {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 9999px;
            flex-shrink: 0;
        }

        .fi-training-schedule__summary-card--registration .fi-training-schedule__summary-dot { background: var(--tsc-reg); }
        .fi-training-schedule__summary-card--program .fi-training-schedule__summary-dot { background: var(--tsc-prog); }
        .fi-training-schedule__summary-card--publish .fi-training-schedule__summary-dot { background: var(--tsc-pub); }

        .fi-training-schedule__summary-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.45rem;
        }

        .fi-training-schedule__summary-badge {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--tsc-text);
        }

        .fi-training-schedule__summary-dates {
            margin: 0.35rem 0 0;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--tsc-text);
            line-height: 1.5;
        }

        .fi-training-schedule__summary-pill {
            display: inline-block;
            margin-top: 0.45rem;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.68rem;
            font-weight: 600;
            color: var(--tsc-muted);
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--tsc-border);
        }

        .fi-training-schedule__summary-meta { display: none; }

        .fi-training-schedule__clear-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.45rem;
            border-radius: 0.4rem;
            font-size: 0.68rem;
            font-weight: 600;
            color: var(--tsc-muted);
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--tsc-border);
            cursor: pointer;
            transition: all 0.15s;
        }

        .fi-training-schedule__clear-btn:hover {
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.35);
            background: rgba(239, 68, 68, 0.1);
        }

        .fi-training-schedule__weekday-picker {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.65rem;
            padding: 0.65rem 0.75rem;
            border-radius: 0.65rem;
            border: 1px solid var(--tsc-border);
            background: var(--tsc-surface);
        }

        .fi-training-schedule__weekday-picker-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--tsc-muted);
            white-space: nowrap;
        }

        .fi-training-schedule__weekday-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            flex: 1;
        }

        .fi-training-schedule__weekday-pill {
            min-width: 2.35rem;
            padding: 0.35rem 0.5rem;
            border-radius: 0.45rem;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--tsc-text);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--tsc-border);
            cursor: pointer;
            transition: all 0.15s;
        }

        .fi-training-schedule__weekday-pill:hover {
            color: var(--tsc-text);
            border-color: rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
        }

        .fi-training-schedule__weekday-pill.is-selected {
            opacity: 1;
            color: #bbf7d0;
            background: rgba(34, 197, 94, 0.2);
            border-color: rgba(34, 197, 94, 0.5);
            box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.3);
            cursor: pointer;
        }

        /* الوضع الفاتح (إن وُجد) */
        html:not(.dark) .fi-training-schedule {
            --tsc-border: rgb(228 228 231);
            --tsc-surface: #fafafa;
            --tsc-text: #18181b;
            --tsc-muted: #71717a;
            background: #fff;
        }
        html:not(.dark) .fi-training-schedule__mode-switch,
        html:not(.dark) .fi-training-schedule__month-bar,
        html:not(.dark) .fi-training-schedule__calendar {
            background: #f4f4f5;
        }
        html:not(.dark) .fi-training-schedule__day {
            color: #3f3f46;
            background: #f0f0f2;
            border-color: #e4e4e7;
        }
        html:not(.dark) .fi-training-schedule__day--registration { color: #1d4ed8; }
        html:not(.dark) .fi-training-schedule__day--program { color: #15803d; }
        html:not(.dark) .fi-training-schedule__weekday-pill {
            color: #3f3f46;
            background: #fff;
            border-color: #e4e4e7;
        }
        html:not(.dark) .fi-training-schedule__weekday-pill.is-selected {
            color: #15803d;
            background: rgba(34, 197, 94, 0.15);
        }
    </style>

    @once
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('trainingScheduleCalendar', (config) => ({
                    showRegistration: config.showRegistration,
                    programHasEnd: config.programHasEnd,
                    showWeekdayPicker: config.showWeekdayPicker,
                    showPublishSchedule: config.showPublishSchedule,
                    months: config.months,
                    dayLabels: config.dayLabels,
                    activeRange: config.showRegistration ? 'registration' : 'program',
                    pendingStart: null,
                    month: new Date().getMonth(),
                    year: new Date().getFullYear(),
                    registrationStart: null,
                    registrationEnd: null,
                    programStart: null,
                    programEnd: null,
                    weekdays: [],
                    publishImmediately: true,
                    publishedAt: null,
                    notifyAudience: false,

                    init() {
                        this.registrationStart = this.$wire.$entangle(config.paths.registrationStart, true);
                        this.registrationEnd = this.$wire.$entangle(config.paths.registrationEnd, true);
                        this.programStart = this.$wire.$entangle(config.paths.programStart, true);
                        this.programEnd = this.$wire.$entangle(config.paths.programEnd, true);
                        this.weekdays = this.$wire.$entangle(config.paths.weekdays, true);
                        this.publishImmediately = this.$wire.$entangle(config.paths.publishImmediately, true);
                        this.publishedAt = this.$wire.$entangle(config.paths.publishedAt, true);
                        this.notifyAudience = this.$wire.$entangle(config.paths.notifyAudience, true);

                        if (this.publishedAt) {
                            this.publishedAt = this.normalizeDate(this.publishedAt);
                        }

                        const anchor = this.registrationStart || this.programStart || this.publishedAt;
                        const anchorDate = anchor ? this.parseDate(anchor) : new Date();
                        if (anchorDate) {
                            this.month = anchorDate.getMonth();
                            this.year = anchorDate.getFullYear();
                        }
                    },

                    setActiveRange(range) {
                        this.activeRange = range;
                        this.pendingStart = null;
                    },

                    onPublishImmediatelyChange() {
                        if (this.publishImmediately) {
                            this.publishedAt = null;
                            if (this.activeRange === 'publish') {
                                this.activeRange = this.showRegistration ? 'registration' : 'program';
                            }
                        } else {
                            this.activeRange = 'publish';
                        }
                    },

                    pendingHint() {
                        if (this.activeRange === 'publish') {
                            return 'اختر يوم النشر';
                        }
                        return 'اختر يوم النهاية';
                    },

                    normalizeDate(value) {
                        if (! value) return null;
                        const str = String(value);
                        return str.length >= 10 ? str.slice(0, 10) : str;
                    },

                    hijriLabel(day) {
                        try {
                            const date = new Date(this.year, this.month, day);
                            const hDay = Number(new Intl.DateTimeFormat('en-u-ca-islamic', { day: 'numeric' }).format(date));
                            if (hDay === 1) {
                                return new Intl.DateTimeFormat('ar-SA-u-ca-islamic', { month: 'short' }).format(date);
                            }
                            return hDay > 0 ? String(hDay) : '';
                        } catch {
                            return '';
                        }
                    },

                    hasWeekdaySelection() {
                        return this.showWeekdayPicker && Array.isArray(this.weekdays) && this.weekdays.length > 0;
                    },

                    isWeekdayOff(day) {
                        if (! this.hasWeekdaySelection()) {
                            return false;
                        }
                        return ! this.weekdaySelected(new Date(this.year, this.month, day).getDay());
                    },

                    todayDateString() {
                        return this.formatDate(new Date());
                    },

                    isBeforeToday(dateStr) {
                        const normalized = this.normalizeDate(dateStr);
                        return normalized !== null && normalized < this.todayDateString();
                    },

                    isTodayDateStr(dateStr) {
                        return this.normalizeDate(dateStr) === this.todayDateString();
                    },

                    get scheduleErrors() {
                        const errors = [];
                        const today = this.todayDateString();
                        const programStart = this.normalizeDate(this.programStart);
                        const programEnd = this.normalizeDate(this.programEnd || this.programStart);
                        const regStart = this.normalizeDate(this.registrationStart);
                        const regEnd = this.normalizeDate(this.registrationEnd || this.registrationStart);

                        if (! this.publishImmediately) {
                            const pubDate = this.normalizeDate(this.publishedAt);
                            if (pubDate && pubDate < today) {
                                errors.push('لا يمكن تحديد تاريخ النشر قبل اليوم.');
                            }
                        }

                        if (programStart && regStart && this.showRegistration && programStart < regStart) {
                            errors.push('لا يمكن أن يبدأ البرنامج قبل تاريخ بدء التسجيل.');
                        }

                        if (programEnd && regEnd && this.showRegistration && regEnd > programEnd) {
                            errors.push('يجب أن ينتهي التسجيل في أو قبل تاريخ انتهاء البرنامج.');
                        }

                        return errors;
                    },

                    weekdaySelected(index) {
                        if (! Array.isArray(this.weekdays)) return false;
                        return this.weekdays.map(Number).includes(index);
                    },

                    weekdayHeaderClass(index) {
                        if (this.weekdaySelected(index)) {
                            return 'is-selected';
                        }
                        if (this.hasWeekdaySelection()) {
                            return 'is-off';
                        }
                        return '';
                    },

                    toggleWeekday(index) {
                        const current = Array.isArray(this.weekdays) ? [...this.weekdays] : [];
                        const normalized = current.map(Number);
                        const pos = normalized.indexOf(index);
                        if (pos >= 0) {
                            normalized.splice(pos, 1);
                        } else {
                            normalized.push(index);
                            normalized.sort((a, b) => a - b);
                        }
                        this.weekdays = normalized;
                    },

                    get daysInMonth() {
                        return new Date(this.year, this.month + 1, 0).getDate();
                    },

                    get leadingBlanks() {
                        const first = new Date(this.year, this.month, 1).getDay();
                        return Array.from({ length: first }, (_, i) => i);
                    },

                    prevMonth() {
                        if (this.month === 0) { this.month = 11; this.year--; }
                        else { this.month--; }
                    },

                    nextMonth() {
                        if (this.month === 11) { this.month = 0; this.year++; }
                        else { this.month++; }
                    },

                    formatDate(date) {
                        const y = date.getFullYear();
                        const m = String(date.getMonth() + 1).padStart(2, '0');
                        const d = String(date.getDate()).padStart(2, '0');
                        return `${y}-${m}-${d}`;
                    },

                    parseDate(value) {
                        if (! value) return null;
                        const parts = String(value).split('-');
                        if (parts.length !== 3) return null;
                        return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
                    },

                    dateForDay(day) {
                        return this.formatDate(new Date(this.year, this.month, day));
                    },

                    isToday(day) {
                        const t = new Date();
                        return t.getFullYear() === this.year && t.getMonth() === this.month && t.getDate() === day;
                    },

                    inRange(dateStr, start, end) {
                        if (! start) return false;
                        const endVal = end || start;
                        return dateStr >= start && dateStr <= endVal;
                    },

                    isRangeStart(dateStr, start, end) {
                        return start && dateStr === start;
                    },

                    isRangeEnd(dateStr, start, end) {
                        if (! start) return false;
                        const endVal = end || start;
                        return dateStr === endVal;
                    },

                    dayClasses(day) {
                        const dateStr = this.dateForDay(day);
                        const inReg = this.showRegistration && this.inRange(dateStr, this.registrationStart, this.registrationEnd);
                        const inProg = this.inRange(dateStr, this.programStart, this.programHasEnd ? this.programEnd : this.programStart);
                        const inPublish = ! this.publishImmediately && this.publishedAt && dateStr === this.normalizeDate(this.publishedAt);
                        const weekdayOff = this.hasWeekdaySelection() && this.isWeekdayOff(day);
                        const classes = [];

                        if (inPublish) {
                            classes.push('fi-training-schedule__day--publish');
                        }

                        if (inReg && inProg && ! weekdayOff) {
                            classes.push('fi-training-schedule__day--overlap');
                        } else if (inReg) {
                            classes.push('fi-training-schedule__day--registration');
                            if (this.isRangeStart(dateStr, this.registrationStart, this.registrationEnd)) classes.push('is-start');
                            if (this.isRangeEnd(dateStr, this.registrationStart, this.registrationEnd)) classes.push('is-end');
                        } else if (inProg) {
                            if (weekdayOff) {
                                classes.push('fi-training-schedule__day--weekday-off', 'fi-training-schedule__day--program-inactive');
                            } else {
                                classes.push('fi-training-schedule__day--program');
                                if (this.isRangeStart(dateStr, this.programStart, this.programEnd)) classes.push('is-start');
                                if (this.isRangeEnd(dateStr, this.programStart, this.programEnd)) classes.push('is-end');
                            }
                        } else if (weekdayOff) {
                            classes.push('fi-training-schedule__day--weekday-off');
                        }

                        if (this.pendingStart === dateStr) classes.push('fi-training-schedule__day--pending');
                        if (this.isToday(day)) classes.push('fi-training-schedule__day--today');
                        if (this.activeRange === 'publish' && this.isBeforeToday(dateStr)) {
                            classes.push('fi-training-schedule__day--past');
                        }

                        return classes.join(' ');
                    },

                    dayAriaLabel(day) {
                        const dateStr = this.dateForDay(day);
                        return this.formatDisplay(dateStr) || `يوم ${day}`;
                    },

                    selectDay(day) {
                        const dateStr = this.dateForDay(day);

                        if (this.activeRange === 'publish') {
                            if (this.isBeforeToday(dateStr)) {
                                return;
                            }

                            if (this.isTodayDateStr(dateStr)) {
                                this.publishImmediately = true;
                                this.publishedAt = null;
                                this.activeRange = this.showRegistration ? 'registration' : 'program';
                            } else {
                                this.publishImmediately = false;
                                this.publishedAt = dateStr;
                            }

                            this.pendingStart = null;
                            return;
                        }

                        if (this.activeRange === 'program' && ! this.programHasEnd) {
                            this.programStart = dateStr;
                            this.programEnd = null;
                            this.pendingStart = null;
                            return;
                        }

                        if (this.pendingStart === dateStr) {
                            this.pendingStart = null;
                            return;
                        }

                        if (! this.pendingStart) {
                            this.pendingStart = dateStr;
                            return;
                        }

                        let start = this.pendingStart;
                        let end = dateStr;
                        if (end < start) [start, end] = [end, start];

                        if (this.activeRange === 'registration') {
                            this.registrationStart = start;
                            this.registrationEnd = end;
                        } else {
                            this.programStart = start;
                            this.programEnd = end;
                        }

                        this.pendingStart = null;
                    },

                    clearRange(type) {
                        if (type === 'registration') {
                            this.registrationStart = null;
                            this.registrationEnd = null;
                        } else {
                            this.programStart = null;
                            this.programEnd = null;
                        }
                        this.pendingStart = null;
                    },

                    clearPublishDate() {
                        this.publishedAt = null;
                    },

                    publishSummary() {
                        if (! this.publishedAt) return '—';
                        return this.formatDisplay(this.normalizeDate(this.publishedAt));
                    },

                    publishMeta() {
                        if (this.publishImmediately) {
                            return 'نشر فوري';
                        }
                        if (! this.publishedAt) return '';
                        const d = this.parseDate(this.normalizeDate(this.publishedAt));
                        if (! d) return '';
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        d.setHours(0, 0, 0, 0);
                        if (d.getTime() === today.getTime()) {
                            return 'نشر فوري';
                        }
                        return d > today ? 'مجدول' : '';
                    },

                    formatDisplay(dateStr) {
                        const d = this.parseDate(dateStr);
                        if (! d) return '';
                        return `${d.getDate()} ${this.months[d.getMonth()]} ${d.getFullYear()}`;
                    },

                    daysBetween(start, end) {
                        if (! start) return 0;
                        const s = this.parseDate(start);
                        const e = this.parseDate(end || start);
                        if (! s || ! e) return 0;
                        return Math.round((e - s) / 86400000) + 1;
                    },

                    rangeSummary(start, end) {
                        if (! start) return '—';
                        if (! end || end === start) return this.formatDisplay(start);
                        return `${this.formatDisplay(start)} — ${this.formatDisplay(end)}`;
                    },

                    rangeMeta(start, end) {
                        if (! start) return '';
                        const days = this.daysBetween(start, end);
                        return days === 1 ? 'يوم واحد' : `${days} أيام`;
                    },

                    programSummary() {
                        if (! this.programStart) return '—';
                        if (! this.programHasEnd) return this.formatDisplay(this.programStart);
                        return this.rangeSummary(this.programStart, this.programEnd);
                    },

                    programMeta() {
                        if (! this.programStart) return '';
                        if (! this.programHasEnd) return 'يوم واحد';
                        return this.rangeMeta(this.programStart, this.programEnd);
                    },
                }));
            });
        </script>
    @endonce
</x-dynamic-component>

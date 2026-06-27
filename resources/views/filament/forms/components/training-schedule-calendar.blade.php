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
            },
        })"
        class="fi-training-schedule"
        dir="rtl"
    >
        <div class="fi-training-schedule__header">
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
                        وقت التسجيل
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
                    وقت البرنامج
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
        </div>

        <div class="fi-training-schedule__publish" x-show="showPublishSchedule" x-cloak>
            <div class="fi-training-schedule__toggle-field">
                <label class="fi-fo-field-label" for="{{ $id }}-publish-immediately">
                    <span class="fi-fo-field-label-content">نشر فوراً</span>
                </label>
                <button
                    type="button"
                    role="switch"
                    id="{{ $id }}-publish-immediately"
                    class="fi-toggle fi-training-schedule__toggle"
                    x-bind:aria-checked="publishImmediately ? 'true' : 'false'"
                    x-on:click="publishImmediately = ! publishImmediately; onPublishImmediatelyChange()"
                    x-bind:class="{ 'fi-toggle-on': publishImmediately, 'fi-toggle-off': ! publishImmediately }"
                >
                    <div>
                        <div aria-hidden="true"></div>
                        <div aria-hidden="true"></div>
                    </div>
                </button>
            </div>
        </div>

        <p class="fi-training-schedule__pending" x-show="pendingStart && activeRange !== 'publish'" x-cloak x-text="pendingHint()"></p>

        <div class="fi-training-schedule__errors" x-show="scheduleErrors.length > 0" x-cloak>
            <template x-for="(error, index) in scheduleErrors" x-bind:key="'err-' + index">
                <p class="fi-training-schedule__error" x-text="error"></p>
            </template>
        </div>

        <div class="fi-training-schedule__body">
            <div class="fi-training-schedule__main">
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
            </div>

            <aside class="fi-training-schedule__aside" aria-label="ملخص الفترات">
                <div class="fi-training-schedule__summary">
                    <template x-if="showRegistration">
                        <div class="fi-training-schedule__summary-card fi-training-schedule__summary-card--registration">
                            <div class="fi-training-schedule__summary-accent"></div>
                            <div class="fi-training-schedule__summary-inner">
                                <div class="fi-training-schedule__summary-head">
                                    <div class="fi-training-schedule__summary-title-row">
                                        <span class="fi-training-schedule__summary-dot"></span>
                                        <span class="fi-training-schedule__summary-badge">وقت التسجيل</span>
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
                                    <span class="fi-training-schedule__summary-badge">وقت البرنامج</span>
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
            </aside>
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
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            width: 100%;
            max-width: 48rem;
        }

        .fi-training-schedule-field.fi-fo-field {
            gap: 0;
        }

        .fi-training-schedule__body {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(12.5rem, 0.65fr);
            gap: 1rem;
            align-items: start;
        }

        .fi-training-schedule__main {
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            min-width: 0;
        }

        .fi-training-schedule__aside {
            display: flex;
            flex-direction: column;
            min-width: 0;
            position: sticky;
            top: 0;
        }

        .fi-training-schedule__header {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0;
            width: 100%;
        }

        .fi-training-schedule__toggle.fi-toggle-off {
            background-color: #52525b !important;
        }

        .fi-training-schedule__toggle.fi-toggle-on {
            background-color: var(--tsc-brand) !important;
        }

        .fi-training-schedule__toggle > :first-child {
            background-color: #fff;
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
            display: flex;
            flex-wrap: wrap;
            width: 100%;
            padding: 0.25rem;
            gap: 0.25rem;
            border-radius: 0.8rem;
            background: rgba(0, 0, 0, 0.22);
            border: 1px solid var(--tsc-border);
        }

        .fi-training-schedule__mode-btn {
            flex: 1 1 0;
            justify-content: center;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 0.65rem;
            border-radius: 0.6rem;
            font-size: 0.78rem;
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
            flex-direction: column;
            align-items: stretch;
            gap: 0;
            padding: 0;
        }

        .fi-training-schedule__toggle-field {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            width: 100%;
            padding: 0.55rem 0.75rem;
            border-radius: 0.65rem;
            border: 1px solid var(--tsc-border);
            background: var(--tsc-surface);
        }

        .fi-training-schedule__toggle-field .fi-fo-field-label {
            margin: 0;
            flex: 1;
        }

        .fi-training-schedule__toggle-field .fi-fo-field-label-content {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--tsc-text);
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
            padding: 0.65rem;
            border-radius: 0.8rem;
            background: rgba(0, 0, 0, 0.18);
            border: 1px solid var(--tsc-border);
            width: 100%;
        }

        .fi-training-schedule__weekdays,
        .fi-training-schedule__grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.28rem;
        }

        .fi-training-schedule__weekday {
            text-align: center;
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--tsc-muted);
            padding: 0.25rem 0;
        }

        .fi-training-schedule__day {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0;
            height: 2.35rem;
            min-height: 2.35rem;
            max-height: 2.35rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--tsc-text);
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: transform 0.12s, background 0.12s, border-color 0.12s, box-shadow 0.12s;
            cursor: pointer;
            padding: 0;
        }

        .fi-training-schedule__day-g {
            line-height: 1;
            font-size: 0.82rem;
        }

        .fi-training-schedule__day-h {
            display: none;
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
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            width: 100%;
        }

        .fi-training-schedule__summary-card {
            position: relative;
            display: flex;
            overflow: hidden;
            border-radius: 0.7rem;
            border: 1px solid var(--tsc-border);
            background: var(--tsc-surface);
        }

        .fi-training-schedule__summary-accent {
            width: 3px;
            flex-shrink: 0;
        }

        .fi-training-schedule__summary-card--registration .fi-training-schedule__summary-accent { background: var(--tsc-reg); }
        .fi-training-schedule__summary-card--program .fi-training-schedule__summary-accent { background: var(--tsc-prog); }
        .fi-training-schedule__summary-card--publish .fi-training-schedule__summary-accent { background: var(--tsc-pub); }

        .fi-training-schedule__summary-inner {
            flex: 1;
            padding: 0.65rem 0.75rem;
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
            margin-bottom: 0.35rem;
        }

        .fi-training-schedule__summary-badge {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--tsc-text);
        }

        .fi-training-schedule__summary-dates {
            margin: 0;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--tsc-text);
            line-height: 1.45;
        }

        .fi-training-schedule__summary-pill {
            display: inline-block;
            margin-top: 0.35rem;
            padding: 0.12rem 0.45rem;
            border-radius: 9999px;
            font-size: 0.65rem;
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
            gap: 0.55rem;
            padding: 0.55rem 0.65rem;
            border-radius: 0.65rem;
            border: 1px solid var(--tsc-border);
            background: var(--tsc-surface);
        }

        .fi-training-schedule__weekday-picker-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--tsc-muted);
            white-space: nowrap;
        }

        .fi-training-schedule__weekday-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            flex: 1;
        }

        .fi-training-schedule__weekday-pill {
            min-width: 2.1rem;
            padding: 0.3rem 0.45rem;
            border-radius: 0.42rem;
            font-size: 0.7rem;
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
        }
        html:not(.dark) .fi-training-schedule__toggle.fi-toggle-off {
            background-color: #d4d4d8 !important;
        }
        html:not(.dark) .fi-training-schedule__toggle.fi-toggle-on {
            background-color: var(--tsc-brand) !important;
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

        .fi-modal .fi-training-schedule {
            max-width: none;
            width: 100%;
        }

        .fi-modal:has(.fi-training-schedule) .fi-modal-content {
            gap: 0.75rem;
        }

        .fi-modal:has(.fi-training-schedule) .fi-training-schedule-field.fi-fo-field {
            margin: 0;
        }

        .fi-modal .fi-training-schedule__body {
            grid-template-columns: minmax(0, 1.45fr) minmax(12rem, 0.55fr);
            gap: 1.15rem;
        }

        @media (max-width: 640px) {
            .fi-training-schedule__body {
                grid-template-columns: 1fr;
            }

            .fi-training-schedule__aside {
                position: static;
            }
        }
    </style>

</x-dynamic-component>

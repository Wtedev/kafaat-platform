(() => {
    if (window.__trainingScheduleCalendarRegistered) {
        return;
    }

    const registerTrainingScheduleCalendar = () => {
        if (window.__trainingScheduleCalendarRegistered) {
            return;
        }

        window.__trainingScheduleCalendarRegistered = true;

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
            _paths: null,
            _syncingFromWire: false,

            init() {
                this.$nextTick(() => {
                    this.bindSchedulePaths(config.paths);
                });
            },

            wireGet(path) {
                if (typeof this.$wire.$get === 'function') {
                    return this.$wire.$get(path);
                }

                if (typeof this.$wire.get === 'function') {
                    return this.$wire.get(path);
                }

                return null;
            },

            wireSet(path, value) {
                if (typeof this.$wire.$set === 'function') {
                    this.$wire.$set(path, value, true);

                    return;
                }

                if (typeof this.$wire.set === 'function') {
                    this.$wire.set(path, value);
                }
            },

            bindSchedulePaths(paths) {
                this._paths = paths;

                this.pullFromWire();

                const sync = (path, value) => {
                    if (this._syncingFromWire) {
                        return;
                    }

                    this.wireSet(path, value);
                };

                this.$watch('registrationStart', (value) => sync(paths.registrationStart, value));
                this.$watch('registrationEnd', (value) => sync(paths.registrationEnd, value));
                this.$watch('programStart', (value) => sync(paths.programStart, value));
                this.$watch('programEnd', (value) => sync(paths.programEnd, value));
                this.$watch('weekdays', (value) => sync(paths.weekdays, value));
                this.$watch('publishImmediately', (value) => sync(paths.publishImmediately, value));
                this.$watch('publishedAt', (value) => sync(paths.publishedAt, value));

                const anchor = this.registrationStart || this.programStart || this.publishedAt;
                const anchorDate = anchor ? this.parseDate(anchor) : new Date();
                if (anchorDate) {
                    this.month = anchorDate.getMonth();
                    this.year = anchorDate.getFullYear();
                }
            },

            pullFromWire() {
                if (! this._paths) {
                    return;
                }

                const paths = this._paths;

                this._syncingFromWire = true;

                this.registrationStart = this.normalizeDate(this.wireGet(paths.registrationStart));
                this.registrationEnd = this.normalizeDate(this.wireGet(paths.registrationEnd));
                this.programStart = this.normalizeDate(this.wireGet(paths.programStart));
                this.programEnd = this.normalizeDate(this.wireGet(paths.programEnd));

                const weekdays = this.wireGet(paths.weekdays);
                this.weekdays = Array.isArray(weekdays) ? weekdays : [];

                const publishImmediately = this.wireGet(paths.publishImmediately);
                this.publishImmediately = publishImmediately === undefined ? true : Boolean(publishImmediately);

                const publishedAt = this.wireGet(paths.publishedAt);
                this.publishedAt = publishedAt ? this.normalizeDate(publishedAt) : null;

                this._syncingFromWire = false;
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
    };

    document.addEventListener('alpine:init', registerTrainingScheduleCalendar);

    if (window.Alpine) {
        registerTrainingScheduleCalendar();
    }
})();

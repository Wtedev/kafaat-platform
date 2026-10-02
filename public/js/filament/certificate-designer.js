document.addEventListener('alpine:init', () => {
    if (window.Alpine.certificateDesignerRegistered) {
        return;
    }
    window.Alpine.certificateDesignerRegistered = true;

    Alpine.data('certificateDesigner', (config) => ({
        programName: config.programName,
        statusLabel: config.statusLabel,
        pageWidthMm: Number(config.pageWidthMm),
        pageHeightMm: Number(config.pageHeightMm),
        backgroundUrl: config.backgroundUrl,
        elements: config.elements || [],
        imageUrls: config.imageUrls || {},
        eligibility: Object.assign({}, config.eligibility || {}, { auto_issue: Boolean(config.autoIssue) }),
        modes: config.modes || [],
        fields: config.fields || [],
        samples: config.samples || {},
        fonts: config.fonts || [],
        registrations: config.registrations || [],
        copyOptions: config.copyOptions || [],
        selectedId: null,
        selectedElement: null,
        zoom: 1,
        showGrid: false,
        showRuler: false,
        useRealValues: false,
        registrationId: '',
        realValues: null,
        copySourceId: '',
        dirty: false,
        guideX: null,
        guideY: null,
        drag: null,

        init() {
            this.onPointerMove = (event) => this.movePointer(event);
            this.onPointerUp = () => this.endPointer();
            window.addEventListener('keydown', (event) => this.onKey(event));
            window.addEventListener('beforeunload', (event) => {
                if (!this.dirty) {
                    return;
                }
                event.preventDefault();
                event.returnValue = '';
            });
            document.addEventListener('livewire:navigate', (event) => {
                if (this.dirty && !window.confirm('لديك تغييرات غير محفوظة. هل تريد المغادرة؟')) {
                    event.preventDefault();
                }
            });
        },

        selected() {
            return this.elements.find((element) => element.id === this.selectedId) || null;
        },

        markDirty() {
            this.dirty = true;
        },

        addField(key) {
            this.elements.push({
                ...this.baseBox(40, 8),
                type: 'field',
                key,
                text: null,
                prefix: null,
                suffix: null,
                ...this.textStyle(),
                image_path: null,
            });
            this.selectedElement = this.elements.at(-1);
            this.selectedId = this.selectedElement.id;
            this.dirty = true;
        },

        addText() {
            this.elements.push({
                ...this.baseBox(36, 8),
                type: 'text',
                key: null,
                text: 'نص ثابت',
                prefix: null,
                suffix: null,
                ...this.textStyle(),
                image_path: null,
            });
            this.selectedElement = this.elements.at(-1);
            this.selectedId = this.selectedElement.id;
            this.dirty = true;
        },

        addQr() {
            this.elements.push({
                ...this.baseBox(12, 12),
                type: 'qr',
                key: null,
                text: null,
                prefix: null,
                suffix: null,
                font_family: null,
                font_size_pt: null,
                font_weight: null,
                color: null,
                align: null,
                image_path: null,
            });
            this.selectedElement = this.elements.at(-1);
            this.selectedId = this.selectedElement.id;
            this.dirty = true;
        },

        addImage() {
            this.elements.push({
                ...this.baseBox(18, 12),
                type: 'image',
                key: null,
                text: null,
                prefix: null,
                suffix: null,
                font_family: null,
                font_size_pt: null,
                font_weight: null,
                color: null,
                align: null,
                image_path: null,
            });
            this.selectedElement = this.elements.at(-1);
            this.selectedId = this.selectedElement.id;
            this.dirty = true;
        },

        baseBox(width, height) {
            const offset = Math.min(this.elements.length * 4, 40);
            return {
                id: crypto.randomUUID(),
                x: this.round2(8 + offset),
                y: this.round2(8 + offset),
                width,
                height,
                auto_shrink: false,
                min_font_size_pt: null,
            };
        },

        textStyle() {
            return {
                font_family: this.fonts[0] || 'ibmplexsansarabic',
                font_size_pt: 18,
                font_weight: 'regular',
                color: '#1a1a1a',
                align: 'center',
            };
        },

        removeSelected() {
            this.elements = this.elements.filter((element) => element.id !== this.selectedId);
            this.selectedId = null;
            this.selectedElement = null;
            this.dirty = true;
        },

        label(element) {
            if (element.type === 'text') {
                return element.text || '';
            }
            if (element.type !== 'field') {
                return '';
            }
            const value = this.useRealValues && this.realValues
                ? (this.realValues[element.key] ?? '')
                : (this.samples[element.key] ?? '');
            return `${element.prefix || ''}${value}${element.suffix || ''}`;
        },

        boxStyle(element) {
            return `left:${element.x}%;top:${element.y}%;width:${element.width}%;height:${element.height}%;`;
        },

        textStyleCss(element) {
            const page = this.$refs.page;
            const width = page ? page.offsetWidth : 800;
            const pxPerMm = width / this.pageWidthMm;
            const px = (Number(element.font_size_pt) || 16) * 0.3528 * pxPerMm;
            const weight = element.font_weight === 'bold' ? 700 : 400;
            return `font-size:${px}px;font-weight:${weight};color:${element.color || '#1a1a1a'};text-align:${element.align || 'center'};`;
        },

        pageStyle() {
            const gridX = (10 / this.pageWidthMm) * 100;
            const gridY = (10 / this.pageHeightMm) * 100;
            return `aspect-ratio:${this.pageWidthMm} / ${this.pageHeightMm};transform:scale(${this.zoom});` +
                (this.showGrid ? `background-size:${gridX}% ${gridY}%;` : '');
        },

        mm(axis) {
            const element = this.selected();
            if (!element) {
                return '';
            }
            const page = axis === 'x' || axis === 'width' ? this.pageWidthMm : this.pageHeightMm;
            return this.round2((Number(element[axis]) / 100) * page);
        },

        setMm(axis, value) {
            const element = this.selected();
            if (!element) {
                return;
            }
            const page = axis === 'x' || axis === 'width' ? this.pageWidthMm : this.pageHeightMm;
            element[axis] = this.round2((Number(value) / page) * 100);
            this.clamp(element);
            this.dirty = true;
        },

        startDrag(event, element) {
            if (event.button !== 0) {
                return;
            }
            this.selectedId = element.id;
            this.selectedElement = element;
            this.drag = {
                mode: 'move',
                id: element.id,
                x: event.clientX,
                y: event.clientY,
                orig: { ...element },
            };
            window.addEventListener('pointermove', this.onPointerMove);
            window.addEventListener('pointerup', this.onPointerUp);
        },

        startResize(event, element, handle) {
            event.stopPropagation();
            this.selectedId = element.id;
            this.selectedElement = element;
            this.drag = {
                mode: 'resize',
                handle,
                id: element.id,
                x: event.clientX,
                y: event.clientY,
                orig: { ...element },
            };
            window.addEventListener('pointermove', this.onPointerMove);
            window.addEventListener('pointerup', this.onPointerUp);
        },

        movePointer(event) {
            if (!this.drag) {
                return;
            }
            const element = this.elements.find((item) => item.id === this.drag.id);
            const page = this.$refs.page;
            if (!element || !page) {
                return;
            }
            const rect = page.getBoundingClientRect();
            const dx = ((event.clientX - this.drag.x) / rect.width) * 100;
            const dy = ((event.clientY - this.drag.y) / rect.height) * 100;
            const orig = this.drag.orig;

            if (this.drag.mode === 'move') {
                element.x = this.round2(orig.x + dx);
                element.y = this.round2(orig.y + dy);
            } else {
                this.resize(element, orig, dx, dy, this.drag.handle);
            }

            this.clamp(element);
            this.snap(element);
            this.dirty = true;
        },

        resize(element, orig, dx, dy, handle) {
            let x = orig.x;
            let y = orig.y;
            let width = orig.width;
            let height = orig.height;
            if (handle.includes('e')) {
                width = orig.width + dx;
            }
            if (handle.includes('s')) {
                height = orig.height + dy;
            }
            if (handle.includes('w')) {
                x = orig.x + dx;
                width = orig.width - dx;
            }
            if (handle.includes('n')) {
                y = orig.y + dy;
                height = orig.height - dy;
            }
            element.x = this.round2(x);
            element.y = this.round2(y);
            element.width = this.round2(width);
            element.height = this.round2(height);
        },

        endPointer() {
            this.drag = null;
            this.guideX = null;
            this.guideY = null;
            window.removeEventListener('pointermove', this.onPointerMove);
            window.removeEventListener('pointerup', this.onPointerUp);
        },

        snap(element) {
            const thresholdX = (1.5 / this.pageWidthMm) * 100;
            const thresholdY = (1.5 / this.pageHeightMm) * 100;
            const centersX = [50];
            const centersY = [50];
            this.elements.forEach((other) => {
                if (other.id === element.id) {
                    return;
                }
                centersX.push(other.x + other.width / 2);
                centersY.push(other.y + other.height / 2);
            });
            const cx = element.x + element.width / 2;
            const cy = element.y + element.height / 2;
            this.guideX = null;
            this.guideY = null;
            centersX.forEach((target) => {
                if (this.guideX === null && Math.abs(cx - target) <= thresholdX) {
                    element.x = this.round2(target - element.width / 2);
                    this.guideX = target;
                }
            });
            centersY.forEach((target) => {
                if (this.guideY === null && Math.abs(cy - target) <= thresholdY) {
                    element.y = this.round2(target - element.height / 2);
                    this.guideY = target;
                }
            });
        },

        clamp(element) {
            element.width = this.round2(Math.min(100, Math.max(1, element.width)));
            element.height = this.round2(Math.min(100, Math.max(1, element.height)));
            element.x = this.round2(Math.min(100 - element.width, Math.max(0, element.x)));
            element.y = this.round2(Math.min(100 - element.height, Math.max(0, element.y)));
        },

        onKey(event) {
            const tag = event.target?.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
                return;
            }
            const element = this.selected();
            const arrows = {
                ArrowLeft: [-1, 0],
                ArrowRight: [1, 0],
                ArrowUp: [0, -1],
                ArrowDown: [0, 1],
            };
            const delta = arrows[event.key];
            if (!element || !delta) {
                return;
            }
            event.preventDefault();
            const step = event.shiftKey ? 5 : 0.5;
            element.x = this.round2(element.x + delta[0] * (step / this.pageWidthMm) * 100);
            element.y = this.round2(element.y + delta[1] * (step / this.pageHeightMm) * 100);
            this.clamp(element);
            this.dirty = true;
        },

        summary() {
            const rules = this.eligibility || {};
            const attendance = rules.min_attendance ?? '…';
            const score = rules.min_score ?? '…';
            const average = rules.min_average ?? '…';
            let core = 'تتحقق شروط الأحقية';
            if (rules.mode === 'attendance_only') {
                core = `حضر ${attendance}% فأكثر`;
            } else if (rules.mode === 'score_only') {
                core = `حصل على ${score} فأكثر في الاختبار`;
            } else if (rules.mode === 'both') {
                core = `حضر ${attendance}% فأكثر وحصل على ${score} فأكثر في الاختبار`;
            } else if (rules.mode === 'average') {
                core = `بلغ متوسط حضوره ودرجته ${average}% فأكثر`;
            } else if (rules.mode === 'completed_all_courses') {
                core = 'أكمل كل دورات المسار';
            } else if (rules.mode === 'min_approved_hours') {
                core = `بلغت ساعاته التطوعية المعتمدة ${rules.min_approved_hours ?? '…'} فأكثر`;
            }
            let sentence = `سيحصل على الشهادة من ${core}`;
            if (rules.require_completed_status) {
                sentence += '، بشرط أن يكون التسجيل مقبولاً أو مكتملاً';
            }
            if (rules.require_activity_ended) {
                sentence += '، وبعد انتهاء النشاط';
            }
            return `${sentence}.`;
        },

        onModeChange() {
            const mode = this.eligibility.mode;
            if (mode !== 'attendance_only' && mode !== 'both') {
                this.eligibility.min_attendance = null;
            }
            if (mode !== 'score_only' && mode !== 'both') {
                this.eligibility.min_score = null;
            }
            if (mode !== 'average') {
                this.eligibility.min_average = null;
            }
            if (mode !== 'min_approved_hours') {
                this.eligibility.min_approved_hours = null;
            }
            this.dirty = true;
        },

        payload() {
            return this.elements.map((element) => ({
                ...element,
                x: this.round2(element.x),
                y: this.round2(element.y),
                width: this.round2(element.width),
                height: this.round2(element.height),
                font_size_pt: element.font_size_pt === null || element.font_size_pt === '' ? null : Number(element.font_size_pt),
                min_font_size_pt: element.min_font_size_pt === null || element.min_font_size_pt === '' ? null : Number(element.min_font_size_pt),
                auto_shrink: Boolean(element.auto_shrink),
            }));
        },

        async saveDraft() {
            await this.$wire.saveDraft(this.payload(), this.eligibility);
            this.statusLabel = 'مسودة';
            this.dirty = false;
        },

        async approve() {
            const result = await this.$wire.approve(this.payload(), this.eligibility);
            if (result?.status === 'ready') {
                this.statusLabel = 'جاهز';
                this.dirty = false;
            }
            if (result?.status === 'draft') {
                this.statusLabel = 'مسودة';
            }
        },

        async preview() {
            await this.$wire.preview(this.payload(), this.useRealValues && this.registrationId ? Number(this.registrationId) : null);
        },

        async copy() {
            if (!this.copySourceId) {
                return;
            }
            const payload = await this.$wire.copyFromProgram(Number(this.copySourceId));
            this.elements = payload.elements || [];
            this.backgroundUrl = payload.backgroundUrl;
            this.pageWidthMm = Number(payload.pageWidthMm);
            this.pageHeightMm = Number(payload.pageHeightMm);
            this.imageUrls = payload.imageUrls || {};
            this.statusLabel = payload.statusLabel || 'مسودة';
            this.selectedId = null;
            this.selectedElement = null;
            this.dirty = false;
        },

        async toggleReal() {
            if (!this.useRealValues) {
                this.realValues = null;
                return;
            }
            if (this.registrationId) {
                this.realValues = await this.$wire.fieldValues(Number(this.registrationId));
            }
        },

        async loadReal() {
            if (!this.useRealValues || !this.registrationId) {
                return;
            }
            this.realValues = await this.$wire.fieldValues(Number(this.registrationId));
        },

        async uploadImage(event) {
            const file = event.target.files?.[0];
            if (!file || !this.selected()) {
                return;
            }
            this.$wire.upload('elementImage', file, async () => {
                const stored = await this.$wire.storeElementImage();
                const element = this.selected();
                if (!element || !stored) {
                    return;
                }
                element.image_path = stored.path;
                this.imageUrls[stored.path] = stored.url;
                this.dirty = true;
            });
            event.target.value = '';
        },

        onBackground(detail) {
            this.backgroundUrl = detail.url;
            this.pageWidthMm = Number(detail.pageWidthMm);
            this.pageHeightMm = Number(detail.pageHeightMm);
        },

        onApproved(detail) {
            this.statusLabel = detail.statusLabel || 'جاهز';
            this.dirty = false;
        },

        round2(value) {
            return Math.round(Number(value) * 100) / 100;
        },
    }));
});

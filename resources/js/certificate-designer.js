const PAGE_WIDTH_PT = 841.89;

function readDesigner() {
    const node = document.getElementById('certificate-designer-data');
    if (!node) {
        return null;
    }

    return JSON.parse(node.textContent || '{}');
}

function boot() {
    const data = readDesigner();
    const canvas = document.getElementById('certificate-canvas');
    if (!data || !canvas) {
        return;
    }

    const saved = Array.isArray(data.elements) ? {} : (data.elements || {});
    const draft = structuredClone(saved);
    const enabled = new Set(Object.keys(saved));
    let selected = null;
    let dirty = false;
    let drag = null;

    const status = document.getElementById('certificate-designer-status');
    const fields = document.getElementById('certificate-designer-fields');
    const hours = document.getElementById('certificate-designer-hours');
    const startDateInput = document.getElementById('certificate-designer-start');
    const endDateInput = document.getElementById('certificate-designer-end');
    const programDates = data.programDates || {};
    const settings = document.getElementById('certificate-designer-settings-form');
    const empty = document.getElementById('certificate-designer-empty');
    const guideX = document.getElementById('certificate-guide-x');
    const guideY = document.getElementById('certificate-guide-y');
    const fontInput = document.getElementById('certificate-font');
    const weightInput = document.getElementById('certificate-weight');
    const sizeInput = document.getElementById('certificate-size');
    const sizeUnit = document.getElementById('certificate-size-unit');
    const colorInput = document.getElementById('certificate-color');
    const hexInput = document.getElementById('certificate-color-hex');
    const alignBox = document.getElementById('certificate-align');
    const widthInput = document.getElementById('certificate-width');
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

    hours.value = data.hours ?? '';
    if (startDateInput) {
        startDateInput.value = draft.start_date?.value || programDates.start_date || '';
    }
    if (endDateInput) {
        endDateInput.value = draft.end_date?.value || programDates.end_date || '';
    }
    if (data.backgroundUrlImage) {
        canvas.style.backgroundImage = `url("${data.backgroundUrlImage}")`;
    }

    const setStatus = (message, kind = '') => {
        status.textContent = message;
        status.className = 'certificate-designer__status' + (kind ? ` is-${kind}` : '');
    };

    const undoButton = document.getElementById('certificate-designer-undo');
    const redoButton = document.getElementById('certificate-designer-redo');
    const undoStack = [];
    const redoStack = [];
    let restoring = false;
    let baseline = '';

    const designSignature = () => JSON.stringify({
        hours: hours.value,
        startDate: startDateInput?.value || '',
        endDate: endDateInput?.value || '',
        enabled: [...enabled].sort(),
        draft: Object.fromEntries([...enabled].sort().map((key) => [key, draft[key]])),
    });

    const markDirty = () => {
        dirty = baseline !== '' && designSignature() !== baseline;
        setStatus('');
    };

    const capture = () => ({
        draft: structuredClone(draft),
        enabled: [...enabled],
        hours: hours.value,
        startDate: startDateInput?.value || '',
        endDate: endDateInput?.value || '',
        selected,
    });

    const syncHistoryButtons = () => {
        if (undoButton) {
            undoButton.disabled = undoStack.length === 0;
        }
        if (redoButton) {
            redoButton.disabled = redoStack.length === 0;
        }
    };

    const pushShot = (state) => {
        if (restoring) {
            return;
        }
        const shot = JSON.stringify(state);
        if (undoStack.length > 0 && undoStack[undoStack.length - 1] === shot) {
            return;
        }
        undoStack.push(shot);
        if (undoStack.length > 100) {
            undoStack.shift();
        }
        redoStack.length = 0;
        syncHistoryButtons();
    };

    const pushUndo = () => {
        pushShot(capture());
    };

    const applyHistory = (shot) => {
        const state = JSON.parse(shot);
        Object.keys(draft).forEach((key) => {
            delete draft[key];
        });
        Object.assign(draft, state.draft);
        enabled.clear();
        state.enabled.forEach((key) => enabled.add(key));
        hours.value = state.hours;
        if (startDateInput && state.startDate !== undefined) {
            startDateInput.value = state.startDate;
            startDateInput.dataset.committed = state.startDate;
            startDateInput.dataset.undoPushed = '0';
        }
        if (endDateInput && state.endDate !== undefined) {
            endDateInput.value = state.endDate;
            endDateInput.dataset.committed = state.endDate;
            endDateInput.dataset.undoPushed = '0';
        }
        fields.querySelectorAll('input[data-field]').forEach((input) => {
            const key = input.dataset.field;
            input.checked = enabled.has(key);
            const node = canvas.querySelector(`[data-key="${key}"]`);
            if (enabled.has(key)) {
                ensureNode(key).hidden = false;
                paint(key);
            } else if (node) {
                node.hidden = true;
                node.classList.remove('is-selected');
            }
        });
        hideGuides();
        if (state.selected && enabled.has(state.selected)) {
            select(state.selected);
        } else {
            selected = null;
            settings.hidden = true;
            empty.hidden = false;
            canvas.querySelectorAll('.certificate-element').forEach((node) => {
                node.classList.remove('is-selected');
            });
        }
    };

    const undo = () => {
        if (undoStack.length === 0) {
            return;
        }
        restoring = true;
        redoStack.push(JSON.stringify(capture()));
        applyHistory(undoStack.pop());
        restoring = false;
        markDirty();
        setStatus('تم التراجع');
        syncHistoryButtons();
    };

    const redo = () => {
        if (redoStack.length === 0) {
            return;
        }
        restoring = true;
        undoStack.push(JSON.stringify(capture()));
        applyHistory(redoStack.pop());
        restoring = false;
        markDirty();
        setStatus('تمت الإعادة');
        syncHistoryButtons();
    };

    const fieldByKey = (key) => data.fields.find((field) => field.key === key);

    // Each field starts in its own spot so new fields never pile up on one another.
    const DEFAULT_PLACEMENT = {
        beneficiary_name: { x: 50, y: 42, size: 32, width: 70 },
        program_title: { x: 50, y: 55, size: 22, width: 70 },
        hours: { x: 50, y: 64, size: 16, width: 40 },
        issue_date: { x: 50, y: 73, size: 14, width: 30 },
        start_date: { x: 30, y: 73, size: 14, width: 30 },
        end_date: { x: 70, y: 73, size: 14, width: 30 },
        certificate_number: { x: 22, y: 86, size: 11, width: 30 },
    };

    const defaultElement = (key) => {
        if (fieldByKey(key)?.qr) {
            return { x: 82, y: 78, size: 28 };
        }

        const placement = DEFAULT_PLACEMENT[key] || { x: 50, y: 50, size: 18, width: 50 };

        return {
            x: placement.x,
            y: placement.y,
            font: 'shamel',
            weight: 'regular',
            size: placement.size,
            color: '#1a1a1a',
            align: 'center',
            width: placement.width,
        };
    };

    const printedDate = (iso) => (/^\d{4}-\d{2}-\d{2}$/.test(iso || '') ? iso.replaceAll('-', '/') : '');

    const textFor = (key) => {
        if (key === 'hours') {
            const value = hours.value.trim();
            // Match the PDF: 12.00 shows as 12 and 12.50 as 12.5.
            return value !== '' && Number.isFinite(Number(value)) ? String(Number(value)) : value;
        }

        if (key === 'start_date' || key === 'end_date') {
            const input = key === 'start_date' ? startDateInput : endDateInput;
            const printed = printedDate(input?.value || programDates[key] || '');
            if (printed !== '') {
                return printed;
            }
        }

        return data.samples[key] || '';
    };

    const fontFamily = (font) => {
        if (font === 'ibmplex') {
            return '"IBM Plex Sans Arabic", sans-serif';
        }

        if (font === 'diodrum') {
            return '"Diodrum Arabic", sans-serif';
        }

        return '"FF Shamel", "IBM Plex Sans Arabic", sans-serif';
    };

    const fontWeight = (weight) => ({
        extralight: '200',
        light: '300',
        regular: '400',
        medium: '500',
        semibold: '600',
        bold: '700',
        heavy: '800',
    }[weight] || '400');

    const syncWeightOptions = (font, preferred) => {
        let matched = false;
        [...weightInput.options].forEach((option) => {
            const fonts = (option.dataset.fonts || '').split(/\s+/).filter(Boolean);
            const allowed = fonts.length === 0 || fonts.includes(font);
            option.hidden = ! allowed;
            option.disabled = ! allowed;
            if (allowed && option.value === preferred) {
                matched = true;
            }
        });
        weightInput.value = matched ? preferred : 'regular';
        return weightInput.value;
    };

    const naturalTextWidth = (node, text) => {
        const probe = document.createElement('span');
        probe.textContent = text;
        probe.style.position = 'fixed';
        probe.style.left = '-9999px';
        probe.style.top = '0';
        probe.style.whiteSpace = 'nowrap';
        probe.style.visibility = 'hidden';
        probe.style.fontFamily = node.style.fontFamily;
        probe.style.fontWeight = node.style.fontWeight;
        probe.style.fontSize = getComputedStyle(node).fontSize;
        document.body.append(probe);
        const width = probe.getBoundingClientRect().width;
        probe.remove();

        return width;
    };

    const paint = (key) => {
        const node = canvas.querySelector(`[data-key="${key}"]`);
        const element = draft[key];
        if (!node || !element) {
            return;
        }

        node.style.left = `${element.x}%`;
        node.style.top = `${element.y}%`;
        if (fieldByKey(key)?.qr) {
            const size = `calc(${Number(element.size)} * 100cqw / 297)`;
            node.style.width = size;
            node.style.height = size;
            return;
        }

        node.style.width = `${element.width}%`;
        node.style.fontFamily = fontFamily(element.font);
        node.style.fontWeight = fontWeight(element.weight);
        node.style.color = element.color;
        node.style.textAlign = element.align;
        const span = node.querySelector('span');
        span.textContent = textFor(key);
        // cqw follows the canvas if the side panels change its width after the
        // first paint. A one-time pixel size stayed large and spilled off the page.
        node.style.fontSize = `calc(${Number(element.size)} * 100cqw / ${PAGE_WIDTH_PT})`;
        const available = node.clientWidth;
        const needed = naturalTextWidth(node, span.textContent);
        const fitted = available > 0 && needed > available
            ? Number(element.size) * available / needed
            : Number(element.size);
        node.style.fontSize = `calc(${fitted} * 100cqw / ${PAGE_WIDTH_PT})`;
    };

    const paintAll = () => {
        enabled.forEach((key) => paint(key));
    };

    const showGuides = (element) => {
        guideY.hidden = Math.abs(element.x - 50) > 0.05;
        guideX.hidden = Math.abs(element.y - 50) > 0.05;
    };

    const hideGuides = () => {
        guideX.hidden = true;
        guideY.hidden = true;
    };

    const select = (key) => {
        selected = key;
        canvas.querySelectorAll('.certificate-element').forEach((node) => {
            node.classList.toggle('is-selected', node.dataset.key === key);
        });
        const field = fieldByKey(key);
        const element = draft[key];
        if (!field || !element) {
            settings.hidden = true;
            empty.hidden = false;
            return;
        }

        empty.hidden = true;
        settings.hidden = false;
        settings.classList.toggle('is-qr', Boolean(field.qr));
        sizeUnit.textContent = field.qr ? 'مليمتر' : 'نقطة';
        sizeInput.value = element.size;
        if (!field.qr) {
            fontInput.value = element.font;
            element.weight = syncWeightOptions(element.font, element.weight || 'regular');
            colorInput.value = element.color;
            hexInput.value = element.color;
            widthInput.value = element.width;
            alignBox.querySelectorAll('button').forEach((button) => {
                button.classList.toggle('is-selected', button.dataset.align === element.align);
            });
        }
    };

    const ensureNode = (key) => {
        let node = canvas.querySelector(`[data-key="${key}"]`);
        if (node) {
            return node;
        }

        node = document.createElement('div');
        node.className = 'certificate-element';
        node.dataset.key = key;
        if (fieldByKey(key)?.qr) {
            const image = document.createElement('img');
            image.alt = '';
            image.src = data.qrDataUri;
            node.append(image);
        } else {
            node.append(document.createElement('span'));
        }

        node.addEventListener('pointerdown', (event) => {
            if (!enabled.has(key)) {
                return;
            }
            // Leave any settings box so the arrow keys move this element right away.
            if (document.activeElement instanceof HTMLElement && document.activeElement.matches('input, select, textarea')) {
                document.activeElement.blur();
            }
            select(key);
            drag = {
                key,
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                originX: draft[key].x,
                originY: draft[key].y,
            };
            node.setPointerCapture(event.pointerId);
            event.preventDefault();
        });

        canvas.append(node);
        return node;
    };

    const showField = (key) => {
        draft[key] = draft[key] || defaultElement(key);
        enabled.add(key);
        ensureNode(key).hidden = false;
        paint(key);
        select(key);
    };

    const hideField = (key) => {
        enabled.delete(key);
        const node = canvas.querySelector(`[data-key="${key}"]`);
        if (node) {
            node.hidden = true;
            node.classList.remove('is-selected');
        }
        if (selected === key) {
            selected = null;
            settings.hidden = true;
            empty.hidden = false;
            hideGuides();
        }
    };

    fields.querySelectorAll('input[data-field]').forEach((input) => {
        const key = input.dataset.field;
        input.addEventListener('change', () => {
            pushUndo();
            if (input.checked) {
                showField(key);
            } else {
                hideField(key);
            }
            markDirty();
        });
        if (input.checked) {
            showField(key);
        }
    });

    baseline = designSignature();
    syncHistoryButtons();

    const rememberOnce = (input) => {
        let open = false;
        const start = () => {
            if (open) {
                return;
            }
            pushUndo();
            open = true;
        };
        input.addEventListener('beforeinput', start);
        input.addEventListener('input', start);
        input.addEventListener('change', () => {
            open = false;
        });
        input.addEventListener('blur', () => {
            open = false;
        });
    };

    const current = () => (selected ? draft[selected] : null);

    const refreshSelected = () => {
        const element = current();
        if (!element) {
            return;
        }
        paint(selected);
        showGuides(element);
        markDirty();
    };

    fontInput.addEventListener('change', () => {
        const element = current();
        if (!element || fieldByKey(selected)?.qr) {
            return;
        }
        pushUndo();
        element.font = fontInput.value;
        element.weight = syncWeightOptions(element.font, element.weight || 'regular');
        refreshSelected();
    });

    weightInput.addEventListener('change', () => {
        const element = current();
        if (!element || fieldByKey(selected)?.qr) {
            return;
        }
        pushUndo();
        element.weight = weightInput.value;
        refreshSelected();
    });

    rememberOnce(sizeInput);
    sizeInput.addEventListener('input', () => {
        const element = current();
        if (!element) {
            return;
        }
        element.size = Number(sizeInput.value);
        refreshSelected();
    });

    rememberOnce(colorInput);
    colorInput.addEventListener('input', () => {
        const element = current();
        if (!element || fieldByKey(selected)?.qr) {
            return;
        }
        element.color = colorInput.value;
        hexInput.value = colorInput.value;
        refreshSelected();
    });

    rememberOnce(hexInput);
    hexInput.addEventListener('change', () => {
        const element = current();
        const match = /^#[0-9A-Fa-f]{6}$/.exec(hexInput.value.trim());
        if (!element || !match) {
            hexInput.value = element?.color || '#1a1a1a';
            return;
        }
        element.color = match[0].toLowerCase();
        colorInput.value = element.color;
        refreshSelected();
    });

    alignBox.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-align]');
        const element = current();
        if (!button || !element || fieldByKey(selected)?.qr) {
            return;
        }
        pushUndo();
        element.align = button.dataset.align;
        select(selected);
        refreshSelected();
    });

    rememberOnce(widthInput);
    widthInput.addEventListener('input', () => {
        const element = current();
        if (!element || fieldByKey(selected)?.qr) {
            return;
        }
        element.width = Number(widthInput.value);
        refreshSelected();
    });

    rememberOnce(hours);
    hours.addEventListener('input', () => {
        if (enabled.has('hours')) {
            paint('hours');
        }
        markDirty();
    });

    const paintBoundaryDates = () => {
        ['start_date', 'end_date'].forEach((key) => {
            if (enabled.has(key)) {
                paint(key);
            }
        });
        markDirty();
    };

    const watchBoundaryDate = (input, key) => {
        if (!input) {
            return;
        }
        const field = key === 'start_date' ? 'startDate' : 'endDate';
        input.dataset.committed = input.value;
        input.dataset.undoPushed = '0';
        const note = () => {
            if (input.dataset.undoPushed !== '1' && input.value !== input.dataset.committed) {
                const shot = capture();
                shot[field] = input.dataset.committed || '';
                pushShot(shot);
                input.dataset.undoPushed = '1';
            }
            paintBoundaryDates();
        };
        const settle = () => {
            input.dataset.committed = input.value;
            input.dataset.undoPushed = '0';
        };
        input.addEventListener('input', note);
        input.addEventListener('change', () => {
            note();
            settle();
        });
        input.addEventListener('blur', settle);
    };

    watchBoundaryDate(startDateInput, 'start_date');
    watchBoundaryDate(endDateInput, 'end_date');

    window.addEventListener('pointermove', (event) => {
        if (!drag || event.pointerId !== drag.pointerId) {
            return;
        }
        if (!drag.remembered) {
            pushUndo();
            drag.remembered = true;
        }
        const element = draft[drag.key];
        let x = drag.originX + ((event.clientX - drag.startX) / canvas.clientWidth) * 100;
        let y = drag.originY + ((event.clientY - drag.startY) / canvas.clientHeight) * 100;
        x = Math.min(100, Math.max(0, x));
        y = Math.min(100, Math.max(0, y));
        if (Math.abs(x - 50) <= 0.6) {
            x = 50;
        }
        if (Math.abs(y - 50) <= 0.6) {
            y = 50;
        }
        element.x = Math.round(x * 100) / 100;
        element.y = Math.round(y * 100) / 100;
        paint(drag.key);
        showGuides(element);
        markDirty();
    });

    window.addEventListener('pointerup', () => {
        drag = null;
    });

    let lastArrowAt = 0;

    window.addEventListener('keydown', (event) => {
        const shortcut = (event.metaKey || event.ctrlKey) && !event.altKey;
        if (shortcut && event.key.toLowerCase() === 'z') {
            event.preventDefault();
            if (event.shiftKey) {
                redo();
            } else {
                undo();
            }
            return;
        }
        if (shortcut && event.key.toLowerCase() === 'y') {
            event.preventDefault();
            redo();
            return;
        }

        const element = current();
        if (!element || event.target.matches('input, select, textarea')) {
            return;
        }
        const step = event.shiftKey ? 2 : 0.5;
        const moves = {
            ArrowLeft: ['x', -step],
            ArrowRight: ['x', step],
            ArrowUp: ['y', -step],
            ArrowDown: ['y', step],
        };
        const move = moves[event.key];
        if (!move) {
            return;
        }
        event.preventDefault();
        if (Date.now() - lastArrowAt > 500) {
            pushUndo();
        }
        lastArrowAt = Date.now();
        element[move[0]] = Math.min(100, Math.max(0, Math.round((element[move[0]] + move[1]) * 100) / 100));
        paint(selected);
        showGuides(element);
        markDirty();
    });

    window.addEventListener('resize', paintAll);
    if (document.fonts?.ready) {
        document.fonts.ready.then(paintAll);
    }
    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(paintAll).observe(canvas);
    }

    window.addEventListener('beforeunload', (event) => {
        if (!dirty) {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    });

    const payload = () => {
        const elements = {};
        enabled.forEach((key) => {
            const element = { ...draft[key] };
            if (key === 'start_date' || key === 'end_date') {
                const chosen = (key === 'start_date' ? startDateInput : endDateInput)?.value || '';
                const automatic = programDates[key] || '';
                if (chosen && chosen !== automatic) {
                    element.value = chosen;
                } else {
                    delete element.value;
                }
            }
            elements[key] = element;
        });

        return {
            hours: hours.value === '' ? null : Number(hours.value),
            elements,
        };
    };

    const save = async () => {
        setStatus('جارٍ الحفظ…');
        const response = await fetch(data.saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify(payload()),
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = firstError(body) || 'تعذر حفظ التصميم.';
            setStatus(message, 'error');
            return false;
        }
        dirty = false;
        setStatus(body.message || 'تم حفظ تصميم الشهادة.', 'ok');
        return true;
    };

    undoButton?.addEventListener('click', undo);
    redoButton?.addEventListener('click', redo);

    document.getElementById('certificate-designer-save').addEventListener('click', () => {
        save();
    });

    document.getElementById('certificate-designer-preview').addEventListener('click', () => {
        if (dirty) {
            setStatus('احفظ التصميم أولاً قبل المعاينة.', 'error');
            return;
        }
        window.open(data.previewPageUrl || data.previewUrl, '_blank', 'noopener');
    });

    document.getElementById('certificate-designer-background').addEventListener('change', async (event) => {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }
        const form = new FormData();
        form.append('background', file);
        setStatus('جارٍ رفع الخلفية…');
        const response = await fetch(data.backgroundUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: form,
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            setStatus(firstError(body) || 'تعذر رفع الخلفية.', 'error');
            return;
        }
        canvas.style.backgroundImage = `url("${body.backgroundUrl}")`;
        setStatus(body.message || 'تم رفع خلفية الشهادة.', 'ok');
    });
}

function firstError(body) {
    const errors = body.errors || {};
    const first = Object.values(errors)[0];
    return Array.isArray(first) ? first[0] : body.message;
}

document.addEventListener('DOMContentLoaded', boot);

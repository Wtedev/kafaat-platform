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
    if (data.backgroundUrlImage) {
        canvas.style.backgroundImage = `url("${data.backgroundUrlImage}")`;
    }

    const markDirty = () => {
        dirty = true;
        setStatus('');
    };

    const setStatus = (message, kind = '') => {
        status.textContent = message;
        status.className = 'certificate-designer__status' + (kind ? ` is-${kind}` : '');
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

    const textFor = (key) => {
        if (key === 'hours') {
            const value = hours.value.trim();
            // Match the PDF: 12.00 shows as 12 and 12.50 as 12.5.
            return value !== '' && Number.isFinite(Number(value)) ? String(Number(value)) : value;
        }

        return data.samples[key] || '';
    };

    const fontFamily = (font) => (font === 'ibmplex' ? '"IBM Plex Sans Arabic", sans-serif' : '"FF Shamel", "IBM Plex Sans Arabic", sans-serif');

    const fontPx = (pt) => (Number(pt) * canvas.clientWidth) / PAGE_WIDTH_PT;

    const mmToPx = (mm) => (Number(mm) * canvas.clientWidth) / 297;

    const paint = (key) => {
        const node = canvas.querySelector(`[data-key="${key}"]`);
        const element = draft[key];
        if (!node || !element) {
            return;
        }

        node.style.left = `${element.x}%`;
        node.style.top = `${element.y}%`;
        if (fieldByKey(key)?.qr) {
            const size = mmToPx(element.size);
            node.style.width = `${size}px`;
            node.style.height = `${size}px`;
            return;
        }

        node.style.width = `${element.width}%`;
        node.style.fontFamily = fontFamily(element.font);
        node.style.fontWeight = element.weight === 'bold' ? '700' : '400';
        node.style.fontSize = `${fontPx(element.size)}px`;
        node.style.color = element.color;
        node.style.textAlign = element.align;
        const span = node.querySelector('span');
        span.textContent = textFor(key);
        // Match the PDF: text that is wider than its box shrinks to stay on one line.
        const available = node.clientWidth;
        const needed = span.scrollWidth;
        if (available > 0 && needed > available) {
            node.style.fontSize = `${(fontPx(element.size) * available) / needed}px`;
        }
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
            weightInput.value = element.weight;
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
        element.font = fontInput.value;
        refreshSelected();
    });

    weightInput.addEventListener('change', () => {
        const element = current();
        if (!element || fieldByKey(selected)?.qr) {
            return;
        }
        element.weight = weightInput.value;
        refreshSelected();
    });

    sizeInput.addEventListener('input', () => {
        const element = current();
        if (!element) {
            return;
        }
        element.size = Number(sizeInput.value);
        refreshSelected();
    });

    colorInput.addEventListener('input', () => {
        const element = current();
        if (!element || fieldByKey(selected)?.qr) {
            return;
        }
        element.color = colorInput.value;
        hexInput.value = colorInput.value;
        refreshSelected();
    });

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
        element.align = button.dataset.align;
        select(selected);
        refreshSelected();
    });

    widthInput.addEventListener('input', () => {
        const element = current();
        if (!element || fieldByKey(selected)?.qr) {
            return;
        }
        element.width = Number(widthInput.value);
        refreshSelected();
    });

    hours.addEventListener('input', () => {
        if (enabled.has('hours')) {
            paint('hours');
        }
        markDirty();
    });

    window.addEventListener('pointermove', (event) => {
        if (!drag || event.pointerId !== drag.pointerId) {
            return;
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

    window.addEventListener('keydown', (event) => {
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
        element[move[0]] = Math.min(100, Math.max(0, Math.round((element[move[0]] + move[1]) * 100) / 100));
        paint(selected);
        showGuides(element);
        markDirty();
    });

    window.addEventListener('resize', paintAll);

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
            elements[key] = draft[key];
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

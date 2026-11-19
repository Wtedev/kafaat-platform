{{-- Behaviour for every program form on the page (wizard step or settings section): cover preview, presenter/topic rows, capacity fields, live acceptance preview. --}}
<template id="sui-presenter-template">
    <div class="sui-wizard-repeat" data-sui-row="presenter">
        <div class="sui-form-grid">
            <label class="sui-field"><span class="sui-field__label">الاسم</span><input class="sui-control" data-field="name"></label>
            <label class="sui-field"><span class="sui-field__label">الصفة</span><input class="sui-control" data-field="role"></label>
        </div>
        <button type="button" class="sui-btn sui-btn--secondary sui-btn--sm" data-sui-remove>حذف</button>
    </div>
</template>
<template id="sui-topic-template">
    <div class="sui-wizard-repeat" data-sui-row="topic">
        <div class="sui-form-grid">
            <label class="sui-field"><span class="sui-field__label">المحور</span><input class="sui-control" data-field="title"></label>
            <label class="sui-field"><span class="sui-field__label">المسؤولون</span><input class="sui-control" data-field="facilitators"></label>
        </div>
        <button type="button" class="sui-btn sui-btn--secondary sui-btn--sm" data-sui-remove>حذف</button>
    </div>
</template>
<script>
    document.querySelectorAll('[data-sui-program-form]').forEach((form) => {
        form.querySelector('[data-sui-cover]')?.addEventListener('change', (event) => {
            const file = event.target.files?.[0];
            const preview = form.querySelector('[data-sui-cover-preview]');
            const name = form.querySelector('[data-sui-cover-name]');
            if (!file || !preview) return;
            preview.hidden = false;
            preview.src = URL.createObjectURL(file);
            if (name) name.textContent = file.name;
        });

        function reindexRows(kind, prefix) {
            form.querySelectorAll(`[data-sui-row="${kind}"]`).forEach((row, index) => {
                row.querySelectorAll('[data-field], [name]').forEach((input) => {
                    const field = input.dataset.field || (input.name?.match(/\[(\w+)\]$/) || [])[1];
                    if (field) input.name = `${prefix}[${index}][${field}]`;
                });
            });
        }

        form.querySelectorAll('[data-sui-add]').forEach((button) => {
            button.addEventListener('click', () => {
                const kind = button.dataset.suiAdd;
                const template = document.getElementById(kind === 'presenter' ? 'sui-presenter-template' : 'sui-topic-template');
                form.querySelector(`[data-sui-rows="${kind}"]`)?.append(template.content.cloneNode(true));
                reindexRows('presenter', 'program_presenters');
                reindexRows('topic', 'session_topics');
            });
        });

        form.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-sui-remove]');
            if (!remove) return;
            remove.closest('[data-sui-row]')?.remove();
            reindexRows('presenter', 'program_presenters');
            reindexRows('topic', 'session_topics');
        });

        const topicsBox = form.querySelector('[data-sui-topics]');
        form.querySelector('[name="session_topics_enabled"]')?.addEventListener('change', (event) => {
            if (topicsBox) topicsBox.hidden = !event.target.checked;
        });

        function syncCapacityFields() {
            const modeInput = form.querySelector('[name="capacity_mode"]');
            if (!modeInput) return;
            const mode = modeInput.value || 'unlimited';
            const genders = [...form.querySelectorAll('[name="acceptance_genders[]"]:checked')].map((input) => input.value);
            const showMale = genders.length === 0 || genders.includes('male');
            const showFemale = genders.length === 0 || genders.includes('female');
            const shared = form.querySelector('[data-sui-capacity="shared"]');
            const male = form.querySelector('[data-sui-capacity="male"]');
            const female = form.querySelector('[data-sui-capacity="female"]');
            if (shared) shared.hidden = mode !== 'shared';
            if (male) male.hidden = mode !== 'per_gender' || !showMale;
            if (female) female.hidden = mode !== 'per_gender' || !showFemale;
            [shared, male, female].forEach((box) => {
                box?.querySelectorAll('input').forEach((input) => {
                    input.disabled = box.hidden;
                });
            });
        }

        form.querySelector('[name="capacity_mode"]')?.addEventListener('change', syncCapacityFields);
        form.querySelectorAll('[name="acceptance_genders[]"]').forEach((input) => {
            input.addEventListener('change', syncCapacityFields);
        });
        syncCapacityFields();

        const previewList = form.querySelector('[data-sui-acceptance-preview]');
        const previewUrl = form.dataset.suiAcceptanceUrl;
        let previewTimer = 0;
        async function refreshAcceptancePreview() {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            if (!response.ok) return;
            const payload = await response.json();
            previewList.replaceChildren(...(payload.lines || []).map((line) => {
                const item = document.createElement('li');
                item.textContent = line;
                return item;
            }));
        }
        function scheduleAcceptancePreview() {
            window.clearTimeout(previewTimer);
            previewTimer = window.setTimeout(refreshAcceptancePreview, 150);
        }
        if (previewList && previewUrl) {
            form.addEventListener('input', scheduleAcceptancePreview);
            form.addEventListener('change', scheduleAcceptancePreview);
            refreshAcceptancePreview();
        }
    });
</script>

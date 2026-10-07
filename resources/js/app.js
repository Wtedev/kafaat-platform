document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-identity-number-input]').forEach((input) => {
        const hint =
            input.parentElement?.querySelector('[data-identity-category-hint]') ??
            document.querySelector('[data-identity-category-hint]');

        if (!hint) {
            return;
        }

        const saudi = hint.getAttribute('data-label-saudi') || 'هوية وطنية';
        const resident = hint.getAttribute('data-label-resident') || 'إقامة';

        const refresh = () => {
            const digits = String(input.value || '').replace(/\D+/g, '');
            const first = digits.charAt(0);

            if (first === '1') {
                hint.textContent = saudi;
                hint.classList.remove('hidden');
            } else if (first === '2') {
                hint.textContent = resident;
                hint.classList.remove('hidden');
            } else {
                hint.textContent = '';
                hint.classList.add('hidden');
            }
        };

        input.addEventListener('input', refresh);
        refresh();
    });
});

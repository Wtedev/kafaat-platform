@props([
    'form',
    'label' => 'حفظ التعديلات',
    'spacer' => true,
])

<x-public.mobile-sticky-action-bar :spacer="$spacer">
    <button
        type="submit"
        form="{{ $form }}"
        {{ $attributes->merge([
            'class' => 'w-full rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-md transition hover:opacity-95 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#335483]',
            'style' => 'background:linear-gradient(135deg,#335483 0%,#264368 100%)',
        ]) }}
    >
        {{ $label }}
    </button>
</x-public.mobile-sticky-action-bar>

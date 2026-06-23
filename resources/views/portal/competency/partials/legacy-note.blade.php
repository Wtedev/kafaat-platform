@if (filled($text ?? null))
<div class="mb-3 rounded-xl border border-[#f5dfa8] bg-[#fef6e6]/90 px-3 py-2 text-xs text-brand">
    <strong>نص قديم محفوظ:</strong> يمكنك نقله إلى الحقول المنظمة أدناه ثم حفظ القسم. سيظهر في التصدير حتى يتم استبداله.
    <p class="mt-1 whitespace-pre-wrap text-gray-800">{{ $text }}</p>
</div>
@endif

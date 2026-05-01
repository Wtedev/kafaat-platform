@if (filled($text ?? null))
<div class="mb-3 rounded-xl border border-amber-200 bg-amber-50/90 px-3 py-2 text-xs text-amber-900">
    <strong>نص قديم محفوظ:</strong> يمكنك نقله إلى الحقول المنظمة أدناه ثم حفظ القسم. سيظهر في التصدير حتى يتم استبداله.
    <p class="mt-1 whitespace-pre-wrap text-gray-800">{{ $text }}</p>
</div>
@endif

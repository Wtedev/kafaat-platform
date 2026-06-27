@if ($showCandidatePoolPrompt ?? false)
<div id="candidate-pool-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
    <div class="max-w-lg w-full rounded-2xl bg-white p-6 shadow-xl text-right">
        <h2 class="text-lg font-bold text-gray-900 mb-3">قاعدة المرشحين الداخلية</h2>
        <p class="text-sm text-gray-700 leading-relaxed mb-4">{{ $candidatePoolConsentText ?? '' }}</p>
        <form method="POST" action="{{ route('portal.candidate-pool.grant') }}" class="mb-2">
            @csrf
            <button type="submit" class="w-full rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white">أرغب بالانضمام</button>
        </form>
        <form method="POST" action="{{ route('portal.candidate-pool.decline') }}" class="mb-2">
            @csrf
            <button type="submit" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700">لا أرغب</button>
        </form>
        <form method="POST" action="{{ route('portal.candidate-pool.prompted') }}">
            @csrf
            <button type="submit" class="w-full text-sm text-gray-500 py-2">إغلاق</button>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    fetch('{{ route('portal.candidate-pool.prompted') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
    }).catch(function () {});
});
</script>
@endif

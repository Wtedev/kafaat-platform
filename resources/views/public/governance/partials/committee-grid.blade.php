@props([
    'committees',
])

@if ($committees->isEmpty())
<div class="py-20 text-center">
    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl" style="background:#e9eff6">
        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
    </div>
    <h3 class="mb-1 text-lg font-semibold" style="color:#374151">لم يتم إضافة اللجان الدائمة بعد</h3>
    <p class="text-sm" style="color:#9CA3AF">سيتم إضافة المحتوى قريباً.</p>
</div>
@else
<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    @foreach ($committees as $committee)
    <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm text-right">
        <h3 class="text-base font-bold" style="color:#111827">{{ $committee->name }}</h3>
        <p class="mt-3 mb-2 text-xs font-semibold" style="color:#9CA3AF">أعضاء اللجنة</p>
        @if ($committee->activeMembers->isEmpty())
        <p class="text-sm" style="color:#6B7280">—</p>
        @else
        <ul class="space-y-2">
            @foreach ($committee->activeMembers as $member)
            <li class="flex items-center gap-2.5 rounded-xl bg-[#F8FAFC] px-3.5 py-2.5 text-sm font-medium" style="color:#374151">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold" style="background:#e9eff6; color:#335483">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </span>
                {{ $member->name }}
            </li>
            @endforeach
        </ul>
        @endif
    </div>
    @endforeach
</div>
@endif

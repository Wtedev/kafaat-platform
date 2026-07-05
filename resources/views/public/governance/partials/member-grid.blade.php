@props([
    'members',
    'emptyTitle' => 'لم يتم إضافة الأعضاء بعد',
])

@if ($members->isEmpty())
<div class="text-center py-20">
    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl" style="background:#e9eff6">
        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    </div>
    <h3 class="mb-1 text-lg font-semibold" style="color:#374151">{{ $emptyTitle }}</h3>
    <p class="text-sm" style="color:#9CA3AF">سيتم إضافة المحتوى قريباً.</p>
</div>
@else
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    @foreach ($members as $member)
    <div class="member-card flex flex-col items-center rounded-2xl border border-gray-100 bg-white p-6 text-center shadow-sm">
        @if ($member->photoPublicUrl())
        <img src="{{ $member->photoPublicUrl() }}"
             alt="{{ $member->name }}"
             class="mb-4 h-20 w-20 rounded-full border-2 border-gray-100 object-cover shadow-sm" />
        @else
        <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full border-2 border-gray-100 shadow-sm" style="background:#e9eff6">
            <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="#335483"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </div>
        @endif

        <h3 class="mb-1 text-base font-bold" style="color:#111827">{{ $member->name }}</h3>
        @if ($member->role)
        <p class="mb-3 rounded-full px-3 py-1 text-xs font-medium" style="background:#e9eff6; color:#335483">{{ $member->role }}</p>
        @endif
        @if ($member->bio)
        <p class="text-sm leading-relaxed" style="color:#6B7280">{{ Str::limit($member->bio, 140) }}</p>
        @endif
    </div>
    @endforeach
</div>
@endif

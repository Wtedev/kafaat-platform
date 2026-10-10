<div class="mx-auto max-w-xl rounded-2xl border border-gray-100 bg-white p-6 text-right" wire:poll.3s>
    <p class="text-sm text-gray-500">{{ $programTitle }}</p>
    <h1 class="mt-2 text-2xl font-bold text-[#335483]">{{ $link->name }}</h1>

    @if ($link->isCancelled())
        <p class="mt-6 text-sm font-semibold text-red-700">رابط التحضير ملغى.</p>
    @else
        <div class="mt-6 flex flex-wrap items-center gap-3">
            @if ($link->isOpen())
                <p class="text-sm font-semibold text-[#335483]" x-data="{ seconds: {{ $link->remainingSeconds() }} }" x-init="setInterval(() => { if (seconds > 0) seconds-- }, 1000)">
                    الوقت المتبقي
                    <span class="tabular-nums" x-text="Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0')">{{ $link->remainingLabel() }}</span>
                </p>
                <button type="button" wire:click="closeWindow" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold">إغلاق</button>
            @else
                <button type="button" wire:click="openWindow" class="rounded-xl bg-[#335483] px-4 py-2 text-sm font-semibold text-white">فتح التحضير</button>
            @endif
        </div>

        @if ($notice)
            <p class="mt-4 text-sm text-red-700">{{ $notice }}</p>
        @endif

        <div class="mt-6 flex gap-2">
            <button type="button" wire:click="$set('tab', 'live')" class="rounded-xl px-4 py-2 text-sm font-semibold {{ $tab === 'live' ? 'bg-[#335483] text-white' : 'border border-gray-300' }}">مباشر</button>
            <button type="button" wire:click="$set('tab', 'manual')" class="rounded-xl px-4 py-2 text-sm font-semibold {{ $tab === 'manual' ? 'bg-[#335483] text-white' : 'border border-gray-300' }}">يدوي</button>
        </div>

        @if ($tab === 'live')
            <p class="mt-6 text-sm text-gray-600">الحاضرون {{ $presentCount }} من {{ $approvedCount }}</p>
            <ul class="mt-3 space-y-2">
                @forelse ($recentNames as $name)
                    <li class="rounded-xl bg-[#e9eff6] px-4 py-3 text-base font-semibold text-[#335483]">{{ $name }}</li>
                @empty
                    <li class="text-sm text-gray-500">لا يظهر اسم الآن.</li>
                @endforelse
            </ul>
        @else
            <label class="mt-6 block text-sm font-semibold" for="attendance-search">بحث</label>
            <input id="attendance-search" type="search" wire:model.live.debounce.300ms="search" class="mt-2 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
            <ul class="mt-4 space-y-2">
                @foreach ($approved as $registration)
                    <li class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 px-4 py-3">
                        <span class="font-semibold text-[#335483]">{{ $registration->user?->fullName() }}</span>
                        @if ($markedIds->contains($registration->id))
                            <span class="text-sm text-gray-500">حاضر</span>
                        @else
                            <button type="button" wire:click="mark({{ $registration->id }})" class="rounded-xl bg-[#335483] px-3 py-1.5 text-sm font-semibold text-white">حاضر</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>

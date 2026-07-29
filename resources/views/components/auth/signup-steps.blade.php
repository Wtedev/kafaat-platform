@props([
    'current' => 1,
])

@php
    $steps = [
        1 => 'بيانات الحساب',
        2 => 'التحقق من البريد',
        3 => 'تم إنشاء الحساب',
    ];
@endphp

<nav aria-label="خطوات إنشاء الحساب" class="mb-6">
    <ol class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
        @foreach ($steps as $number => $label)
            @php
                $isCurrent = (int) $current === $number;
                $isDone = (int) $current > $number;
            @endphp
            <li class="flex min-w-0 flex-1 items-center gap-2 {{ $isCurrent ? 'text-brand' : ($isDone ? 'text-brand' : 'text-gray-400') }}">
                <span @class([
                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                    'bg-brand text-white' => $isCurrent || $isDone,
                    'bg-gray-100 text-gray-500' => ! $isCurrent && ! $isDone,
                ])>
                    @if ($isDone)
                        ✓
                    @else
                        {{ $number }}
                    @endif
                </span>
                <span @class([
                    'truncate text-xs sm:text-sm',
                    'font-bold' => $isCurrent,
                    'font-medium' => ! $isCurrent,
                ])>{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</nav>

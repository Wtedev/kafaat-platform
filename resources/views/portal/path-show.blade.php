@php
use App\Enums\RegistrationStatus;

$statusLabels = [
RegistrationStatus::Pending->value => 'قيد المراجعة',
RegistrationStatus::Approved->value => 'مقبول',
RegistrationStatus::Rejected->value => 'مرفوض',
RegistrationStatus::Cancelled->value => 'ملغي',
RegistrationStatus::Completed->value => 'مكتمل',
];
$statusColors = [
RegistrationStatus::Pending->value => 'bg-yellow-100 text-yellow-700',
RegistrationStatus::Approved->value => 'bg-green-100 text-green-700',
RegistrationStatus::Rejected->value => 'bg-red-100 text-red-700',
RegistrationStatus::Cancelled->value => 'bg-gray-100 text-gray-600',
RegistrationStatus::Completed->value => 'bg-blue-100 text-blue-700',
];

$pathSv = $registration->status->value;
@endphp

@extends('layouts.portal')
@section('title', $learningPath->title)
@section('content')

<div class="mb-6">
    <a href="{{ route('portal.paths') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800">
        ← مساراتي
    </a>
</div>

<h1 class="mb-2 text-2xl font-bold text-gray-900">{{ $learningPath->title }}</h1>

<div class="flex flex-wrap items-center gap-3 mb-6">
    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$pathSv] ?? 'bg-gray-100 text-gray-600' }}">
        {{ $statusLabels[$pathSv] ?? $pathSv }}
    </span>
    @if ($pathProgress !== null)
    <span class="text-sm text-gray-500">التقدّم الإجمالي: {{ number_format($pathProgress, 0) }}%</span>
    @endif
</div>

@if (! $registration->canAccessPathPrograms())
<div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    بانتظار قبول تسجيلك في المسار لعرض تفاصيل التقدّم والبرامج بشكل كامل.
</div>
@endif

<div class="space-y-4">
    <h2 class="text-lg font-semibold text-gray-800">البرامج في المسار</h2>

    @forelse ($programRows as $row)
    @php
    $program = $row['program'];
    $reg = $row['registration'];
    $pct = (float) $row['progress'];
    $kind = $program->program_kind;
    @endphp
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-800">
                        {{ $kind->label() }}
                    </span>
                    @if ($reg)
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$reg->status->value] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusLabels[$reg->status->value] ?? $reg->status->value }}
                    </span>
                    @else
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">غير مسجل</span>
                    @endif
                </div>
                <h3 class="font-semibold text-gray-900">{{ $program->title }}</h3>
                @if ($registration->canAccessPathPrograms())
                <div class="mt-3 flex items-center gap-2">
                    <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden max-w-xs">
                        <div class="h-2 rounded-full bg-indigo-500 transition-all" style="width: {{ min(100, max(0, $pct)) }}%"></div>
                    </div>
                    <span class="text-xs text-gray-500">{{ number_format($pct, 0) }}%</span>
                </div>
                @endif
            </div>
            <div class="flex-shrink-0">
                <a href="{{ route('public.programs.show', $program) }}" class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#253B5B">
                    عرض البرنامج
                </a>
            </div>
        </div>
    </div>
    @empty
    <p class="text-sm text-gray-500">لا توجد برامج منشورة مرتبطة بهذا المسار بعد.</p>
    @endforelse
</div>

@endsection

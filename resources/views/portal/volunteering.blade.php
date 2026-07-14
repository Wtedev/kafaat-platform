@php
use App\Enums\RegistrationStatus;

$statusColors = RegistrationStatus::badgeClasses();

$statusLabels = [
RegistrationStatus::Pending->value => 'قيد المراجعة',
RegistrationStatus::Approved->value => 'مقبول',
RegistrationStatus::Rejected->value => 'مرفوض',
RegistrationStatus::Cancelled->value => 'ملغي',
RegistrationStatus::Completed->value => 'مكتمل',
];
@endphp

@extends('layouts.portal')
@section('title', 'الفرص التطوعية')
@section('content')
<h1 class="text-2xl font-bold text-gray-900 mb-6">الفرص التطوعية</h1>

@if ($registrations->isEmpty())
<x-portal.empty-state
    title="لا توجد تسجيلات تطوعية"
    description="لم تسجّل في أي فرصة تطوعية بعد. استعرض الفرص المنشورة واختر ما يناسبك."
>
    <a href="{{ route('public.volunteering.index') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-95" style="background:#335483">استكشف الفرص التطوعية</a>
    <a href="{{ route('portal.competency') }}" class="inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 transition hover:bg-gray-50">طوّر صفحة الكفاءة</a>
</x-portal.empty-state>
@else
<div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <x-portal.card-header variant="bar" />
    <table class="w-full text-sm">
        <thead class="bg-[#e9eff6] text-xs text-[#335483]">
            <tr>
                <th class="px-5 py-3 text-right font-semibold">الفرصة</th>
                <th class="px-5 py-3 text-center font-semibold">الحالة</th>
                <th class="px-5 py-3 text-center font-semibold">الساعات</th>
                <th class="px-5 py-3 text-center font-semibold">التقدم</th>
                <th class="px-5 py-3 text-center font-semibold">شهادة التطوع</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach ($registrations as $reg)
            @php
            $sv = $reg->status->value;
            $approvedHours = (float) $reg->approved_hours;
            $required = (float) optional($reg->opportunity)->hours_expected;
            $pct = ($required > 0) ? min(100, ($approvedHours / $required) * 100) : 0;
            @endphp
            <tr class="hover:bg-gray-50 transition">
                <td class="px-5 py-4 font-medium text-gray-800">
                    {{ optional($reg->opportunity)->title ?? '—' }}
                </td>
                <td class="px-5 py-4 text-center">
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $statusColors[$sv] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $statusLabels[$sv] ?? $sv }}
                    </span>
                </td>
                <td class="px-5 py-4 text-center text-gray-600">
                    {{ number_format($approvedHours, 1) }} / {{ $required > 0 ? number_format($required, 1) : '—' }} ساعة
                </td>
                <td class="px-5 py-4 text-center">
                    @if ($required > 0)
                    <div class="flex items-center gap-2 justify-center">
                        <div class="w-20 bg-gray-200 rounded-full h-2 overflow-hidden">
                            <div class="bg-brand-secondary h-2 rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="text-xs text-gray-500">{{ number_format($pct, 0) }}%</span>
                    </div>
                    @else
                    <span class="text-xs text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-center">
                    @if ($sv === \App\Enums\RegistrationStatus::Completed->value)
                    <a href="{{ route('portal.certificates') }}" class="text-xs text-brand hover:underline font-medium">
                        عرض الشهادة ←
                    </a>
                    @else
                    <span class="text-xs text-gray-400">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if ($registrations->hasPages())
    <div class="px-5 py-4 border-t border-gray-100">
        {{ $registrations->links() }}
    </div>
    @endif
</div>
@endif
@endsection

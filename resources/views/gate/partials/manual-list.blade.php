@php
    use App\Enums\AttendanceStatus;
@endphp
<div class="overflow-hidden rounded-2xl border border-gray-100 bg-white">
    <table class="w-full table-fixed text-[11px] sm:text-sm" id="manual-list">
        <thead class="bg-[#e9eff6] text-[10px] text-[#335483] sm:text-xs">
            <tr>
                <th scope="col" class="px-2 py-2 text-right font-semibold sm:px-4 sm:py-3">الاسم</th>
                <th scope="col" class="w-24 px-1.5 py-2 text-center font-semibold sm:w-28 sm:px-3 sm:py-3">الإجراء</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($registrations as $registration)
                @php
                    $user = $registration->user;
                    $fullName = $user?->fullName() ?: ($user?->name ?? '—');
                    $isPresent = $registration->attendanceRecords
                        ->contains(fn ($row) => $row->status === AttendanceStatus::Present);
                @endphp
                <tr
                    class="hover:bg-gray-50/80 transition"
                    data-registration-id="{{ $registration->id }}"
                >
                    <td class="min-w-0 px-2 py-2 text-right align-middle font-semibold text-gray-900 sm:px-4 sm:py-3">
                        <span class="block break-words leading-snug" title="{{ $fullName }}">{{ $fullName }}</span>
                    </td>
                    <td class="px-1.5 py-2 text-center align-middle sm:px-3 sm:py-3">
                        <button
                            type="button"
                            class="prep-mark rounded-md border px-2.5 py-1 text-[10px] font-semibold whitespace-nowrap transition sm:rounded-lg sm:px-3.5 sm:py-1.5 sm:text-xs {{ $isPresent ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-transparent text-gray-700 border-gray-300 hover:bg-gray-50' }}"
                            data-present="{{ $isPresent ? '1' : '0' }}"
                            aria-label="تحضير {{ $fullName }}"
                            @disabled(! $isPrepDayToday)
                        >{{ $isPresent ? 'حاضر' : 'تحضير' }}</button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="px-4 py-8 text-center text-sm text-gray-500">
                        @if ($search !== '')
                            لا توجد نتائج مطابقة للبحث.
                        @else
                            لا يوجد مسجلون مقبولون لهذا البرنامج.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div id="manual-pagination" class="mt-4 w-full max-w-full overflow-hidden {{ $registrations && $registrations->hasPages() ? '' : 'hidden' }}">
    @if ($registrations && $registrations->hasPages())
        {{ $registrations->links('gate.pagination') }}
    @endif
</div>

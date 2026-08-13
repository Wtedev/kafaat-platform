@php
    use App\Enums\AttendanceStatus;
@endphp
<div class="overflow-hidden rounded-2xl border border-gray-100 bg-white">
    <table class="w-full table-fixed text-[11px] sm:text-sm" id="manual-list">
        <thead class="bg-[#e9eff6] text-[10px] text-[#335483] sm:text-xs">
            <tr>
                <th scope="col" class="px-2 py-2 text-right font-semibold sm:px-4 sm:py-3">الاسم</th>
                <th scope="col" class="w-28 px-1.5 py-2 text-center font-semibold sm:w-36 sm:px-3 sm:py-3">الإجراء</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($registrations as $registration)
                @php
                    $user = $registration->user;
                    $fullName = $user?->fullName() ?: ($user?->name ?? '—');
                    $dayRecord = $registration->attendanceRecords
                        ->first(fn ($row) => $row->status === AttendanceStatus::Present);
                    $isPresent = $dayRecord !== null;
                    $internalNote = filled(trim((string) ($dayRecord?->internal_notes ?? '')))
                        ? trim((string) $dayRecord->internal_notes)
                        : '';
                @endphp
                <tr
                    class="hover:bg-gray-50/80 transition"
                    data-registration-id="{{ $registration->id }}"
                    data-internal-note="{{ e($internalNote) }}"
                >
                    <td class="min-w-0 px-2 py-2 text-right align-middle font-semibold text-gray-900 sm:px-4 sm:py-3">
                        <span class="block break-words leading-snug" title="{{ $fullName }}">{{ $fullName }}</span>
                        <p
                            class="internal-note-preview mt-1 text-[10px] font-normal leading-snug text-amber-800/90 sm:text-xs {{ $internalNote === '' ? 'hidden' : '' }}"
                            title="ملاحظة داخلية"
                        >
                            {{ $internalNote }}
                        </p>
                    </td>
                    <td class="px-1.5 py-2 text-center align-middle sm:px-3 sm:py-3">
                        <div class="inline-flex flex-col items-stretch gap-1 sm:flex-row sm:items-center sm:justify-center">
                            <button
                                type="button"
                                class="prep-mark rounded-md border px-2.5 py-1 text-[10px] font-semibold whitespace-nowrap transition sm:rounded-lg sm:px-3.5 sm:py-1.5 sm:text-xs {{ $isPresent ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-transparent text-gray-700 border-gray-300 hover:bg-gray-50' }}"
                                data-present="{{ $isPresent ? '1' : '0' }}"
                                data-name="{{ $fullName }}"
                                aria-label="{{ $isPresent ? 'إلغاء حضور '.$fullName : 'تحضير '.$fullName }}"
                                @disabled(! $isPrepDayToday)
                            >{{ $isPresent ? 'حاضر' : 'تحضير' }}</button>
                            <button
                                type="button"
                                class="prep-note rounded-md border px-2 py-1 text-[10px] font-semibold whitespace-nowrap transition sm:rounded-lg sm:px-2.5 sm:py-1.5 sm:text-xs {{ $internalNote !== '' ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50' }} {{ $isPresent ? '' : 'hidden' }}"
                                data-name="{{ $fullName }}"
                                aria-label="ملاحظة داخلية لـ {{ $fullName }}"
                                @disabled(! $isPrepDayToday)
                            >ملاحظة</button>
                        </div>
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

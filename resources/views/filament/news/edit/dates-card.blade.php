@props([
    'record' => null,
])
@php
    /** @var \App\Models\News $record */
    $scheduled = $record->isScheduled();
    $scheduledFull = $scheduled ? $record->adminFullDateTime($record->published_at) : null;
@endphp
<div class="news-edit-card news-edit-dates-card">
    <div class="news-edit-card-header">التواريخ والنشر</div>
    <div class="news-edit-card-body">
        <table class="news-edit-dates-table">
            <tbody>
                <tr>
                    <td>تاريخ الإنشاء</td>
                    <td title="{{ $record->adminFullDateTime($record->created_at) }}">{{ $record->adminRelativeTime($record->created_at) }}</td>
                </tr>
                <tr>
                    <td>آخر تعديل</td>
                    <td title="{{ $record->updated_at ? $record->adminFullDateTime($record->updated_at) : '' }}">{{ $record->adminRelativeTime($record->updated_at) }}</td>
                </tr>
                <tr>
                    <td>تاريخ النشر</td>
                    <td>
                        @if($record->isPublished())
                            <span title="{{ $record->adminFullDateTime($record->published_at) }}">{{ $record->adminFullDateTime($record->published_at) }}</span>
                        @else
                            <span class="text-zinc-500">—</span>
                        @endif
                    </td>
                </tr>
                @if($scheduled)
                    <tr>
                        <td>موعد النشر المجدول</td>
                        <td title="{{ $scheduledFull }}">{{ $scheduledFull }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

{{-- Mobile/desktop approximation for Filament preview — sanitized HTML only. --}}
@php
    $name = $beneficiaryName ?? 'المستفيد';
    $program = $programTitle ?? 'البرنامج';
    $subjectLine = $subject ?? '';
    $body = $contentHtml ?? '';
@endphp
<div class="program-broadcast-preview" dir="rtl" style="font-family: Tahoma, Arial, sans-serif; color: #18181b;">
    <div style="margin-bottom: 0.75rem; font-size: 0.75rem; color: #71717a;">معاينة تقريبية للبريد</div>
    <div style="border: 1px solid #e4e4e7; border-radius: 0.5rem; overflow: hidden; background: #fff;">
        <div style="background: #335483; color: #fff; padding: 0.75rem 1rem; font-weight: 700;">كفاءات</div>
        <div style="padding: 1rem 1.25rem;">
            <div style="font-size: 0.8rem; color: #71717a; margin-bottom: 0.5rem;">الموضوع: {{ $subjectLine }}</div>
            <p style="margin: 0 0 0.75rem;">مرحباً {{ $name }}،</p>
            <p style="margin: 0 0 1rem; color: #3f3f46;">رسالة بخصوص البرنامج التدريبي «{{ $program }}».</p>
            <div class="prose prose-sm max-w-none" style="line-height: 1.7;">{!! $body !!}</div>
            <p style="margin: 1.25rem 0 0; color: #52525b;">مع تحيات فريق كفاءات</p>
        </div>
    </div>
</div>

@extends('public.experience-houses._shell')

@section('title', 'تم استلام طلبكم — مشروع بيوت الخبرة')
@section('compact', 'شكراً لكم')

@section('styles')
    .done { text-align: center; padding: clamp(10px, 3vw, 24px) 0; }
    .done .badge {
        width: 84px; height: 84px; margin: 0 auto 18px; display: grid; place-items: center;
        border-radius: 26px 26px 26px 8px; background: var(--teal-300); color: #fff;
    }
    .done h2 { margin: 0; color: var(--teal-600); font-size: clamp(26px, 4vw, 38px); }
    .done p { margin: 10px auto 0; color: var(--muted); max-width: 520px; font-size: 17px; }
    .ref {
        display: inline-block; margin-top: 26px; background: var(--pale); border: 1.5px dashed #a9d7d8;
        border-radius: 16px; padding: 14px 28px;
    }
    .ref small { display: block; color: var(--muted); font-size: 14px; }
    .ref b { font-size: 28px; color: var(--teal-950); letter-spacing: 1px; direction: ltr; display: inline-block; }
    .next { margin-top: 30px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; text-align: right; }
    .next div { background: var(--teal-50); border-radius: 16px; padding: 16px 18px; }
    .next b { color: var(--teal-700); display: block; }
    .next span { color: var(--ink); font-size: 14.5px; }
    @media (max-width: 680px) { .next { grid-template-columns: 1fr; } }
@endsection

@section('content')
<div class="panel">
    <div class="done">
        <div class="badge" aria-hidden="true">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
        </div>
        <h2>تم استلام طلبكم بنجاح</h2>
        <p>شكراً لثقتكم بجمعية كفاءات الأهلية. احتفظوا برقم الطلب للرجوع إليه عند التواصل معنا.</p>
        <div class="ref">
            <small>رقم الطلب</small>
            <b>{{ $reference }}</b>
        </div>
        <div class="next">
            <div><b>1. الدراسة</b><span>يراجع فريق بيوت الخبرة طلبكم ويحدد الإدارة المختصة.</span></div>
            <div><b>2. التواصل</b><span>نتواصل مع ممثل الجمعية لتأكيد التفاصيل والمواعيد.</span></div>
            <div><b>3. التنفيذ</b><span>نقدّم الدعم بالأسلوب المتفق عليه ونتابع النتائج.</span></div>
        </div>
    </div>
</div>
@endsection

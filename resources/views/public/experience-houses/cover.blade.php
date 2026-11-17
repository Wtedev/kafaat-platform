@extends('public.experience-houses._shell')
@section('title', 'طلب دعم بيوت الخبرة')

@section('hero_logos', '1')
@section('headline', 'طلب دعم بيوت الخبرة')

@section('lead')
    منصة لاستقبال طلبات الدعم من الجمعيات المستفيدة في المجالات الإدارية والفنية.
@endsection

@section('hero_extra')
    <div class="cta-row">
        <a class="cta" href="{{ route('public.experience-houses.create') }}">
            تقديم طلب
            @include('experience-houses.manage._icon', ['name' => 'arrow-left', 'size' => 24, 'stroke' => 2.2])
        </a>
        <span class="cta-note">@include('experience-houses.manage._icon', ['name' => 'clock', 'size' => 18]) حوالي دقيقة واحدة</span>
    </div>
@endsection

@section('hero_media')
    <figure class="preview" aria-label="معاينة نظام متابعة الطلبات">
        <span class="preview-badge">@include('experience-houses.manage._icon', ['name' => 'inbox', 'size' => 17]) نظام متابعة الطلبات</span>
        <div class="preview-frame">
            <div class="preview-bar"><i></i><i></i><i></i></div>
            <img src="{{ asset('images/experience-houses/system-preview.jpg') }}" alt="لقطة من نظام متابعة طلبات بيوت الخبرة" loading="eager">
        </div>
    </figure>
@endsection

@section('styles')
    .hero { padding-bottom: clamp(70px, 10vw, 110px); }
    .hero-logos { display: flex; align-items: center; gap: clamp(14px, 2.4vw, 22px); margin-bottom: clamp(20px, 3.4vw, 30px); }
    .hero-logos .kf { height: clamp(46px, 5.4vw, 62px); width: auto; display: block; }
    .hero-logos .mh { height: clamp(40px, 4.6vw, 54px); width: auto; display: block; }
    .hero-logos .sep { width: 1.5px; align-self: stretch; min-height: 44px; background: rgba(255,255,255,.6); }
    .hero-lead { margin-top: clamp(12px, 2vw, 18px) !important; }
    .hero-card { padding-top: clamp(80px, 9vw, 104px); }
    .ministry { margin-top: clamp(18px, 3vw, 28px); }
    .ministry img { height: clamp(38px, 4.4vw, 50px); }
    .ministry p { font-size: clamp(14px, 1.7vw, 18px); }
    .hero-lead { margin-top: clamp(18px, 3vw, 26px); }

    .cta-row { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; margin-top: clamp(22px, 3.5vw, 34px); }
    .cta {
        display: inline-flex; align-items: center; gap: 14px;
        background: #fff; color: var(--teal-700); font-weight: 700; font-size: clamp(18px, 2.2vw, 21px);
        padding: 15px 30px; border-radius: 18px 18px 18px 5px; text-decoration: none;
        box-shadow: 0 18px 34px -16px rgba(8,60,65,.75); transition: transform .18s, box-shadow .18s;
    }
    .cta:hover { transform: translateY(-2px); }
    .cta svg { transition: transform .18s; }
    .cta:hover svg { transform: translateX(-4px); }
    .cta-note { display: inline-flex; align-items: center; gap: 7px; color: rgba(255,255,255,.9); font-size: 15px; }

    /* المعاينة العائمة */
    .preview { margin: 28px 0 0; position: relative; }
    .preview-frame { background: #fff; border-radius: 18px; padding-bottom: 6px; box-shadow: 0 40px 70px -30px rgba(4,30,45,.7), 0 0 0 1px rgba(255,255,255,.35); overflow: hidden; }
    .preview-bar { display: flex; gap: 6px; padding: 10px 14px; background: #f1f6f7; direction: ltr; }
    .preview-bar i { width: 10px; height: 10px; border-radius: 50%; background: #d3dfe1; }
    .preview img { display: block; width: 100%; height: auto; }
    .preview-badge { position: absolute; top: -16px; right: 18px; z-index: 2; display: inline-flex; align-items: center; gap: 7px; background: var(--teal-950); color: #fff; border-radius: 999px; padding: 7px 16px; font-size: 14px; font-weight: 700; box-shadow: 0 10px 20px -10px rgba(0,0,0,.5); }
    @media (min-width: 1000px) {
        .hero-wrap, .hero-card { max-width: 1200px; }
        .hero-card { padding-left: 47%; padding-right: 56px; }
        .headline { font-size: clamp(34px, 3.6vw, 50px); }
        .hero-lead { font-size: clamp(18px, 1.7vw, 22px); }
        .preview { position: absolute; left: -24px; top: 50%; width: 46%; margin: 0; transform: translateY(-50%) perspective(1400px) rotateY(9deg) rotate(-1.5deg); }
        .preview-frame { animation: floaty 6s ease-in-out infinite; }
    }
    @keyframes floaty { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
    @media (prefers-reduced-motion: reduce) { .preview-frame { animation: none; } }

    /* المحتوى */
    .main { margin-top: calc(-1 * clamp(40px, 6vw, 70px)); }
    .sec + .sec { margin-top: clamp(30px, 5vw, 44px); }
    .sec-h { margin: 0 0 16px; color: var(--teal-600); font-size: clamp(21px, 2.8vw, 26px); line-height: 1.3; display: flex; align-items: center; gap: 10px; }
    .sec-h::before { content: ''; width: 6px; height: 26px; border-radius: 4px; background: var(--teal-300); }

    .intro { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 720px) { .intro { grid-template-columns: 1fr; } }
    .intro-card { display: flex; gap: 14px; align-items: flex-start; background: var(--pale); border: 1px solid #dcefef; border-radius: 20px; padding: 18px; }
    .intro-card .ic { width: 48px; height: 48px; border-radius: 14px 14px 14px 4px; background: var(--teal-300); color: #fff; display: grid; place-items: center; flex: none; }
    .intro-card h3 { margin: 0 0 4px; color: var(--teal-900); font-size: 18px; }
    .intro-card p { margin: 0; font-size: 15.5px; line-height: 1.85; color: var(--ink); }

    .areas { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
    .area { display: flex; align-items: center; gap: 10px; border: 1px solid #dcefef; border-radius: 14px; padding: 10px 12px; color: var(--teal-950); font-weight: 700; font-size: 15px; background: #fff; }
    @media (max-width: 560px) { .areas { grid-template-columns: 1fr 1fr; gap: 8px; } .area { font-size: 13.5px; padding: 9px; gap: 8px; line-height: 1.4; } .area .ic { width: 30px; height: 30px; } }
    .area .ic { width: 36px; height: 36px; border-radius: 11px 11px 11px 3px; display: grid; place-items: center; background: var(--teal-50); color: var(--teal-600); flex: none; }

    .steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    @media (max-width: 720px) { .steps { grid-template-columns: 1fr; } }
    .stp { display: flex; align-items: center; gap: 12px; background: var(--pale); border-radius: 16px; padding: 14px 16px; }
    .stp .n { width: 38px; height: 38px; border-radius: 12px 12px 12px 4px; background: var(--teal-600); color: #fff; font-weight: 700; display: grid; place-items: center; flex: none; }
    .stp b { display: block; color: var(--teal-900); font-size: 16px; line-height: 1.4; }
    .stp span { color: var(--muted); font-size: 14px; }

    .final { margin-top: clamp(30px, 5vw, 44px); display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; background: linear-gradient(135deg, var(--teal-600), var(--teal-700)); color: #fff; border-radius: 22px; padding: 22px clamp(20px, 4vw, 32px); }
    .final h3 { margin: 0; font-size: clamp(19px, 2.6vw, 24px); }
    .final .cta { box-shadow: none; padding: 13px 26px; }
@endsection

@section('content')
<div class="panel">
    <section class="sec">
        <div class="intro">
            <div class="intro-card">
                <span class="ic">@include('experience-houses.manage._icon', ['name' => 'building', 'size' => 24])</span>
                <div>
                    <h3>عن المشروع</h3>
                    <p>مشروع تابع لوزارة الموارد البشرية والتنمية الاجتماعية، يهدف إلى نقل الخبرات المؤسسية إلى الجمعيات الناشئة. وتشارك فيه جمعية كفاءات الأهلية بصفتها أحد مراكز التميز المعتمدة.</p>
                </div>
            </div>
            <div class="intro-card">
                <span class="ic">@include('experience-houses.manage._icon', ['name' => 'inbox', 'size' => 24])</span>
                <div>
                    <h3>عن المنصة</h3>
                    <p>منصة مخصصة للجمعيات المستفيدة لتقديم طلبات الدعم، وتوجيهها إلى الإدارة المختصة في جمعية كفاءات الأهلية.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="sec">
        <h2 class="sec-h">مجالات الدعم</h2>
        <div class="areas">
            @foreach ($departments as $key => $label)
                @continue($key === 'other')
                <div class="area">
                    <span class="ic">@include('experience-houses.manage._icon', ['name' => $icons[$key] ?? 'dots', 'size' => 19])</span>
                    {{ $label }}
                </div>
            @endforeach
        </div>
    </section>

    <section class="sec">
        <h2 class="sec-h">مراحل الطلب</h2>
        <div class="steps">
            <div class="stp"><span class="n">1</span><span><b>تقديم الطلب</b><span>تحديد المجال ووصف الاحتياج</span></span></div>
            <div class="stp"><span class="n">2</span><span><b>دراسة الطلب</b><span>والتواصل مع ممثل الجمعية</span></span></div>
            <div class="stp"><span class="n">3</span><span><b>تقديم الدعم</b><span>وفق الأسلوب المتفق عليه</span></span></div>
        </div>
    </section>

    <div class="final">
        <h3>التقديم متاح للجمعيات المستفيدة من المشروع</h3>
        <a class="cta" href="{{ route('public.experience-houses.create') }}">
            تقديم طلب
            @include('experience-houses.manage._icon', ['name' => 'arrow-left', 'size' => 22, 'stroke' => 2.2])
        </a>
    </div>
</div>
@endsection

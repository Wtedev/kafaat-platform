@extends('public.experience-houses._shell')
@section('title', 'مشروع بيوت الخبرة')

@section('lead')
    تقدّم <strong>جمعية كفاءات الأهلية</strong> خبراتها المؤسسية للجمعيات المستفيدة،
    لتمكينها من تطبيق أفضل الممارسات وتعزيز كفاءتها
@endsection

@section('hero_extra')
    <div class="cta-row">
        <a class="cta" href="{{ route('public.experience-houses.create') }}">
            ابدأ طلب الدعم
            @include('experience-houses.manage._icon', ['name' => 'arrow-left', 'size' => 24, 'stroke' => 2.2])
        </a>
        <span class="cta-note">@include('experience-houses.manage._icon', ['name' => 'clock', 'size' => 18]) حوالي 5 دقائق</span>
    </div>
@endsection

@section('styles')
    .hero { padding-bottom: clamp(70px, 10vw, 110px); }
    .cta-row { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; margin-top: clamp(26px, 4vw, 40px); }
    .cta {
        display: inline-flex; align-items: center; gap: 14px;
        background: #fff; color: var(--teal-700); font-weight: 700; font-size: clamp(18px, 2.2vw, 22px);
        padding: 16px 30px; border-radius: 18px 18px 18px 5px; text-decoration: none;
        box-shadow: 0 18px 34px -16px rgba(8,60,65,.75); transition: transform .18s, box-shadow .18s;
    }
    .cta:hover { transform: translateY(-2px); box-shadow: 0 22px 40px -16px rgba(8,60,65,.85); }
    .cta svg { transition: transform .18s; }
    .cta:hover svg { transform: translateX(-4px); }
    .cta-note { display: inline-flex; align-items: center; gap: 7px; color: rgba(255,255,255,.9); font-size: 15px; }

    .main { margin-top: calc(-1 * clamp(40px, 6vw, 70px)); }
    .block { margin-top: 22px; }
    .block-h { display: flex; align-items: center; gap: 12px; margin: 0 0 18px; }
    .block-h .bar { width: 6px; height: 30px; border-radius: 4px; background: var(--teal-300); }
    .block-h h2 { margin: 0; color: var(--teal-600); font-size: clamp(22px, 3vw, 28px); line-height: 1.3; }

    .areas { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; }
    .area {
        display: flex; flex-direction: column; align-items: flex-start; gap: 12px;
        background: var(--pale); border: 1px solid #dcefef; border-radius: 18px; padding: 16px;
        color: var(--teal-950); font-weight: 700; font-size: 15.5px; line-height: 1.45;
    }
    .area .ic {
        width: 46px; height: 46px; border-radius: 14px 14px 14px 4px; display: grid; place-items: center;
        background: #fff; color: var(--teal-600); border: 1px solid #dcefef;
    }

    .how { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; counter-reset: s; }
    @media (max-width: 720px) { .how { grid-template-columns: 1fr; } }
    .how div { position: relative; background: #fff; border: 1px solid #dcefef; border-radius: 20px; padding: 20px 18px 18px; }
    .how .ic { width: 52px; height: 52px; border-radius: 16px 16px 16px 5px; display: grid; place-items: center; background: var(--teal-300); color: #fff; margin-bottom: 12px; }
    .how b { display: block; color: var(--teal-900); font-size: 18px; }
    .how span { color: var(--muted); font-size: 15px; }
    .how div::after { counter-increment: s; content: counter(s); position: absolute; top: 16px; left: 18px; font-size: 40px; font-weight: 700; color: var(--teal-50); line-height: 1; }

    .final { margin-top: 26px; text-align: center; background: linear-gradient(135deg, var(--teal-600), var(--teal-700)); color: #fff; border-radius: 22px; padding: clamp(24px, 4vw, 36px) 20px; }
    .final h3 { margin: 0 0 14px; font-size: clamp(20px, 2.8vw, 26px); }
    .final .cta { box-shadow: none; }
@endsection

@section('content')
<div class="panel">
    <section class="block" style="margin-top:0">
        <div class="block-h"><span class="bar"></span><h2>مجالات الدعم</h2></div>
        <div class="areas">
            @foreach ($departments as $key => $label)
                @continue($key === 'other')
                <div class="area">
                    <span class="ic">@include('experience-houses.manage._icon', ['name' => $icons[$key] ?? 'dots', 'size' => 24])</span>
                    {{ $label }}
                </div>
            @endforeach
        </div>
    </section>

    <section class="block" style="margin-top:36px">
        <div class="block-h"><span class="bar"></span><h2>كيف تتم العملية؟</h2></div>
        <div class="how">
            <div>
                <span class="ic">@include('experience-houses.manage._icon', ['name' => 'send', 'size' => 26])</span>
                <b>ارفعوا طلبكم</b>
                <span>حدّدوا الإدارة التي تحتاجون فيها الدعم وصفوا احتياجكم.</span>
            </div>
            <div>
                <span class="ic">@include('experience-houses.manage._icon', ['name' => 'search', 'size' => 26])</span>
                <b>ندرس الاحتياج</b>
                <span>يراجع فريق بيوت الخبرة الطلب ويتواصل معكم.</span>
            </div>
            <div>
                <span class="ic">@include('experience-houses.manage._icon', ['name' => 'check', 'size' => 26])</span>
                <b>نقدّم الدعم</b>
                <span>استشارة أو ورشة أو مرافقة، بالأسلوب المناسب لكم.</span>
            </div>
        </div>
    </section>

    <div class="final">
        <h3>جاهزون لنقل الخبرة إلى جمعيتكم</h3>
        <a class="cta" href="{{ route('public.experience-houses.create') }}">
            ابدأ طلب الدعم
            @include('experience-houses.manage._icon', ['name' => 'arrow-left', 'size' => 24, 'stroke' => 2.2])
        </a>
    </div>
</div>
@endsection

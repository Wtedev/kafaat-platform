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
    .about { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 22px; align-items: start; }
    @media (max-width: 760px) { .about { grid-template-columns: 1fr; } }
    .about p { margin: 0 0 12px; font-size: 17px; line-height: 1.95; color: var(--ink); }
    .about p strong { color: var(--teal-900); }
    .facts { display: grid; gap: 10px; }
    .fact { display: flex; align-items: center; gap: 12px; background: var(--pale); border: 1px solid #dcefef; border-radius: 16px; padding: 12px 14px; }
    .fact .ic { width: 42px; height: 42px; border-radius: 13px 13px 13px 4px; background: #fff; border: 1px solid #dcefef; color: var(--teal-600); display: grid; place-items: center; flex: none; }
    .fact small { display: block; color: var(--muted); font-size: 13px; line-height: 1.4; }
    .fact b { color: var(--teal-950); font-size: 15px; line-height: 1.5; }

    .system { margin-top: 36px; background: linear-gradient(160deg, var(--teal-50), #f6fdfd); border: 1px solid #d3eded; border-radius: 24px; padding: clamp(20px, 4vw, 34px); position: relative; overflow: hidden; }
    .system::before { content: ''; position: absolute; top: 0; left: 0; width: 90px; height: 70px; background: var(--teal-100); clip-path: polygon(0 0, 100% 0, 100% 18%, 30% 100%, 0 100%); border-radius: 24px 0 0 0; }
    .system .label { display: inline-flex; align-items: center; gap: 7px; background: #fff; color: var(--teal-600); border-radius: 999px; padding: 4px 14px; font-size: 13.5px; font-weight: 700; border: 1px solid #d3eded; }
    .system h2 { margin: 12px 0 8px; color: var(--teal-900); font-size: clamp(24px, 3.4vw, 32px); line-height: 1.35; }
    .system > p { margin: 0 0 22px; font-size: 17px; line-height: 1.95; max-width: 760px; }
    .feats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    @media (max-width: 760px) { .feats { grid-template-columns: 1fr; } }
    .feat { background: #fff; border-radius: 18px; padding: 16px; border: 1px solid #dcefef; }
    .feat .ic { width: 44px; height: 44px; border-radius: 13px 13px 13px 4px; background: var(--teal-300); color: #fff; display: grid; place-items: center; margin-bottom: 10px; }
    .feat b { display: block; color: var(--teal-900); font-size: 16.5px; }
    .feat span { color: var(--muted); font-size: 14.5px; line-height: 1.75; }
    .who { margin-top: 16px; display: flex; align-items: center; gap: 10px; color: var(--teal-900); font-size: 15px; background: #fff; border-radius: 14px; padding: 12px 14px; border: 1px dashed #b7dfe0; }
    .who svg { color: var(--teal-600); }
@endsection

@section('content')
<div class="panel">
    {{-- 1. التعريف بالمبادرة --}}
    <section class="block" style="margin-top:0">
        <div class="block-h"><span class="bar"></span><h2>عن مشروع بيوت الخبرة</h2></div>
        <div class="about">
            <div>
                <p><strong>بيوت الخبرة</strong> مشروع تابع لوزارة الموارد البشرية والتنمية الاجتماعية، يهدف إلى <strong>نقل الخبرات المؤسسية</strong> وتمكين المنظمات غير الربحية الناشئة من تطبيق أفضل الممارسات، بما يعزّز كفاءتها واستدامة أثرها.</p>
                <p>وتشارك <strong>جمعية كفاءات الأهلية لبناء قدرات الشباب</strong> في المشروع بصفتها أحد <strong>مراكز التميز المعتمدة</strong>، فتضع خبرتها في العمل المؤسسي بين أيدي الجمعيات المستفيدة، عبر لقاءات متخصصة في مجالات مثل التخطيط الاستراتيجي والحوكمة والإدارة المالية.</p>
            </div>
            <div class="facts">
                <div class="fact"><span class="ic">@include('experience-houses.manage._icon', ['name' => 'building', 'size' => 21])</span><span><small>الجهة المشرفة</small><b>وزارة الموارد البشرية والتنمية الاجتماعية</b></span></div>
                <div class="fact"><span class="ic">@include('experience-houses.manage._icon', ['name' => 'shield', 'size' => 21])</span><span><small>دور كفاءات</small><b>مركز تميز معتمد ضمن المشروع</b></span></div>
                <div class="fact"><span class="ic">@include('experience-houses.manage._icon', ['name' => 'users', 'size' => 21])</span><span><small>المستفيدون</small><b>الجمعيات والمنظمات غير الربحية الناشئة</b></span></div>
            </div>
        </div>
    </section>

    {{-- 2. التعريف بالنظام والغاية منه --}}
    <section class="system">
        <span class="label">@include('experience-houses.manage._icon', ['name' => 'inbox', 'size' => 16]) عن هذا النظام</span>
        <h2>نظام طلبات الدعم</h2>
        <p>هذه المنصة هي البوابة الرسمية للجمعيات المستفيدة لطلب الدعم من جمعية كفاءات ضمن مشروع بيوت الخبرة. الغاية منها أن يصلنا احتياج كل جمعية <strong>واضحاً ومكتملاً من أول مرة</strong>، فنوجّهه للإدارة المختصة ونبدأ معكم بسرعة، بدلاً من تبادل الرسائل والتفاصيل المتفرقة.</p>
        <div class="feats">
            <div class="feat">
                <span class="ic">@include('experience-houses.manage._icon', ['name' => 'clipboard', 'size' => 22])</span>
                <b>طلب واحد يجمع كل شيء</b>
                <span>بيانات الجمعية، والإدارة المطلوبة، ووصف الاحتياج في نموذج واحد.</span>
            </div>
            <div class="feat">
                <span class="ic">@include('experience-houses.manage._icon', ['name' => 'target', 'size' => 22])</span>
                <b>توجيه للمختص مباشرة</b>
                <span>يصل طلبكم لفريق بيوت الخبرة ويُحال للإدارة المناسبة لاحتياجكم.</span>
            </div>
            <div class="feat">
                <span class="ic">@include('experience-houses.manage._icon', ['name' => 'phone', 'size' => 22])</span>
                <b>رقم طلب ومتابعة</b>
                <span>تحصلون على رقم للطلب، ويتواصل معكم الفريق لترتيب الدعم.</span>
            </div>
        </div>
        <div class="who">@include('experience-houses.manage._icon', ['name' => 'check', 'size' => 20]) <span><b>من يمكنه التقديم؟</b> الجمعيات المستفيدة من مشروع بيوت الخبرة، عن طريق ممثل مخوّل من الجمعية.</span></div>
    </section>

    <section class="block" style="margin-top:36px">
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

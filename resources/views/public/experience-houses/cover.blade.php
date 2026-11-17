<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#177179">
    <title>طلب دعم بيوت الخبرة | جمعية كفاءات الأهلية</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        @font-face { font-family: 'FF Shamel'; src: url('/fonts/shamel/FFShamelFamily-SansOneBook.ttf') format('truetype'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'FF Shamel'; src: url('/fonts/shamel/FFShamelFamily-SansOneBold.ttf') format('truetype'); font-weight: 700; font-display: swap; }
        /* ألوان هوية منشور بيوت الخبرة */
        :root {
            --outer-1: #177179; --outer-2: #1f8a91; --navy: #0c3555;
            --card-1: #42bdbe; --card-2: #2f9fa1; --card-3: #24898f;
            --title: #0d4c50; --accent: #1f8a91; --pale: #f2fcfd; --ink: #2f3d42;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body { font-family: 'FF Shamel', system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif; background: var(--pale); color: var(--ink); -webkit-font-smoothing: antialiased; }

        /* الجزء العلوي: تدرّج المنشور مع الزاوية الكحلية */
        .top {
            background:
                radial-gradient(75% 85% at 100% 100%, var(--navy) 0%, rgba(12,53,85,0) 62%),
                linear-gradient(170deg, var(--outer-1) 0%, var(--outer-2) 55%, #1a7f88 100%);
            padding: clamp(22px, 4vw, 48px) clamp(14px, 4vw, 40px) clamp(150px, 20vw, 260px);
        }
        .wrap { position: relative; max-width: 1040px; margin: 0 auto; }

        /* البطاقة الفاتحة بالزاوية المقطوعة */
        .card {
            position: relative;
            background: linear-gradient(180deg, var(--card-1) 0%, var(--card-2) 55%, var(--card-3) 100%);
            border-radius: 28px;
            padding: clamp(96px, 12vw, 132px) clamp(24px, 8vw, 120px) clamp(36px, 6vw, 60px);
            clip-path: polygon(clamp(160px, 21vw, 250px) 0, 100% 0, 100% 100%, 0 100%, 0 clamp(128px, 15vw, 190px));
        }
        .tab {
            position: absolute; top: 0; left: 0; z-index: 1;
            width: clamp(136px, 18vw, 214px); height: clamp(112px, 13.5vw, 168px);
            background: linear-gradient(160deg, #4cc6c5, #3cb4b5);
            border-radius: 28px 6px 6px 6px;
            clip-path: polygon(0 0, 100% 0, 100% 18%, 30% 100%, 0 100%);
        }
        .tab::after {
            content: ''; position: absolute; top: 24%; left: 20%; width: 34%; height: 34%;
            border-top: clamp(9px, 1.15vw, 14px) solid #fff; border-left: clamp(9px, 1.15vw, 14px) solid #fff; border-top-left-radius: 4px;
        }

        .logos { display: flex; align-items: center; gap: clamp(14px, 2.4vw, 24px); margin-bottom: clamp(22px, 3.6vw, 34px); }
        .logos .kf { height: clamp(42px, 5.6vw, 64px); width: auto; display: block; }
        .logos .mh { height: clamp(38px, 5vw, 58px); width: auto; display: block; }
        .logos .sep { width: 1px; align-self: stretch; background: rgba(255,255,255,.6); }

        .kicker { margin: 0; color: var(--title); font-weight: 700; font-size: clamp(17px, 2.4vw, 28px); line-height: 1.4; }
        h1 { margin: 4px 0 0; color: var(--title); font-weight: 700; font-size: clamp(36px, 6.6vw, 76px); line-height: 1.15; letter-spacing: -.5px; }
        .desc { margin: clamp(16px, 2.6vw, 26px) 0 0; color: #fff; font-size: clamp(17px, 2.5vw, 28px); line-height: 1.6; max-width: 760px; }

        .actions { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-top: clamp(24px, 3.6vw, 36px); }
        .cta {
            display: inline-flex; align-items: center; gap: 14px; text-decoration: none; font-weight: 700;
            font-size: clamp(17px, 2vw, 21px); padding: 15px 30px; border-radius: 16px 16px 16px 5px;
            background: var(--title); color: #fff; box-shadow: 0 16px 30px -16px rgba(8,50,55,.8); transition: transform .18s, filter .18s;
        }
        .cta:hover { transform: translateY(-2px); filter: brightness(1.12); }
        .cta svg { transition: transform .18s; }
        .cta:hover svg { transform: translateX(-4px); }
        .note { display: inline-flex; align-items: center; gap: 7px; color: rgba(255,255,255,.92); font-size: 15px; }
        .actions.below { display: none; justify-content: center; }
        .actions.below .note { color: #5f7479; }
        .actions.below .cta { background: var(--accent); }

        /* المعاينة: تعبر من الأخضر إلى الفاتح مثل صور المنشور */
        .bottom { padding: 0 clamp(14px, 4vw, 40px); }
        .preview { position: relative; max-width: 860px; margin: calc(-1 * clamp(130px, 18vw, 230px)) auto 0; }
        .frame { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 30px 60px -30px rgba(8,50,55,.55), 0 0 0 1px rgba(13,76,80,.08); }
        .bar { display: flex; gap: 6px; padding: 10px 14px; background: #eef5f6; direction: ltr; }
        .bar i { width: 10px; height: 10px; border-radius: 50%; background: #cfdcde; }
        .frame img { display: block; width: 100%; height: auto; }
        .label { margin: clamp(22px, 3vw, 30px) auto 0; max-width: 860px; }
        .label b { display: block; color: var(--accent); font-size: clamp(20px, 2.6vw, 28px); }
        .label p { margin: 4px 0 0; font-size: clamp(16px, 2vw, 22px); line-height: 1.7; color: #4b5b60; }

        /* التذييل كما في المنشور */
        .foot { max-width: 860px; margin: clamp(30px, 5vw, 50px) auto 0; padding: 20px 0 14px; display: flex; align-items: center; justify-content: space-between; gap: 18px; flex-wrap: wrap; border-top: 1px solid #dceeee; }
        .brand { display: flex; align-items: center; gap: 14px; }
        .brand img { height: 56px; width: auto; display: block; }
        .brand b { display: block; color: var(--navy); font-size: 16px; }
        .brand span { display: block; color: var(--navy); font-size: 12.5px; direction: ltr; text-align: right; opacity: .85; }
        .copy { color: #7f9599; font-size: 13px; }

        @media (max-width: 760px) {
            .actions.inside { display: none; }
            .actions.below { display: flex; margin-top: 26px; }
            .preview { margin-top: calc(-1 * clamp(110px, 30vw, 150px)); }
            .top { padding-bottom: clamp(130px, 34vw, 170px); }
        }
        @media (max-width: 560px) {
            .top { padding: 14px 12px 120px; }
            .card { border-radius: 22px; padding: 76px 20px 26px; }
            .tab { width: 104px; height: 86px; border-radius: 22px 5px 5px 5px; }
            .logos { gap: 12px; margin-bottom: 18px; }
            .logos .kf { height: 36px; } .logos .mh { height: 32px; }
            .kicker { font-size: 14.5px; }
            h1 { font-size: 31px; }
            .desc { font-size: 15.5px; margin-top: 12px; }
            .bottom { padding: 0 12px; }
            .preview { margin-top: -100px; }
            .frame { border-radius: 12px; }
            .bar { padding: 7px 10px; } .bar i { width: 7px; height: 7px; }
            .label b { font-size: 18px; } .label p { font-size: 14.5px; }
            .cta { font-size: 16px; padding: 12px 24px; gap: 10px; border-radius: 14px 14px 14px 4px; }
            .note { font-size: 13px; }
            .foot { justify-content: center; text-align: center; }
            .brand img { height: 44px; }
            .brand b { font-size: 13.5px; } .brand span { font-size: 10.5px; }
            .copy { width: 100%; font-size: 11.5px; }
        }
    </style>
</head>
<body>
    <header class="top">
        <div class="wrap">
            <span class="tab" aria-hidden="true"></span>
            <div class="card">
                <div class="logos">
                    <img class="kf" src="{{ asset('images/brand/kafaat-logo-white.svg') }}" alt="جمعية كفاءات الأهلية">
                    <span class="sep" aria-hidden="true"></span>
                    <img class="mh" src="{{ asset('images/experience-houses/mhrsd-white.png') }}" alt="وزارة الموارد البشرية والتنمية الاجتماعية">
                </div>
                <p class="kicker">جمعية كفاءات الأهلية · مركز تميز معتمد</p>
                <h1>طلب دعم بيوت الخبرة</h1>
                <p class="desc">منصة الجمعيات المستفيدة لطلب دعم كفاءات ضمن مشروع وزارة الموارد البشرية.</p>
                <div class="actions inside">
                    <a class="cta" href="{{ route('public.experience-houses.create') }}">تقديم طلب @include('experience-houses.manage._icon', ['name' => 'arrow-left', 'size' => 22, 'stroke' => 2.2])</a>
                    <span class="note">@include('experience-houses.manage._icon', ['name' => 'clock', 'size' => 17]) حوالي دقيقة واحدة</span>
                </div>
            </div>
        </div>
    </header>

    <main class="bottom">
        <figure class="preview" style="margin-bottom:0">
            <div class="frame">
                <div class="bar"><i></i><i></i><i></i></div>
                <img src="{{ asset('images/experience-houses/system-preview.jpg') }}" alt="لقطة من نظام متابعة طلبات بيوت الخبرة" loading="eager">
            </div>
        </figure>

        <div class="actions below">
            <a class="cta" href="{{ route('public.experience-houses.create') }}">تقديم طلب @include('experience-houses.manage._icon', ['name' => 'arrow-left', 'size' => 20, 'stroke' => 2.2])</a>
            <span class="note">@include('experience-houses.manage._icon', ['name' => 'clock', 'size' => 16]) حوالي دقيقة واحدة</span>
        </div>

        <div class="label">
            <b>بهدف</b>
            <p>نقل الخبرات المؤسسية وتمكين المنظمات غير الربحية الناشئة من تطبيق أفضل الممارسات.</p>
        </div>

        <footer class="foot">
            <div class="brand">
                <img src="{{ asset('images/brand/kafaat-logo.svg') }}" alt="كفاءات">
                <div><b>جمعية كفاءات الأهلية لبناء قدرات الشباب</b><span>Kafaat Civil Association To Build Youth Capacity</span></div>
            </div>
            <span class="copy">© {{ now()->year }} جميع الحقوق محفوظة</span>
        </footer>
    </main>
</body>
</html>

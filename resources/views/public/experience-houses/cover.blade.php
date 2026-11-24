<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#177179">
    <title>استفد من خبرات كفاءات | جمعية كفاءات الأهلية</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        @font-face { font-family: 'FF Shamel'; src: url('/fonts/shamel/FFShamelFamily-SansOneBook.ttf') format('truetype'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'FF Shamel'; src: url('/fonts/shamel/FFShamelFamily-SansOneBold.ttf') format('truetype'); font-weight: 700; font-display: swap; }
        /* ألوان هوية منشور بيوت الخبرة */
        :root { --outer-1: #177179; --outer-2: #1f8a91; --navy: #0c3555; --card-1: #42bdbe; --card-2: #2f9fa1; --card-3: #24898f; --title: #0d4c50; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            font-family: 'FF Shamel', system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif; -webkit-font-smoothing: antialiased; color: #fff;
            min-height: 100vh; min-height: 100svh; display: flex; flex-direction: column;
            background:
                radial-gradient(75% 85% at 100% 100%, var(--navy) 0%, rgba(12,53,85,0) 62%),
                linear-gradient(170deg, var(--outer-1) 0%, var(--outer-2) 55%, #1a7f88 100%);
            padding: clamp(14px, 3vh, 36px) clamp(12px, 3.5vw, 40px) 0;
        }
        .wrap { position: relative; flex: 1; display: flex; width: 100%; max-width: 1240px; margin: 0 auto; }

        /* البطاقة الفاتحة بالزاوية المقطوعة */
        .card {
            position: relative; flex: 1; display: grid; align-items: center;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr); grid-template-areas: 'copy preview' 'actions preview';
            column-gap: clamp(28px, 4vw, 64px); align-content: center;
            background: linear-gradient(180deg, var(--card-1) 0%, var(--card-2) 55%, var(--card-3) 100%);
            border-radius: 28px;
            padding: clamp(100px, 15vh, 140px) clamp(28px, 5vw, 72px) clamp(32px, 6vh, 64px);
            clip-path: polygon(clamp(160px, 18vw, 240px) 0, 100% 0, 100% 100%, 0 100%, 0 clamp(128px, 14vw, 180px));
        }
        .tab {
            position: absolute; top: 0; left: 0; z-index: 1;
            width: clamp(136px, 15.5vw, 206px); height: clamp(112px, 12.5vw, 162px);
            background: linear-gradient(160deg, #4cc6c5, #3cb4b5); border-radius: 28px 6px 6px 6px;
            clip-path: polygon(0 0, 100% 0, 100% 18%, 30% 100%, 0 100%);
        }
        .tab::after { content: ''; position: absolute; top: 24%; left: 20%; width: 34%; height: 34%; border-top: clamp(9px, 1vw, 13px) solid #fff; border-left: clamp(9px, 1vw, 13px) solid #fff; border-top-left-radius: 4px; }

        .copy { grid-area: copy; align-self: end; }
        .logos { display: flex; align-items: center; gap: clamp(14px, 2vw, 22px); margin-bottom: clamp(16px, 3vh, 30px); }
        .logos .kf { height: clamp(40px, 6vh, 60px); width: auto; display: block; }
        .logos .mh { height: clamp(36px, 5.4vh, 54px); width: auto; display: block; }
        .logos .sep { width: 1px; align-self: stretch; background: rgba(255,255,255,.6); }
        .kicker { margin: 0; color: rgba(255,255,255,.8); font-weight: 700; font-size: clamp(13px, 1.25vw, 16.5px); line-height: 1.6; max-width: 34em; }
        h1 { margin: 2px 0 0; color: #fff; font-weight: 700; font-size: clamp(28px, 2.9vw, 42px); white-space: nowrap; line-height: 1.15; letter-spacing: -.5px; }
        .desc { margin: clamp(10px, 2vh, 18px) 0 0; font-size: clamp(16px, 1.7vw, 22px); line-height: 1.6; max-width: 520px; }

        .actions { grid-area: actions; align-self: start; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-top: clamp(18px, 3.4vh, 32px); }
        .cta { display: inline-flex; align-items: center; gap: 14px; text-decoration: none; font-weight: 700; font-size: clamp(17px, 1.8vw, 20px);
            padding: 14px 28px; border-radius: 16px 16px 16px 5px; background: var(--title); color: #fff;
            box-shadow: 0 16px 30px -16px rgba(8,50,55,.8); transition: transform .18s, filter .18s; }
        .cta:hover { transform: translateY(-2px); filter: brightness(1.12); }
        .cta svg { transition: transform .18s; }
        .cta:hover svg { transform: translateX(-4px); }
        .note { display: inline-flex; align-items: center; gap: 7px; color: rgba(255,255,255,.92); font-size: 15px; }

        .preview { grid-area: preview; margin: 0; }
        .frame { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 30px 60px -28px rgba(8,50,55,.7), 0 0 0 1px rgba(255,255,255,.3); }
        .bar { display: flex; gap: 6px; padding: 9px 13px; background: #eef5f6; direction: ltr; }
        .bar i { width: 9px; height: 9px; border-radius: 50%; background: #cfdcde; }
        .frame img { display: block; width: 100%; height: auto; max-height: 56vh; object-fit: cover; object-position: top; }

        .foot { text-align: center; padding: 10px 12px 14px; font-size: 12.5px; color: rgba(255,255,255,.7); }

        /* شاشات طولية: نص ← معاينة ← زر */
        @media (max-width: 900px), (orientation: portrait) {
            /* الشعارات والنص التعريفي أعلى البطاقة، والفراغ موزع بالتساوي فوق العنوان وتحت الزر */
            .card { grid-template-columns: 1fr; grid-template-areas: 'logos' 'kicker' '.' 'title' 'desc' 'preview' 'actions' '.';
                grid-template-rows: auto auto minmax(28px, 1fr) auto auto auto auto minmax(20px, 1fr); align-content: stretch; row-gap: 0;
                padding: clamp(78px, 11vh, 110px) clamp(18px, 5vw, 48px) clamp(20px, 3.5vh, 36px); }
            .copy { display: contents; }
            .logos { grid-area: logos; margin-bottom: 10px; }
            .kicker { grid-area: kicker; text-align: center; }
            h1 { grid-area: title; text-align: center; margin-top: 0; }
            .desc { grid-area: desc; text-align: center; }
            .logos { justify-content: center; }
            .desc { margin-inline: auto; }
            .preview { margin-top: clamp(14px, 2.6vh, 26px); }
            .frame img { max-height: 30vh; max-height: 30svh; }
            .actions { margin-top: clamp(14px, 2.6vh, 24px); }
        }
        @media (max-width: 560px) {
            body { padding: 10px 10px 0; }
            .card { border-radius: 22px; clip-path: polygon(118px 0, 100% 0, 100% 100%, 0 100%, 0 96px); }
            .tab { width: 100px; height: 82px; border-radius: 22px 5px 5px 5px; }
            .tab::after { border-width: 8px; }
            .logos { gap: 11px; margin-bottom: 10px; }
            .logos .kf { height: 34px; } .logos .mh { height: 30px; }
            .kicker { font-size: 13.5px; }
            h1 { font-size: clamp(24px, 8.2vw, 36px); white-space: normal; }
            .desc { font-size: 14.5px; margin-top: 8px; }
            .frame { border-radius: 11px; }
            .bar { padding: 6px 9px; } .bar i { width: 6px; height: 6px; }
            .cta { font-size: 16px; padding: 11px 22px; gap: 10px; border-radius: 13px 13px 13px 4px; }
            .note { font-size: 12.5px; }
            .foot { font-size: 11px; padding: 8px 10px 10px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <span class="tab" aria-hidden="true"></span>
        <main class="card">
            <section class="copy">
                <div class="logos">
                    <img class="kf" src="{{ asset('images/brand/kafaat-logo-white.svg') }}" alt="جمعية كفاءات الأهلية">
                    <span class="sep" aria-hidden="true"></span>
                    <img class="mh" src="{{ asset('images/experience-houses/mhrsd-white.png') }}" alt="وزارة الموارد البشرية والتنمية الاجتماعية">
                </div>
                <p class="kicker">مركز تميّز معتمد وبيت خبرة من وزارة الموارد البشرية والتنمية الاجتماعية</p>
                <h1>استفد من خبرات كفاءات</h1>
                <p class="desc">منصة الجمعيات المستفيدة لطلب الاستفادة من خدمات كفاءات ضمن مشروع بيوت الخبرة.</p>
            </section>

            <figure class="preview">
                <div class="frame">
                    <div class="bar"><i></i><i></i><i></i></div>
                    <img src="{{ asset('images/experience-houses/system-preview.jpg') }}" alt="لقطة من نظام متابعة طلبات بيوت الخبرة" loading="eager">
                </div>
            </figure>

            <div class="actions">
                <a class="cta" href="{{ route('public.experience-houses.create') }}">ابدأ الآن @include('experience-houses.manage._icon', ['name' => 'arrow-left', 'size' => 21, 'stroke' => 2.2])</a>
                <span class="note">@include('experience-houses.manage._icon', ['name' => 'clock', 'size' => 16]) حوالي دقيقة واحدة</span>
            </div>
        </main>
    </div>
    <p class="foot">© {{ now()->year }} جمعية كفاءات الأهلية — جميع الحقوق محفوظة</p>
</body>
</html>

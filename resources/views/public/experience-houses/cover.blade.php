<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0c4a50">
    <title>طلب دعم بيوت الخبرة | جمعية كفاءات الأهلية</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        @font-face { font-family: 'FF Shamel'; src: url('/fonts/shamel/FFShamelFamily-SansOneBook.ttf') format('truetype'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'FF Shamel'; src: url('/fonts/shamel/FFShamelFamily-SansOneBold.ttf') format('truetype'); font-weight: 700; font-display: swap; }
        :root { --deep: #08363b; --teal-900: #0c4a50; --teal-700: #177179; --teal-600: #1f8a91; --teal-300: #3fb8b9; --mint: #8fe0dc; --navy: #0b2f4a; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            font-family: 'FF Shamel', system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif;
            color: #fff; -webkit-font-smoothing: antialiased; min-height: 100vh; min-height: 100svh;
            background:
                radial-gradient(60% 70% at 18% 45%, rgba(63,184,185,.38) 0%, rgba(63,184,185,0) 70%),
                radial-gradient(70% 60% at 100% 0%, rgba(31,138,145,.55) 0%, rgba(31,138,145,0) 65%),
                radial-gradient(80% 70% at 0% 100%, var(--navy) 0%, rgba(11,47,74,0) 60%),
                linear-gradient(160deg, var(--teal-900) 0%, var(--deep) 100%);
            display: flex; flex-direction: column; overflow-x: hidden; position: relative;
        }
        /* زخارف من هوية المنشور: الزاوية والنقاط */
        body::before {
            content: ''; position: fixed; inset: 0; pointer-events: none;
            background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1.4px);
            background-size: 26px 26px;
            mask-image: linear-gradient(115deg, #000 0%, transparent 55%);
            -webkit-mask-image: linear-gradient(115deg, #000 0%, transparent 55%);
        }
        .corner { position: fixed; pointer-events: none; border-color: rgba(255,255,255,.08); border-style: solid; }
        .corner.a { top: 40px; left: 40px; width: 200px; height: 200px; border-width: 32px 0 0 32px; border-top-left-radius: 14px; }
        .corner.b { bottom: -60px; right: -60px; width: 340px; height: 340px; border-width: 0 46px 46px 0; border-bottom-right-radius: 18px; border-color: rgba(143,224,220,.07); }

        .stage { flex: 1; width: 100%; max-width: 1240px; margin: 0 auto; padding: clamp(28px, 5vw, 56px) clamp(18px, 4vw, 40px);
            display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr); gap: clamp(28px, 5vw, 72px); align-items: center; position: relative; z-index: 1; }

        .logos { display: flex; align-items: center; gap: 20px; margin-bottom: clamp(26px, 4vw, 40px); }
        .logos .kf { height: 60px; width: auto; display: block; }
        .logos .mh { height: 52px; width: auto; display: block; }
        .logos .sep { width: 1.5px; align-self: stretch; background: rgba(255,255,255,.35); }

        .eyebrow { display: inline-flex; align-items: center; gap: 8px; background: rgba(143,224,220,.12); border: 1px solid rgba(143,224,220,.28); color: var(--mint);
            border-radius: 999px; padding: 6px 14px; font-size: 14px; font-weight: 700; margin-bottom: 16px; }
        .eyebrow::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--mint); box-shadow: 0 0 0 4px rgba(143,224,220,.2); }
        h1 { margin: 0; font-size: clamp(36px, 3.5vw, 50px); white-space: nowrap; line-height: 1.18; font-weight: 700; letter-spacing: -.5px; }
        h1 .hl { color: var(--mint); }
        .desc { margin: 18px 0 0; font-size: clamp(17px, 1.6vw, 20px); line-height: 1.8; color: rgba(255,255,255,.82); max-width: 520px; }

        .actions { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; margin-top: clamp(26px, 4vw, 38px); }
        .cta { display: inline-flex; align-items: center; gap: 14px; background: #fff; color: var(--teal-900); font-weight: 700; font-size: 20px;
            padding: 16px 30px; border-radius: 18px 18px 18px 5px; text-decoration: none; box-shadow: 0 18px 40px -16px rgba(0,0,0,.55), 0 0 0 6px rgba(255,255,255,.08);
            transition: transform .18s, box-shadow .18s; }
        .cta:hover { transform: translateY(-2px); box-shadow: 0 22px 44px -16px rgba(0,0,0,.6), 0 0 0 8px rgba(255,255,255,.12); }
        .cta svg { transition: transform .18s; }
        .cta:hover svg { transform: translateX(-4px); }
        .note { display: inline-flex; align-items: center; gap: 7px; color: rgba(255,255,255,.72); font-size: 15px; }

        .preview { position: relative; margin: 0; }
        .preview::before { content: ''; position: absolute; inset: 8% 6% -6% 6%; background: var(--teal-300); filter: blur(60px); opacity: .45; border-radius: 40px; }
        .frame { position: relative; background: #fff; border-radius: 18px; overflow: hidden;
            box-shadow: 0 50px 90px -40px rgba(0,0,0,.75), 0 0 0 1px rgba(255,255,255,.25);
            transform: perspective(1600px) rotateY(10deg) rotate(-1.5deg); animation: floaty 6s ease-in-out infinite; }
        .bar { display: flex; gap: 6px; padding: 10px 14px; background: #eef4f5; direction: ltr; }
        .bar i { width: 10px; height: 10px; border-radius: 50%; background: #cfdcde; }
        .frame img { display: block; width: 100%; height: auto; }
        .tag { position: absolute; z-index: 2; display: inline-flex; align-items: center; gap: 8px; background: #fff; color: var(--teal-900); font-weight: 700; font-size: 14px;
            border-radius: 14px 14px 14px 4px; padding: 9px 14px; box-shadow: 0 16px 30px -14px rgba(0,0,0,.55); }
        .tag .ic { width: 28px; height: 28px; border-radius: 9px; display: grid; place-items: center; background: var(--teal-300); color: #fff; }
        .tag.t1 { top: -18px; right: 8%; }
        .tag.t2 { bottom: -20px; left: 6%; }
        @keyframes floaty { 0%,100% { translate: 0 0; } 50% { translate: 0 -10px; } }
        @media (prefers-reduced-motion: reduce) { .frame { animation: none; } }

        .foot { position: relative; z-index: 1; text-align: center; padding: 14px 16px 22px; color: rgba(255,255,255,.55); font-size: 13px; }

        @media (max-width: 900px) {
            h1 { white-space: normal; }
            .stage { grid-template-columns: 1fr; align-content: start; }
            .preview { margin: 18px 6px 10px; }
            .frame { transform: none; }
        }
        @media (max-width: 560px) {
            .corner.a { top: 14px; left: 14px; width: 90px; height: 90px; border-width: 16px 0 0 16px; }
            .corner.b { width: 200px; height: 200px; border-width: 0 28px 28px 0; }
            .stage { padding: 26px 18px 18px; gap: 22px; }
            .logos { gap: 12px; margin-bottom: 22px; }
            .logos .kf { height: 40px; }
            .logos .mh { height: 34px; }
            .eyebrow { font-size: 12px; padding: 4px 11px; margin-bottom: 12px; }
            h1 { font-size: 30px; white-space: normal; }
            .desc { font-size: 15.5px; margin-top: 12px; line-height: 1.75; }
            .actions { gap: 12px; margin-top: 22px; }
            .cta { font-size: 17px; padding: 12px 22px; gap: 10px; border-radius: 14px 14px 14px 4px; }
            .cta svg { width: 19px; height: 19px; }
            .note { font-size: 13px; }
            .frame { border-radius: 12px; }
            .bar { padding: 7px 10px; } .bar i { width: 7px; height: 7px; }
            .tag { font-size: 12px; padding: 6px 10px; gap: 6px; }
            .tag .ic { width: 22px; height: 22px; } .tag .ic svg { width: 13px; height: 13px; }
            .tag.t1 { top: -14px; } .tag.t2 { bottom: -14px; }
            .foot { font-size: 11.5px; padding-bottom: 18px; }
        }
    </style>
</head>
<body>
    <span class="corner a" aria-hidden="true"></span>
    <span class="corner b" aria-hidden="true"></span>

    <main class="stage">
        <section>
            <div class="logos">
                <img class="kf" src="{{ asset('images/brand/kafaat-logo-white.svg') }}" alt="جمعية كفاءات الأهلية">
                <span class="sep" aria-hidden="true"></span>
                <img class="mh" src="{{ asset('images/experience-houses/mhrsd-white.png') }}" alt="وزارة الموارد البشرية والتنمية الاجتماعية">
            </div>
            <span class="eyebrow">مشروع بيوت الخبرة</span>
            <h1>طلب دعم <span class="hl">بيوت الخبرة</span></h1>
            <p class="desc">منصة الجمعيات المستفيدة لطلب دعم كفاءات ضمن مشروع وزارة الموارد البشرية.</p>
            <div class="actions">
                <a class="cta" href="{{ route('public.experience-houses.create') }}">
                    تقديم طلب
                    @include('experience-houses.manage._icon', ['name' => 'arrow-left', 'size' => 22, 'stroke' => 2.2])
                </a>
                <span class="note">@include('experience-houses.manage._icon', ['name' => 'clock', 'size' => 17]) حوالي دقيقة واحدة</span>
            </div>
        </section>

        <figure class="preview" aria-label="معاينة نظام متابعة الطلبات">
            <span class="tag t1"><span class="ic">@include('experience-houses.manage._icon', ['name' => 'inbox', 'size' => 16])</span> نظام متابعة الطلبات</span>
            <div class="frame">
                <div class="bar"><i></i><i></i><i></i></div>
                <img src="{{ asset('images/experience-houses/system-preview.jpg') }}" alt="لقطة من نظام متابعة طلبات بيوت الخبرة" loading="eager">
            </div>
            <span class="tag t2"><span class="ic">@include('experience-houses.manage._icon', ['name' => 'check', 'size' => 16])</span> متابعة حتى الإنجاز</span>
        </figure>
    </main>

    <p class="foot">© {{ now()->year }} جمعية كفاءات الأهلية لبناء قدرات الشباب — جميع الحقوق محفوظة</p>
</body>
</html>

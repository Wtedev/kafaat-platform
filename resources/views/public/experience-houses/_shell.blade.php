<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- صفحة غير مدرجة: تُشارك برابط مباشر ولا تُفهرس --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'استفد من خبرات كفاءات — مشروع بيوت الخبرة') | جمعية كفاءات الأهلية</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        @font-face { font-family: 'FF Shamel'; src: url('/fonts/shamel/FFShamelFamily-SansOneBook.ttf') format('truetype'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'FF Shamel'; src: url('/fonts/shamel/FFShamelFamily-SansOneBold.ttf') format('truetype'); font-weight: 700; font-display: swap; }

        :root {
            --teal-950: #083c41;
            --teal-900: #0d4c50;
            --teal-700: #177179;
            --teal-600: #1f8a91;
            --teal-500: #2a9899;
            --teal-300: #3fb8b9;
            --teal-100: #d7f1f1;
            --teal-50:  #eaf8f8;
            --navy: #0c3555;
            --pale: #f2fcfd;
            --ink: #2f3d42;
            --muted: #6b7c82;
            --line: #d9e9ea;
            --danger: #c2413b;
            --radius: 22px;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            font-family: 'FF Shamel', system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif;
            background: var(--pale);
            color: var(--ink);
            line-height: 1.75;
            -webkit-font-smoothing: antialiased;
        }

        /* ===== الهيرو: نفس تكوين المنشور ===== */
        .hero {
            position: relative;
            background:
                radial-gradient(120% 90% at 100% 100%, var(--navy) 0%, rgba(12,53,85,0) 55%),
                linear-gradient(165deg, var(--teal-700) 0%, var(--teal-600) 55%, #146a78 100%);
            padding: clamp(28px, 6vw, 64px) clamp(16px, 5vw, 48px) clamp(120px, 16vw, 170px);
        }
        .hero-card {
            position: relative;
            max-width: 980px;
            margin: 0 auto;
            background: linear-gradient(180deg, var(--teal-300) 0%, #35aeb0 45%, var(--teal-500) 100%);
            border-radius: 26px;
            padding: clamp(88px, 11vw, 120px) clamp(22px, 7vw, 120px) clamp(36px, 6vw, 64px);
            /* الزاوية المقطوعة أعلى اليسار كما في المنشور */
            clip-path: polygon(clamp(150px, 20vw, 240px) 0, 100% 0, 100% 100%, 0 100%, 0 clamp(120px, 15vw, 190px));
        }
        .hero-tab {
            position: absolute;
            top: 0; left: 0;
            width: clamp(130px, 17vw, 210px);
            height: clamp(108px, 13vw, 165px);
            background: linear-gradient(160deg, #46c2c1, #37b0b1);
            border-radius: 26px 6px 6px 6px;
            clip-path: polygon(0 0, 100% 0, 100% 18%, 30% 100%, 0 100%);
        }
        .hero-tab::after { /* علامة الزاوية البيضاء */
            content: '';
            position: absolute;
            top: 24%; left: 20%;
            width: 34%; height: 34%;
            border-top: clamp(8px, 1.1vw, 13px) solid #fff;
            border-left: clamp(8px, 1.1vw, 13px) solid #fff;
            border-top-left-radius: 4px;
        }
        .hero-wrap { position: relative; max-width: 980px; margin: 0 auto; }
        .kicker {
            margin: 0;
            color: var(--teal-900);
            font-weight: 700;
            font-size: clamp(20px, 3vw, 34px);
            line-height: 1.4;
        }
        .headline {
            margin: 6px 0 0;
            color: var(--teal-900);
            font-weight: 700;
            font-size: clamp(32px, 5.1vw, 60px);
            line-height: 1.15;
            letter-spacing: -0.5px;
        }
        .ministry {
            display: flex;
            align-items: center;
            gap: clamp(14px, 3vw, 28px);
            margin-top: clamp(22px, 4vw, 40px);
            color: #fff;
            flex-wrap: wrap;
        }
        .ministry img { height: clamp(46px, 6vw, 64px); width: auto; display: block; }
        .ministry .sep { width: 1px; align-self: stretch; background: rgba(255,255,255,.55); min-height: 46px; }
        @media (max-width: 560px) { .ministry .sep { display: none; } .ministry { gap: 10px; } }
        .ministry p { margin: 0; font-size: clamp(16px, 2.3vw, 26px); line-height: 1.45; }
        .hero-lead {
            margin: clamp(26px, 5vw, 48px) 0 0;
            color: #fff;
            font-size: clamp(19px, 3vw, 32px);
            line-height: 1.55;
        }
        .hero-lead strong { font-weight: 700; }

        /* ===== جسم الصفحة ===== */
        .main {
            position: relative;
            max-width: 980px;
            margin: calc(-1 * clamp(90px, 12vw, 130px)) auto 0;
            padding: 0 clamp(12px, 4vw, 24px) 40px;
        }
        .panel {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: 0 18px 50px -24px rgba(8, 60, 65, .35);
            padding: clamp(22px, 5vw, 56px) clamp(18px, 6vw, 72px);
        }

        .footer {
            max-width: 980px;
            margin: 0 auto;
            padding: 28px clamp(16px, 5vw, 48px) 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            flex-wrap: wrap;
            text-align: right;
        }
        .footer img { height: 68px; width: auto; }
        .footer .names { line-height: 1.5; }
        .footer .names b { display: block; color: var(--navy); font-size: 17px; }
        .footer .names span { display: block; color: var(--navy); font-size: 13px; opacity: .85; direction: ltr; text-align: right; }
        /* ترويسة مختصرة لصفحات النموذج */
        .bar-head {
            background:
                radial-gradient(120% 120% at 0% 100%, var(--navy) 0%, rgba(12,53,85,0) 55%),
                linear-gradient(165deg, var(--teal-700) 0%, var(--teal-600) 70%, #146a78 100%);
            color: #fff; padding: 18px 0 clamp(110px, 13vw, 140px);
        }
        .bar-in { max-width: 980px; margin: 0 auto; padding-inline: clamp(12px, 4vw, 24px); }
        .bar-top { display: flex; align-items: center; justify-content: center; gap: 12px; }
        .bar-logo img { height: 60px; width: auto; display: block; }
        .bar-logo { display: flex; align-items: center; gap: 14px; text-decoration: none; }
        .bar-logo .bar-ec { height: 34px; }
        .bar-sep { width: 1px; height: 28px; align-self: center; background: rgba(255,255,255,.5); }
        .bar-kicker { margin: clamp(26px, 5vw, 44px) 0 0; font-size: 16px; opacity: .85; }
        .bar-title { margin: 2px 0 0; font-size: clamp(24px, 3.4vw, 34px); line-height: 1.3; font-weight: 700; }
        @media (max-width: 520px) { .bar-logo img { height: 48px; } .bar-logo .bar-ec { height: 24px; } .bar-sep { height: 22px; } .bar-logo { gap: 10px; } }
        .copyright { margin: 0; padding: 16px 16px 28px; text-align: center; color: #7f9599; font-size: 13.5px; border-top: 1px solid #e1eeef; max-width: 980px; margin-inline: auto; }
        .footer { padding-bottom: 22px; }
        .site-strip { position: relative; z-index: 5; display: flex; align-items: center; justify-content: center; gap: 5px; min-height: 30px; padding: 5px 12px;
            background: #26a69a; color: #fff; text-decoration: none; font-size: 13px; font-weight: 700; text-align: center; line-height: 1.4;
            box-shadow: 0 2px 8px rgba(5, 45, 50, .18); transition: background .15s; }
        .site-strip:hover { background: #229a8f; }
        @media (max-width: 520px) { .site-strip { font-size: 11.5px; min-height: 26px; padding: 4px 10px; } }
        @media (max-width: 560px) { .main, .bar-in { padding-inline: 20px; } }
        @yield('styles')
    </style>
</head>
<body>
    <a class="site-strip" href="{{ config('site.website_url', 'https://kafaat.org.sa') }}" target="_blank" rel="noopener">توجّه للموقع الإلكتروني لجمعية كفاءات @include('experience-houses.manage._icon', ['name' => 'arrow-left', 'size' => 13, 'stroke' => 2.4])</a>
    @hasSection('compact')
    <header class="bar-head">
        <div class="bar-in">
            <div class="bar-top">
                <a href="{{ route('public.experience-houses.cover') }}" class="bar-logo" aria-label="مشروع بيوت الخبرة">
                    <img src="{{ asset('images/brand/kafaat-logo-white.svg') }}" alt="كفاءات">
                    <span class="bar-sep" aria-hidden="true"></span>
                    <img class="bar-ec" src="{{ asset('images/experience-houses/excellence-center-white.png') }}" alt="مركز تميّز معتمد وبيت خبرة من وزارة الموارد البشرية والتنمية الاجتماعية">
                </a>
            </div>
            <p class="bar-kicker">مشروع بيوت الخبرة</p>
            <h1 class="bar-title">@yield('compact')</h1>
        </div>
    </header>
    @else
    <header class="hero">
        <div class="hero-wrap">
            <div class="hero-tab" aria-hidden="true"></div>
            <div class="hero-card">
                @hasSection('hero_logos')
                    <div class="hero-logos">
                        <img class="kf" src="{{ asset('images/brand/kafaat-logo-white.svg') }}" alt="جمعية كفاءات الأهلية">
                        <span class="sep" aria-hidden="true"></span>
                        <img class="mh" src="{{ asset('images/experience-houses/mhrsd-white.png') }}" alt="وزارة الموارد البشرية والتنمية الاجتماعية">
                    </div>
                @else
                <p class="kicker">@yield('kicker', 'انطلاقًا من دورها كأحد مراكز التميز المعتمدة')</p>
                @endif
                <h1 class="headline">@yield('headline', 'ضمن مشروع بيوت الخبرة')</h1>
@unless (View::hasSection('hero_logos'))
                <div class="ministry">
                    <img src="{{ asset('images/experience-houses/mhrsd-white.png') }}" alt="وزارة الموارد البشرية والتنمية الاجتماعية">
                    <span class="sep" aria-hidden="true"></span>
                    <p>التابع لوزارة الموارد البشرية<br>والتنمية الاجتماعية</p>
                </div>
                @endunless
                @hasSection('lead')
                    <p class="hero-lead">@yield('lead')</p>
                @endif
                @yield('hero_extra')
            </div>
            @yield('hero_media')
        </div>
    </header>
    @endif

    <main class="main">
        @yield('content')
    </main>

    <p class="copyright">© {{ now()->year }} جمعية كفاءات الأهلية لبناء قدرات الشباب — جميع الحقوق محفوظة</p>
    @stack('scripts')
</body>
</html>

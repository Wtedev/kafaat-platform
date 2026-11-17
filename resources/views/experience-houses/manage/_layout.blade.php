<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#177179">
    <title>@yield('title', 'طلبات بيوت الخبرة') | كفاءات</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        @font-face { font-family: 'FF Shamel'; src: url('/fonts/shamel/FFShamelFamily-SansOneBook.ttf') format('truetype'); font-weight: 400; font-display: swap; }
        @font-face { font-family: 'FF Shamel'; src: url('/fonts/shamel/FFShamelFamily-SansOneBold.ttf') format('truetype'); font-weight: 700; font-display: swap; }
        :root {
            --teal-950:#083c41; --teal-900:#0d4c50; --teal-700:#177179; --teal-600:#1f8a91; --teal-500:#2a9899;
            --teal-300:#3fb8b9; --teal-100:#d7f1f1; --teal-50:#eaf8f8; --navy:#0c3555; --pale:#f2fcfd;
            --ink:#2f3d42; --muted:#6b7c82; --line:#e1eeef; --danger:#c2413b; --amber:#b7791f;
        }
        *{box-sizing:border-box}
        html,body{margin:0}
        body{font-family:'FF Shamel',system-ui,-apple-system,'Segoe UI',Tahoma,sans-serif;background:var(--pale);color:var(--ink);line-height:1.7;-webkit-font-smoothing:antialiased;-webkit-tap-highlight-color:transparent}
        a{color:inherit;text-decoration:none}
        button{font:inherit;cursor:pointer}

        .topbar{position:sticky;top:0;z-index:20;background:linear-gradient(135deg,var(--teal-700),var(--teal-600));color:#fff;box-shadow:0 6px 20px -14px rgba(8,60,65,.8)}
        .topbar-in{max-width:1080px;margin:0 auto;display:flex;align-items:center;gap:14px;padding:12px 18px}
        .brand{display:flex;align-items:center;gap:12px;font-weight:700;font-size:18px;white-space:nowrap}
        .brand img{height:34px;width:auto;display:block}
        .brand small{display:block;font-weight:400;font-size:12px;opacity:.8;line-height:1.2}
        .top-actions{margin-inline-start:auto;display:flex;align-items:center;gap:8px}
        .icon-btn{display:inline-flex;align-items:center;gap:8px;border:0;background:rgba(255,255,255,.14);color:#fff;border-radius:12px;padding:9px 13px;font-size:14px}
        .icon-btn:hover{background:rgba(255,255,255,.24)}
        .icon-btn.active{background:#fff;color:var(--teal-700)}
        .avatar{width:38px;height:38px;border-radius:12px 12px 12px 4px;background:var(--teal-300);color:#fff;display:grid;place-items:center;font-weight:700}
        @media(max-width:640px){.icon-btn .lbl{display:none}.brand small{display:none}.brand{font-size:15px;gap:8px}.brand img{height:28px}.topbar-in{padding:10px 12px;gap:8px}.icon-btn{padding:9px 10px}.avatar{width:34px;height:34px}}

        .wrap{max-width:1080px;margin:0 auto;padding:22px 16px 60px}
        .card{background:#fff;border-radius:20px;box-shadow:0 10px 30px -22px rgba(8,60,65,.45);border:1px solid var(--line)}

        .toast{position:fixed;inset-inline:0;bottom:22px;margin:0 auto;width:max-content;max-width:calc(100% - 32px);z-index:50;
            background:var(--teal-950);color:#fff;border-radius:14px;padding:12px 18px;display:flex;align-items:center;gap:10px;
            box-shadow:0 14px 30px -12px rgba(0,0,0,.45);animation:toast 4.2s forwards}
        .toast.err{background:var(--danger)}
        @keyframes toast{0%{opacity:0;transform:translateY(16px)}8%,85%{opacity:1;transform:none}100%{opacity:0;transform:translateY(16px);visibility:hidden}}

        .btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;border:0;border-radius:14px 14px 14px 4px;padding:13px 22px;font-weight:700;font-size:16px;
            background:linear-gradient(135deg,var(--teal-600),var(--teal-700));color:#fff;box-shadow:0 10px 20px -12px rgba(23,113,121,.8);transition:filter .15s,transform .15s}
        .btn:hover{filter:brightness(1.07);transform:translateY(-1px)}
        .btn.ghost{background:var(--teal-50);color:var(--teal-700);box-shadow:none}
        .btn.light{background:#fff;color:var(--teal-700);border:1.5px solid var(--line);box-shadow:none}
        .btn.danger-ghost{background:#fdf0ef;color:var(--danger);box-shadow:none}
        .input{width:100%;font:inherit;font-size:16px;color:var(--ink);background:var(--pale);border:1.5px solid var(--line);border-radius:14px;padding:13px 15px;transition:border-color .15s,box-shadow .15s,background .15s}
        .input:focus{outline:none;border-color:var(--teal-300);background:#fff;box-shadow:0 0 0 4px rgba(63,184,185,.18)}
        .err{color:var(--danger);font-size:14px;margin-top:6px}
        .muted{color:var(--muted)}
        .pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:700;white-space:nowrap}
        .st-new{background:#e6f0fb;color:#2f5f98}
        .st-in_review{background:#fdf3e2;color:var(--amber)}
        .st-scheduled,.st-in_progress{background:var(--teal-50);color:var(--teal-700)}
        .st-completed{background:#e5f6ea;color:#2f8a4d}
        .st-rejected{background:#f4f4f5;color:#71717a}
        @yield('styles')
    </style>
</head>
<body>
    @isset($manager)
    <header class="topbar">
        <div class="topbar-in">
            <a class="brand" href="{{ route('experience-houses.manage.index') }}">
                <img src="{{ asset('images/brand/kafaat-logo-white.svg') }}" alt="كفاءات">
                <span>طلبات بيوت الخبرة<small>جمعية كفاءات الأهلية</small></span>
            </a>
            <nav class="top-actions">
                <a class="icon-btn {{ request()->routeIs('experience-houses.manage.index', 'experience-houses.manage.show') ? 'active' : '' }}" href="{{ route('experience-houses.manage.index') }}">@include('experience-houses.manage._icon', ['name' => 'inbox', 'size' => 18])<span class="lbl">الطلبات</span></a>
                <a class="icon-btn {{ request()->routeIs('experience-houses.manage.team') ? 'active' : '' }}" href="{{ route('experience-houses.manage.team') }}">@include('experience-houses.manage._icon', ['name' => 'team', 'size' => 18])<span class="lbl">الفريق</span></a>
                <form method="POST" action="{{ route('experience-houses.manage.logout') }}" style="margin:0">
                    @csrf
                    <button class="icon-btn" title="تسجيل الخروج">@include('experience-houses.manage._icon', ['name' => 'logout', 'size' => 18])<span class="lbl">خروج</span></button>
                </form>
                <span class="avatar" title="{{ $manager->name }}">{{ $manager->initial() }}</span>
            </nav>
        </div>
    </header>
    @endisset

    @yield('body')

    @if (session('toast'))
        <div class="toast" role="status">@include('experience-houses.manage._icon', ['name' => 'check', 'size' => 20]) {{ session('toast') }}</div>
    @elseif (session('toast_error'))
        <div class="toast err" role="alert">@include('experience-houses.manage._icon', ['name' => 'x', 'size' => 20]) {{ session('toast_error') }}</div>
    @endif
</body>
</html>

@extends('experience-houses.manage._layout')
@section('title', 'الدخول')

@section('styles')
    body{min-height:100vh;display:flex;flex-direction:column;background:
        radial-gradient(110% 80% at 100% 100%,var(--navy) 0%,rgba(12,53,85,0) 55%),
        linear-gradient(165deg,var(--teal-700) 0%,var(--teal-600) 60%,#146a78 100%)}
    .login{flex:1;display:grid;place-items:center;padding:28px 16px}
    .box{width:100%;max-width:430px;background:#fff;border-radius:26px;padding:34px 28px 28px;position:relative;overflow:hidden;box-shadow:0 30px 60px -30px rgba(0,0,0,.5)}
    .box::before{content:'';position:absolute;top:0;left:0;width:110px;height:86px;background:linear-gradient(160deg,#46c2c1,#37b0b1);clip-path:polygon(0 0,100% 0,100% 18%,30% 100%,0 100%);border-radius:26px 0 0 0}
    .box::after{content:'';position:absolute;top:20px;left:22px;width:30px;height:30px;border-top:8px solid #fff;border-left:8px solid #fff;border-top-left-radius:3px}
    .logo{display:block;height:58px;margin:0 0 18px auto}
    h1{margin:0;color:var(--teal-900);font-size:26px;line-height:1.35}
    .sub{margin:6px 0 24px;color:var(--muted);font-size:15.5px}
    label{display:block;font-weight:700;color:var(--teal-950);margin-bottom:8px}
    .btn{width:100%;margin-top:16px;padding:15px}
    .sent{text-align:center}
    .sent .ic{width:76px;height:76px;margin:6px auto 16px;border-radius:24px 24px 24px 8px;background:var(--teal-50);color:var(--teal-600);display:grid;place-items:center}
    .sent b{direction:ltr;display:inline-block;color:var(--teal-900)}
    .tip{margin-top:18px;background:var(--pale);border-radius:14px;padding:12px 14px;font-size:14px;color:var(--muted);display:flex;gap:10px;align-items:flex-start}
    .alert{background:#fdf0ef;color:var(--danger);border-radius:14px;padding:12px 14px;margin-bottom:16px;font-size:14.5px;display:flex;gap:10px;align-items:center}
    .foot{color:rgba(255,255,255,.75);text-align:center;font-size:13px;padding:0 0 22px}
@endsection

@section('body')
<main class="login">
    <div class="box">
        <img class="logo" src="{{ asset('images/brand/kafaat-logo.svg') }}" alt="كفاءات">

        @if (session('sent_to'))
            <div class="sent">
                <div class="ic">@include('experience-houses.manage._icon', ['name' => 'mail', 'size' => 36])</div>
                <h1>تفقّد بريدك</h1>
                <p class="sub">إذا كان <b>{{ session('sent_to') }}</b> مسجلاً في الفريق، فقد أرسلنا له رابط الدخول الآن. اضغط الرابط وستدخل مباشرة.</p>
                <a class="btn ghost" href="{{ route('experience-houses.manage.login') }}">@include('experience-houses.manage._icon', ['name' => 'arrow-right', 'size' => 18]) استخدم بريداً آخر</a>
                <div class="tip">@include('experience-houses.manage._icon', ['name' => 'clock', 'size' => 18]) <span>الرابط صالح 30 دقيقة. لم يصلك؟ تأكد من مجلد الرسائل غير المرغوب فيها.</span></div>
            </div>
        @else
            <h1>طلبات بيوت الخبرة</h1>
            <p class="sub">اكتب بريدك وسنرسل لك رابط دخول. ما تحتاج كلمة مرور.</p>

            @if (session('link_error'))
                <div class="alert">@include('experience-houses.manage._icon', ['name' => 'clock', 'size' => 20]) {{ session('link_error') }}</div>
            @endif

            <form method="POST" action="{{ route('experience-houses.manage.login.send') }}">
                @csrf
                <label for="email">البريد الإلكتروني</label>
                <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" inputmode="email" dir="ltr" placeholder="name@example.com" style="text-align:right">
                @error('email')<div class="err">{{ $message }}</div>@enderror
                <button class="btn" type="submit">@include('experience-houses.manage._icon', ['name' => 'send', 'size' => 19]) أرسل رابط الدخول</button>
            </form>
        @endif
    </div>
</main>
<p class="foot">جمعية كفاءات الأهلية لبناء قدرات الشباب</p>
@endsection

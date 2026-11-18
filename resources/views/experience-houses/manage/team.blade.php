@extends('experience-houses.manage._layout')
@section('title', 'الفريق')

@section('styles')
    .page-h{margin:6px 0 18px}
    .page-h h1{margin:0;font-size:clamp(24px,4vw,30px);color:var(--teal-900)}
    .page-h p{margin:2px 0 0;color:var(--muted)}
    .grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:16px;align-items:start}
    @media(max-width:860px){.grid{grid-template-columns:1fr}}
    .member{display:flex;align-items:center;gap:12px;padding:14px 18px}
    .member+.member{border-top:1px solid var(--line)}
    .member .avatar{width:44px;height:44px}
    .member .info{flex:1;min-width:0}
    .member b{display:block;color:var(--teal-950)}
    .member small{color:var(--muted);direction:ltr;display:inline-block}
    .wait{background:#fdf3e2;color:var(--amber)}
    .ok{background:#e5f6ea;color:#2f8a4d}
    .mini{display:flex;gap:6px}
    .mini button{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;border:1px solid var(--line);background:#fff;color:var(--teal-700)}
    .mini button.del{color:var(--danger)}
    .mini button:hover{border-color:var(--teal-300)}
    .invite{padding:22px}
    .invite h2{margin:0 0 4px;color:var(--teal-900);font-size:20px;display:flex;align-items:center;gap:8px}
    .invite p{margin:0 0 16px;color:var(--muted);font-size:14.5px}
    .invite label{display:block;font-weight:700;color:var(--teal-950);margin:12px 0 6px;font-size:14.5px}
    .invite .btn{width:100%;margin-top:16px}
@endsection

@section('body')
<main class="wrap">
    <div class="page-h">
        <h1>الفريق</h1>
        <p>من يقدر يدخل ويتابع طلبات بيوت الخبرة</p>
    </div>

    <div class="grid">
        <div class="card">
            @foreach ($members as $m)
                <div class="member">
                    <span class="avatar">{{ $m->initial() }}</span>
                    <div class="info">
                        <b>{{ $m->name }} @if ($m->id === $manager->id)<span class="muted" style="font-weight:400">(أنت)</span>@endif</b>
                        <small>{{ $m->email }}</small>
                    </div>
                    @if ($m->activated_at)
                        <span class="pill ok">مفعّل</span>
                    @else
                        <span class="pill wait">بانتظار التفعيل</span>
                    @endif
                    @if ($m->id !== $manager->id)
                        <div class="mini">
                            @unless ($m->activated_at)
                                <form method="POST" action="{{ route('experience-houses.manage.team.resend', $m) }}">@csrf<button title="إعادة إرسال إيميل التفعيل">@include('experience-houses.manage._icon', ['name' => 'send', 'size' => 17])</button></form>
                            @endunless
                            <form method="POST" action="{{ route('experience-houses.manage.team.remove', $m) }}" onsubmit="return confirm('إزالة {{ $m->name }} من الفريق؟')">@csrf<button class="del" title="إزالة من الفريق">@include('experience-houses.manage._icon', ['name' => 'trash', 'size' => 17])</button></form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <form class="card invite" method="POST" action="{{ route('experience-houses.manage.team.invite') }}">
            @csrf
            <h2>@include('experience-houses.manage._icon', ['name' => 'plus', 'size' => 20]) إضافة شخص</h2>
            <p>سيصله إيميل فيه زر «إنشاء كلمة المرور»، ينشئ كلمته ثم يدخل بها مع بريده.</p>
            <label for="name">الاسم</label>
            <input class="input" id="name" name="name" value="{{ old('name') }}" required maxlength="120">
            @error('name')<div class="err">{{ $message }}</div>@enderror
            <label for="email">البريد الإلكتروني</label>
            <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" required dir="ltr" style="text-align:right" placeholder="name@example.com">
            @error('email')<div class="err">{{ $message }}</div>@enderror
            <button class="btn">@include('experience-houses.manage._icon', ['name' => 'send', 'size' => 18]) أرسل إيميل التفعيل</button>
        </form>
    </div>
</main>
@endsection

@extends('experience-houses.manage._layout')
@php use App\Models\ExperienceHouseRequest as R; @endphp

@section('styles')
    .hello{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:6px 0 20px}
    .hello h1{margin:0;font-size:clamp(24px,4vw,32px);color:var(--teal-900);line-height:1.3}
    .hello p{margin:2px 0 0;color:var(--muted)}
    .tabs{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:18px}
    .tab{display:flex;align-items:center;gap:12px;background:#fff;border:1.5px solid var(--line);border-radius:18px;padding:14px 16px;transition:all .15s}
    .tab:hover{border-color:var(--teal-300)}
    .tab .ic{width:44px;height:44px;border-radius:13px 13px 13px 4px;display:grid;place-items:center;background:var(--teal-50);color:var(--teal-600);flex:none}
    .tab b{display:block;font-size:24px;line-height:1.1;color:var(--teal-950)}
    .tab span{font-size:14px;color:var(--muted)}
    .tab.on{background:linear-gradient(135deg,var(--teal-600),var(--teal-700));border-color:transparent;box-shadow:0 12px 24px -14px rgba(23,113,121,.9)}
    .tab.on b,.tab.on span{color:#fff}
    .tab.on .ic{background:rgba(255,255,255,.18);color:#fff}
    @media(max-width:1000px){.tabs{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(max-width:760px){.tabs{grid-template-columns:repeat(2,minmax(0,1fr))}.tabs .tab:first-child{grid-column:1/-1}}
    .searchbar{position:relative;margin-bottom:16px}
    .searchbar .input{padding-inline-start:46px;background:#fff}
    .searchbar svg{position:absolute;top:50%;inset-inline-start:15px;transform:translateY(-50%);color:var(--muted)}
    .list{display:grid;gap:12px}
    .item{display:flex;align-items:center;gap:16px;padding:16px 18px;background:#fff;border:1px solid var(--line);border-radius:18px;transition:all .15s}
    .item:hover{border-color:var(--teal-300);transform:translateY(-1px);box-shadow:0 12px 24px -18px rgba(8,60,65,.5)}
    .org-ic{width:50px;height:50px;border-radius:15px 15px 15px 5px;background:var(--teal-100);color:var(--teal-700);display:grid;place-items:center;flex:none;font-weight:700;font-size:20px}
    .item-main{flex:1;min-width:0}
    .item-top{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .item-top h3{margin:0;font-size:17px;color:var(--teal-950);line-height:1.4}
    .urgent{background:#fdecea;color:var(--danger)}
    .meta{display:flex;gap:14px;flex-wrap:wrap;color:var(--muted);font-size:13.5px;margin-top:2px}
    .meta span{display:inline-flex;align-items:center;gap:5px}
    .deps{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
    .dep{display:inline-flex;align-items:center;gap:6px;background:var(--pale);border:1px solid var(--line);color:var(--teal-900);border-radius:10px;padding:3px 10px;font-size:13px}
    .dep svg{color:var(--teal-600)}
    .go{color:var(--teal-300);flex:none}
    .empty{text-align:center;padding:56px 20px;background:#fff;border:1.5px dashed var(--line);border-radius:22px}
    .empty .ic{width:80px;height:80px;margin:0 auto 14px;border-radius:26px 26px 26px 8px;background:var(--teal-50);color:var(--teal-500);display:grid;place-items:center}
    .empty h3{margin:0;color:var(--teal-900)}
    .pager{display:flex;justify-content:center;gap:10px;margin-top:18px}
    @media(max-width:560px){.hello .btn{padding:10px 14px;font-size:14px}.go{display:none}.item{padding:14px;gap:12px}.org-ic{width:42px;height:42px}}
@endsection

@section('body')
<main class="wrap">
    <div class="hello">
        <div>
            <h1>{{ now()->hour < 12 ? 'صباح الخير' : 'مساء الخير' }}، {{ $manager->firstName() }}</h1>
            <p>{{ $counts['new'] > 0 ? 'لديك '.$counts['new'].' '.($counts['new'] === 1 ? 'طلب جديد' : 'طلبات جديدة').' بانتظارك' : 'لا توجد طلبات جديدة الآن' }}</p>
        </div>
        <a class="btn light" href="{{ route('public.experience-houses.cover') }}" target="_blank">@include('experience-houses.manage._icon', ['name' => 'link', 'size' => 18]) صفحة الجمعيات</a>
    </div>

    <nav class="tabs">
        @foreach ($tabs as $key => $t)
            <a class="tab {{ $tab === $key ? 'on' : '' }}" href="{{ route('experience-houses.manage.index', ['tab' => $key]) }}">
                <span class="ic">@include('experience-houses.manage._icon', ['name' => $t['icon'], 'size' => 22])</span>
                <span><b>{{ $counts[$key] }}</b><span>{{ $t['label'] }}</span></span>
            </a>
        @endforeach
    </nav>

    <form class="searchbar" method="GET">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @include('experience-houses.manage._icon', ['name' => 'search', 'size' => 20])
        <input class="input" type="search" name="q" value="{{ $q }}" placeholder="ابحث باسم الجمعية أو المدينة">
    </form>

    @if ($requests->isEmpty())
        <div class="empty">
            <div class="ic">@include('experience-houses.manage._icon', ['name' => $q !== '' ? 'search' : $tabs[$tab]['icon'], 'size' => 38])</div>
            <h3>{{ $q !== '' ? 'لا توجد نتائج' : 'لا يوجد شيء هنا' }}</h3>
            <p class="muted">{{ $q !== '' ? 'جرّب كلمة أخرى.' : 'ستظهر الطلبات هنا عند وصولها.' }}</p>
        </div>
    @else
        <div class="list">
            @foreach ($requests as $r)
                <a class="item" href="{{ route('experience-houses.manage.show', $r) }}">
                    <span class="org-ic">@include('experience-houses.manage._icon', ['name' => 'building', 'size' => 24])</span>
                    <div class="item-main">
                        <div class="item-top">
                            <h3>{{ $r->organization_name }}</h3>
                            @if ($r->priority === 'urgent')<span class="pill urgent">@include('experience-houses.manage._icon', ['name' => 'flame', 'size' => 14]) عاجل</span>@endif
                            @if (in_array($tab, ['active', 'all'], true))<span class="pill st-{{ $r->status }}">{{ R::STATUSES[$r->status] }}</span>@endif
                        </div>
                        <div class="meta">
                            <span>@include('experience-houses.manage._icon', ['name' => 'pin', 'size' => 15]) {{ $r->city }}</span>
                            <span>@include('experience-houses.manage._icon', ['name' => 'clock', 'size' => 15]) {{ $r->created_at->locale('ar')->diffForHumans() }}</span>
                        </div>
                        <div class="deps">
                            @foreach (array_slice($r->departments ?? [], 0, 3) as $d)
                                <span class="dep">@include('experience-houses.manage._icon', ['name' => R::DEPARTMENT_ICONS[$d] ?? 'dots', 'size' => 15]) {{ R::DEPARTMENTS[$d] ?? $d }}</span>
                            @endforeach
                            @if (count($r->departments ?? []) > 3)<span class="dep" dir="ltr">+{{ count($r->departments) - 3 }}</span>@endif
                        </div>
                    </div>
                    <span class="go">@include('experience-houses.manage._icon', ['name' => 'arrow-left', 'size' => 22])</span>
                </a>
            @endforeach
        </div>
        @if ($requests->hasPages())
            <div class="pager">
                @if ($requests->previousPageUrl())<a class="btn light" href="{{ $requests->previousPageUrl() }}">السابق</a>@endif
                @if ($requests->nextPageUrl())<a class="btn light" href="{{ $requests->nextPageUrl() }}">التالي</a>@endif
            </div>
        @endif
    @endif
</main>
@endsection

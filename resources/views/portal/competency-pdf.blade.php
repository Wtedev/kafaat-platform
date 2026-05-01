<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>كفاءات — {{ $user->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
            direction: rtl;
            line-height: 1.5;
            padding: 12mm 14mm;
        }
        h1 { font-size: 20px; color: #253B5B; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #6b7280; margin-bottom: 12px; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 9px;
            background: #EAF2FA;
            color: #253B5B;
            margin-left: 6px;
        }
        h2 {
            font-size: 12px;
            color: #253B5B;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin: 14px 0 8px;
        }
        .muted { color: #9ca3af; font-size: 10px; }
        .block { margin-bottom: 8px; white-space: pre-wrap; }
        ul { padding-right: 16px; margin: 6px 0; }
        li { margin-bottom: 4px; }
        .tag {
            font-size: 8px;
            background: #f3f4f6;
            color: #4b5563;
            padding: 1px 5px;
            border-radius: 3px;
        }
        .footer { margin-top: 16px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $user->name }}</h1>
    <p class="meta" dir="ltr" style="text-align: right;">{{ $user->email }}</p>
    <p>
        <span class="badge">{{ $membership->label() }}</span>
        @if (filled($profile?->iconic_skill))
        <span class="badge" style="background:#FFFBEB;color:#92400E;">{{ $profile->iconic_skill }}</span>
        @endif
    </p>

    @if (filled($profile?->bio))
    <h2>نبذة</h2>
    <div class="block">{{ $profile->bio }}</div>
    @endif

    @if (filled($profile?->cvSection('education')))
    <h2>التعليم</h2>
    <div class="block">{{ $profile->cvSection('education') }}</div>
    @endif

    @if (filled($profile?->cvSection('languages')))
    <h2>اللغات</h2>
    <div class="block">{{ $profile->cvSection('languages') }}</div>
    @endif

    @if (filled($profile?->cvSection('skills')) || count($competencyCards) > 0)
    <h2>المهارات</h2>
    @if (filled($profile?->cvSection('skills')))
    <div class="block">{{ $profile->cvSection('skills') }}</div>
    @endif
    @if (count($competencyCards) > 0)
    <ul>
        @foreach ($competencyCards as $c)
        <li><strong>{{ $c['title'] }}:</strong> {{ $c['level'] }}</li>
        @endforeach
    </ul>
    @endif
    @endif

    @if (filled($profile?->cvSection('external_training')))
    <h2>الدورات والشهادات الخارجية</h2>
    <div class="block">{{ $profile->cvSection('external_training') }}</div>
    @endif

    @if (filled($profile?->cvSection('experience')))
    <h2>الخبرات أو المشاركات</h2>
    <div class="block">{{ $profile->cvSection('experience') }}</div>
    @endif

    @php $pdfLinks = $profile?->cvLinksList() ?? []; @endphp
    @if (count($pdfLinks) > 0)
    <h2>روابط</h2>
    <ul>
        @foreach ($pdfLinks as $l)
        <li>{{ $l['label'] }} — {{ $l['url'] }}</li>
        @endforeach
    </ul>
    @endif

    <h2>إنجازات المنصة <span class="tag">تلقائي</span></h2>
    <p><strong>المسارات المكتملة:</strong>
        @forelse ($completedPaths as $reg)
        {{ $reg->learningPath?->title }}@if(!$loop->last) — @endif
        @empty
        —
        @endforelse
    </p>
    <p><strong>البرامج المكتملة:</strong>
        @forelse ($completedPrograms as $reg)
        {{ $reg->trainingProgram?->title }}@if(!$loop->last) — @endif
        @empty
        —
        @endforelse
    </p>
    <p><strong>التطوع المكتمل:</strong>
        @forelse ($completedVolunteering as $reg)
        {{ $reg->opportunity?->title }}@if(!$loop->last) — @endif
        @empty
        —
        @endforelse
    </p>
    <p><strong>الشهادات:</strong>
        @forelse ($platformCertificates as $cert)
        {{ \App\Services\Portal\CompetencyProfilePresenter::certificateTitle($cert) }}@if(!$loop->last) — @endif
        @empty
        —
        @endforelse
    </p>
    <p><strong>ساعات التطوع المعتمدة:</strong> {{ number_format($approvedVolunteerHours, 1) }}</p>

    <h2>التوصيات</h2>
    @forelse ($recommendations as $rec)
    <p class="block" style="font-style:italic;">«{{ $rec->body }}»<br/>
        <span style="font-style:normal;font-size:10px;color:#6b7280;">— {{ $rec->author_name }}@if(filled($rec->author_title))، {{ $rec->author_title }}@endif</span>
    </p>
    @empty
    <p class="muted">لا توجد توصيات مضافة حتى الآن</p>
    @endforelse

    <p class="footer">وثيقة مُنشأة من منصة كفاءات — {{ now()->translatedFormat('j F Y') }}</p>
</body>
</html>

<!DOCTYPE html>
@php
    use App\Services\Portal\CvFormOptions;
    use App\Services\Portal\CvUiTranslator;

    $p = $profile;
    $loc = $cvLocale ?? 'ar';
    $L = $cvLabels ?? CvUiTranslator::sectionLabels($loc);
    $isRtl = $loc !== 'en';
    $edge = $isRtl ? 'right' : 'left';
    $pdfJobTitle = $pdfJobTitle ?? ($p?->pdfHeadlineForExport($membership, $loc) ?? null);
    $city = trim((string) ($p?->city ?? ''));
    $skillsPdf = $p?->cvSkillsStructured() ?? [];
    $lngPdf = $p?->cvLanguagesStructured() ?? [];
    $toolsPdf = $p?->cvOfficeToolsStructured() ?? [];
    $eduPdf = $p?->cvEducationStructured() ?? [];
    $expMerged = $mergedExperience ?? [];
    $coursesMerged = $mergedCourses ?? [];
    $pdfLinks = $p?->cvLinksList() ?? [];
    $linkTypeLabels = CvFormOptions::linkTypeLabels();
    $linkTypeEn = ['LinkedIn' => 'LinkedIn', 'GitHub' => 'GitHub', 'Portfolio' => 'Portfolio', 'Website' => 'Website', 'Other' => 'Other'];

    $vis = fn (string $k): bool => $p ? $p->cvSectionVisible($k) : true;

    $hasBio = $vis('bio') && filled($p?->bio);
    $hasSkills = $vis('skills') && count($skillsPdf) > 0;
    $hasLang = $vis('languages') && count($lngPdf) > 0;
    $hasTools = $vis('office_tools') && count($toolsPdf) > 0;
    $hasEdu = $vis('education') && count($eduPdf) > 0;
    $hasExp = $vis('experience') && count($expMerged) > 0;
    $hasCourses = $vis('external_courses') && count($coursesMerged) > 0;
    $hasLinks = $vis('links') && count($pdfLinks) > 0;
    $hasPaths = $vis('platform') && $completedPaths->isNotEmpty();
    $hasPlatformHours = $vis('platform') && ($approvedVolunteerHours ?? 0) > 0;
    $hasPlatform = $vis('platform') && ($hasPaths || $hasPlatformHours);
    $hasRecs = $vis('recommendations') && ($recommendations?->isNotEmpty() ?? false);
@endphp
<html lang="{{ $loc }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8" />
    <title>{{ $user->name }}</title>
    <style>
        * { font-family: cairo !important; }
        body {
            font-family: cairo, sans-serif !important;
            font-size: 12px;
            line-height: 1.6;
            color: #111827;
            margin: 0;
        }
        body.rtl-doc {
            direction: rtl;
            text-align: right;
        }
        body.ltr-doc {
            direction: ltr;
            text-align: left;
        }
        .doc-header {
            margin-bottom: 8px;
            padding-bottom: 14px;
            border-bottom: 1px solid #e5e7eb;
        }
        .name {
            font-size: 20px;
            font-weight: 700;
            color: #1e3a5f;
            margin: 0 0 4px 0;
        }
        .job-title {
            font-size: 14px;
            color: #555;
            margin: 0 0 8px 0;
            font-weight: 600;
        }
        .header-line {
            font-size: 12px;
            margin: 4px 0;
            color: #374151;
        }
        .label {
            font-weight: 700;
            color: #4b5563;
        }
        .email {
            direction: ltr;
            unicode-bidi: embed;
            display: inline-block;
            text-align: left;
        }
        .section {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
        }
        .section-title {
            font-weight: 700;
            font-size: 14px;
            color: #1e3a5f;
            margin: 0 0 6px 0;
        }
        .text {
            font-size: 12px;
            line-height: 1.7;
            margin: 0;
        }
        .muted {
            color: #777;
            font-size: 11px;
        }
        .summary-body {
            white-space: pre-wrap;
            font-size: 12px;
            line-height: 1.7;
            margin: 0;
        }
        .badges-wrap {
            margin: 0;
            line-height: 1.9;
        }
        .badge {
            display: inline-block;
            background: #f3f4f6;
            color: #111827;
            font-size: 11px;
            padding: 3px 8px;
            margin: 2px 4px 2px 0;
            border-radius: 2px;
        }
        .badge-meta {
            color: #6b7280;
            font-weight: 600;
        }
        .list-line {
            font-size: 12px;
            margin: 0 0 4px 0;
            padding-{{ $edge }}: 8px;
        }
        .bullet {
            color: #1e3a5f;
            font-weight: 700;
        }
        .timeline-item {
            margin: 0 0 14px 0;
            padding-{{ $edge }}: 12px;
            border-{{ $edge }}: 2px solid #1e3a5f;
        }
        .exp-title {
            font-weight: 700;
            font-size: 12px;
            margin: 0 0 2px 0;
        }
        .exp-org {
            margin: 0 0 4px 0;
        }
        .exp-tags, .exp-dates {
            margin: 0 0 4px 0;
        }
        .exp-desc {
            margin: 6px 0 0 0;
            white-space: pre-wrap;
        }
        .edu-block, .course-block {
            margin: 0 0 12px 0;
        }
        .edu-title, .course-title {
            font-weight: 700;
            font-size: 12px;
            margin: 0 0 2px 0;
        }
        .rec-block {
            margin: 0 0 12px 0;
            font-size: 12px;
        }
        .rec-body {
            font-style: italic;
            margin: 0 0 4px 0;
        }
        .rec-by {
            font-style: normal;
            font-size: 11px;
            color: #6b7280;
        }
    </style>
</head>
<body class="{{ $isRtl ? 'rtl-doc' : 'ltr-doc' }}">
    <header class="doc-header">
        <h1 class="name">{{ $user->name }}</h1>
        @if (filled($pdfJobTitle))
        <p class="job-title">{{ $pdfJobTitle }}</p>
        @endif
        @if (filled($user->email))
        <p class="header-line"><span class="label">{{ $L['email'] ?? 'Email' }}:</span> <span class="email">{{ $user->email }}</span></p>
        @endif
        @if ($city !== '')
        <p class="header-line"><span class="label">{{ $L['city'] ?? '' }}:</span> {{ $city }}</p>
        @endif
    </header>

    @if ($hasBio)
    <div class="section">
        <p class="section-title">{{ $L['summary'] }}</p>
        <p class="summary-body">{{ $p->bio }}</p>
    </div>
    @endif

    @if ($hasSkills)
    <div class="section">
        <p class="section-title">{{ $L['skills'] }}</p>
        <p class="badges-wrap">
            @foreach ($skillsPdf as $s)
            <span class="badge">{{ $s['skill_name'] }} <span class="badge-meta">{{ CvFormOptions::skillLevelLabel($s['level'] ?? '', $loc) }}@if (! empty($s['category'])) · {{ $s['category'] }}@endif</span></span>
            @endforeach
        </p>
    </div>
    @endif

    @if ($hasLang)
    <div class="section">
        <p class="section-title">{{ $L['languages'] }}</p>
        @foreach ($lngPdf as $lng)
        <p class="list-line text"><span class="bullet">•</span>
            @if(!empty($lng['highlight_english']))<strong>@endif{{ $lng['language_name'] }}@if(!empty($lng['highlight_english']))</strong>@endif<span class="muted"> — {{ CvFormOptions::languageLevelLabel($lng['level'] ?? '', $loc) }}</span>
        </p>
        @endforeach
    </div>
    @endif

    @if ($hasTools)
    <div class="section">
        <p class="section-title">{{ $L['tools'] }}</p>
        @foreach ($toolsPdf as $t)
        <p class="list-line text"><span class="bullet">•</span> {{ $t['tool_name'] }}<span class="muted"> — {{ CvFormOptions::skillLevelLabel($t['level'] ?? '', $loc) }}</span></p>
        @endforeach
    </div>
    @endif

    @if ($hasExp)
    <div class="section">
        <p class="section-title">{{ $L['experience'] }}</p>
        @foreach ($expMerged as $ex)
        <div class="timeline-item">
            <p class="exp-title">{{ $ex['title'] ?? '' }}@if(filled($ex['organization'] ?? null)) <span class="muted">— {{ $ex['organization'] }}</span>@endif</p>
            <p class="exp-org muted">{{ CvFormOptions::employmentLabel((string) ($ex['employment_type'] ?? ''), $loc) }} · {{ CvFormOptions::workModeLabel((string) ($ex['type'] ?? ''), $loc) }}@if (($ex['source'] ?? '') === 'platform_volunteer') · {{ $L['platform_auto'] ?? '' }}@endif</p>
            <p class="exp-dates muted">
                @if (!empty($ex['is_current']))
                {{ $ex['start_date'] ?? '' }} — {{ $L['current'] }}
                @else
                {{ $ex['start_date'] ?? '' }}@if(filled($ex['end_date'] ?? null)) — {{ $ex['end_date'] }} @endif
                @endif
            </p>
            @if (filled($ex['description'] ?? null))
            <p class="exp-desc text">{{ $ex['description'] }}</p>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    @if ($hasEdu)
    <div class="section">
        <p class="section-title">{{ $L['education'] }}</p>
        @foreach ($eduPdf as $ed)
        <div class="edu-block">
            <p class="edu-title">{{ $ed['institution'] }}</p>
            <p class="text muted">{{ $ed['degree_or_program'] ?? '' }}@if(!empty($ed['field'])) · {{ $ed['field'] }} @endif</p>
            <p class="muted">
                @if (!empty($ed['is_current']))
                {{ $ed['start_year'] ?? '?' }} — {{ $L['current'] }}
                @else
                {{ $ed['start_year'] ?? '?' }} — {{ $ed['end_year'] ?? '?' }}
                @endif
            </p>
        </div>
        @endforeach
    </div>
    @endif

    @if ($hasCourses)
    <div class="section">
        <p class="section-title">{{ $L['courses'] }}</p>
        @foreach ($coursesMerged as $crs)
        <div class="course-block">
            <p class="course-title">{{ $crs['title'] }}</p>
            @if (filled($crs['provider'] ?? null))
            <p class="text muted">{{ $crs['provider'] }}</p>
            @endif
            @if (filled($crs['date'] ?? null))
            <p class="muted">{{ $crs['date'] }}</p>
            @endif
            @if (filled($crs['certificate_url'] ?? null))
            <p class="muted"><span class="email">{{ $crs['certificate_url'] }}</span></p>
            @endif
            @if (filled($crs['description'] ?? null))
            <p class="text">{{ $crs['description'] }}</p>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    @if ($hasLinks)
    <div class="section">
        <p class="section-title">{{ $L['links'] }}</p>
        @foreach ($pdfLinks as $l)
        <p class="list-line text"><span class="bullet">•</span>
            {{ $l['label'] }}@if (!empty($l['type'])) <span class="muted">({{ $loc === 'en' ? ($linkTypeEn[$l['type']] ?? $l['type']) : ($linkTypeLabels[$l['type']] ?? $l['type']) }})</span>@endif
            <span class="muted"> — </span><span class="email">{{ $l['url'] }}</span>
        </p>
        @endforeach
    </div>
    @endif

    @if ($hasPlatform)
    <div class="section">
        <p class="section-title">{{ $loc === 'en' ? 'Achievements' : $L['platform'] }}</p>
        @if ($hasPaths)
        <p class="text" style="font-weight:700;margin-bottom:4px;">{{ $L['learning_paths'] ?? '' }}</p>
        @foreach ($completedPaths as $reg)
        <p class="list-line text"><span class="bullet">•</span> {{ $reg->learningPath?->title ?? '—' }}</p>
        @endforeach
        @endif
        @if ($hasPlatformHours)
        <p class="text" style="margin-top:8px;"><span class="label">{{ $L['volunteer_hours'] ?? '' }}:</span> {{ number_format($approvedVolunteerHours, 1) }}</p>
        @endif
    </div>
    @endif

    @if ($hasRecs)
    <div class="section">
        <p class="section-title">{{ $L['recommendations'] }}</p>
        @foreach ($recommendations as $rec)
        <div class="rec-block">
            <p class="rec-body">«{{ $rec->body }}»</p>
            <p class="rec-by">— {{ $rec->author_name }}@if(filled($rec->author_title))، {{ $rec->author_title }}@endif</p>
        </div>
        @endforeach
    </div>
    @endif
</body>
</html>

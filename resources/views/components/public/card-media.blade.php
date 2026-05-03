@props([
    'hasImage' => false,
    'imageUrl' => '',
    'alt' => '',
    /** فهرس لاختيار تدرج من مجموعة الأخبار (كتالوج) */
    'index' => 0,
    /** catalog: h-48 + أيقونة w-14 | hero: h-56 + أيقونة w-20 (مثل عرض الخبر) | thumb: مربع صغير للقوائم الفرعية */
    'variant' => 'catalog',
    /**
     * نوع المحتوى للأيقونة عند غياب الصورة:
     * path | learning_path → مسار، volunteer → تطوع، program (افتراضي) → برنامج حسب programKind
     */
    'mediaContext' => 'program',
    /** قيمة TrainingProgramKind: course | session | workshop | bootcamp | event (عند mediaContext=program) */
    'programKind' => null,
])

@php
    $catalogBgs = [
        'linear-gradient(135deg, #EAF2FA, #DCE8F5)',
        'linear-gradient(135deg, #ECFDF5, #D1FAE5)',
        'linear-gradient(135deg, #FFF7ED, #FED7AA)',
        'linear-gradient(135deg, #F5F3FF, #DDD6FE)',
        'linear-gradient(135deg, #FFF1F2, #FFE4E6)',
        'linear-gradient(135deg, #F0FDF4, #BBF7D0)',
    ];
    $heroBg = 'linear-gradient(135deg, #EAF2FA, #DCE8F5)';

    $ctx = strtolower((string) $mediaContext);
    $pk = '';
    if ($programKind instanceof \BackedEnum) {
        $pk = strtolower((string) $programKind->value);
    } elseif ($programKind !== null && $programKind !== '') {
        $pk = strtolower((string) $programKind);
    }

    $iconKey = match (true) {
        in_array($ctx, ['path', 'learning_path'], true) => 'path',
        in_array($ctx, ['volunteer', 'volunteering'], true) => 'volunteer',
        default => match ($pk) {
            'session' => 'session',
            'workshop' => 'workshop',
            'bootcamp' => 'bootcamp',
            'event' => 'event',
            default => 'course',
        },
    };

    // Heroicons 24/outline — مسار / برنامج (دورة، لقاء، ورشة، معسكر، فعالية) / تطوع
    $iconPaths = match ($iconKey) {
        'path' => [
            'M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z',
        ],
        'volunteer' => [
            'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
        ],
        'session' => [
            'M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z',
        ],
        'workshop' => [
            'M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605',
        ],
        'bootcamp' => [
            'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
        ],
        'event' => [
            'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
        ],
        default => [
            'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
        ],
    };

    if ($variant === 'hero') {
        $heightWrap = 'rounded-2xl h-56 flex items-center justify-center mb-8';
        $bgStyle = $heroBg;
        $iconWrap = 'w-20 h-20 opacity-25';
        $imgWrap = 'rounded-2xl overflow-hidden mb-8';
        $imgClass = 'w-full max-h-[420px] object-cover';
    } elseif ($variant === 'thumb') {
        $heightWrap = 'h-full w-full flex items-center justify-center rounded-lg';
        $bgStyle = $catalogBgs[$index % count($catalogBgs)];
        $iconWrap = 'w-10 h-10 opacity-30';
        $imgWrap = '';
        $imgClass = 'h-full w-full object-cover';
    } else {
        $heightWrap = 'h-48 flex items-center justify-center';
        $bgStyle = $catalogBgs[$index % count($catalogBgs)];
        $iconWrap = 'w-14 h-14 opacity-30';
        $imgWrap = 'h-48 overflow-hidden';
        $imgClass = 'h-full w-full object-cover group-hover:scale-105 transition-transform duration-300';
    }
@endphp

@if ($hasImage)
    @if ($variant === 'hero')
        <div class="{{ $imgWrap }}">
            <img
                src="{{ $imageUrl }}"
                alt="{{ $alt }}"
                class="{{ $imgClass }}"
                loading="eager"
                decoding="async"
            />
        </div>
    @elseif ($variant === 'thumb')
        <div class="h-20 w-20 shrink-0 overflow-hidden rounded-lg ring-1 ring-gray-100">
            <img
                src="{{ $imageUrl }}"
                alt="{{ $alt }}"
                class="{{ $imgClass }}"
                loading="lazy"
                decoding="async"
            />
        </div>
    @else
        <div class="{{ $imgWrap }}">
            <img
                src="{{ $imageUrl }}"
                alt="{{ $alt }}"
                class="{{ $imgClass }}"
                loading="lazy"
                decoding="async"
            />
        </div>
    @endif
@else
    @if ($variant === 'hero')
        <div class="{{ $heightWrap }}" style="background: {{ $bgStyle }}">
            <svg class="{{ $iconWrap }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" style="color:#253B5B" aria-hidden="true">
                @foreach ($iconPaths as $d)
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}" />
                @endforeach
            </svg>
        </div>
    @elseif ($variant === 'thumb')
        <div class="h-20 w-20 shrink-0 overflow-hidden rounded-lg ring-1 ring-gray-100">
            <div class="{{ $heightWrap }}" style="background: {{ $bgStyle }}">
                <svg class="{{ $iconWrap }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" style="color:#253B5B" aria-hidden="true">
                    @foreach ($iconPaths as $d)
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}" />
                    @endforeach
                </svg>
            </div>
        </div>
    @else
        <div class="{{ $heightWrap }}" style="background: {{ $bgStyle }}">
            <svg class="{{ $iconWrap }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" style="color:#253B5B" aria-hidden="true">
                @foreach ($iconPaths as $d)
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}" />
                @endforeach
            </svg>
        </div>
    @endif
@endif

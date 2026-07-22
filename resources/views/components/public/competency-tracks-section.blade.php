{{--
    Horizontal accordion — expand a track to see its brief and programs.
--}}
@props([
    'programCounts' => collect(),
    'trackPrograms' => collect(),
])

@php
    use App\Enums\CompetencyTrack;
    use App\Support\CompetencyTrackCatalog;

    $counts = collect($programCounts);
    $programsByTrack = collect($trackPrograms);
    $trackKeys = CompetencyTrackCatalog::order();
    $defaultTrack = $trackKeys[0] ?? CompetencyTrack::Self->value;

    $collapsedLabels = [
        CompetencyTrack::Self->value => 'الذاتية',
        CompetencyTrack::Professional->value => 'المهنية',
        CompetencyTrack::Community->value => 'المجتمعية',
    ];
@endphp

<section class="reveal-fade" aria-labelledby="competency-tracks-heading">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-right">
            <p class="mb-1 text-sm font-semibold" style="color:#1a9399">
                {{ config('competency_tracks.intro.badge') }}
            </p>
            <h2 id="competency-tracks-heading" class="text-2xl font-bold text-brand">
                {{ config('competency_tracks.intro.title', 'ثلاثة مسارات للتمكين') }}
            </h2>
        </div>
        <a
            href="{{ route('public.tracks.index') }}"
            class="tracks-about-link inline-flex shrink-0 items-center gap-1.5 self-end text-sm"
        >
            <span>عن المسارات</span>
            <span class="tracks-about-icon" aria-hidden="true">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
        </a>
    </div>

    <div
        id="track-accordion"
        class="track-accordion"
        dir="rtl"
        data-default-track="{{ $defaultTrack }}"
        role="tablist"
        aria-label="مسارات الكفاءة"
    >
        @foreach ($trackKeys as $trackKey)
            @php
                $track = CompetencyTrack::from($trackKey);
                $cfg = CompetencyTrackCatalog::trackConfig($track);
                $color = $cfg['color'] ?? '#1a9399';
                $gradientFrom = $cfg['gradient_from'] ?? $color;
                $gradientTo = $cfg['gradient_to'] ?? $color;
                $gradientBloom = $cfg['gradient_bloom'] ?? $gradientTo;
                $programs = $programsByTrack->get($trackKey, collect());
                $count = (int) ($counts[$trackKey] ?? 0);
                $isDefault = $trackKey === $defaultTrack;
                $trackGlow = $cfg['panel_glow'] ?? $color.'4d';
                $driftDelay = ($loop->index ?? 0) * -2.8;
            @endphp

            <article
                class="track-panel{{ $isDefault ? ' is-active is-expanded' : '' }}"
                data-track="{{ $trackKey }}"
                style="--track-color: {{ $color }}; --track-base: {{ $gradientFrom }}; --track-light: {{ $gradientTo }}; --track-bloom: {{ $gradientBloom }}; --track-glow: {{ $trackGlow }}; --track-drift-delay: {{ $driftDelay }}s;"
                role="presentation"
            >
                <div class="track-panel-bg" aria-hidden="true"></div>
                <button
                    type="button"
                    class="track-panel-trigger"
                    role="tab"
                    id="track-tab-{{ $trackKey }}"
                    aria-controls="track-panel-{{ $trackKey }}"
                    aria-selected="{{ $isDefault ? 'true' : 'false' }}"
                    aria-expanded="{{ $isDefault ? 'true' : 'false' }}"
                    aria-label="توسيع {{ $track->shortLabel() }}"
                    tabindex="{{ $isDefault ? '0' : '-1' }}"
                >
                    <span class="track-collapsed-label text-white">{{ $collapsedLabels[$trackKey] ?? $track->shortLabel() }}</span>
                    <span class="track-expand-chevron" aria-hidden="true">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
                        </svg>
                    </span>
                </button>

                <div
                    class="track-panel-body"
                    id="track-panel-{{ $trackKey }}"
                    role="tabpanel"
                    aria-labelledby="track-tab-{{ $trackKey }}"
                    @unless ($isDefault) hidden @endunless
                >
                    <div class="track-panel-top">
                        <div class="track-panel-intro">
                            <h3 class="track-panel-title text-white">{{ $track->shortLabel() }}</h3>
                            <p class="track-panel-desc clamp-2 text-white">{{ $cfg['description'] ?? '' }}</p>
                        </div>
                    </div>

                    @if ($programs->isNotEmpty())
                        <div class="track-program-grid">
                            @foreach ($programs as $index => $program)
                                @php $descriptionExcerpt = $program->descriptionExcerpt(); @endphp
                                <a href="{{ route('public.programs.show', $program->slug) }}" class="track-program-card group">
                                    <x-public.card-media
                                        variant="catalog"
                                        mediaContext="program"
                                        :programKind="$program->program_kind"
                                        :hasImage="filled($program->image)"
                                        :imageUrl="$program->imagePublicUrl()"
                                        objectFit="cover"
                                        :alt="$program->title"
                                        :index="$index"
                                    />
                                    <div class="track-program-body">
                                        <h4 class="track-program-title">{{ $program->title }}</h4>
                                        @if (filled($descriptionExcerpt))
                                            <p class="track-program-desc">{{ $descriptionExcerpt }}</p>
                                        @endif
                                        <div class="track-program-cta">
                                            عرض البرنامج
                                            <svg class="h-3.5 w-3.5 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>

                        @if ($count > $programs->count())
                            <a href="{{ route('public.programs.track', $track) }}" class="track-view-all">
                                عرض كل البرامج ({{ en_num($count) }})
                            </a>
                        @endif
                    @else
                        <div class="track-empty">
                            <p class="track-empty-text text-white">لا توجد برامج منشورة حالياً.</p>
                            <a href="{{ route('public.programs.track', $track) }}" class="track-empty-link">تصفّح المسار</a>
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>

@once
    <style>
        .tracks-about-link {
            color: #9ca3af;
            font-weight: 400;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .tracks-about-link:hover {
            color: #6b7280;
        }

        .tracks-about-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.35rem;
            height: 1.35rem;
            border-radius: 9999px;
            border: 1px solid #d1d5db;
            color: #9ca3af;
            flex-shrink: 0;
        }

        .tracks-about-link:hover .tracks-about-icon {
            border-color: #9ca3af;
            color: #6b7280;
        }

        .track-accordion {
            --track-expand-duration: 0.85s;
            --track-expand-ease: cubic-bezier(0.45, 0, 0.15, 1);
            --track-content-reveal-at: 0.48;
            --track-content-reveal: 0.48s;
            --track-content-fade: 0.5s;
            --track-content-ease: cubic-bezier(0.33, 1, 0.45, 1);
            display: flex;
            flex-direction: row;
            gap: 0.65rem;
            min-height: 30rem;
            padding: 0.75rem;
            border-radius: 1.5rem;
            background: linear-gradient(180deg, #ffffff 0%, #eef2f7 100%);
            border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.9),
                0 24px 60px -20px rgba(51, 84, 131, 0.18);
            overflow: hidden;
        }

        .track-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            flex: 0 0 7.5rem;
            min-width: 7.5rem;
            border-radius: 1.1rem;
            background: var(--track-base);
            color: #fff;
            overflow: hidden;
            cursor: pointer;
            isolation: isolate;
            transition:
                flex var(--track-expand-duration) var(--track-expand-ease),
                min-width var(--track-expand-duration) var(--track-expand-ease),
                min-height var(--track-expand-duration) var(--track-expand-ease),
                box-shadow 0.45s ease,
                transform 0.45s ease;
        }

        .track-panel-bg {
            position: absolute;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-color: var(--track-base);
            background-image:
                radial-gradient(
                    ellipse 72% 58% at 78% 18%,
                    color-mix(in srgb, var(--track-bloom) 36%, var(--track-base)) 0%,
                    transparent 70%
                ),
                linear-gradient(
                    148deg,
                    var(--track-base) 0%,
                    color-mix(in srgb, var(--track-light) 38%, var(--track-base)) 100%
                );
            background-size: 125% 125%, 100% 100%;
            background-position: 74% 14%, center;
            animation: track-bg-drift 30s ease-in-out infinite alternate;
            animation-delay: var(--track-drift-delay, 0s);
        }

        .track-panel-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0.025;
            pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            mix-blend-mode: soft-light;
        }

        @keyframes track-bg-drift {
            0% {
                background-position: 70% 12%, center;
            }
            100% {
                background-position: 84% 20%, center;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .track-panel-bg {
                animation: none;
            }
        }

        .track-panel:not(.is-active):hover {
            box-shadow: 0 8px 28px var(--track-glow);
            transform: translateY(-2px) scale(1.015);
            z-index: 2;
        }

        .track-panel.is-active {
            flex: 1 1 0%;
            min-width: 0;
            cursor: default;
            transform: none;
            box-shadow:
                0 12px 48px var(--track-glow),
                0 4px 16px rgba(15, 23, 42, 0.12);
            z-index: 1;
        }

        .track-panel-trigger,
        .track-panel-body {
            position: relative;
            z-index: 2;
        }

        .track-panel-trigger {
            display: flex;
            flex: 1;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.85rem;
            width: 100%;
            padding: 1.35rem 0.85rem;
            border: none;
            background: transparent;
            color: inherit;
            cursor: pointer;
            transition: background 0.25s ease, opacity 0.22s ease, visibility 0.22s ease;
        }

        .track-panel-trigger:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .track-panel-trigger:focus-visible {
            outline: 2px solid rgba(255, 255, 255, 0.9);
            outline-offset: -5px;
        }

        .track-expand-chevron {
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.88;
            transition: transform 0.25s ease, opacity 0.25s ease;
        }

        .track-expand-chevron svg {
            width: 1.25rem;
            height: 1.25rem;
            stroke-width: 2.5;
        }

        .track-panel:not(.is-active):hover .track-expand-chevron {
            opacity: 1;
            transform: translateX(-2px);
        }

        .track-collapsed-label {
            font-size: 0.98rem;
            font-weight: 800;
            line-height: 1.35;
            text-align: center;
            white-space: normal;
            color: #fff;
            text-shadow: 0 1px 8px rgba(0, 0, 0, 0.15);
        }

        .track-panel.is-active .track-panel-trigger {
            position: absolute;
            inset: 0;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .track-panel-body {
            display: flex;
            flex: 1;
            flex-direction: column;
            min-height: 0;
            min-width: 0;
            padding: 2.35rem 1.65rem 1.65rem;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: translate3d(0, 0.45rem, 0);
        }

        .track-panel.is-active .track-panel-body {
            position: absolute;
            inset: 0;
        }

        .track-panel.is-active.is-expanded .track-panel-body {
            position: relative;
            visibility: visible;
            pointer-events: auto;
            animation: track-body-reveal var(--track-content-fade) var(--track-content-ease) both;
        }

        .track-panel.is-active.is-expanded .track-panel-top {
            animation: track-content-lift calc(var(--track-content-reveal) * 0.92) var(--track-content-ease) 0.03s both;
        }

        .track-panel.is-active.is-expanded .track-program-grid {
            animation: track-content-lift calc(var(--track-content-reveal) * 0.92) var(--track-content-ease) 0.06s both;
        }

        .track-panel.is-active.is-expanded .track-empty {
            animation: track-content-fade-in calc(var(--track-content-reveal) * 1.05) cubic-bezier(0.4, 0, 0.2, 1) 0.1s both;
        }

        .track-panel.is-active.is-expanded .track-view-all {
            animation: track-content-lift calc(var(--track-content-reveal) * 0.88) var(--track-content-ease) 0.09s both;
        }

        @keyframes track-body-reveal {
            0% {
                opacity: 0;
                transform: translate3d(0, 0.45rem, 0);
            }
            28% {
                opacity: 0.22;
            }
            58% {
                opacity: 0.68;
            }
            100% {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        @keyframes track-content-lift {
            from {
                opacity: 0.72;
                transform: translate3d(0, 0.35rem, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        @keyframes track-content-fade-in {
            0% {
                opacity: 0;
            }
            35% {
                opacity: 0.35;
            }
            70% {
                opacity: 0.78;
            }
            100% {
                opacity: 0.95;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .track-accordion {
                --track-expand-duration: 0.01ms;
                --track-content-reveal: 0.01ms;
                --track-content-fade: 0.01ms;
            }

            .track-panel.is-active .track-panel-body {
                position: relative;
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                transform: none;
                animation: none;
            }

            .track-panel.is-active.is-expanded .track-panel-top,
            .track-panel.is-active.is-expanded .track-program-grid,
            .track-panel.is-active.is-expanded .track-empty,
            .track-panel.is-active.is-expanded .track-view-all {
                animation: none;
            }
        }

        .track-panel-top {
            margin-bottom: 1.15rem;
            padding-bottom: 1.15rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.18);
            flex-shrink: 0;
        }

        .track-panel-intro {
            flex: 1;
            min-width: 0;
        }

        /*
         | Brand typography (html:not(.fi) h3/h4/p) paints titles teal/blue.
         | Colored track panels need white copy — scoped here only.
         */
        .track-panel .track-panel-title,
        .track-panel .track-collapsed-label {
            color: #fff;
        }

        .track-panel .track-panel-desc,
        .track-panel .track-empty-text {
            color: rgba(255, 255, 255, 0.92);
        }

        .track-panel-title {
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1.35;
            margin-bottom: 0.45rem;
        }

        .track-panel-desc {
            font-size: 0.875rem;
            line-height: 1.65;
            opacity: 0.9;
            max-width: 36rem;
        }

        .track-program-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 0.15rem;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.35) transparent;
        }

        .track-program-card {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-radius: 1rem;
            background: #fff;
            border: 1px solid #f3f4f6;
            color: #111827;
            text-align: right;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .track-program-card:hover {
            transform: translateY(-0.25rem);
            box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.1), 0 4px 6px -4px rgba(15, 23, 42, 0.1);
        }

        /* Slightly shorter cover than full catalog pages so cards fit the accordion panel. */
        .track-program-card .h-48 {
            height: 8.5rem;
        }

        /* Always fill the media frame — no letterboxing around wide logos. */
        .track-program-card .h-48 img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            padding: 0;
        }

        .track-program-body {
            display: flex;
            flex: 1;
            flex-direction: column;
            padding: 1rem 1.15rem 1.15rem;
        }

        /* White program cards — keep dark titles (beat global h4 brand color). */
        .track-program-card .track-program-title {
            margin-bottom: 0.4rem;
            font-size: 0.9375rem;
            font-weight: 700;
            line-height: 1.4;
            color: #111827;
            transition: color 0.2s ease;
        }

        .track-program-card:hover .track-program-title {
            color: #335483;
        }

        .track-program-desc {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            margin: 0;
            font-size: 0.8125rem;
            line-height: 1.55;
            color: #6b7280;
        }

        .track-program-cta {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.35rem;
            margin-top: auto;
            padding-top: 0.85rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #335483;
        }

        .track-view-all {
            display: inline-flex;
            align-items: center;
            margin-top: 1rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.92);
            text-decoration: underline;
            text-underline-offset: 3px;
            align-self: flex-start;
            transition: color 0.2s ease, opacity 0.2s ease;
        }

        .track-view-all:hover {
            color: #fff;
            opacity: 1;
        }

        .track-empty {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem 1rem;
            width: 100%;
            padding: 1.15rem 1.25rem;
            border-radius: 0.85rem;
            background: rgba(255, 255, 255, 0.1);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .track-empty-text {
            margin: 0;
            opacity: 0.92;
        }

        .track-empty-link {
            font-weight: 600;
            color: #fff;
            text-decoration: underline;
            text-underline-offset: 3px;
            white-space: nowrap;
        }

        .track-empty-link:hover {
            opacity: 0.9;
        }

        /*
         | Mobile stacks panels vertically, but expand/collapse must use the
         | same duration/easing as desktop. Explicit height endpoints let the
         | panel grow/shrink smoothly (flex/min-height alone snaps when the
         | body is toggled with [hidden]).
         */
        @media (max-width: 1023px) {
            .track-accordion {
                flex-direction: column;
                min-height: 0;
                gap: 0.35rem;
            }

            .track-panel {
                flex: 0 0 auto;
                min-width: 0;
                width: 100%;
                height: 3.5rem;
                min-height: 0;
                transition:
                    height var(--track-expand-duration) var(--track-expand-ease),
                    box-shadow 0.45s ease,
                    transform 0.45s ease;
            }

            .track-panel:not(.is-active) {
                flex: 0 0 auto;
            }

            .track-panel.is-active {
                flex: 0 0 auto;
                height: 28rem;
                min-height: 0;
            }

            .track-panel-trigger {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                min-height: 3.25rem;
                padding: 0.85rem 1rem;
                gap: 0.75rem;
            }

            .track-expand-chevron {
                order: -1;
            }

            .track-collapsed-label {
                flex: 1;
                text-align: right;
                font-size: 0.95rem;
            }
        }

        @media (max-width: 639px) {
            .track-program-grid {
                grid-template-columns: 1fr;
            }

            .track-panel-body {
                padding: 1.85rem 1.15rem 1.15rem;
            }
        }
    </style>

    <script>
        (function () {
            const accordion = document.getElementById('track-accordion');
            if (!accordion) return;

            const panels = Array.from(accordion.querySelectorAll('.track-panel'));
            const triggers = Array.from(accordion.querySelectorAll('.track-panel-trigger'));
            const isRtl = accordion.getAttribute('dir') === 'rtl';
            const stepForward = isRtl ? -1 : 1;
            const stepBack = isRtl ? 1 : -1;
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const expandMs = reduceMotion
                ? 0
                : Math.round(parseFloat(getComputedStyle(accordion).getPropertyValue('--track-expand-duration')) * 1000) || 850;
            const revealRatio = reduceMotion
                ? 0
                : parseFloat(getComputedStyle(accordion).getPropertyValue('--track-content-reveal-at')) || 0.48;
            const revealDelay = Math.round(expandMs * revealRatio);

            function revealContent(panel) {
                if (panel.classList.contains('is-active')) {
                    panel.classList.add('is-expanded');
                    const body = panel.querySelector('.track-panel-body');
                    if (body) {
                        body.setAttribute('aria-hidden', 'false');
                    }
                }
            }

            function activate(trackKey) {
                panels.forEach((panel) => {
                    const isActive = panel.dataset.track === trackKey;
                    const trigger = panel.querySelector('.track-panel-trigger');
                    const body = panel.querySelector('.track-panel-body');

                    clearTimeout(panel._expandTimer);
                    panel.classList.remove('is-expanded');

                    panel.classList.toggle('is-active', isActive);

                    if (trigger) {
                        trigger.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        trigger.setAttribute('aria-expanded', isActive ? 'true' : 'false');
                        trigger.tabIndex = isActive ? 0 : -1;
                    }

                    if (body) {
                        if (isActive) {
                            body.removeAttribute('hidden');
                            body.setAttribute('aria-hidden', 'true');

                            if (reduceMotion) {
                                revealContent(panel);
                            } else {
                                panel._expandTimer = window.setTimeout(() => revealContent(panel), revealDelay);
                            }
                        } else {
                            body.setAttribute('hidden', '');
                            body.setAttribute('aria-hidden', 'true');
                        }
                    }
                });
            }

            function focusAdjacent(currentIndex, direction) {
                const nextIndex = (currentIndex + direction + triggers.length) % triggers.length;
                triggers[nextIndex].focus();
                activate(triggers[nextIndex].closest('.track-panel').dataset.track);
            }

            triggers.forEach((trigger, index) => {
                trigger.addEventListener('click', () => {
                    activate(trigger.closest('.track-panel').dataset.track);
                });

                trigger.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                        event.preventDefault();
                        focusAdjacent(index, event.key === 'ArrowRight' ? stepForward : 1);
                    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        focusAdjacent(index, event.key === 'ArrowLeft' ? stepBack : -1);
                    } else if (event.key === 'Home') {
                        event.preventDefault();
                        triggers[0].focus();
                        activate(triggers[0].closest('.track-panel').dataset.track);
                    } else if (event.key === 'End') {
                        event.preventDefault();
                        triggers[triggers.length - 1].focus();
                        activate(triggers[triggers.length - 1].closest('.track-panel').dataset.track);
                    }
                });
            });

            const defaultTrack = accordion.dataset.defaultTrack || panels[0]?.dataset.track;
            const defaultPanel = panels.find((panel) => panel.dataset.track === defaultTrack);

            if (defaultPanel?.classList.contains('is-expanded')) {
                const body = defaultPanel.querySelector('.track-panel-body');
                if (body) {
                    body.setAttribute('aria-hidden', 'false');
                }
            } else {
                activate(defaultTrack);
            }
        })();
    </script>
@endonce

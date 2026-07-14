<x-filament-panels::page>
    @php
        $stats = $this->stats;
        $fmt = static fn (int $n): string => number_format($n);
    @endphp

    <div class="eps" dir="rtl">
        <header class="eps-hero">
            <div class="eps-hero__text">
                <p class="eps-hero__eyebrow">الأمان والامتثال</p>
                <h2 class="eps-hero__title">إحصاءات صفحات الأخطاء</h2>
                <p class="eps-hero__desc">
                    عدد مرات عرض صفحات الأخطاء التي تُقدّمها المنصة نفسها (Laravel).
                    صفحة Railway «Application failed to respond» تظهر عندما لا يستجيب الحاوية أصلاً، ولذلك لا تُحسب هنا.
                </p>
            </div>
            <button
                type="button"
                class="eps-refresh"
                wire:click="refreshStats"
                wire:loading.attr="disabled"
            >
                <x-heroicon-o-arrow-path class="h-4 w-4" wire:loading.class="animate-spin" />
                تحديث
            </button>
        </header>

        <div class="eps-grid" role="list">
            <article class="eps-card eps-card--gateway" role="listitem">
                <div class="eps-card__icon" aria-hidden="true">
                    <x-heroicon-o-cloud class="h-6 w-6" />
                </div>
                <p class="eps-card__label">تعذّر الاستجابة / بوابة</p>
                <p class="eps-card__value">{{ $fmt($stats['gateway']) }}</p>
                <p class="eps-card__meta">اليوم: {{ $fmt($stats['today']['gateway']) }}</p>
                <p class="eps-card__hint">يشمل 502 و 503 و 504 عندما تستطيع Laravel عرض الصفحة المخصّصة</p>
            </article>

            <article class="eps-card eps-card--server" role="listitem">
                <div class="eps-card__icon" aria-hidden="true">
                    <x-heroicon-o-exclamation-triangle class="h-6 w-6" />
                </div>
                <p class="eps-card__label">خطأ خادم (500)</p>
                <p class="eps-card__value">{{ $fmt($stats['server_error']) }}</p>
                <p class="eps-card__meta">اليوم: {{ $fmt($stats['today']['server_error']) }}</p>
                <p class="eps-card__hint">يشمل HTTP 500، وأي ظهور نادر لـ 505 إن وُجد</p>
            </article>

            <article class="eps-card eps-card--notfound" role="listitem">
                <div class="eps-card__icon" aria-hidden="true">
                    <x-heroicon-o-magnifying-glass class="h-6 w-6" />
                </div>
                <p class="eps-card__label">صفحة غير موجودة (404)</p>
                <p class="eps-card__value">{{ $fmt($stats['not_found']) }}</p>
                <p class="eps-card__meta">اليوم: {{ $fmt($stats['today']['not_found']) }}</p>
                <p class="eps-card__hint">طلبات HTML التي وصلت للمنصة ولم تُعثر على مسارها</p>
            </article>
        </div>

        <section class="eps-note" aria-label="حدود القياس">
            <h3 class="eps-note__title">ما الذي يُحسب؟</h3>
            <ul class="eps-note__list">
                <li>تُسجَّل الزيارات عندما يستجيب التطبيق ويعرض صفحة الخطأ العربية ذات العلامة التجارية.</li>
                <li>إذا توقّف الحاوية أو لم يردّ، تعرض بوابة Railway صفحتها الخاصة — ولا يمكن للمنصة رصد ذلك من الداخل.</li>
                <li>لتقليل ظهور صفحة Railway: تحقّق الصحة على <code>/up</code>، وإبقاء الخدمة مستيقظة، وتداخل نشر بدون توقف.</li>
            </ul>
        </section>
    </div>

    <style>
        .eps { display: flex; flex-direction: column; gap: 1.25rem; }
        .eps-hero {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.15rem 1.25rem;
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: linear-gradient(135deg, rgba(51, 84, 131, 0.22), rgba(26, 147, 153, 0.08));
        }
        .eps-hero__eyebrow {
            margin: 0 0 0.35rem;
            font-size: 0.75rem;
            letter-spacing: 0.04em;
            color: rgba(226, 232, 240, 0.7);
        }
        .eps-hero__title {
            margin: 0 0 0.4rem;
            font-size: 1.2rem;
            font-weight: 700;
            color: #f8fafc;
        }
        .eps-hero__desc {
            margin: 0;
            max-width: 42rem;
            font-size: 0.875rem;
            line-height: 1.7;
            color: rgba(226, 232, 240, 0.78);
        }
        .eps-refresh {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            min-height: 2.4rem;
            padding: 0.45rem 0.9rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(148, 163, 184, 0.28);
            background: rgba(15, 23, 42, 0.35);
            color: #e2e8f0;
            font: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
        }
        .eps-refresh:hover { background: rgba(15, 23, 42, 0.55); }
        .eps-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }
        @media (max-width: 960px) {
            .eps-grid { grid-template-columns: 1fr; }
        }
        .eps-card {
            padding: 1.2rem 1.15rem 1.1rem;
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: rgba(24, 24, 27, 0.55);
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.18);
        }
        .eps-card__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            margin-bottom: 0.85rem;
            border-radius: 0.8rem;
        }
        .eps-card--gateway .eps-card__icon { background: rgba(251, 187, 46, 0.15); color: #fbbb2e; }
        .eps-card--server .eps-card__icon { background: rgba(236, 96, 86, 0.15); color: #ec6056; }
        .eps-card--notfound .eps-card__icon { background: rgba(51, 84, 131, 0.22); color: #93b4d8; }
        .eps-card__label {
            margin: 0 0 0.35rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: #e2e8f0;
        }
        .eps-card__value {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
            color: #fff;
            font-variant-numeric: tabular-nums;
        }
        .eps-card__meta {
            margin: 0.35rem 0 0;
            font-size: 0.8rem;
            color: rgba(226, 232, 240, 0.65);
        }
        .eps-card__hint {
            margin: 0.75rem 0 0;
            font-size: 0.78rem;
            line-height: 1.6;
            color: rgba(148, 163, 184, 0.95);
        }
        .eps-note {
            padding: 1rem 1.15rem;
            border-radius: 0.95rem;
            border: 1px solid rgba(148, 163, 184, 0.14);
            background: rgba(15, 23, 42, 0.28);
        }
        .eps-note__title {
            margin: 0 0 0.55rem;
            font-size: 0.9rem;
            font-weight: 700;
            color: #e2e8f0;
        }
        .eps-note__list {
            margin: 0;
            padding-inline-start: 1.15rem;
            color: rgba(226, 232, 240, 0.75);
            font-size: 0.84rem;
            line-height: 1.75;
        }
        .eps-note code {
            font-size: 0.8em;
            padding: 0.05rem 0.35rem;
            border-radius: 0.3rem;
            background: rgba(51, 84, 131, 0.35);
            color: #c7d7ec;
        }
    </style>
</x-filament-panels::page>

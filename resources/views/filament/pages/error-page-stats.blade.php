<x-filament-panels::page>
    @php
        $stats = $this->stats;
        $fmt = static fn (int $n): string => number_format($n);
        $maxDaily = max(1, ...array_map(fn ($d) => (int) $d['hits'], $stats['daily'] ?: [['hits' => 0]]));
        $statusLabels = [
            403 => 'غير مصرح',
            404 => 'غير موجود',
            419 => 'انتهت الجلسة',
            429 => 'طلبات كثيرة',
            500 => 'خطأ خادم',
            502 => 'بوابة',
            503 => 'غير متاح',
            504 => 'انتهاء المهلة',
            505 => 'إصدار HTTP',
        ];
    @endphp

    <div class="eps" dir="rtl">
        <header class="eps-hero">
            <div class="eps-hero__text">
                <p class="eps-hero__eyebrow">الأمان والامتثال</p>
                <h2 class="eps-hero__title">إحصاءات صفحات الأخطاء</h2>
                <p class="eps-hero__desc">
                    زيارات صفحات الأخطاء التي تُقدّمها المنصة نفسها (Laravel).
                    صفحة Railway «Application failed to respond» تظهر عندما لا يستجيب الحاوية، ولذلك لا تُحسب هنا.
                </p>
            </div>
        </header>

        <form wire:submit="applyFilters" class="eps-filters" aria-label="فلاتر الإحصاءات">
            <label class="eps-field">
                <span>من تاريخ</span>
                <input type="date" wire:model.live="filterFrom" class="eps-input" />
            </label>
            <label class="eps-field">
                <span>إلى تاريخ</span>
                <input type="date" wire:model.live="filterTo" class="eps-input" />
            </label>
            <label class="eps-field">
                <span>رمز الحالة</span>
                <select wire:model.live="filterStatus" class="eps-input">
                    <option value="">الكل</option>
                    @foreach([403,404,419,429,500,502,503,504,505] as $code)
                        <option value="{{ $code }}">{{ $code }} — {{ $statusLabels[$code] ?? $code }}</option>
                    @endforeach
                </select>
            </label>
            <label class="eps-field eps-field--grow">
                <span>الرابط يحتوي على</span>
                <input type="search" wire:model.live.debounce.400ms="filterUrl" class="eps-input" placeholder="/path" autocomplete="off" />
            </label>
            <div class="eps-filter-actions">
                <button type="submit" class="eps-btn eps-btn--primary">تطبيق</button>
                <button type="button" class="eps-btn" wire:click="clearFilters">مسح</button>
            </div>
        </form>

        <div class="eps-grid eps-grid--4" role="list">
            <article class="eps-card" role="listitem">
                <p class="eps-card__label">إجمالي (حسب الفلتر)</p>
                <p class="eps-card__value">{{ $fmt($stats['total']) }}</p>
            </article>
            <article class="eps-card" role="listitem">
                <p class="eps-card__label">اليوم</p>
                <p class="eps-card__value">{{ $fmt($stats['today']) }}</p>
            </article>
            <article class="eps-card" role="listitem">
                <p class="eps-card__label">آخر 7 أيام</p>
                <p class="eps-card__value">{{ $fmt($stats['last_7_days']) }}</p>
            </article>
            <article class="eps-card" role="listitem">
                <p class="eps-card__label">آخر 30 يومًا</p>
                <p class="eps-card__value">{{ $fmt($stats['last_30_days']) }}</p>
            </article>
        </div>

        <section class="eps-panel" aria-label="التوزيع اليومي">
            <h3 class="eps-panel__title">الرسم اليومي (30 يومًا)</h3>
            <div class="eps-chart" role="img" aria-label="رسم بياني لعدد الأخطاء يوميًا">
                @foreach($stats['daily'] as $day)
                    @php $h = (int) round(((int) $day['hits'] / $maxDaily) * 100); @endphp
                    <div class="eps-chart__col" title="{{ $day['date'] }}: {{ $day['hits'] }}">
                        <div class="eps-chart__bar" style="height: {{ max($h, $day['hits'] > 0 ? 4 : 0) }}%"></div>
                        <span class="eps-chart__label">{{ \Illuminate\Support\Str::substr($day['date'], 5) }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="eps-split">
            <section class="eps-panel" aria-label="حسب رمز الحالة">
                <h3 class="eps-panel__title">حسب رمز الحالة</h3>
                @if(($stats['by_status'] ?? []) === [])
                    <p class="eps-empty">لا توجد بيانات.</p>
                @else
                    <ul class="eps-list">
                        @foreach($stats['by_status'] as $code => $hits)
                            <li>
                                <span>{{ $code }} — {{ $statusLabels[(int) $code] ?? 'أخرى' }}</span>
                                <strong>{{ $fmt((int) $hits) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="eps-panel" aria-label="أكثر الروابط">
                <h3 class="eps-panel__title">أكثر الروابط أخطاءً</h3>
                @if(($stats['top_urls'] ?? []) === [])
                    <p class="eps-empty">لا توجد بيانات.</p>
                @else
                    <ul class="eps-list eps-list--urls">
                        @foreach($stats['top_urls'] as $row)
                            <li>
                                <code title="{{ $row['url'] }}">{{ \Illuminate\Support\Str::limit($row['url'], 64) }}</code>
                                <strong>{{ $fmt((int) $row['hits']) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="eps-panel" aria-label="أكثر صفحات 404">
                <h3 class="eps-panel__title">أكثر صفحات 404</h3>
                @if(($stats['top_404s'] ?? []) === [])
                    <p class="eps-empty">لا توجد بيانات.</p>
                @else
                    <ul class="eps-list eps-list--urls">
                        @foreach($stats['top_404s'] as $row)
                            <li>
                                <code title="{{ $row['url'] }}">{{ \Illuminate\Support\Str::limit($row['url'], 64) }}</code>
                                <strong>{{ $fmt((int) $row['hits']) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <section class="eps-panel" aria-label="آخر الأخطاء">
            <h3 class="eps-panel__title">آخر الأخطاء المسجّلة</h3>
            <div class="eps-table-wrap">
                <table class="eps-table">
                    <thead>
                        <tr>
                            <th>الوقت</th>
                            <th>الحالة</th>
                            <th>الطريقة</th>
                            <th>الرابط</th>
                            <th>المستخدم</th>
                            <th>متصفح (مختصر)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->recentVisits as $visit)
                            <tr wire:key="visit-{{ $visit->id }}">
                                <td>{{ $visit->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                <td>{{ $visit->status_code }}</td>
                                <td>{{ $visit->request_method }}</td>
                                <td><code title="{{ $visit->requested_url }}">{{ \Illuminate\Support\Str::limit($visit->requested_url, 48) }}</code></td>
                                <td>{{ $visit->user?->name ?? '—' }}</td>
                                <td title="{{ $visit->user_agent }}">{{ \Illuminate\Support\Str::limit($visit->user_agent ?? '—', 40) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="eps-empty">لا توجد سجلات.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="eps-pagination">
                {{ $this->recentVisits->links() }}
            </div>
        </section>

        <section class="eps-note" aria-label="حدود القياس">
            <h3 class="eps-note__title">ما الذي يُحسب؟</h3>
            <ul class="eps-note__list">
                <li>تُسجَّل الزيارات عندما يستجيب التطبيق ويعرض صفحة الخطأ العربية.</li>
                <li>لا تُحسب فحوصات <code>/up</code> ولا طلبات JSON/API ولا أصول الضوضاء مثل favicon.</li>
                <li>تُزال معاملات الاستعلام الحساسة (كلمات المرور، الرموز، …) من الرابط قبل الحفظ.</li>
                <li>إذا توقّف الحاوية، تعرض بوابة Railway صفحتها الخاصة — ولا يمكن للمنصة رصد ذلك من الداخل.</li>
            </ul>
        </section>
    </div>

    <style>
        .eps { display: flex; flex-direction: column; gap: 1.15rem; }
        .eps-hero {
            padding: 1.15rem 1.25rem;
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: linear-gradient(135deg, rgba(51, 84, 131, 0.22), rgba(26, 147, 153, 0.08));
        }
        .eps-hero__eyebrow { margin: 0 0 0.35rem; font-size: 0.75rem; color: rgba(226, 232, 240, 0.7); }
        .eps-hero__title { margin: 0 0 0.4rem; font-size: 1.2rem; font-weight: 700; color: #f8fafc; }
        .eps-hero__desc { margin: 0; max-width: 46rem; font-size: 0.875rem; line-height: 1.7; color: rgba(226, 232, 240, 0.78); }
        .eps-filters {
            display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: end;
            padding: 1rem; border-radius: 1rem; border: 1px solid rgba(148, 163, 184, 0.16);
            background: rgba(24, 24, 27, 0.45);
        }
        .eps-field { display: flex; flex-direction: column; gap: 0.35rem; min-width: 9rem; font-size: 0.8rem; color: rgba(226,232,240,0.75); }
        .eps-field--grow { flex: 1 1 14rem; }
        .eps-input {
            min-height: 2.4rem; padding: 0.4rem 0.65rem; border-radius: 0.65rem;
            border: 1px solid rgba(148, 163, 184, 0.28); background: rgba(15, 23, 42, 0.45); color: #e2e8f0; font: inherit;
        }
        .eps-filter-actions { display: flex; gap: 0.5rem; }
        .eps-btn {
            min-height: 2.4rem; padding: 0.4rem 0.9rem; border-radius: 0.65rem;
            border: 1px solid rgba(148, 163, 184, 0.28); background: rgba(15, 23, 42, 0.35); color: #e2e8f0;
            font: inherit; font-size: 0.85rem; font-weight: 600; cursor: pointer;
        }
        .eps-btn--primary { background: rgba(51, 84, 131, 0.85); border-color: rgba(51, 84, 131, 0.9); }
        .eps-grid { display: grid; gap: 0.85rem; }
        .eps-grid--4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        @media (max-width: 1100px) { .eps-grid--4 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 640px) { .eps-grid--4 { grid-template-columns: 1fr; } }
        .eps-card {
            padding: 1rem 1.05rem; border-radius: 1rem; border: 1px solid rgba(148, 163, 184, 0.16);
            background: rgba(24, 24, 27, 0.55);
        }
        .eps-card__label { margin: 0 0 0.35rem; font-size: 0.85rem; color: #e2e8f0; }
        .eps-card__value { margin: 0; font-size: 1.75rem; font-weight: 700; color: #fff; font-variant-numeric: tabular-nums; }
        .eps-panel {
            padding: 1rem 1.1rem; border-radius: 1rem; border: 1px solid rgba(148, 163, 184, 0.14);
            background: rgba(15, 23, 42, 0.28);
        }
        .eps-panel__title { margin: 0 0 0.75rem; font-size: 0.95rem; font-weight: 700; color: #e2e8f0; }
        .eps-split { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.85rem; }
        @media (max-width: 1100px) { .eps-split { grid-template-columns: 1fr; } }
        .eps-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.55rem; }
        .eps-list li {
            display: flex; justify-content: space-between; gap: 0.75rem; align-items: start;
            font-size: 0.84rem; color: rgba(226,232,240,0.85);
        }
        .eps-list code { font-size: 0.8em; word-break: break-all; color: #c7d7ec; }
        .eps-empty { margin: 0; font-size: 0.85rem; color: rgba(148,163,184,0.95); text-align: center; padding: 0.75rem; }
        .eps-chart {
            display: flex; align-items: end; gap: 0.2rem; height: 9rem; overflow-x: auto; padding-bottom: 0.25rem;
        }
        .eps-chart__col { flex: 1 0 0.55rem; min-width: 0.55rem; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: end; gap: 0.25rem; }
        .eps-chart__bar { width: 100%; max-width: 0.85rem; border-radius: 0.25rem 0.25rem 0 0; background: linear-gradient(180deg, #93b4d8, #335483); }
        .eps-chart__label { font-size: 0.55rem; color: rgba(148,163,184,0.85); writing-mode: horizontal-tb; transform: rotate(-45deg); transform-origin: top center; height: 1.5rem; white-space: nowrap; }
        .eps-table-wrap { overflow-x: auto; }
        .eps-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; color: rgba(226,232,240,0.9); }
        .eps-table th, .eps-table td { padding: 0.55rem 0.45rem; border-bottom: 1px solid rgba(148,163,184,0.12); text-align: right; vertical-align: top; }
        .eps-table th { color: rgba(226,232,240,0.7); font-weight: 600; }
        .eps-table code { font-size: 0.8em; color: #c7d7ec; }
        .eps-pagination { margin-top: 0.85rem; }
        .eps-note { padding: 1rem 1.15rem; border-radius: 0.95rem; border: 1px solid rgba(148, 163, 184, 0.14); background: rgba(15, 23, 42, 0.28); }
        .eps-note__title { margin: 0 0 0.55rem; font-size: 0.9rem; font-weight: 700; color: #e2e8f0; }
        .eps-note__list { margin: 0; padding-inline-start: 1.15rem; color: rgba(226, 232, 240, 0.75); font-size: 0.84rem; line-height: 1.75; }
        .eps-note code { font-size: 0.8em; padding: 0.05rem 0.35rem; border-radius: 0.3rem; background: rgba(51, 84, 131, 0.35); color: #c7d7ec; }
    </style>
</x-filament-panels::page>

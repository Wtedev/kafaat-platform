@php
    use App\Enums\SurveyQuestionType;
    use App\Enums\SurveyType;

    $tz = config('app.timezone');
    $when = fn ($at) => $at ? en_digits(ar_date($at->copy()->timezone($tz), 'd MMM y')).' — '.$at->copy()->timezone($tz)->format('H:i') : '—';
    $titles = [
        'pre' => ['الاستبيان القبلي', 'قبل البرنامج، يقيس مستوى المتدرب في البداية.'],
        'post' => ['الاستبيان البعدي', 'نفس أسئلة القبلي بعد البرنامج، للمقارنة.'],
        'satisfaction' => ['استبيان الرضا', 'مجهول: لا يظهر اسم المتدرب مع إجاباته.'],
    ];
    $tabUrl = route('staff-ui.programs.tab', [$program, 'surveys']);
@endphp

@if ($errors->any())
    <p class="sui-alert sui-alert--danger" role="alert">{{ $errors->first() }}</p>
@endif

@if ($editingSurvey)
    @php
        $stats = $surveyStats[$editingSurvey->id];
        $isPost = $editingSurvey->type === SurveyType::Post;
        $editable = ! $isPost && ! $stats['locked'];
        $questions = old('questions', $editingSurvey->questions->map(fn ($q) => [
            'type' => $q->type->value,
            'prompt' => $q->prompt,
            'options' => implode("\n", $q->options ?? []),
            'scale_min_label' => $q->scale_min_label,
            'scale_max_label' => $q->scale_max_label,
            'required' => $q->required,
        ])->all());
        $local = fn ($at) => $at?->copy()->timezone($tz)->format('Y-m-d\TH:i');
    @endphp
    <form method="POST" action="{{ route('staff-ui.programs.surveys.update', [$program, $editingSurvey]) }}" class="sui-stack" data-survey-editor>
        @csrf
        <section class="sui-card">
            <div class="sui-card__head sui-card__head--row">
                <div>
                    <a class="sui-back" href="{{ $tabUrl }}">الاستبيانات</a>
                    <h2>تعديل {{ $titles[$editingSurvey->type->value][0] }}</h2>
                </div>
            </div>
            <div class="sui-form-grid sui-survey-window">
                <x-staff-ui.input type="datetime-local" name="opens_at" label="يفتح" :value="old('opens_at', $local($editingSurvey->opens_at))" />
                <x-staff-ui.input type="datetime-local" name="closes_at" label="يغلق" :value="old('closes_at', $local($editingSurvey->closes_at))" />
            </div>
        </section>

        <section class="sui-card">
            <div class="sui-card__head">
                <h2>الأسئلة</h2>
                @if ($isPost)
                    <p>أسئلة البعدي نسخة من القبلي، وتتغيّر تلقائياً لما تعدّلين القبلي.</p>
                @elseif ($stats['locked'])
                    <p>وصلت ردود على القبلي أو البعدي، فالأسئلة مقفلة حتى تبقى المقارنة بينهما صحيحة.</p>
                @elseif ($editingSurvey->type === SurveyType::Pre)
                    <p>رتّبي الأسئلة بالأسهم. أي تعديل هنا ينتقل للبعدي تلقائياً.</p>
                @else
                    <p>رتّبي الأسئلة بالأسهم.</p>
                @endif
            </div>

            @if ($editable)
                <input type="hidden" name="has_questions" value="1">
                <ol class="sui-survey-questions" data-survey-list>
                    @foreach ($questions as $i => $q)
                        @include('staff-ui.programs.partials.survey-question', ['i' => $i, 'q' => $q])
                    @endforeach
                </ol>
                <template data-survey-template>
                    @include('staff-ui.programs.partials.survey-question', ['i' => '__I__', 'q' => ['type' => 'scale', 'prompt' => '', 'options' => '', 'scale_min_label' => '', 'scale_max_label' => '', 'required' => true]])
                </template>
                <div class="sui-survey-add">
                    <x-staff-ui.button type="button" variant="secondary" size="sm" icon="plus" data-survey-add>إضافة سؤال</x-staff-ui.button>
                </div>
            @else
                <ol class="sui-survey-readonly">
                    @foreach ($editingSurvey->questions as $q)
                        <li>
                            <strong>{{ $q->prompt }}</strong>
                            <small>{{ $q->type->label() }}@if (! $q->required) · اختياري@endif</small>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <div class="sui-survey-actions">
            <x-staff-ui.button variant="ghost" size="md" :href="$tabUrl">إلغاء</x-staff-ui.button>
            <x-staff-ui.button type="submit" size="md">حفظ</x-staff-ui.button>
        </div>
    </form>

    @if ($editable)
        <script>
            (() => {
                const list = document.querySelector('[data-survey-list]');
                const template = document.querySelector('[data-survey-template]').innerHTML;
                const renumber = () => {
                    [...list.children].forEach((item, index) => {
                        item.querySelectorAll('[name^="questions["]').forEach((field) => {
                            field.name = field.name.replace(/^questions\[[^\]]*\]/, `questions[${index}]`);
                        });
                        item.querySelector('[data-survey-num]').textContent = index + 1;
                    });
                };
                const sync = (item) => {
                    const type = item.querySelector('[data-survey-type]').value;
                    item.querySelector('[data-survey-scale]').hidden = type !== 'scale';
                    item.querySelector('[data-survey-options]').hidden = !['single', 'multiple'].includes(type);
                };
                list.querySelectorAll('[data-survey-item]').forEach(sync);
                document.querySelector('[data-survey-add]').addEventListener('click', () => {
                    list.insertAdjacentHTML('beforeend', template.replaceAll('__I__', list.children.length));
                    const item = list.lastElementChild;
                    sync(item);
                    renumber();
                    window.lucide?.createIcons();
                    item.querySelector('textarea, input[type="text"]')?.focus();
                });
                list.addEventListener('change', (e) => {
                    if (e.target.matches('[data-survey-type]')) sync(e.target.closest('[data-survey-item]'));
                });
                list.addEventListener('click', (e) => {
                    const button = e.target.closest('[data-survey-move], [data-survey-remove]');
                    if (!button) return;
                    const item = button.closest('[data-survey-item]');
                    if (button.matches('[data-survey-remove]')) {
                        item.remove();
                    } else if (button.dataset.surveyMove === 'up' && item.previousElementSibling) {
                        item.previousElementSibling.before(item);
                    } else if (button.dataset.surveyMove === 'down' && item.nextElementSibling) {
                        item.nextElementSibling.after(item);
                    }
                    renumber();
                });
            })();
        </script>
    @endif
@elseif ($surveys->isEmpty())
    <section class="sui-card">
        <div class="sui-card__head">
            <h2>الاستبيانات</h2>
            <p>لكل برنامج ثلاثة استبيانات: قبلي، وبعدي بنفس أسئلة القبلي، ورضا مجهول. تُنشأ مرة وحدة وتعدّلينها بعدها.</p>
        </div>
        @if ($canUpdate)
            <form method="POST" action="{{ route('staff-ui.programs.surveys.provision', $program) }}" class="sui-survey-create" data-survey-create>
                @csrf
                <div class="sui-survey-source">
                    <label class="sui-survey-source__opt">
                        <input type="radio" name="source" value="template" @checked(old('source', 'template') === 'template') data-survey-source>
                        <span><strong>من قالب</strong><small>أسئلة جاهزة تقدرين تعدّلينها بعدين.</small></span>
                    </label>
                    <label class="sui-survey-source__opt">
                        <input type="radio" name="source" value="blank" @checked(old('source') === 'blank') data-survey-source>
                        <span><strong>من الصفر</strong><small>استبيانات فاضية تضيفين أسئلتها.</small></span>
                    </label>
                </div>
                <div class="sui-form-grid" data-survey-templates>
                    <x-staff-ui.select name="pre_template_id" label="قالب القبلي والبعدي" :selected="old('pre_template_id', '')" :options="['' => 'اختاري…'] + $surveyTemplates" />
                    <x-staff-ui.select name="satisfaction_template_id" label="قالب الرضا" :selected="old('satisfaction_template_id', '')" :options="['' => 'اختاري…'] + $surveyTemplates" />
                </div>
                <div>
                    <x-staff-ui.button type="submit" size="sm" icon="plus">إنشاء الاستبيانات</x-staff-ui.button>
                </div>
            </form>
            <script>
                (() => {
                    const form = document.querySelector('[data-survey-create]');
                    const box = form.querySelector('[data-survey-templates]');
                    const sync = () => { box.hidden = form.querySelector('[data-survey-source]:checked')?.value === 'blank'; };
                    form.addEventListener('change', sync);
                    sync();
                })();
            </script>
        @else
            <p class="sui-program-muted">لم تُنشأ الاستبيانات بعد.</p>
        @endif
    </section>
@else
    <div class="sui-survey-grid">
        @foreach ($surveys as $survey)
            @php
                $stats = $surveyStats[$survey->id];
                $open = $survey->isOpen();
                $upcoming = ! $open && $survey->opens_at?->isFuture();
                $rate = $stats['eligible'] > 0 ? min(100, (int) round($stats['responses'] / $stats['eligible'] * 100)) : 0;
                $report = $surveyReports[$survey->id];
            @endphp
            <section class="sui-card sui-survey-card">
                <header class="sui-survey-card__head">
                    <div>
                        <h2>{{ $titles[$survey->type->value][0] }}</h2>
                        <p>{{ $titles[$survey->type->value][1] }}</p>
                    </div>
                    <x-staff-ui.status :tone="$open ? 'success' : ($upcoming ? 'warning' : 'muted')">{{ $open ? 'مفتوح' : ($upcoming ? 'لم يُفتح بعد' : 'مغلق') }}</x-staff-ui.status>
                </header>

                <div class="sui-survey-card__rate">
                    <div class="sui-survey-card__numbers">
                        <strong>{{ en_num($stats['responses']) }}</strong>
                        <span>من {{ en_num($stats['eligible']) }} مقبول · {{ en_num($rate) }}٪</span>
                    </div>
                    <div class="sui-survey-bar" role="progressbar" aria-valuenow="{{ $rate }}" aria-valuemin="0" aria-valuemax="100" aria-label="نسبة التعبئة"><span style="width: {{ $rate }}%"></span></div>
                </div>

                <dl class="sui-survey-card__dates">
                    <div><dt>يفتح</dt><dd>{{ $when($survey->opens_at) }}</dd></div>
                    <div><dt>يغلق</dt><dd>{{ $when($survey->closes_at) }}</dd></div>
                    <div><dt>الأسئلة</dt><dd>{{ en_num($survey->questions->count()) }}</dd></div>
                </dl>

                <div class="sui-survey-card__actions">
                    <button type="button" class="sui-btn sui-btn--secondary sui-btn--sm" data-copy="{{ $survey->publicUrl() }}" title="{{ $survey->publicUrl() }}">
                        <i data-lucide="copy" class="sui-icon sui-icon--sm"></i><span>نسخ الرابط</span>
                    </button>
                    @if ($canUpdate)
                        <form method="POST" action="{{ route('staff-ui.programs.surveys.window', [$program, $survey]) }}" class="sui-inline-form">
                            @csrf
                            <input type="hidden" name="action" value="{{ $open ? 'close' : 'open' }}">
                            <button type="submit" class="sui-btn sui-btn--secondary sui-btn--sm">{{ $open ? 'إغلاق الآن' : 'فتح الآن' }}</button>
                        </form>
                        <x-staff-ui.button variant="ghost" size="sm" icon="pencil" :href="$tabUrl.'?edit='.$survey->id">تعديل</x-staff-ui.button>
                    @endif
                    <x-staff-ui.button variant="ghost" size="sm" icon="download" :href="route('staff-ui.programs.surveys.export', [$program, $survey])">Excel</x-staff-ui.button>
                </div>

                @if ($stats['responses'] > 0 && ($report['lines'] !== [] || $report['comparison'] !== []))
                    <details class="sui-survey-report">
                        <summary>ملخص النتائج</summary>
                        <ul>
                            @foreach ($report['lines'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                        @if ($report['comparison'] !== [])
                            <h3>القبلي مقابل البعدي (متوسط من 5)</h3>
                            <table class="sui-table sui-survey-compare">
                                <thead><tr><th>السؤال</th><th>قبلي</th><th>بعدي</th></tr></thead>
                                <tbody>
                                    @foreach ($report['comparison'] as $row)
                                        <tr>
                                            <td>{{ $row['prompt'] }}</td>
                                            <td>{{ $row['pre_average'] ?? '—' }}</td>
                                            <td @class(['is-up' => $row['pre_average'] !== null && $row['post_average'] !== null && $row['post_average'] > $row['pre_average']])>{{ $row['post_average'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </details>
                @endif
            </section>
        @endforeach
    </div>

    <script>
        document.querySelectorAll('.sui-survey-card [data-copy]').forEach((button) => button.addEventListener('click', async () => {
            const label = button.querySelector('span');
            const original = label.textContent;
            try {
                await navigator.clipboard.writeText(button.dataset.copy);
                label.textContent = 'نُسخ ✓';
            } catch (e) {
                window.prompt('انسخي الرابط:', button.dataset.copy);
            }
            setTimeout(() => { label.textContent = original; }, 1500);
        }));
    </script>
@endif

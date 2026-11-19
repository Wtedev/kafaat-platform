@php
    use App\Enums\ProgramBroadcastStatus;

    $tz = config('app.timezone');
    $tabUrl = route('staff-ui.programs.tab', [$program, 'broadcasts']);
    $tone = [
        'draft' => 'muted',
        'queued' => 'info',
        'sending' => 'warning',
        'completed' => 'success',
        'completed_with_errors' => 'warning',
        'failed' => 'danger',
    ];
    $selectedSegment = old('segment', $editingSegment ?? 'approved');
    $groups = collect($broadcastSegments)->groupBy('group', preserveKeys: true);
@endphp

@if ($errors->any())
    <p class="sui-alert sui-alert--danger" role="alert">{{ $errors->first() }}</p>
@endif

@if ($canSendBroadcasts)
    <section class="sui-card" data-bc-compose-card>
        <div class="sui-card__head sui-card__head--row">
            <div>
                <h2>{{ $editingBroadcast ? 'تعديل المسودة' : 'رسالة جديدة' }}</h2>
                <p>إيميل باسم كل متدرب، يوصله لحاله. يُرسل في الخلفية على دفعات.</p>
            </div>
            @unless ($composeOpen)
                <x-staff-ui.button type="button" size="sm" icon="plus" data-bc-open>رسالة جديدة</x-staff-ui.button>
            @endunless
        </div>

        <form method="POST"
              action="{{ $editingBroadcast ? route('staff-ui.programs.broadcasts.update', [$program, $editingBroadcast]) : route('staff-ui.programs.broadcasts.store', $program) }}"
              class="sui-bc-form" data-bc-form @unless ($composeOpen) hidden @endunless>
            @csrf
            <input type="hidden" name="intent" value="draft" data-bc-intent>

            <div class="sui-field">
                <label class="sui-field__label" for="bc-segment">إلى</label>
                <select id="bc-segment" name="segment" class="sui-control" data-bc-segment>
                    @foreach ($groups as $group => $items)
                        <optgroup label="{{ $group }}">
                            @foreach ($items as $key => $segment)
                                <option value="{{ $key }}" data-count="{{ $segment['count'] }}" @selected($selectedSegment === $key)>{{ $segment['label'] }} ({{ en_num($segment['count']) }})</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <x-staff-ui.input name="subject" label="العنوان" maxlength="255" :value="old('subject', $editingBroadcast?->subject)" required />

            <div class="sui-bc-body">
                <div class="sui-field">
                    <label class="sui-field__label" for="bc-content">نص الرسالة</label>
                    <textarea id="bc-content" name="content" rows="10" class="sui-control" required data-bc-content>{{ old('content', $editingBroadcast ? \App\Support\RichContentSupport::toEditableText($editingBroadcast->content) : '') }}</textarea>
                    <p class="sui-field__hint">تبدأ الرسالة تلقائياً بـ«مرحباً» واسم المتدرب، واسم البرنامج يظهر فيها.</p>
                </div>
                <div class="sui-bc-preview">
                    <span class="sui-field__label">المعاينة</span>
                    <iframe title="معاينة الإيميل" sandbox data-bc-preview></iframe>
                </div>
            </div>

            <div class="sui-bc-actions">
                @if ($editingBroadcast)
                    <x-staff-ui.button variant="ghost" size="sm" :href="$tabUrl">إلغاء</x-staff-ui.button>
                @else
                    <button type="button" class="sui-btn sui-btn--ghost sui-btn--sm" data-bc-close>إلغاء</button>
                @endif
                <button type="submit" class="sui-btn sui-btn--secondary sui-btn--sm" data-bc-submit="draft">حفظ كمسودة</button>
                <button type="submit" class="sui-btn sui-btn--primary sui-btn--sm" data-bc-submit="send"><i data-lucide="send" class="sui-icon sui-icon--sm"></i><span>إرسال</span></button>
            </div>
        </form>
    </section>
@endif

<section class="sui-card">
    <div class="sui-card__head">
        <h2>سجل الرسائل</h2>
        <p>الرسائل الجماعية ومسوداتها، ورسائل القبول لحظة إرسالها.</p>
    </div>

    @if ($messageLog->isEmpty())
        <p class="sui-program-muted">ما أُرسلت رسائل لهذا البرنامج بعد.</p>
    @else
        <div class="sui-table-wrap">
            <table class="sui-table sui-bc-table">
                <thead>
                    <tr>
                        <th>الرسالة</th>
                        <th>إلى</th>
                        <th>الحالة</th>
                        <th>الوصول</th>
                        <th>الوقت</th>
                        <th>بواسطة</th>
                        <th aria-label="إجراءات"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($messageLog as $entry)
                        @php $at = $entry['at']?->copy()->timezone($tz); @endphp
                        @if ($entry['kind'] === 'acceptance')
                            @php
                                $items = collect($entry['items']);
                                $failed = $items->where('status', 'failed')->count();
                                $names = $items->map(fn ($log) => $log->registration?->user?->name ?? $log->recipient_email);
                            @endphp
                            <tr>
                                <td data-label="الرسالة"><span class="sui-bc-kind"><i data-lucide="mail-check" class="sui-icon sui-icon--sm"></i>رسالة القبول</span></td>
                                <td data-label="إلى" class="sui-bc-audience">
                                    @if ($items->count() === 1)
                                        {{ $names->first() }}
                                    @else
                                        <details class="sui-bc-names">
                                            <summary>{{ en_num($items->count()) }} متدرب</summary>
                                            <p>{{ $names->implode('، ') }}</p>
                                        </details>
                                    @endif
                                </td>
                                <td data-label="الحالة"><x-staff-ui.status :tone="$failed > 0 ? 'warning' : 'success'">{{ $failed > 0 ? 'أُرسلت مع أخطاء' : 'أُرسلت' }}</x-staff-ui.status></td>
                                <td data-label="الوصول" class="sui-att-count">
                                    {{ en_num($items->count() - $failed) }} / {{ en_num($items->count()) }}
                                    @if ($failed > 0)
                                        <span class="sui-bc-failed">· تعذّر {{ en_num($failed) }}</span>
                                    @endif
                                </td>
                                <td data-label="الوقت" class="sui-reg-table__time">{{ $at ? en_digits(ar_date($at, 'd MMM')).' — '.$at->format('H:i') : '—' }}</td>
                                <td data-label="بواسطة">{{ $entry['sender'] ?? 'تلقائي' }}</td>
                                <td></td>
                            </tr>
                        @else
                            @php
                                $broadcast = $entry['broadcast'];
                                $status = $broadcast->status;
                            @endphp
                            <tr>
                                <td data-label="الرسالة"><strong>{{ $broadcast->subject }}</strong></td>
                                <td data-label="إلى" class="sui-bc-audience">{{ $broadcast->audienceLabel() }}</td>
                                <td data-label="الحالة"><x-staff-ui.status :tone="$tone[$status->value] ?? 'muted'">{{ $status->label() }}</x-staff-ui.status></td>
                                <td data-label="الوصول" class="sui-att-count">
                                    @if ($status === ProgramBroadcastStatus::Draft)
                                        —
                                    @else
                                        {{ en_num((int) $broadcast->sent_count) }} / {{ en_num((int) $broadcast->recipients_count) }}
                                        @if ($broadcast->failed_count > 0)
                                            <span class="sui-bc-failed">· تعذّر {{ en_num((int) $broadcast->failed_count) }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td data-label="الوقت" class="sui-reg-table__time">{{ $at ? en_digits(ar_date($at, 'd MMM')).' — '.$at->format('H:i') : '—' }}</td>
                                <td data-label="بواسطة">{{ $broadcast->creator?->name ?? '—' }}</td>
                                <td class="sui-reg-table__actions">
                                    @if ($canSendBroadcasts)
                                        @if ($status === ProgramBroadcastStatus::Draft)
                                            <x-staff-ui.button variant="secondary" size="sm" :href="$tabUrl.'?edit='.$broadcast->id">تعديل</x-staff-ui.button>
                                            <form method="POST" action="{{ route('staff-ui.programs.broadcasts.action', [$program, $broadcast]) }}" class="sui-inline-form" onsubmit="return confirm('حذف المسودة؟');">
                                                @csrf
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="sui-btn sui-btn--ghost sui-btn--sm" aria-label="حذف {{ $broadcast->subject }}"><i data-lucide="trash-2" class="sui-icon sui-icon--sm"></i></button>
                                            </form>
                                        @else
                                            @if ($broadcast->canRetryFailed())
                                                <form method="POST" action="{{ route('staff-ui.programs.broadcasts.action', [$program, $broadcast]) }}" class="sui-inline-form">
                                                    @csrf
                                                    <input type="hidden" name="action" value="retry">
                                                    <button type="submit" class="sui-btn sui-btn--secondary sui-btn--sm">إعادة الإرسال للي ما وصلتهم</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('staff-ui.programs.broadcasts.action', [$program, $broadcast]) }}" class="sui-inline-form">
                                                @csrf
                                                <input type="hidden" name="action" value="copy">
                                                <button type="submit" class="sui-btn sui-btn--ghost sui-btn--sm">نسخ</button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($messageLog->hasPages())
            <div class="sui-pager">
                <span class="sui-pager__label">{{ en_num($messageLog->firstItem() ?? 0) }}–{{ en_num($messageLog->lastItem() ?? 0) }} من {{ en_num($messageLog->total()) }}</span>
                @foreach ([
                    ['url' => $messageLog->previousPageUrl(), 'enabled' => ! $messageLog->onFirstPage(), 'icon' => 'chevron-right', 'label' => 'السابق'],
                    ['url' => $messageLog->nextPageUrl(), 'enabled' => $messageLog->hasMorePages(), 'icon' => 'chevron-left', 'label' => 'التالي'],
                ] as $pager)
                    @if ($pager['enabled'])
                        <a class="sui-icon-btn" href="{{ $pager['url'] }}" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></a>
                    @else
                        <span class="sui-icon-btn" aria-disabled="true" aria-label="{{ $pager['label'] }}"><i data-lucide="{{ $pager['icon'] }}" class="sui-icon"></i></span>
                    @endif
                @endforeach
            </div>
        @endif
    @endif
</section>

@if ($canSendBroadcasts)
    <script>
        (() => {
            const form = document.querySelector('[data-bc-form]');
            if (!form) return;
            const card = document.querySelector('[data-bc-compose-card]');
            const openBtn = card.querySelector('[data-bc-open]');
            const frame = form.querySelector('[data-bc-preview]');
            const previewUrl = @json(route('staff-ui.programs.broadcasts.preview', $program));
            const token = form.querySelector('input[name="_token"]').value;
            let timer = null;

            const refresh = async () => {
                const body = new FormData();
                body.append('_token', token);
                body.append('subject', form.subject.value);
                body.append('content', form.content.value);
                try {
                    const response = await fetch(previewUrl, { method: 'POST', body, headers: { Accept: 'text/html' } });
                    if (response.ok) frame.srcdoc = await response.text();
                } catch (e) {}
            };
            const schedule = () => { clearTimeout(timer); timer = setTimeout(refresh, 500); };

            openBtn?.addEventListener('click', () => { form.hidden = false; openBtn.hidden = true; form.subject.focus(); refresh(); });
            form.querySelector('[data-bc-close]')?.addEventListener('click', () => { form.hidden = true; if (openBtn) openBtn.hidden = false; });
            form.subject.addEventListener('input', schedule);
            form.content.addEventListener('input', schedule);
            if (!form.hidden) refresh();

            form.querySelectorAll('[data-bc-submit]').forEach((button) => button.addEventListener('click', () => {
                form.querySelector('[data-bc-intent]').value = button.dataset.bcSubmit;
            }));
            form.addEventListener('submit', (e) => {
                if (form.querySelector('[data-bc-intent]').value !== 'send') return;
                const option = form.querySelector('[data-bc-segment]').selectedOptions[0];
                const count = Number(option?.dataset.count || 0);
                if (count === 0) {
                    e.preventDefault();
                    alert('ما فيه أحد في هذه الفئة.');
                    return;
                }
                if (!confirm(`إرسال الرسالة إلى ${count} شخص الآن؟ ما تقدرين تتراجعين بعد الإرسال.`)) e.preventDefault();
            });
        })();
    </script>
@endif

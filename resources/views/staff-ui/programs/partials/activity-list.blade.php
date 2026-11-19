@php
    use App\Enums\AttendanceStatus;

    $tz = config('app.timezone');
    $settingsLabels = [
        'title' => 'العنوان', 'program_kind' => 'النوع', 'competency_track' => 'مسار الكفاءة',
        'delivery_mode' => 'أسلوب التنفيذ', 'venue' => 'المكان', 'description' => 'النبذة', 'image' => 'الغلاف',
        'program_presenters' => 'المقدمون', 'session_topics' => 'المحاور', 'session_topics_enabled' => 'عرض المحاور',
        'start_date' => 'البداية', 'end_date' => 'النهاية', 'registration_start' => 'بداية التسجيل',
        'registration_end' => 'نهاية التسجيل', 'weekdays' => 'أيام الأسبوع', 'acceptance_conditions' => 'شروط القبول',
        'capacity' => 'السعة', 'capacity_male' => 'سعة الرجال', 'capacity_female' => 'سعة النساء',
        'auto_accept_registrations' => 'القبول التلقائي', 'approval_message' => 'رسالة القبول',
        'whatsapp_groups_enabled' => 'مجموعات التواصل', 'whatsapp_group_male' => 'مجموعة الرجال',
        'whatsapp_group_female' => 'مجموعة النساء', 'notify_on_publish' => 'الإشعارات',
        'notify_milestones' => 'الإشعارات', 'notify_registrants_on_update' => 'الإشعارات',
    ];
@endphp

@if ($items->isEmpty())
    <p class="sui-program-muted">لا يوجد نشاط مسجّل بعد.</p>
@else
    <ol class="sui-activity">
        @foreach ($items as $item)
            @php
                $props = is_array($item->properties) ? $item->properties : [];
                $details = [];
                if (! empty($props['fields'])) {
                    $details[] = collect($props['fields'])->map(fn ($f) => $settingsLabels[$f] ?? null)->filter()->unique()->implode('، ');
                }
                if (! empty($props['status'])) {
                    $details[] = AttendanceStatus::tryFrom((string) $props['status'])?->label();
                }
                if (! empty($props['date'])) {
                    $details[] = 'يوم '.en_digits(ar_date((string) $props['date'], 'd MMM y'));
                }
                if (! empty($props['reason'])) {
                    $details[] = 'السبب: '.$props['reason'];
                }
                if (! empty($props['number'])) {
                    $details[] = $props['number'];
                }
                $details = array_values(array_filter($details));
                $at = $item->created_at?->timezone($tz);
            @endphp
            <li class="sui-activity__item">
                <span class="sui-activity__icon sui-activity__icon--{{ $item->action->group() }}" aria-hidden="true">
                    <i data-lucide="{{ $item->action->icon() }}" class="sui-icon sui-icon--sm"></i>
                </span>
                <div class="sui-activity__body">
                    <p class="sui-activity__line">
                        <strong>{{ $item->action->label() }}</strong>
                        @if (filled($item->description))
                            <span>{{ $item->description }}</span>
                        @endif
                    </p>
                    @if ($details !== [])
                        <p class="sui-activity__details">{{ implode(' · ', $details) }}</p>
                    @endif
                    <p class="sui-activity__meta">
                        <time datetime="{{ $item->created_at?->toIso8601String() }}">{{ $at ? en_digits(ar_date($at, 'd MMM y')).' — '.$at->format('H:i') : '' }}</time>
                        <span>{{ $item->actor?->name ?? 'تلقائي / المستفيد' }}</span>
                    </p>
                </div>
            </li>
        @endforeach
    </ol>
@endif

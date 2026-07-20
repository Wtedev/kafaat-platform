<?php

namespace App\Support;

use App\Enums\ProfileGender;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

final class TrainingProgramExtrasSupport
{
    /**
     * @return array<int, Placeholder>
     */
    public static function descriptionPreviewFields(): array
    {
        return [
            Placeholder::make('public_description_preview')
                ->label('معاينة النبذة المنشورة')
                ->content(function (Get $get): HtmlString {
                    return self::descriptionPreviewHtml(
                        $get('description'),
                        (bool) $get('session_topics_enabled'),
                        is_array($get('session_topics')) ? $get('session_topics') : [],
                    );
                })
                ->visible(fn (Get $get): bool => self::shouldShowDescriptionPreview(
                    $get('description'),
                    (bool) $get('session_topics_enabled'),
                    is_array($get('session_topics')) ? $get('session_topics') : [],
                ))
                ->columnSpanFull(),
        ];
    }

    /**
     * Safe form-state → storage string for TipTap arrays, JSON strings, plain text, or empty.
     */
    public static function normalizeDescriptionForForm(mixed $description): ?string
    {
        return RichContentSupport::normalizeForStorage($description);
    }

    /**
     * @param  list<array{title?: string, facilitators?: string}>|null  $topics
     */
    public static function shouldShowDescriptionPreview(
        mixed $description,
        bool $topicsEnabled,
        ?array $topics,
    ): bool {
        $normalized = self::normalizeDescriptionForForm($description);

        if ($normalized !== null && $normalized !== '' && RichContentSupport::toPlainText($normalized) !== '') {
            return true;
        }

        return $topicsEnabled && self::normalizeSessionTopics($topics) !== [];
    }

    /**
     * Admin create/edit preview of the published description (accepts TipTap array form state).
     *
     * @param  list<array{title?: string, facilitators?: string}>|null  $topics
     */
    public static function descriptionPreviewHtml(
        mixed $description,
        bool $topicsEnabled,
        ?array $topics,
    ): HtmlString {
        $text = self::formatPublicDescription(
            self::normalizeDescriptionForForm($description),
            $topicsEnabled,
            $topics,
        );

        if ($text === '') {
            $inner = e('—');
        } elseif (RichContentSupport::isRichContent($text)) {
            $inner = RichContentSupport::toDisplayHtml($text);
        } else {
            $inner = nl2br(e($text));
        }

        return new HtmlString(
            '<div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm leading-7 text-gray-700 prose prose-sm max-w-none">'
            .$inner
            .'</div>'
        );
    }

    public static function sessionTopicsBlock(): Toggle
    {
        return Toggle::make('session_topics_enabled')
            ->label('إضافة محاور اللقاء')
            ->inline(true)
            ->live()
            ->helperText('للقاءات والبرامج التي تتضمن عدة محاور مع مسؤولين أو مدربين.')
            ->extraFieldWrapperAttributes(['class' => 'fi-advanced-settings-toggle-row']);
    }

    /**
     * @return array<int, Repeater>
     */
    public static function sessionTopicsRepeaterFields(): array
    {
        return [
            Repeater::make('session_topics')
                ->label('محاور البرنامج')
                ->schema([
                    TextInput::make('title')
                        ->label('المحور')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('facilitators')
                        ->label('المسؤولون / المدربون')
                        ->placeholder('مثال: أ. محمد العتيبي، د. سارة القحطاني')
                        ->helperText('يمكن إضافة أكثر من مسؤول أو مدرب في نفس الحقل مفصولين بفاصلة.')
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])
                ->addActionLabel('أضف محوراً جديداً')
                ->default([])
                ->visible(fn (Get $get): bool => (bool) $get('session_topics_enabled'))
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Repeater>
     */
    public static function programPresentersRepeaterFields(): array
    {
        return [
            Repeater::make('program_presenters')
                ->label('مقدمو البرنامج')
                ->helperText('تظهر أسماؤهم في صفحة البرنامج العامة وفي بوابة المستفيد.')
                ->schema([
                    TextInput::make('name')
                        ->label('الاسم')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('role')
                        ->label('الصفة / الدور')
                        ->placeholder('اختياري — مثال: مقدّم، مدرب')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ])
                ->addActionLabel('أضف مقدّماً')
                ->default([])
                ->columnSpanFull(),
        ];
    }

    public static function whatsappGroupsBlock(): Toggle
    {
        return Toggle::make('whatsapp_groups_enabled')
            ->label('روابط مجموعات الواتساب')
            ->inline(true)
            ->live()
            ->helperText('يُرسل الرابط المناسب تلقائياً عند قبول التسجيل (حسب جنس المستفيد).')
            ->extraFieldWrapperAttributes(['class' => 'fi-advanced-settings-toggle-row']);
    }

    /**
     * @return array<int, TextInput>
     */
    public static function whatsappGroupUrlFields(): array
    {
        return [
            TextInput::make('whatsapp_group_male')
                ->label('مجموعة الذكور')
                ->url()
                ->maxLength(512)
                ->placeholder('https://chat.whatsapp.com/...')
                ->visible(fn (Get $get): bool => (bool) $get('whatsapp_groups_enabled'))
                ->columnSpanFull(),
            TextInput::make('whatsapp_group_female')
                ->label('مجموعة الإناث')
                ->url()
                ->maxLength(512)
                ->placeholder('https://chat.whatsapp.com/...')
                ->visible(fn (Get $get): bool => (bool) $get('whatsapp_groups_enabled'))
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyFormData(array $data): array
    {
        if (array_key_exists('description', $data)) {
            $data['description'] = self::normalizeDescriptionForForm($data['description']);
        }

        $data = self::applySessionTopics($data);
        $data = self::applyProgramPresenters($data);
        $data = self::applyWhatsappGroups($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyProgramPresenters(array $data): array
    {
        $presenters = self::normalizeProgramPresenters(
            is_array($data['program_presenters'] ?? null) ? $data['program_presenters'] : null,
        );

        $data['program_presenters'] = $presenters === [] ? null : $presenters;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applySessionTopics(array $data): array
    {
        if (! (bool) ($data['session_topics_enabled'] ?? false)) {
            $data['session_topics'] = null;

            return $data;
        }

        $topics = collect(is_array($data['session_topics'] ?? null) ? $data['session_topics'] : [])
            ->map(static function (mixed $row): ?array {
                if (! is_array($row)) {
                    return null;
                }

                $title = trim((string) ($row['title'] ?? ''));

                if ($title === '') {
                    return null;
                }

                return [
                    'title' => $title,
                    'facilitators' => trim((string) ($row['facilitators'] ?? '')),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $data['session_topics'] = $topics === [] ? null : $topics;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyWhatsappGroups(array $data): array
    {
        if (! (bool) ($data['whatsapp_groups_enabled'] ?? false)) {
            $data['whatsapp_group_male'] = null;
            $data['whatsapp_group_female'] = null;

            return $data;
        }

        $data['whatsapp_group_male'] = self::normalizeWhatsappUrl($data['whatsapp_group_male'] ?? null);
        $data['whatsapp_group_female'] = self::normalizeWhatsappUrl($data['whatsapp_group_female'] ?? null);

        return $data;
    }

    public static function normalizeWhatsappUrl(mixed $url): ?string
    {
        $url = trim((string) $url);

        return $url !== '' ? $url : null;
    }

    /**
     * @param  list<array{title?: string, facilitators?: string}>|null  $topics
     * @return list<array{title: string, facilitators: string}>
     */
    public static function normalizeSessionTopics(?array $topics): array
    {
        if ($topics === null || $topics === []) {
            return [];
        }

        return collect($topics)
            ->map(static function (mixed $row): ?array {
                if (! is_array($row)) {
                    return null;
                }

                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    return null;
                }

                return [
                    'title' => $title,
                    'facilitators' => trim((string) ($row['facilitators'] ?? '')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, facilitators: string}>
     */
    public static function publicSessionTopics(TrainingProgram $program): array
    {
        if (! (bool) $program->session_topics_enabled) {
            return [];
        }

        return self::normalizeSessionTopics(
            is_array($program->session_topics) ? $program->session_topics : null,
        );
    }

    /**
     * @param  list<array{name?: string, role?: string}>|null  $presenters
     * @return list<array{name: string, role: string}>
     */
    public static function normalizeProgramPresenters(?array $presenters): array
    {
        if ($presenters === null || $presenters === []) {
            return [];
        }

        return collect($presenters)
            ->map(static function (mixed $row): ?array {
                if (! is_array($row)) {
                    return null;
                }

                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'role' => trim((string) ($row['role'] ?? '')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, role: string}>
     */
    public static function publicProgramPresenters(TrainingProgram $program): array
    {
        return self::normalizeProgramPresenters(
            is_array($program->program_presenters) ? $program->program_presenters : null,
        );
    }

    public static function presenterInitials(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            return '؟';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $skip = ['د.', 'د', 'أ.', 'أ', 'م.', 'م', 'ا.', 'الشيخ', 'الأستاذ', 'الاستاذ'];

        $significant = array_values(array_filter(
            $parts,
            static fn (string $part): bool => ! in_array($part, $skip, true),
        ));

        if ($significant === []) {
            $significant = $parts;
        }

        $first = self::significantWordInitial($significant[0]);
        $last = count($significant) > 1
            ? self::significantWordInitial($significant[count($significant) - 1])
            : '';

        $initials = $first.$last;

        return $initials !== '' ? $initials : '؟';
    }

    private static function significantWordInitial(string $word): string
    {
        $word = trim($word);
        if ($word === '') {
            return '';
        }

        // Skip Arabic definite article «ال» so الرفاعي → ر
        if (mb_strpos($word, 'ال') === 0 && mb_strlen($word) > 2) {
            return mb_substr($word, 2, 1);
        }

        return mb_substr($word, 0, 1);
    }

    /**
     * Plain-text fallback (exports / plain previews).
     *
     * @param  list<array{title?: string, facilitators?: string}>|null  $topics
     */
    public static function formatSessionTopicsBlock(?array $topics): string
    {
        $topics = self::normalizeSessionTopics($topics);
        if ($topics === []) {
            return '';
        }

        $lines = ['محاور البرنامج:', str_repeat('─', 18)];

        foreach ($topics as $index => $topic) {
            $lines[] = ($index + 1).'. '.$topic['title'];

            if ($topic['facilitators'] !== '') {
                $lines[] = '   المسؤولون / المدربون: '.$topic['facilitators'];
            }

            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Semantic HTML for admin/Filament preview (survives HTMLPurifier).
     *
     * @param  list<array{title?: string, facilitators?: string}>|null  $topics
     */
    public static function formatSessionTopicsHtml(?array $topics): string
    {
        $topics = self::normalizeSessionTopics($topics);
        if ($topics === []) {
            return '';
        }

        $items = '';
        foreach ($topics as $topic) {
            $items .= '<li style="margin-bottom:0.75rem">'
                .'<p style="margin:0;font-weight:600;color:#111827">'.e($topic['title']).'</p>';

            if ($topic['facilitators'] !== '') {
                $items .= '<p style="margin:0.35rem 0 0;color:#4b5563;font-size:0.95em">'
                    .'<span style="color:#335483;font-weight:600">المسؤولون / المدربون</span>'
                    .' · '.e($topic['facilitators'])
                    .'</p>';
            }

            $items .= '</li>';
        }

        return '<div class="program-session-topics">'
            .'<p style="margin:0 0 0.75rem;font-weight:700;color:#335483">محاور البرنامج</p>'
            .'<ol style="margin:0;padding-inline-start:1.25rem;list-style:decimal">'
            .$items
            .'</ol>'
            .'</div>';
    }

    public static function looksLikeHtml(string $value): bool
    {
        return RichContentSupport::isRichContent($value);
    }

    /**
     * @param  list<array{title?: string, facilitators?: string}>|null  $topics
     */
    public static function formatPublicDescription(
        ?string $description,
        bool $topicsEnabled,
        ?array $topics,
    ): string {
        $parts = [];

        $description = trim((string) $description);
        $descriptionIsRich = RichContentSupport::isRichContent($description);
        $descriptionHtml = RichContentSupport::toDisplayHtml($description);
        $topicsHtml = $topicsEnabled ? self::formatSessionTopicsHtml($topics) : '';
        $hasTopicsHtml = $topicsHtml !== '';

        if ($description !== '') {
            if ($hasTopicsHtml && ! $descriptionIsRich) {
                $parts[] = '<div class="program-description-text">'.nl2br(e($description)).'</div>';
            } else {
                $parts[] = $descriptionHtml;
            }
        }

        if ($hasTopicsHtml) {
            $parts[] = $topicsHtml;
        }

        $separator = ($descriptionIsRich || $hasTopicsHtml) ? "\n" : "\n\n";

        return trim(implode($separator, $parts));
    }

    public static function publicDescription(TrainingProgram $program): string
    {
        return self::formatPublicDescription(
            $program->description,
            (bool) $program->session_topics_enabled,
            is_array($program->session_topics) ? $program->session_topics : null,
        );
    }

    public static function whatsappGroupUrlFor(TrainingProgram $program, User $user): ?string
    {
        if (! $program->whatsapp_groups_enabled) {
            return null;
        }

        $gender = $user->profile?->gender;

        if ($gender === ProfileGender::Female) {
            return self::normalizeWhatsappUrl($program->whatsapp_group_female)
                ?? self::normalizeWhatsappUrl($program->whatsapp_group_male);
        }

        if ($gender === ProfileGender::Male) {
            return self::normalizeWhatsappUrl($program->whatsapp_group_male)
                ?? self::normalizeWhatsappUrl($program->whatsapp_group_female);
        }

        return self::normalizeWhatsappUrl($program->whatsapp_group_male)
            ?? self::normalizeWhatsappUrl($program->whatsapp_group_female);
    }

    public static function registrationApprovalMessage(TrainingProgram $program, User $recipient): string
    {
        return 'تم قبول طلبك في البرنامج التدريبي «'.$program->title.'».';
    }

    public static function isSessionKind(?TrainingProgramKind $kind): bool
    {
        return $kind === TrainingProgramKind::Session;
    }
}

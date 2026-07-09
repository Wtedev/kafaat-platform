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
                ->label('معاينة الوصف المنشور')
                ->content(function (Get $get): HtmlString {
                    $text = self::formatPublicDescription(
                        (string) ($get('description') ?? ''),
                        (bool) $get('session_topics_enabled'),
                        is_array($get('session_topics')) ? $get('session_topics') : [],
                    );

                    return new HtmlString(
                        '<div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm leading-7 text-gray-700 whitespace-pre-line">'
                        .e($text !== '' ? $text : '—')
                        .'</div>'
                    );
                })
                ->visible(fn (Get $get): bool => filled($get('description'))
                    || ((bool) $get('session_topics_enabled') && is_array($get('session_topics')) && $get('session_topics') !== []))
                ->columnSpanFull(),
        ];
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
        $data = self::applySessionTopics($data);
        $data = self::applyWhatsappGroups($data);

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
     */
    public static function formatSessionTopicsBlock(?array $topics): string
    {
        if ($topics === null || $topics === []) {
            return '';
        }

        $lines = ['محاور البرنامج:', str_repeat('─', 18)];

        foreach ($topics as $index => $topic) {
            $title = trim((string) ($topic['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $lines[] = ($index + 1).'. '.$title;

            $facilitators = trim((string) ($topic['facilitators'] ?? ''));
            if ($facilitators !== '') {
                $lines[] = '   المسؤولون / المدربون: '.$facilitators;
            }

            $lines[] = '';
        }

        return trim(implode("\n", $lines));
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
        if ($description !== '') {
            $parts[] = $description;
        }

        if ($topicsEnabled) {
            $block = self::formatSessionTopicsBlock($topics);
            if ($block !== '') {
                $parts[] = $block;
            }
        }

        return trim(implode("\n\n", $parts));
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
        $message = 'تم قبول طلبك في البرنامج التدريبي «'.$program->title.'».';

        $whatsappUrl = self::whatsappGroupUrlFor($program, $recipient);
        if ($whatsappUrl !== null) {
            $message .= "\n\nرابط مجموعة الواتساب:\n".$whatsappUrl;
        }

        return $message;
    }

    public static function isSessionKind(?TrainingProgramKind $kind): bool
    {
        return $kind === TrainingProgramKind::Session;
    }
}

<?php

namespace App\Filament\Support;

use App\Enums\LearningPathKind;
use App\Models\LearningPath;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

final class LearningPathInlineEditSupport
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function fields(LearningPath $path): array
    {
        return [
            'overview' => [
                TextInput::make('title')
                    ->label('اسم المسار')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('path_kind')
                    ->label('نوع المسار')
                    ->options(LearningPathKind::options())
                    ->required()
                    ->native(false)
                    ->columnSpanFull(),
            ],
            'enrollment' => TrainingEntityFormSupport::registrationAdvancedSettingsFields(
                null,
                includeProgramAudienceNotifications: false,
            ),
            'schedule' => [
                ...TrainingEntityFormSupport::publicationInlineFields(),
                TrainingEntityFormSupport::advancedSettingsToggle('notify_on_publish', 'إشعارات المستفيدين'),
            ],
            'team' => TrainingEntityFormSupport::pathStaffFieldsForEdit(),
            'description' => [
                TrainingEntityFormSupport::descriptionField(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'overview' => 'نظرة عامة',
            'enrollment' => 'التسجيل والسعة',
            'schedule' => 'الجدول الزمني',
            'team' => 'الفريق',
            'description' => 'نبذة عن المسار',
        ];
    }
}

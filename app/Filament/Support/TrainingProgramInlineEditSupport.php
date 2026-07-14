<?php

namespace App\Filament\Support;

use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use App\Support\TrainingProgramExtrasSupport;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

final class TrainingProgramInlineEditSupport
{
    /**
     * @return list<string>
     */
    public static function fieldKeys(): array
    {
        return array_keys(self::labels());
    }

    /**
     * Build schema for a single inline-edit section only (avoids eager evaluation of all sections).
     *
     * @return array<int, mixed>
     */
    public static function fieldSchema(string $field, TrainingProgram $program): array
    {
        return match ($field) {
            'overview' => self::overviewFields($program),
            'schedule' => self::scheduleFields($program),
            'enrollment' => self::enrollmentFields(),
            'team' => TrainingEntityFormSupport::programStaffFieldsForEdit(),
            'description' => [
                TrainingEntityFormSupport::programDescriptionInlineRichEditorField(),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function fields(TrainingProgram $program): array
    {
        $fields = [];

        foreach (self::fieldKeys() as $field) {
            $fields[$field] = self::fieldSchema($field, $program);
        }

        return $fields;
    }

    /**
     * @return array<int, mixed>
     */
    private static function overviewFields(TrainingProgram $program): array
    {
        $capacityVisible = fn (Get $get): bool => ! (bool) $get('is_linked_to_path');

        $overview = [
            TextInput::make('title')
                ->label('اسم البرنامج')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('program_kind')
                ->label('نوع البرنامج')
                ->options(TrainingProgramKind::options())
                ->required()
                ->native(false)
                ->live()
                ->columnSpan(1),
            TrainingEntityFormSupport::competencyTrackSelect()
                ->columnSpan(1),
            ...TrainingEntityFormSupport::programDeliveryFields(),
        ];

        if (TrainingEntityFormSupport::publishControlsVisibleForRecord($program, ProgramStatus::Published)) {
            $overview = array_merge($overview, TrainingEntityFormSupport::publicationInlineFields());
        } else {
            $overview[] = Placeholder::make('publication_status_display')
                ->label('حالة النشر')
                ->content($program->status?->label() ?? 'غير محدد')
                ->columnSpanFull();
        }

        return array_merge(
            $overview,
            TrainingEntityFormSupport::programPathLinkFields(persistToggleState: true),
        );
    }

    /**
     * @return array<int, mixed>
     */
    private static function scheduleFields(TrainingProgram $program): array
    {
        return [
            Hidden::make('is_linked_to_path')->dehydrated(false),
            Hidden::make('program_kind')->dehydrated(false),
            ...TrainingEntityFormSupport::scheduleDateHiddenFields(hideRegistrationWhenLinked: true),
            TrainingEntityFormSupport::trainingScheduleCalendar(
                showRegistrationRange: fn (Get $get): bool => ! (bool) $get('is_linked_to_path'),
                programHasEndDate: fn (Get $get): bool => ($get('program_kind') ?? '') !== TrainingProgramKind::Session->value,
                showWeekdayPicker: fn (Get $get): bool => ($get('program_kind') ?? '') !== TrainingProgramKind::Session->value,
                showPublishSchedule: fn (?TrainingProgram $record): bool => TrainingEntityFormSupport::publishControlsVisibleForRecord(
                    $record,
                    ProgramStatus::Published,
                ),
            ),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function enrollmentFields(): array
    {
        $capacityVisible = fn (Get $get): bool => ! (bool) $get('is_linked_to_path');

        return [
            ...TrainingEntityFormSupport::programAcceptanceConditionsFields(),
            ...TrainingEntityFormSupport::registrationAdvancedSettingsFields(
                $capacityVisible,
                includeProgramAudienceNotifications: true,
                includeAutoAcceptRegistrations: false,
            ),
            TrainingProgramExtrasSupport::sessionTopicsBlock(),
            ...TrainingProgramExtrasSupport::sessionTopicsRepeaterFields(),
            ...TrainingProgramExtrasSupport::programPresentersRepeaterFields(),
            TrainingProgramExtrasSupport::whatsappGroupsBlock(),
            ...TrainingProgramExtrasSupport::whatsappGroupUrlFields(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'overview' => 'نظرة عامة',
            'schedule' => 'الجدول الزمني',
            'enrollment' => 'التسجيل والسعة',
            'team' => 'الفريق',
            'description' => 'نبذة عن البرنامج',
        ];
    }
}

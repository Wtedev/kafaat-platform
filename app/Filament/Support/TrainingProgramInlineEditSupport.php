<?php

namespace App\Filament\Support;

use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Resources\TrainingProgramResource;
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
     * @return array<string, array<int, mixed>>
     */
    public static function fields(TrainingProgram $program): array
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

        $overview = array_merge(
            $overview,
            TrainingEntityFormSupport::programPathLinkFields(persistToggleState: true),
        );

        return [
            'cover' => [
                TrainingProgramResource::trainingProgramImageUploadField()
                    ->label('صورة الغلاف')
                    ->helperText('يفضّل نسبة 16:9، وبحد أقصى 4 ميجابايت (JPEG أو PNG أو WebP). تظهر في كتالوج البرامج والبوابة.')
                    ->imagePreviewHeight('16rem')
                    ->required(false),
            ],
            'overview' => $overview,
            'schedule' => [
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
            ],
            'enrollment' => [
                ...TrainingEntityFormSupport::registrationAdvancedSettingsFields(
                    $capacityVisible,
                    includeProgramAudienceNotifications: true,
                ),
                TrainingProgramExtrasSupport::sessionTopicsBlock(),
                ...TrainingProgramExtrasSupport::sessionTopicsRepeaterFields(),
                TrainingProgramExtrasSupport::whatsappGroupsBlock(),
                ...TrainingProgramExtrasSupport::whatsappGroupUrlFields(),
            ],
            'team' => TrainingEntityFormSupport::programStaffFieldsForEdit(),
            'description' => [
                ...TrainingEntityFormSupport::descriptionFieldsWithPreview(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'cover' => 'صورة البرنامج',
            'overview' => 'نظرة عامة',
            'schedule' => 'الجدول الزمني',
            'enrollment' => 'التسجيل والسعة',
            'team' => 'الفريق',
            'description' => 'نبذة عن البرنامج',
        ];
    }
}

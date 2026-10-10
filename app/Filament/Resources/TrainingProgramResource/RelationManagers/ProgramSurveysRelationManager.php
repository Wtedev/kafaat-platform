<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Enums\SurveyQuestionType;
use App\Enums\SurveyType;
use App\Exports\ProgramSurveyExport;
use App\Models\ProgramSurvey;
use App\Models\SurveyTemplate;
use App\Models\TrainingProgram;
use App\Services\Surveys\ProgramSurveyService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Facades\Excel;

class ProgramSurveysRelationManager extends RelationManager
{
    protected static string $relationship = 'surveys';

    protected static ?string $title = 'الاستبيانات';

    protected static ?string $modelLabel = 'استبيان';

    protected static ?string $pluralModelLabel = 'الاستبيانات';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user !== null
            && $ownerRecord instanceof TrainingProgram
            && ($user->can('view', $ownerRecord) || $user->can('update', $ownerRecord));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(function (): ?string {
                /** @var TrainingProgram $program */
                $program = $this->getOwnerRecord();
                $existing = $program->surveys()->pluck('type')->map(fn (SurveyType|string $type): string => $type instanceof SurveyType ? $type->value : $type);
                $missing = collect(SurveyType::cases())
                    ->reject(fn (SurveyType $type): bool => $existing->contains($type->value))
                    ->map(fn (SurveyType $type): string => $type->label());

                return $missing->isEmpty() ? null : 'لم يُنشأ: '.$missing->implode('، ');
            })
            ->columns([
                TextColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn (SurveyType|string $state): string => $state instanceof SurveyType ? $state->label() : (SurveyType::tryFrom($state)?->label() ?? $state)),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->state(fn (ProgramSurvey $record): string => $record->statusLabel()),
                TextColumn::make('responses')
                    ->label('الردود / المقبولون')
                    ->state(function (ProgramSurvey $record): string {
                        $service = app(ProgramSurveyService::class);

                        return $service->responseCount($record).' / '.$service->eligibleCount($record);
                    }),
                TextColumn::make('opens_at')->label('يفتح')->dateTime('Y-m-d H:i'),
                TextColumn::make('closes_at')->label('يغلق')->dateTime('Y-m-d H:i'),
            ])
            ->headerActions([
                Action::make('provision')
                    ->label('إنشاء الاستبيانات')
                    ->visible(fn (): bool => $this->getOwnerRecord()->surveys()->count() === 0
                        && (auth()->user()?->can('update', $this->getOwnerRecord()) ?? false))
                    ->form([
                        Select::make('source')
                            ->label('طريقة الإنشاء')
                            ->options([
                                'template' => 'من قالب',
                                'blank' => 'من الصفر',
                            ])
                            ->default('template')
                            ->required()
                            ->live(),
                        Select::make('pre_template_id')
                            ->label('قالب القبلي')
                            ->options(fn (): array => SurveyTemplate::query()->orderBy('title')->pluck('title', 'id')->all())
                            ->visible(fn (Get $get): bool => $get('source') !== 'blank')
                            ->required(fn (Get $get): bool => $get('source') !== 'blank'),
                        Select::make('satisfaction_template_id')
                            ->label('قالب الرضا')
                            ->options(fn (): array => SurveyTemplate::query()->orderBy('title')->pluck('title', 'id')->all())
                            ->visible(fn (Get $get): bool => $get('source') !== 'blank')
                            ->required(fn (Get $get): bool => $get('source') !== 'blank'),
                    ])
                    ->action(function (array $data): void {
                        /** @var TrainingProgram $program */
                        $program = $this->getOwnerRecord();
                        $service = app(ProgramSurveyService::class);
                        if (($data['source'] ?? 'template') === 'blank') {
                            $service->provisionFromScratch($program);

                            return;
                        }

                        $service->provision(
                            $program,
                            SurveyTemplate::query()->findOrFail($data['pre_template_id']),
                            SurveyTemplate::query()->findOrFail($data['satisfaction_template_id']),
                        );
                    }),
            ])
            ->actions([
                Action::make('copyLink')
                    ->label('نسخ الرابط')
                    ->modalHeading('رابط الاستبيان')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(fn (ProgramSurvey $record): HtmlString => new HtmlString(
                        '<p dir="ltr">'.e($record->publicUrl()).'</p>'
                    )),
                Action::make('report')
                    ->label('التقرير')
                    ->modalHeading('تقرير الاستبيان')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(function (ProgramSurvey $record): HtmlString {
                        $report = app(ProgramSurveyService::class)->report($record);
                        $rate = $report['rate'] === null ? '—' : $report['rate'].'%';
                        $lines = array_map(
                            fn (string $line): string => '<li>'.e($line).'</li>',
                            $report['lines'],
                        );
                        $comparison = '';
                        foreach ($report['comparison'] as $row) {
                            $comparison .= '<li>'.e($row['prompt']).': قبلي '.e((string) ($row['pre_average'] ?? '—')).' / بعدي '.e((string) ($row['post_average'] ?? '—')).'</li>';
                        }

                        return new HtmlString(
                            '<p>الردود '.$report['responses'].' / المقبولون '.$report['eligible'].' — نسبة الاستجابة '.$rate.'</p>'
                            .($lines === [] ? '' : '<ul>'.implode('', $lines).'</ul>')
                            .($comparison === '' ? '' : '<p>مقارنة المقياس</p><ul>'.$comparison.'</ul>')
                        );
                    }),
                Action::make('export')
                    ->label('تصدير Excel')
                    ->action(fn (ProgramSurvey $record) => Excel::download(
                        new ProgramSurveyExport($record),
                        'survey-'.$record->type->value.'.xlsx',
                    )),
                EditAction::make()
                    ->label('تعديل')
                    ->modalDescription(function (ProgramSurvey $record): ?string {
                        if (app(ProgramSurveyService::class)->questionsLocked($record)) {
                            return ProgramSurveyService::QUESTIONS_LOCKED_MESSAGE;
                        }

                        return $record->type === SurveyType::Post
                            ? ProgramSurveyService::POST_FOLLOWS_PRE_MESSAGE
                            : null;
                    })
                    ->authorize(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->fillForm(function (ProgramSurvey $record): array {
                        $locked = app(ProgramSurveyService::class)->questionsLocked($record);

                        return [
                            'opens_at' => $record->opens_at,
                            'closes_at' => $record->closes_at,
                            'questions' => $locked || $record->type === SurveyType::Post
                                ? []
                                : $record->questions()->orderBy('position')->get()->map(fn ($question): array => [
                                    'type' => $question->type->value,
                                    'prompt' => $question->prompt,
                                    'options' => implode("\n", $question->options ?? []),
                                    'required' => $question->required,
                                    'scale_min_label' => $question->scale_min_label,
                                    'scale_max_label' => $question->scale_max_label,
                                ])->all(),
                        ];
                    })
                    ->form([
                        DateTimePicker::make('opens_at')->label('يفتح'),
                        DateTimePicker::make('closes_at')->label('يغلق'),
                        Placeholder::make('post_notice')
                            ->label('')
                            ->content(ProgramSurveyService::POST_FOLLOWS_PRE_MESSAGE)
                            ->visible(fn (?ProgramSurvey $record): bool => $record?->type === SurveyType::Post),
                        Placeholder::make('locked_notice')
                            ->label('')
                            ->content(ProgramSurveyService::QUESTIONS_LOCKED_MESSAGE)
                            ->visible(fn (?ProgramSurvey $record): bool => $record !== null && app(ProgramSurveyService::class)->questionsLocked($record)),
                        Repeater::make('questions')
                            ->label('الأسئلة')
                            ->visible(fn (?ProgramSurvey $record): bool => $record !== null
                                && $record->type !== SurveyType::Post
                                && ! app(ProgramSurveyService::class)->questionsLocked($record))
                            ->reorderable()
                            ->addActionLabel('إضافة سؤال')
                            ->schema([
                                Select::make('type')
                                    ->label('النوع')
                                    ->options(collect(SurveyQuestionType::cases())->mapWithKeys(
                                        fn (SurveyQuestionType $type): array => [$type->value => $type->label()]
                                    )->all())
                                    ->required()
                                    ->live(),
                                TextInput::make('prompt')->label('نص السؤال')->required(),
                                TextInput::make('scale_min_label')
                                    ->label('نص بداية المقياس')
                                    ->maxLength(80)
                                    ->visible(fn (Get $get): bool => $get('type') === SurveyQuestionType::Scale->value),
                                TextInput::make('scale_max_label')
                                    ->label('نص نهاية المقياس')
                                    ->maxLength(80)
                                    ->visible(fn (Get $get): bool => $get('type') === SurveyQuestionType::Scale->value),
                                Textarea::make('options')
                                    ->label('الخيارات، كل خيار في سطر')
                                    ->visible(fn (Get $get): bool => in_array($get('type'), [
                                        SurveyQuestionType::Single->value,
                                        SurveyQuestionType::Multiple->value,
                                    ], true)),
                                Toggle::make('required')->label('إلزامي')->default(true),
                            ]),
                    ])
                    ->action(function (ProgramSurvey $record, array $data): void {
                        $service = app(ProgramSurveyService::class);
                        $service->updateWindow($record, $data['opens_at'] ?? null, $data['closes_at'] ?? null);
                        if (array_key_exists('questions', $data) && $record->type !== SurveyType::Post) {
                            $service->replaceQuestions($record, $data['questions']);
                        }
                    }),
            ]);
    }
}

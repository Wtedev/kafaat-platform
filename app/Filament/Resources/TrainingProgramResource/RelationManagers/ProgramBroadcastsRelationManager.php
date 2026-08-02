<?php

namespace App\Filament\Resources\TrainingProgramResource\RelationManagers;

use App\Enums\ProgramBroadcastAudienceMode;
use App\Enums\ProgramBroadcastStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\ProgramBroadcastException;
use App\Models\ProgramBroadcast;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramBroadcasts\ProgramBroadcastService;
use App\Support\NewsFormSupport;
use App\Support\RichContentSupport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Throwable;

class ProgramBroadcastsRelationManager extends RelationManager
{
    protected static string $relationship = 'broadcasts';

    protected static ?string $title = 'الرسائل الجماعية';

    protected static ?string $modelLabel = 'رسالة جماعية';

    protected static ?string $pluralModelLabel = 'الرسائل الجماعية';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if ($user === null || ! $ownerRecord instanceof TrainingProgram) {
            return false;
        }

        if ($ownerRecord instanceof TrainingProgram) {
            return $user->can('viewOperational', $ownerRecord);
        }

        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->broadcastFormSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')
                    ->label('الموضوع')
                    ->searchable()
                    ->limit(40)
                    ->wrap(),
                TextColumn::make('audience_label')
                    ->label('الجمهور')
                    ->state(fn (ProgramBroadcast $record): string => $record->audienceLabel())
                    ->wrap(),
                TextColumn::make('recipients_count')
                    ->label('المستلمون')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?ProgramBroadcastStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?ProgramBroadcastStatus $state): string => $state?->badgeColor() ?? 'gray'),
                TextColumn::make('sent_count')
                    ->label('أُرسل')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('failed_count')
                    ->label('فشل')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('creator.name')
                    ->label('المنشئ')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('أُنشئت')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
                TextColumn::make('sending_started_at')
                    ->label('بدء الإرسال')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),
                TextColumn::make('sending_completed_at')
                    ->label('اكتمال الإرسال')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('8s')
            ->emptyStateHeading('لا توجد رسائل جماعية بعد')
            ->emptyStateDescription('أنشئ رسالة جديدة لإرسالها إلى مسجّلي هذا البرنامج.')
            ->emptyStateIcon('heroicon-o-envelope')
            ->headerActions([
                CreateAction::make()
                    ->label('رسالة جديدة')
                    ->modalHeading('رسالة جماعية جديدة')
                    ->modalSubmitActionLabel('حفظ كمسودة')
                    ->createAnother(false)
                    ->authorize(fn (): bool => $this->canManage())
                    ->form($this->broadcastFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['content'] = RichContentSupport::normalizeForStorage($data['content'] ?? null);
                        $data['audience_mode'] = $data['audience_mode'] ?? ProgramBroadcastAudienceMode::Statuses->value;
                        if (($data['audience_mode'] ?? null) === ProgramBroadcastAudienceMode::All->value) {
                            $data['audience_statuses'] = null;
                        }

                        return $data;
                    })
                    ->using(function (array $data): ProgramBroadcast {
                        /** @var TrainingProgram $program */
                        $program = $this->getOwnerRecord();
                        /** @var User $actor */
                        $actor = auth()->user();

                        return app(ProgramBroadcastService::class)->createDraft($program, $actor, $data);
                    })
                    ->successNotificationTitle('تم حفظ المسودة')
                    ->extraModalFooterActions(fn (CreateAction $action): array => [
                        Action::make('sendNowFromCreate')
                            ->label('إرسال الآن')
                            ->color('success')
                            ->icon('heroicon-o-paper-airplane')
                            ->requiresConfirmation()
                            ->modalHeading('تأكيد إرسال الرسالة الجماعية')
                            ->modalDescription('سيتم حفظ المسودة ثم إرسالها فوراً في الخلفية لمسجّلي هذا البرنامج فقط. لا يمكن التراجع بعد التأكيد.')
                            ->modalSubmitActionLabel('تأكيد الإرسال')
                            ->action(function () use ($action): void {
                                $this->createAndSend($action);
                            }),
                    ]),
            ])
            ->actions([
                Action::make('viewBroadcast')
                    ->label('عرض')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (ProgramBroadcast $record): string => 'رسالة: '.$record->subject)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->form(fn (ProgramBroadcast $record): array => $this->viewBroadcastSchema($record)),

                Action::make('editDraft')
                    ->label('تعديل المسودة')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (ProgramBroadcast $record): bool => $record->isDraft() && $this->canManage())
                    ->fillForm(fn (ProgramBroadcast $record): array => [
                        'subject' => $record->subject,
                        'content' => $record->content,
                        'audience_mode' => $record->audience_mode?->value ?? ProgramBroadcastAudienceMode::Statuses->value,
                        'audience_statuses' => $record->audience_statuses ?? ProgramBroadcastService::DEFAULT_AUDIENCE_STATUSES,
                    ])
                    ->form($this->broadcastFormSchema())
                    ->action(function (ProgramBroadcast $record, array $data): void {
                        try {
                            /** @var User $actor */
                            $actor = auth()->user();
                            app(ProgramBroadcastService::class)->updateDraft($record, $actor, $data);
                            Notification::make()->title('تم تحديث المسودة')->success()->send();
                        } catch (ProgramBroadcastException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('sendDraft')
                    ->label('إرسال الآن')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (ProgramBroadcast $record): bool => $record->isDraft() && $this->canManage())
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد إرسال الرسالة الجماعية')
                    ->modalDescription(fn (ProgramBroadcast $record): string => $this->sendConfirmDescription($record))
                    ->modalSubmitActionLabel('تأكيد الإرسال — لا يمكن التراجع')
                    ->action(function (ProgramBroadcast $record): void {
                        $this->sendBroadcast($record);
                    }),

                Action::make('retryFailed')
                    ->label('إعادة محاولة الفاشلة')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (ProgramBroadcast $record): bool => $record->canRetryFailed() && $this->canManage())
                    ->requiresConfirmation()
                    ->modalHeading('إعادة محاولة الإرسال للفاشلة فقط')
                    ->modalDescription('سيتم إعادة إرسال الرسائل التي فشلت فقط، دون إعادة إرسال الناجحة.')
                    ->action(function (ProgramBroadcast $record): void {
                        try {
                            /** @var User $actor */
                            $actor = auth()->user();
                            app(ProgramBroadcastService::class)->retryFailed($record, $actor);
                            Notification::make()
                                ->title('بدأت إعادة المحاولة في الخلفية')
                                ->body('يتم إرسال الرسائل الفاشلة عبر الطابور.')
                                ->success()
                                ->send();
                        } catch (ProgramBroadcastException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        } catch (Throwable) {
                            Notification::make()->title('تعذّرت إعادة المحاولة. حاول مرة أخرى.')->danger()->send();
                        }
                    }),

                Action::make('copyToDraft')
                    ->label('نسخ إلى مسودة جديدة')
                    ->icon('heroicon-o-document-duplicate')
                    ->visible(fn (ProgramBroadcast $record): bool => ! $record->isDraft() && $this->canManage())
                    ->action(function (ProgramBroadcast $record): void {
                        try {
                            /** @var User $actor */
                            $actor = auth()->user();
                            app(ProgramBroadcastService::class)->copyToNewDraft($record, $actor);
                            Notification::make()->title('تم إنشاء مسودة جديدة من الرسالة')->success()->send();
                        } catch (ProgramBroadcastException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                DeleteAction::make()
                    ->label('حذف المسودة')
                    ->visible(fn (ProgramBroadcast $record): bool => $record->canBeDeleted() && $this->canManage())
                    ->using(function (ProgramBroadcast $record): void {
                        /** @var User $actor */
                        $actor = auth()->user();
                        app(ProgramBroadcastService::class)->deleteDraft($record, $actor);
                    }),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function broadcastFormSchema(): array
    {
        return [
            TextInput::make('subject')
                ->label('الموضوع')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->columnSpanFull(),
            NewsFormSupport::brandedRichEditorField(
                'content',
                'محتوى الرسالة',
                required: true,
                placeholder: 'اكتب نص الرسالة هنا…',
                helperText: 'نفس محرّر الأخبار. يُنظَّف المحتوى قبل المعاينة والإرسال.',
            )->live(onBlur: true),
            Radio::make('audience_mode')
                ->label('الجمهور')
                ->options([
                    ProgramBroadcastAudienceMode::All->value => ProgramBroadcastAudienceMode::All->label(),
                    ProgramBroadcastAudienceMode::Statuses->value => ProgramBroadcastAudienceMode::Statuses->label(),
                ])
                ->default(ProgramBroadcastAudienceMode::Statuses->value)
                ->live()
                ->required()
                ->columnSpanFull(),
            CheckboxList::make('audience_statuses')
                ->label('حالات التسجيل المستهدفة')
                ->options([
                    RegistrationStatus::Pending->value => RegistrationStatus::Pending->label(),
                    RegistrationStatus::Approved->value => RegistrationStatus::Approved->label(),
                    RegistrationStatus::Completed->value => RegistrationStatus::Completed->label(),
                    RegistrationStatus::Rejected->value => RegistrationStatus::Rejected->label(),
                ])
                ->default(ProgramBroadcastService::DEFAULT_AUDIENCE_STATUSES)
                ->columns(2)
                ->live()
                ->visible(fn (Get $get): bool => $get('audience_mode') === ProgramBroadcastAudienceMode::Statuses->value)
                ->required(fn (Get $get): bool => $get('audience_mode') === ProgramBroadcastAudienceMode::Statuses->value)
                ->helperText(function (Get $get): ?string {
                    $statuses = $get('audience_statuses') ?? [];
                    if (is_array($statuses) && in_array(RegistrationStatus::Rejected->value, $statuses, true)) {
                        return 'تحذير: تضمين المرفوضين سيرسل الرسالة لمن رُفض تسجيلهم في هذا البرنامج.';
                    }

                    return 'الافتراضي الآمن: المقبولون والمكتملون.';
                })
                ->columnSpanFull(),
            Placeholder::make('recipient_count')
                ->label('عدد المستلمين المتوقع')
                ->content(fn (Get $get): string => (string) $this->liveRecipientCount($get))
                ->helperText('يُحتسب لمسجّلي هذا البرنامج فقط، مع استبعاد الحسابات غير التشغيلية أو ذات البريد غير الصالح.')
                ->columnSpanFull(),
            ViewField::make('email_preview')
                ->label('معاينة البريد')
                ->view('filament.program-broadcasts.preview')
                ->viewData(fn (Get $get): array => $this->previewViewData($get))
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function viewBroadcastSchema(ProgramBroadcast $record): array
    {
        $contentHtml = RichContentSupport::toDisplayHtml($record->content);
        $failures = $record->recipients()
            ->where('status', 'failed')
            ->limit(20)
            ->get(['email', 'failure_reason']);

        $failureLines = $failures->map(function ($row): string {
            $reason = trim((string) ($row->failure_reason ?? 'سبب غير محدد'));

            return '• '.$this->maskEmail((string) $row->email).': '.$reason;
        })->implode("\n");

        return [
            Placeholder::make('v_subject')
                ->label('الموضوع')
                ->content($record->subject),
            Placeholder::make('v_program')
                ->label('البرنامج')
                ->content((string) ($this->getOwnerRecord()->title ?? '—')),
            Placeholder::make('v_audience')
                ->label('الجمهور وقت الإرسال')
                ->content($record->audienceLabel()),
            Placeholder::make('v_stats')
                ->label('إحصاءات التسليم')
                ->content(sprintf(
                    'المستلمون: %d — أُرسل: %d — فشل: %d — تخطّي: %d',
                    $record->recipients_count,
                    $record->sent_count,
                    $record->failed_count,
                    $record->skipped_count,
                )),
            Placeholder::make('v_content')
                ->label('المحتوى')
                ->content(new HtmlString('<div dir="rtl" class="prose prose-sm max-w-none">'.$contentHtml.'</div>')),
            Placeholder::make('v_failures')
                ->label('أسباب الفشل (آمنة)')
                ->content($failureLines !== '' ? $failureLines : 'لا توجد حالات فشل مسجّلة.')
                ->visible(fn (): bool => $record->failed_count > 0),
        ];
    }

    private function canManage(): bool
    {
        $user = auth()->user();
        /** @var TrainingProgram $program */
        $program = $this->getOwnerRecord();

        return $user instanceof User
            && $user->can('create', [ProgramBroadcast::class, $program]);
    }

    private function liveRecipientCount(Get $get): int
    {
        /** @var TrainingProgram $program */
        $program = $this->getOwnerRecord();

        return app(ProgramBroadcastService::class)->countEligibleRecipients(
            $program,
            $get('audience_mode'),
            is_array($get('audience_statuses')) ? $get('audience_statuses') : null,
        );
    }

    /**
     * @return array{beneficiaryName: string, programTitle: string, subject: string, contentHtml: string}
     */
    private function previewViewData(Get $get): array
    {
        /** @var TrainingProgram $program */
        $program = $this->getOwnerRecord();
        $payload = app(ProgramBroadcastService::class)->previewPayload(
            $program,
            (string) ($get('subject') ?? ''),
            $get('content'),
            auth()->user() instanceof User ? auth()->user() : null,
        );

        return [
            'beneficiaryName' => $payload['beneficiary_name'],
            'programTitle' => $payload['program_title'],
            'subject' => $payload['subject'] !== '' ? $payload['subject'] : '—',
            'contentHtml' => $payload['content_html'] !== ''
                ? $payload['content_html']
                : '<p style="color:#a1a1aa">اكتب المحتوى لعرض المعاينة…</p>',
        ];
    }

    private function sendConfirmDescription(ProgramBroadcast $record): string
    {
        /** @var TrainingProgram $program */
        $program = $this->getOwnerRecord();
        $count = app(ProgramBroadcastService::class)->countEligibleRecipients(
            $program,
            $record->audience_mode,
            $record->audience_statuses,
        );

        return sprintf(
            "البرنامج: %s\nالموضوع: %s\nالجمهور: %s\nعدد المستلمين: %d\n\nالإرسال فوري ولا يمكن التراجع عنه بعد التأكيد. سيتم الإرسال في الخلفية.",
            $program->title,
            $record->subject,
            $record->audienceLabel(),
            $count,
        );
    }

    private function createAndSend(CreateAction $action): void
    {
        try {
            $data = method_exists($action, 'getFormData')
                ? $action->getFormData()
                : (method_exists($action, 'getData') ? $action->getData() : []);

            if (! is_array($data) || $data === []) {
                // Fallback: mounted form state on the relation manager create action.
                $data = $this->mountedTableActionsData[0] ?? $this->mountedActionsData[0] ?? [];
            }

            /** @var TrainingProgram $program */
            $program = $this->getOwnerRecord();
            /** @var User $actor */
            $actor = auth()->user();
            $service = app(ProgramBroadcastService::class);

            $count = $service->countEligibleRecipients(
                $program,
                $data['audience_mode'] ?? null,
                is_array($data['audience_statuses'] ?? null) ? $data['audience_statuses'] : null,
            );

            if ($count === 0) {
                Notification::make()->title('لا يوجد مستلمون — تم إيقاف الإرسال')->danger()->send();

                return;
            }

            $draft = $service->createDraft($program, $actor, $data);
            $service->sendNow($draft, $actor);

            Notification::make()
                ->title('بدأ الإرسال في الخلفية')
                ->body('تتم معالجة الرسائل عبر الطابور. يمكنك متابعة التقدم من الجدول.')
                ->success()
                ->send();

            if (method_exists($action, 'cancelParentActions')) {
                $action->cancelParentActions();
            }

            $this->dispatch('$refresh');
        } catch (ProgramBroadcastException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        } catch (Throwable) {
            Notification::make()->title('تعذّر بدء الإرسال. حاول مرة أخرى.')->danger()->send();
        }
    }

    private function sendBroadcast(ProgramBroadcast $record): void
    {
        try {
            /** @var User $actor */
            $actor = auth()->user();
            /** @var TrainingProgram $program */
            $program = $this->getOwnerRecord();
            $service = app(ProgramBroadcastService::class);

            $count = $service->countEligibleRecipients(
                $program,
                $record->audience_mode,
                $record->audience_statuses,
            );

            if ($count === 0) {
                Notification::make()->title('لا يوجد مستلمون — تم إيقاف الإرسال')->danger()->send();

                return;
            }

            $service->sendNow($record, $actor);

            Notification::make()
                ->title('بدأ الإرسال في الخلفية')
                ->body('تتم معالجة الرسائل عبر الطابور. يمكنك متابعة التقدم من الجدول.')
                ->success()
                ->send();
        } catch (ProgramBroadcastException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        } catch (Throwable) {
            Notification::make()->title('تعذّر بدء الإرسال. حاول مرة أخرى.')->danger()->send();
        }
    }

    private function maskEmail(string $email): string
    {
        $email = strtolower(trim($email));
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];
        $visible = mb_substr($local, 0, 1);

        return $visible.'***@'.$domain;
    }
}

<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Enums\ProgramStatus;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Resources\TrainingProgramResource;
use App\Models\TrainingProgram;
use App\Policies\TrainingProgramPolicy;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Access: {@see TrainingProgramPolicy::update()} via
 * {@see TrainingProgramResource::canEdit()} (Filament {@see \Filament\Resources\Resource::authorizeEdit()}).
 */
class EditTrainingProgram extends BaseEditRecord
{
    protected static string $resource = TrainingProgramResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        /** @var TrainingProgram $record */
        $record = parent::resolveRecord($key);

        return $record->loadMissing(['learningPath', 'owner', 'assignee']);
    }

    public function form(Schema $schema): Schema
    {
        return TrainingProgramResource::editForm($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var TrainingProgram $record */
        $record = $this->getRecord();
        $data['visible_on_site'] = $record->status === ProgramStatus::Published;
        $data['is_linked_to_path'] = $record->learning_path_id !== null;
        $data['capacity_unlimited'] = $record->capacity === null;
        $data['editors'] = $record->editors()->pluck('id')->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var TrainingProgram $program */
        $program = $this->getRecord();
        $wasArchived = $program->status === ProgramStatus::Archived;
        $visible = (bool) ($data['visible_on_site'] ?? false);

        $linked = (bool) ($data['is_linked_to_path'] ?? false);
        $unlimited = (bool) ($data['capacity_unlimited'] ?? false);

        unset($data['is_linked_to_path'], $data['capacity_unlimited']);

        if ($unlimited) {
            $data['capacity'] = null;
        }

        if (! $linked) {
            $data['learning_path_id'] = null;
            $data['path_sort_order'] = null;
        }

        if ($wasArchived && ! $visible) {
            $data['status'] = ProgramStatus::Archived->value;
        } else {
            $data['status'] = $visible
                ? ProgramStatus::Published->value
                : ProgramStatus::Draft->value;
        }

        unset($data['visible_on_site']);

        return $data;
    }

    /**
     * @return array<class-string<RelationManager> | RelationGroup | RelationManagerConfiguration>
     */
    protected function getAllRelationManagers(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make('تعديل البيانات الأساسية وفريق العمل من هذه الصفحة؛ إدارة التسجيلات من صفحة عرض البرنامج.')
                    ->columnSpanFull(),
                $this->getFormContentComponent(),
            ]);
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('حفظ التغييرات')
                ->color('success'),
            DeleteAction::make()
                ->label('حذف البرنامج')
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false),
            $this->getCancelFormAction()
                ->label('إلغاء')
                ->color('gray'),
        ];
    }
}

<?php

namespace App\Filament\Resources\LearningPathResource\Pages;

use App\Enums\PathStatus;
use App\Filament\Resources\LearningPathResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Models\LearningPath;
use App\Policies\LearningPathPolicy;
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
 * Access: {@see LearningPathPolicy::update()} via {@see LearningPathResource::canEdit()}.
 */
class EditLearningPath extends BaseEditRecord
{
    protected static string $resource = LearningPathResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        /** @var LearningPath $record */
        $record = parent::resolveRecord($key);

        return $record->loadMissing(['owner', 'creator']);
    }

    public function form(Schema $schema): Schema
    {
        return LearningPathResource::editForm($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var LearningPath $record */
        $record = $this->getRecord();
        $data['visible_on_site'] = $record->status === PathStatus::Published;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var LearningPath $path */
        $path = $this->getRecord();
        $wasArchived = $path->status === PathStatus::Archived;

        $visible = (bool) ($data['visible_on_site'] ?? false);

        if ($wasArchived && ! $visible) {
            $data['status'] = PathStatus::Archived->value;
        } else {
            $data['status'] = $visible
                ? PathStatus::Published->value
                : PathStatus::Draft->value;
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
                Text::make('تعديل البيانات الأساسية يتم من صفحة التعديل، وإدارة التسجيلات والفريق من صفحة العرض.')
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
                ->label('حذف المسار')
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false),
            $this->getCancelFormAction()
                ->label('إلغاء')
                ->color('gray'),
        ];
    }
}

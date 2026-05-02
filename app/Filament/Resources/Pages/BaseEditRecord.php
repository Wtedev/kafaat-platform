<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord as FilamentEditRecord;

/**
 * يجمع إجراءات صفحة التعديل مع «حفظ التغييرات» و«إلغاء» في شريط واحد أسفل النموذج (بدون أزرار في هيدر الصفحة).
 */
abstract class BaseEditRecord extends FilamentEditRecord
{
    /**
     * إجراءات بجانب حفظ/إلغاء (عرض، حذف، نشر، …).
     *
     * @return array<int, Action|ActionGroup>
     */
    protected function getRecordToolbarActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            ...$this->getRecordToolbarActions(),
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}

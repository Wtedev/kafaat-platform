<?php

namespace App\Filament\Resources\NewsResource\Pages;

use App\Filament\Resources\NewsResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Models\News;
use App\Services\News\NewsImageSyncService;
use App\Support\NewsFormSupport;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EditNews extends BaseEditRecord
{
    protected static string $resource = NewsResource::class;

    public static bool $formActionsAreSticky = true;

    /** الحقل المفتوح للتحرير في الواجهة (عرض ثابت ↔ حقل). */
    public ?string $editingField = null;

    /** يُحدَّث بعد حفظ الصور من المودال لإعادة رسم المعاينة. */
    public int $imagesRevision = 0;

    public function form(Schema $schema): Schema
    {
        return NewsResource::editForm($schema, $this);
    }

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();
        $full = trim((string) $record->title);
        if ($full === '') {
            $full = '/'.ltrim((string) $record->slug, '/');
        }
        $short = Str::limit($full, 96);

        $badgeClasses = match ($record->publicationStatusLabel()) {
            'منشور' => 'bg-brand-secondary text-white ring-1 ring-[#b8e0e2]/40',
            'مجدول' => 'bg-[#fbbb2e]/25 text-white ring-1 ring-[#f5dfa8]/35',
            default => 'bg-zinc-600/50 text-zinc-100 ring-1 ring-white/12',
        };

        return new HtmlString(
            '<div class="news-edit-header-block">'
            .'<div class="flex flex-wrap items-center gap-x-4 gap-y-2">'
            .'<h1 class="news-edit-main-title min-w-0 max-w-5xl flex-1 truncate text-2xl font-bold tracking-tight text-gray-950 dark:text-white" title="'.e($full).'">'
            .e('تعديل خبر - '.$short)
            .'</h1>'
            .'<span class="inline-flex shrink-0 items-center gap-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">'
            .'<span>الحالة:</span>'
            .'<span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold '.$badgeClasses.'">'
            .e($record->publicationStatusLabel())
            .'</span>'
            .'</span>'
            .'</div>'
            .'</div>'
        );
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewPublic')
                ->label('معاينة')
                ->icon('heroicon-o-eye')
                ->size(Size::Medium)
                ->color('success')
                ->url(fn (): string => route('public.news.show', ['news' => $this->getRecord()->slug]))
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->getRecord()->slug)),
        ];
    }

    public function isEditingField(string $field): bool
    {
        return $this->editingField === $field;
    }

    public function startEditingField(string $field): void
    {
        $this->editingField = $field;
    }

    /**
     * إغلاق وضع التحرير للحقل الحالي.
     *
     * @param  bool  $discard  عند true يُعاد الحقل من قاعدة البيانات ويُهمل ما كُتب في النموذج.
     */
    public function stopEditingField(bool $discard = false): void
    {
        if ($this->editingField === null) {
            return;
        }

        $field = $this->editingField;

        if ($discard) {
            $filled = $this->mutateFormDataBeforeFill($this->getRecord()->attributesToArray());
            $this->data[$field] = $filled[$field] ?? null;
        }

        $this->editingField = null;
    }

    /**
     * تحديث النموذج من قاعدة البيانات بعد تغيير حالة النشر من بطاقة الملخص.
     */
    public function refreshFormFromRecord(): void
    {
        $this->editingField = null;
        $this->fillForm();
    }

    protected function afterSave(): void
    {
        $this->editingField = null;
        $this->refreshRecordImagesState();
    }

    protected function getSavedNotification(): ?Notification
    {
        $this->getRecord()->refresh();

        /** @var News $record */
        $record = $this->getRecord();

        return Notification::make()
            ->success()
            ->title('تم حفظ التعديلات')
            ->body('حالة النشر: '.$record->publicationStatusLabel().'.');
    }

    /**
     * حفظ صور الخبر من المودال — المصدر الوحيد لتعديل الصور في صفحة التعديل.
     *
     * @param  array<int, array<string, mixed>>  $imagesState
     */
    public function persistImagesFromModal(array $imagesState): void
    {
        $record = $this->getRecord();

        app(NewsImageSyncService::class)->sync($record, $imagesState, allowEmpty: true);

        $this->refreshRecordImagesState();

        Notification::make()
            ->success()
            ->title('تم حفظ الصور')
            ->body('صور الخبر محفوظة. يمكنك متابعة تعديل النص ثم حفظ التغييرات.')
            ->send();
    }

    public function refreshRecordImagesState(): void
    {
        $this->record->refresh();
        $this->record->unsetRelation('images');
        $this->imagesRevision++;
    }

    public function previewPrimaryImageUrl(): ?string
    {
        $path = app(NewsImageSyncService::class)->primaryImagePath(
            $this->getRecord()->fresh()
        );

        return filled($path) ? NewsResource::resolveNewsImagePublicUrl($path) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['news_images'], $data['image']);

        return $data;
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('حفظ التغييرات')
                ->color('success'),
            Action::make('deleteNews')
                ->label('حذف الخبر')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('حذف الخبر')
                ->modalDescription('سيتم حذف الخبر نهائياً من المنصة (مسودة أو مجدول أو منشور). لا يمكن التراجع عن هذا الإجراء.')
                ->modalSubmitActionLabel('حذف نهائياً')
                ->action(function (): void {
                    $record = $this->getRecord();
                    $this->authorize('delete', $record);
                    $record->delete();
                    $this->redirect(NewsResource::getUrl('index'));
                }),
            $this->getCancelFormAction()
                ->label('إلغاء'),
        ];
    }

    public static function contentModalEditorField(): RichEditor
    {
        return NewsFormSupport::contentRichEditorField()
            ->label('المحتوى');
    }
}

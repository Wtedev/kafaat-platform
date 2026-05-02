<?php

namespace App\Filament\Resources\NewsResource\Pages;

use App\Filament\Resources\NewsResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Models\News;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EditNews extends BaseEditRecord
{
    protected static string $resource = NewsResource::class;

    public static bool $formActionsAreSticky = true;

    /** الحقل المفتوح للتحرير في الواجهة (عرض ثابت ↔ حقل). */
    public ?string $editingField = null;

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
        $indexUrl = NewsResource::getUrl('index');

        $badgeClasses = match ($record->publicationStatusLabel()) {
            'منشور' => 'bg-emerald-600 text-white ring-1 ring-emerald-500/40',
            'مجدول' => 'bg-amber-500/25 text-amber-100 ring-1 ring-amber-500/35',
            default => 'bg-zinc-600/50 text-zinc-100 ring-1 ring-white/12',
        };

        return new HtmlString(
            '<div class="news-edit-header-block space-y-1">'
            .'<p class="news-edit-breadcrumb text-xs font-medium text-zinc-500 dark:text-zinc-400">'
            .'<a class="transition-colors hover:text-primary-400" href="'.e($indexUrl).'">الأخبار</a>'
            .' <span class="text-zinc-500/80" aria-hidden="true">/</span> '
            .'<span>تعديل</span>'
            .'</p>'
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
     * حفظ صورة من مودال دون تغيير منطق التخزين.
     */
    public function persistImageFromModal(mixed $imageState): void
    {
        $newPath = $imageState;
        if (is_array($newPath)) {
            $newPath = $newPath[0] ?? null;
        }

        $record = $this->getRecord();
        $originalPath = $record->getOriginal('image');

        if (filled($originalPath)
            && is_string($originalPath)
            && $originalPath !== $newPath
            && ! Str::startsWith($originalPath, ['http://', 'https://'])
            && Storage::disk('public')->exists($originalPath)) {
            Storage::disk('public')->delete($originalPath);
        }

        $record->update(['image' => $newPath]);
        $this->data['image'] = $newPath;
        $this->fillForm();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $originalPath = $this->getRecord()->getOriginal('image');

        $newPath = $data['image'] ?? null;
        if (is_array($newPath)) {
            $newPath = $newPath[0] ?? null;
            $data['image'] = $newPath;
        }

        if (filled($originalPath)
            && is_string($originalPath)
            && $originalPath !== $newPath
            && ! Str::startsWith($originalPath, ['http://', 'https://'])
            && Storage::disk('public')->exists($originalPath)) {
            Storage::disk('public')->delete($originalPath);
        }

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

    /**
     * مكوّن رفع الصورة داخل مودال تعديل الصورة المميزة.
     */
    public static function featuredImageModalUploadField(): FileUpload
    {
        return FileUpload::make('image')
            ->label('صورة الخبر')
            ->image()
            ->disk('public')
            ->directory('news/images')
            ->visibility('public')
            ->maxSize(4096)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->imagePreviewHeight('12rem')
            ->imageResizeMode('cover')
            ->nullable()
            ->helperText('JPEG أو PNG أو WebP — حتى 4 ميجابايت.')
            ->columnSpanFull();
    }

    /**
     * محرر المحتوى الغني داخل المودال (تنسيق مدونات: ألوان، عناوين، قوائم، جداول، …).
     */
    public static function contentModalEditorField(): RichEditor
    {
        return RichEditor::make('content')
            ->label('المحتوى')
            ->required()
            ->columnSpanFull()
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'textColor', 'highlight'],
                ['link'],
                ['h2', 'h3', 'h4', 'blockquote', 'code', 'codeBlock'],
                ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
                ['bulletList', 'orderedList'],
                ['table'],
                ['horizontalRule'],
                ['undo', 'redo'],
            ])
            ->textColors([
                'أسود' => '#18181b',
                'رمادي' => '#71717a',
                'أحمر' => '#dc2626',
                'برتقالي' => '#ea580c',
                'ذهبي' => '#ca8a04',
                'أخضر' => '#16a34a',
                'تركواز' => '#0d9488',
                'أزرق' => '#2563eb',
                'بنفسجي' => '#7c3aed',
                'وردي' => '#db2777',
            ])
            ->extraInputAttributes([
                'dir' => 'rtl',
                'style' => 'min-height: 22rem;',
            ]);
    }
}

<?php

namespace App\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

final class NewsFormSupport
{
    /** نسبة بطاقة الخبر العامة (عرض ٣٢٠ × ارتفاع ١٩٢). */
    public const CARD_ASPECT_RATIO = '5:3';

    public static function newsImageUploadField(string $name = 'path'): FileUpload
    {
        return FileUpload::make($name)
            ->label('الصورة')
            ->image()
            ->disk('public')
            ->directory('news/images')
            ->visibility('public')
            ->storeFiles()
            ->maxSize(4096)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->imagePreviewHeight('10rem')
            ->panelAspectRatio(self::CARD_ASPECT_RATIO)
            ->imageEditor()
            ->imageAspectRatio(self::CARD_ASPECT_RATIO)
            ->automaticallyCropImagesToAspectRatio()
            ->imageEditorAspectRatioOptions([
                self::CARD_ASPECT_RATIO => 'بطاقة الخبر (٥:٣)',
            ])
            ->automaticallyResizeImagesMode('cover')
            ->afterStateUpdated(function (FileUpload $component): void {
                // Persist to the public disk as soon as FilePond finishes uploading,
                // so modal/create saves never keep a Livewire temporary preview URL.
                $component->saveUploadedFiles();
            })
            ->required()
            ->columnSpanFull();
    }

    public static function newsImagesRepeater(): Repeater
    {
        return Repeater::make('news_images')
            ->label('صور الخبر')
            ->schema([
                self::newsImageUploadField(),
                Toggle::make('is_primary')
                    ->label('صورة أساسية (تظهر في بطاقة الخبر)')
                    ->inline(false)
                    ->default(false)
                    ->helperText('اختر صورة واحدة فقط كأساسية. الباقي يظهر في معرض صفحة الخبر.'),
            ])
            ->addActionLabel('إضافة صورة')
            ->reorderable()
            ->collapsible()
            ->itemLabel(function (array $state): string {
                if ((bool) ($state['is_primary'] ?? false)) {
                    return 'صورة أساسية';
                }

                return 'صورة إضافية';
            })
            ->default([])
            ->columnSpanFull()
            ->helperText('قص الصور بنسبة ٥:٣ لتطابق بطاقة الخبر. يمكن رفع أكثر من صورة.');
    }

    public static function contentRichEditorField(): RichEditor
    {
        return RichEditor::make('content')
            ->label('نص الخبر')
            ->required()
            ->columnSpanFull()
            ->placeholder('ابدأ بكتابة محتوى الخبر هنا…')
            ->helperText('انقر داخل المربع وابدأ الكتابة مباشرة. استخدم شريط الأدوات للتنسيق عند الحاجة.')
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
                'أزرق غامق' => '#335483',
                'تركواز' => '#1a9399',
                'أصفر' => '#fbbb2e',
                'أحمر' => '#ec6056',
            ])
            ->extraAttributes([
                'class' => 'news-content-rich-editor',
            ]);
    }

    /**
     * بطاقة محرر المحتوى — للإنشاء والتعديل.
     */
    public static function contentEditorSection(): Section
    {
        return Section::make('المحتوى')
            ->description('اكتب نص الخبر كاملاً كما سيظهر في صفحة التفاصيل.')
            ->icon('heroicon-o-document-text')
            ->schema([
                self::contentRichEditorField(),
            ])
            ->columnSpanFull()
            ->extraAttributes([
                'class' => 'news-content-editor-section',
            ]);
    }
}

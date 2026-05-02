<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsResource\Pages;
use App\Filament\Resources\NewsResource\Pages\EditNews;
use App\Models\News;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class NewsResource extends Resource
{
    protected static ?string $model = News::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static string|\UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'الأخبار';

    protected static ?string $modelLabel = 'خبر';

    protected static ?string $pluralModelLabel = 'الأخبار';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('view_news') || $user->can('manage_news'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_news') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('manage_news') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('manage_news') ?? false;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    /**
     * @return array<string, string>
     */
    protected static function categoryOptions(): array
    {
        return [
            'إطلاق' => 'إطلاق',
            'ورشة عمل' => 'ورشة عمل',
            'شراكة' => 'شراكة',
            'برامج' => 'برامج',
            'تقارير' => 'تقارير',
            'فعالية' => 'فعالية',
            'أخرى' => 'أخرى',
        ];
    }

    public static function platformTimezoneLabel(): string
    {
        return (string) config('app.timezone');
    }

    /**
     * نص مساعد موحّد لحقول التاريخ في لوحة الأخبار.
     */
    public static function publicationTimezoneHelper(): string
    {
        return 'يتم احتساب الوقت حسب توقيت المنصة: '.static::platformTimezoneLabel().'.';
    }

    /**
     * رابط عرض صورة الخبر (تخزين محلي أو رابط خارجي) أو صورة افتراضية.
     */
    public static function resolveNewsImagePublicUrl(?string $path): string
    {
        if ($path === null || $path === '') {
            return asset('images/news-placeholder.svg');
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : asset('images/news-placeholder.svg');
    }

    /**
     * حقل رفع صورة الخبر — نفس المسمى في الإنشاء والتعديل.
     */
    public static function newsImageUploadField(): FileUpload
    {
        return FileUpload::make('image')
            ->label('صورة الخبر')
            ->image()
            ->disk('public')
            ->directory('news/images')
            ->visibility('public')
            ->maxSize(4096)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->imagePreviewHeight('14rem')
            ->imageResizeMode('cover')
            ->nullable()
            ->helperText('JPEG أو PNG أو WebP — حتى 4 ميجابايت. تُحفظ في التخزين العام ويُعرض معاينة تلقائياً.')
            ->columnSpanFull();
    }

    /**
     * نموذج الإنشاء — بدون حقل published_at؛ النشر/الجدولة عبر أزرار الصفحة فقط.
     */
    public static function createForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('محتوى الخبر')
                ->description('احفظ كمسودة أو انشر فوراً أو جدّل الموعد — دون ضبط التاريخ يدوياً هنا.')
                ->columns(2)
                ->schema(static::contentFieldsSchema()),
        ]);
    }

    /**
     * نموذج التعديل — تخطيط لوحة معلومات: معاينة + تعديل مضمّن.
     */
    public static function editForm(Schema $schema, EditNews $page): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Group::make(static::editNewsPageSchemaComponents($page))
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'news-edit-dashboard-stack news-edit-page-inner mx-auto w-full max-w-7xl space-y-6']),
            ]);
    }

    /**
     * @return array<int, Component|Group>
     */
    private static function editNewsPageSchemaComponents(EditNews $page): array
    {
        /** @var Closure(): News $resolveNews */
        $resolveNews = fn (): News => $page->getRecord();

        return [
            Grid::make(['default' => 1, 'lg' => 2])
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => 'news-edit-main-grid news-edit-two-col-ltr gap-6 lg:gap-8',
                ])
                ->schema([
                    static::editNewsFeaturedImageCard($page, $resolveNews)
                        ->columnSpan(1),
                    Group::make([
                        static::editNewsDatesCard(),
                        static::editNewsFieldCard($page, 'title', 'عنوان الخبر', static::editNewsTitleStack($page, $resolveNews), true, 'soft'),
                        static::editNewsMetadataCard($page, $resolveNews),
                        static::editNewsContentCard($page, $resolveNews),
                    ])
                        ->columnSpan(1)
                        ->extraAttributes(['class' => 'news-edit-mock-details flex min-w-0 flex-col gap-4']),
                ]),
            TextInput::make('slug')
                ->hidden()
                ->dehydrated(),
            Hidden::make('content')
                ->dehydrated()
                ->dehydratedWhenHidden(true),
            static::editNewsHiddenImageField(),
        ];
    }

    /**
     * بطاقة الصورة: معاينة أو مساحة احتياط، والتعديل عبر المودال فقط.
     */
    private static function editNewsFeaturedImageCard(EditNews $page, Closure $resolveNews): Group
    {
        $imageLayer = Html::make(function (News $record): HtmlString {
            $url = filled($record->image) ? static::resolveNewsImagePublicUrl($record->image) : null;

            return new HtmlString(
                View::make('filament.news.edit.image-preview', [
                    'imageUrl' => $url,
                ])->render()
            );
        })
            ->columnSpanFull();

        $footer = Flex::make([
            Text::make('JPEG أو PNG أو WebP — حتى 4 ميجابايت')
                ->size(TextSize::ExtraSmall)
                ->color('gray')
                ->extraAttributes(['class' => 'news-edit-image-card__hint min-w-0 flex-1 text-zinc-500 dark:text-zinc-400']),
            Action::make('news_featured_image_edit')
                ->label('')
                ->icon('heroicon-o-pencil-square')
                ->iconButton()
                ->color('gray')
                ->extraAttributes(['class' => 'news-edit-pencil-btn'])
                ->tooltip('تعديل الصورة')
                ->modalHeading('صورة الخبر')
                ->modalSubmitActionLabel('حفظ الصورة')
                ->fillForm(fn (): array => ['image' => $resolveNews()->image])
                ->form([
                    EditNews::featuredImageModalUploadField(),
                ])
                ->action(function (array $data) use ($page): void {
                    $page->persistImageFromModal($data['image'] ?? null);
                }),
        ])
            ->columnSpanFull()
            ->alignBetween()
            ->verticallyAlignCenter()
            ->extraAttributes([
                'class' => 'news-edit-card-footer news-edit-image-card__footer mt-4 gap-3 border-t border-zinc-200/70 pt-4 dark:border-white/10',
            ]);

        return Group::make([
            Text::make('صورة الخبر')
                ->size(TextSize::ExtraSmall)
                ->weight(FontWeight::SemiBold)
                ->color('gray')
                ->extraAttributes(['class' => 'news-edit-card-header block']),
            $imageLayer,
            $footer,
        ])
            ->columnSpanFull()
            ->extraAttributes([
                'class' => 'news-edit-card news-edit-image-card news-edit-mock-image-col',
            ]);
    }

    /**
     * بطاقة التواريخ والنشر — جدول داخل البطاقة.
     */
    private static function editNewsDatesCard(): Html
    {
        return Html::make(function (News $record): HtmlString {
            return new HtmlString(
                View::make('filament.news.edit.dates-card', ['record' => $record])->render()
            );
        })
            ->columnSpanFull();
    }

    /**
     * بطاقة البيانات الوصفية: تصنيف، مقتطف، آخر تعديل — ثلاثة أعمدة مع فواصل.
     */
    private static function editNewsMetadataCard(EditNews $page, Closure $resolveNews): Group
    {
        $catCol = Group::make([
            Flex::make([
                Text::make('التصنيف')
                    ->size(TextSize::ExtraSmall)
                    ->weight(FontWeight::SemiBold)
                    ->color('gray')
                    ->extraAttributes(['class' => 'news-edit-meta-label min-w-0 flex-1']),
                static::editNewsFieldToolbarPencilOnly($page, 'category'),
            ])
                ->alignBetween()
                ->verticallyAlignStart()
                ->columnSpanFull()
                ->extraAttributes(['class' => 'news-edit-field-card-row gap-2']),
            Group::make(static::editNewsCategoryStack($page, $resolveNews))->columns(1)->columnSpanFull(),
            static::editNewsFieldToolbarApplyCancelRow($page, 'category'),
        ])
            ->columns(1)
            ->columnSpan(1)
            ->extraAttributes([
                'class' => 'news-edit-meta-grid__col min-w-0',
            ]);

        $excerptCol = Group::make([
            Flex::make([
                Text::make('المقتطف')
                    ->size(TextSize::ExtraSmall)
                    ->weight(FontWeight::SemiBold)
                    ->color('gray')
                    ->extraAttributes(['class' => 'news-edit-meta-label min-w-0 flex-1']),
                static::editNewsFieldToolbarPencilOnly($page, 'excerpt'),
            ])
                ->alignBetween()
                ->verticallyAlignStart()
                ->columnSpanFull()
                ->extraAttributes(['class' => 'news-edit-field-card-row gap-2']),
            Group::make(static::editNewsExcerptStack($page, $resolveNews))->columns(1)->columnSpanFull(),
            static::editNewsFieldToolbarApplyCancelRow($page, 'excerpt'),
        ])
            ->columns(1)
            ->columnSpan(1)
            ->extraAttributes([
                'class' => 'news-edit-meta-grid__col min-w-0',
            ]);

        $updatedCol = Group::make([
            Text::make('آخر تعديل')
                ->size(TextSize::ExtraSmall)
                ->weight(FontWeight::SemiBold)
                ->color('gray')
                ->extraAttributes(['class' => 'news-edit-meta-label']),
            Text::make(fn (News $record): string => $record->adminRelativeTime($record->updated_at))
                ->size(TextSize::Small)
                ->weight(FontWeight::Medium)
                ->color('gray')
                ->tooltip(fn (News $record): string => $record->updated_at ? $record->adminFullDateTime($record->updated_at) : ''),
        ])
            ->columns(1)
            ->columnSpan(1)
            ->extraAttributes([
                'class' => 'news-edit-meta-grid__col news-edit-meta-grid__col--readonly min-w-0',
            ]);

        return Group::make([
            Text::make('البيانات الوصفية')
                ->size(TextSize::ExtraSmall)
                ->weight(FontWeight::SemiBold)
                ->color('gray')
                ->extraAttributes(['class' => 'news-edit-card-header block']),
            Grid::make(['default' => 1, 'md' => 3])
                ->columnSpanFull()
                ->extraAttributes(['class' => 'news-edit-meta-grid'])
                ->schema([
                    $catCol,
                    $excerptCol,
                    $updatedCol,
                ]),
        ])
            ->columnSpanFull()
            ->extraAttributes([
                'class' => 'news-edit-card news-edit-metadata-card',
            ]);
    }

    /**
     * بطاقة المحتوى الكامل — معاينة + تعديل عبر المودال.
     */
    private static function editNewsContentCard(EditNews $page, Closure $resolveNews): Group
    {
        $preview = Text::make(fn (): string => static::editNewsFieldPreviewText($page, $resolveNews(), 'content', 1200))
            ->size(TextSize::Small)
            ->weight(FontWeight::Normal)
            ->color('gray')
            ->extraAttributes(['class' => 'news-edit-content-body news-edit-content-preview max-w-prose leading-relaxed line-clamp-[14] min-h-[6rem]']);

        return Group::make([
            Text::make('المحتوى')
                ->size(TextSize::ExtraSmall)
                ->weight(FontWeight::SemiBold)
                ->color('gray')
                ->extraAttributes(['class' => 'news-edit-card-header block']),
            $preview,
            Flex::make([
                Action::make('news_full_content_editor')
                    ->label('')
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->color('gray')
                    ->extraAttributes(['class' => 'news-edit-pencil-btn'])
                    ->tooltip('تعديل المحتوى')
                    ->modalHeading('المحتوى')
                    ->modalWidth('7xl')
                    ->modalSubmitActionLabel('تطبيق')
                    ->fillForm(function () use ($page, $resolveNews): array {
                        $v = data_get($page->data, 'content');

                        return [
                            'content' => ($v !== null && $v !== '') ? $v : $resolveNews()->content,
                        ];
                    })
                    ->form([
                        EditNews::contentModalEditorField(),
                    ])
                    ->action(function (array $data) use ($page): void {
                        $page->data['content'] = $data['content'] ?? '';
                    }),
            ])
                ->columnSpanFull()
                ->alignStart()
                ->extraAttributes(['class' => 'mt-2']),
        ])
            ->columnSpanFull()
            ->extraAttributes([
                'class' => 'news-edit-card news-edit-content-card',
            ]);
    }

    /**
     * حقل الصورة مخفى في النموذج للحفظ الجماعي (القيمة تُحدَّث من المودال أو الحفظ).
     */
    private static function editNewsHiddenImageField(): FileUpload
    {
        return static::newsImageUploadField()
            ->hidden()
            ->dehydrated();
    }

    /**
     * @return array<int, Component|Field>
     */
    private static function editNewsCategoryStack(EditNews $page, Closure $resolveNews): array
    {
        return [
            Text::make(fn (): string => static::editNewsCategoryPreview($page, $resolveNews()))
                ->visible(fn (): bool => ! $page->isEditingField('category'))
                ->size(TextSize::Small)
                ->weight(FontWeight::Medium)
                ->color('gray'),
            Select::make('category')
                ->label('التصنيف')
                ->hiddenLabel()
                ->options(static::categoryOptions())
                ->nullable()
                ->native(false)
                ->visible(fn (): bool => $page->isEditingField('category'))
                ->dehydratedWhenHidden(true)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Component|Field>
     */
    private static function editNewsTitleStack(EditNews $page, Closure $resolveNews): array
    {
        return [
            Text::make(fn (): string => static::editNewsFieldPreviewText($page, $resolveNews(), 'title', 800))
                ->visible(fn (): bool => ! $page->isEditingField('title'))
                ->size(TextSize::Large)
                ->weight(FontWeight::Bold)
                ->color('gray')
                ->extraAttributes(['class' => 'leading-snug tracking-tight']),
            TextInput::make('title')
                ->label('العنوان')
                ->hiddenLabel()
                ->required()
                ->maxLength(255)
                ->extraInputAttributes(['id' => 'news-field-title'])
                ->visible(fn (): bool => $page->isEditingField('title'))
                ->dehydratedWhenHidden(true)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Component|Field>
     */
    private static function editNewsExcerptStack(EditNews $page, Closure $resolveNews): array
    {
        return [
            Text::make(fn (): string => static::editNewsFieldPreviewText($page, $resolveNews(), 'excerpt', 360))
                ->visible(fn (): bool => ! $page->isEditingField('excerpt'))
                ->size(TextSize::Small)
                ->weight(FontWeight::Normal)
                ->color('gray')
                ->extraAttributes(['class' => 'leading-relaxed line-clamp-4']),
            Textarea::make('excerpt')
                ->label('المقتطف')
                ->hiddenLabel()
                ->rows(4)
                ->maxLength(500)
                ->visible(fn (): bool => $page->isEditingField('excerpt'))
                ->dehydratedWhenHidden(true)
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<int, Component|Field>  $stack
     */
    private static function editNewsFieldCard(
        EditNews $page,
        string $fieldKey,
        string $heading,
        array $stack,
        bool $fullWidth = true,
        string $skin = 'panel',
    ): Group {
        $headingEl = Text::make($heading)
            ->size(TextSize::ExtraSmall)
            ->weight(FontWeight::SemiBold)
            ->color('gray');

        if ($skin === 'soft') {
            $headingEl->extraAttributes(['class' => 'news-edit-card-header']);
        }

        $group = Group::make([
            Flex::make([
                Group::make([
                    $headingEl,
                    ...$stack,
                ])->columns(1)->columnSpanFull(),
                static::editNewsFieldToolbarPencilOnly($page, $fieldKey),
            ])
                ->columnSpanFull()
                ->alignBetween()
                ->verticallyAlignStart()
                ->extraAttributes(['class' => 'news-edit-field-card-row gap-3 sm:gap-4']),
            static::editNewsFieldToolbarApplyCancelRow($page, $fieldKey),
        ]);

        if ($fullWidth) {
            $group->columnSpanFull();
        } else {
            $group->columnSpan(1);
        }

        return $group->extraAttributes(function () use ($page, $fieldKey, $skin): array {
            $active = $page->isEditingField($fieldKey);

            if ($skin === 'strip' || $skin === 'soft-strip') {
                $base = $skin === 'soft-strip'
                    ? 'news-edit-soft-strip py-4 transition-colors duration-200'
                    : 'news-edit-strip py-4 border-b border-zinc-200/90 dark:border-white/10 transition-colors duration-200';
                $state = $active ? ' news-edit-strip--active' : '';

                return ['class' => $base.$state];
            }

            if ($skin === 'soft') {
                $base = 'news-edit-card news-edit-card--field transition-[box-shadow,border-color,background-color] duration-200';
                $state = $active
                    ? ' news-edit-card--active'
                    : '';

                return ['class' => $base.$state];
            }

            $base = 'news-edit-row rounded-2xl border p-4 sm:p-5 transition-[box-shadow,border-color,background-color] duration-200';
            $state = $active
                ? ' ring-1 ring-emerald-500/40 border-emerald-500/35 bg-emerald-500/[0.07] dark:ring-emerald-400/30'
                : ' border-zinc-200/90 bg-white/90 hover:border-zinc-300 dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-white/18';

            return ['class' => $base.$state];
        });
    }

    private static function editNewsFieldToolbarPencilOnly(EditNews $page, string $fieldKey): Flex
    {
        return Flex::make([
            Action::make('enews_edit_'.$fieldKey)
                ->label('')
                ->icon('heroicon-o-pencil-square')
                ->iconButton()
                ->color('gray')
                ->extraAttributes(['class' => 'news-edit-pencil-btn'])
                ->tooltip('تعديل الحقل')
                ->visible(fn (): bool => ! $page->isEditingField($fieldKey))
                ->action(fn () => $page->startEditingField($fieldKey)),
        ])
            ->alignEnd()
            ->extraAttributes(['class' => 'news-edit-row__actions shrink-0']);
    }

    private static function editNewsFieldToolbarApplyCancelRow(EditNews $page, string $fieldKey): Group
    {
        return Group::make([
            Flex::make([
                Action::make('enews_done_'.$fieldKey)
                    ->label('تطبيق')
                    ->size(Size::Small)
                    ->color('primary')
                    ->action(fn () => $page->stopEditingField(false)),
                Action::make('enews_cancel_'.$fieldKey)
                    ->label('تراجع')
                    ->size(Size::Small)
                    ->color('gray')
                    ->action(fn () => $page->stopEditingField(true)),
            ])
                ->alignEnd()
                ->extraAttributes(['class' => 'news-edit-field-apply-row']),
        ])
            ->visible(fn (): bool => $page->isEditingField($fieldKey))
            ->columns(1)
            ->columnSpanFull();
    }

    private static function editNewsFieldPreviewValue(EditNews $page, News $record, string $key): mixed
    {
        $v = data_get($page->data, $key);

        if ($v !== null && $v !== '') {
            return $v;
        }

        return $record->getAttribute($key);
    }

    private static function editNewsFieldPreviewText(EditNews $page, News $record, string $key, int $limit): string
    {
        $raw = static::editNewsFieldPreviewValue($page, $record, $key);
        if ($raw === null || $raw === '') {
            return '—';
        }
        $s = is_string($raw) ? $raw : (string) $raw;
        if ($key === 'content') {
            $s = strip_tags($s);
            $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $normalized = preg_replace('/\s+/u', ' ', $s);
        $s = trim(is_string($normalized) ? $normalized : $s);

        return Str::limit($s, $limit);
    }

    private static function editNewsCategoryPreview(EditNews $page, News $record): string
    {
        $v = static::editNewsFieldPreviewValue($page, $record, 'category');
        if ($v === null || $v === '') {
            return '—';
        }

        $opts = static::categoryOptions();

        return (string) ($opts[(string) $v] ?? $v);
    }

    /**
     * النموذج الافتراضي — يُستخدم إن وُجد استدعاء عام (التوافق مع Filament).
     */
    public static function form(Schema $schema): Schema
    {
        return static::createForm($schema);
    }

    /**
     * @return array<int, Component>
     */
    protected static function contentFieldsSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('العنوان')
                ->required()
                ->maxLength(255)
                ->extraInputAttributes(['id' => 'news-field-title'])
                ->columnSpanFull(),

            TextInput::make('slug')
                ->label('الرابط المختصر')
                ->maxLength(255)
                ->helperText('يُنشأ تلقائياً من العنوان إذا تُرك فارغاً')
                ->columnSpanFull(),

            Textarea::make('excerpt')
                ->label('المقتطف')
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),

            Textarea::make('content')
                ->label('المحتوى')
                ->rows(12)
                ->required()
                ->columnSpanFull(),

            static::newsImageUploadField(),

            Select::make('category')
                ->label('التصنيف')
                ->options(static::categoryOptions())
                ->nullable(),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('ملخص النشر والتنبيهات')
                ->description(function (News $record): HtmlString {
                    return new HtmlString(
                        '<div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">'
                        .'<p><span class="font-medium text-gray-950 dark:text-white">الحالة:</span> '
                        .e($record->publicationStatusLabel()).'</p>'
                        .'<p><span class="font-medium text-gray-950 dark:text-white">موعد الظهور للعامة:</span> '
                        .e($record->adminVisibilitySummary()).'</p>'
                        .'<p><span class="font-medium text-gray-950 dark:text-white">تنبيه الوارد:</span> '
                        .e($record->inboxNotificationStatusLabel()).'</p>'
                        .'</div>'
                    );
                })
                ->schema([
                    TextEntry::make('id')
                        ->hidden(),
                ]),
            Section::make('بيانات الخبر')
                ->columns(2)
                ->schema([
                    TextEntry::make('title')
                        ->label('العنوان')
                        ->columnSpanFull(),
                    TextEntry::make('category')
                        ->label('التصنيف')
                        ->placeholder('—'),
                    TextEntry::make('created_at')
                        ->label('تاريخ الإنشاء')
                        ->dateTime('Y/m/d H:i'),
                    TextEntry::make('updated_at')
                        ->label('آخر تحديث')
                        ->dateTime('Y/m/d H:i'),
                ]),
            Section::make('المحتوى')
                ->schema([
                    TextEntry::make('excerpt')
                        ->label('المقتطف')
                        ->placeholder('—')
                        ->columnSpanFull(),
                    TextEntry::make('content')
                        ->label('المحتوى')
                        ->columnSpanFull(),
                    TextEntry::make('image')
                        ->label('صورة الخبر')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->url(fn (News $record): string => auth()->user()?->can('update', $record) ?? false
                        ? static::getUrl('edit', ['record' => $record])
                        : static::getUrl('view', ['record' => $record]))
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->alignment(Alignment::Start),

                TextColumn::make('publication_status')
                    ->label('الحالة')
                    ->badge()
                    ->getStateUsing(fn (News $record): string => $record->publicationStatusLabel())
                    ->color(fn (News $record): string => $record->publicationStatusColor())
                    ->alignment(Alignment::Start),

                TextColumn::make('visibility_line')
                    ->label('موعد الظهور للعامة')
                    ->getStateUsing(function (News $record): string {
                        if ($record->isDraft()) {
                            return 'لم يُنشر بعد';
                        }
                        $fmt = $record->published_at?->timezone(config('app.timezone'))->format('Y/m/d H:i');

                        return $record->isScheduled()
                            ? 'مجدول في: '.$fmt
                            : 'نُشر في: '.$fmt;
                    })
                    ->alignment(Alignment::Start),

                TextColumn::make('category')
                    ->label('التصنيف')
                    ->badge()
                    ->default('—')
                    ->sortable()
                    ->alignment(Alignment::Start),

                TextColumn::make('created_at')
                    ->label('أُنشئ في')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignment(Alignment::Start),
            ])
            ->filters([
                SelectFilter::make('publication_segment')
                    ->label('حالة النشر')
                    ->placeholder('الكل')
                    ->options([
                        'draft' => 'المسودات',
                        'scheduled' => 'المجدولة',
                        'published' => 'المنشورة',
                    ])
                    ->query(function (Builder $query, array $data): void {
                        if (blank($data['value'] ?? null)) {
                            return;
                        }

                        match ($data['value']) {
                            'published' => $query->published(),
                            'scheduled' => $query->scheduled(),
                            'draft' => $query->draft(),
                            default => null,
                        };
                    }),
                SelectFilter::make('category')
                    ->label('التصنيف')
                    ->options(static::categoryOptions()),
            ])
            ->actions([
                EditAction::make()
                    ->color('gray'),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ])->visible(fn (): bool => auth()->user()?->can('manage_news') ?? false),
            ])
            ->defaultSort('published_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNews::route('/'),
            'create' => Pages\CreateNews::route('/create'),
            'view' => Pages\ViewNews::route('/{record}'),
            'edit' => EditNews::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\BelongsToStaffUiModule;
use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Resources\ExperienceHouseRequestResource\Pages;
use App\Models\ExperienceHouseRequest;
use App\Support\StaffUi\StaffUiModule;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * طلبات الجمعيات المستفيدة ضمن مشروع بيوت الخبرة.
 */
class ExperienceHouseRequestResource extends Resource
{
    use BelongsToStaffUiModule;
    use ConfiguresEditOnlyResourceTable;

    protected static function staffUiModule(): string
    {
        return StaffUiModule::SUPPORT;
    }

    protected static ?string $model = ExperienceHouseRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|\UnitEnum|null $navigationGroup = 'المستفيدون';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'طلبات بيوت الخبرة';

    protected static ?string $modelLabel = 'طلب بيوت الخبرة';

    protected static ?string $pluralModelLabel = 'طلبات بيوت الخبرة';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('manage_experience_house_requests') || $user->hasRole(['super_admin', 'admin']));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole('super_admin');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canViewAny()) {
            return null;
        }

        $count = ExperienceHouseRequest::query()->where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'new' => 'info',
            'in_review' => 'warning',
            'scheduled', 'in_progress' => 'primary',
            'completed' => 'success',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    private static function show(string $name, string $label, \Closure $content, bool $full = false): Placeholder
    {
        $field = Placeholder::make($name)
            ->label($label)
            ->content(fn (?ExperienceHouseRequest $record) => $record ? ($content($record) ?: '—') : '—');

        return $full ? $field->columnSpanFull() : $field;
    }

    private static function multiline(?string $text): HtmlString
    {
        return new HtmlString(nl2br(e((string) $text)));
    }

    public static function form(Schema $schema): Schema
    {
        $R = ExperienceHouseRequest::class;

        return $schema->components([
            Section::make('متابعة الطلب')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->label('حالة الطلب')
                        ->options(ExperienceHouseRequest::STATUSES)
                        ->required()
                        ->native(false),
                    static::show('reference_display', 'رقم الطلب / تاريخ الرفع', fn ($r) => $r->reference_no.' — '.$r->created_at?->format('Y/m/d H:i')),
                    Textarea::make('staff_notes')
                        ->label('ملاحظات داخلية')
                        ->helperText('لا تظهر للجمعية')
                        ->rows(4)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                ]),

            Section::make('الاحتياج')
                ->columns(2)
                ->schema([
                    static::show('departments_display', 'الإدارات المطلوب دعمها', fn ($r) => implode('، ', $R::labelsFor($r->departments, $R::DEPARTMENTS)).($r->other_department ? ' ('.$r->other_department.')' : ''), true),
                    static::show('support_types_display', 'نوع الدعم', fn ($r) => implode('، ', $R::labelsFor($r->support_types, $R::SUPPORT_TYPES)), true),
                    static::show('priority_display', 'الأولوية', fn ($r) => $R::PRIORITIES[$r->priority] ?? $r->priority),
                    static::show('delivery_display', 'أسلوب التنفيذ', fn ($r) => $R::DELIVERY_MODES[$r->delivery_mode] ?? $r->delivery_mode),
                    static::show('challenge_display', 'وصف الاحتياج', fn ($r) => static::multiline($r->challenge), true),
                    static::show('outcome_display', 'النتائج المتوقعة', fn ($r) => static::multiline($r->expected_outcome), true),
                    static::show('attachment_display', 'المرفق', fn ($r) => $r->attachment_name ? $r->attachment_name.' (حمّله من زر «تحميل المرفق» أعلى الصفحة)' : 'لا يوجد', true),
                ]),

            Section::make('بيانات الجمعية')
                ->columns(3)
                ->schema([
                    static::show('org_display', 'اسم الجمعية', fn ($r) => $r->organization_name),
                    static::show('license_display', 'رقم الترخيص', fn ($r) => $r->license_number),
                    static::show('location_display', 'المنطقة / المدينة', fn ($r) => $r->region.' — '.$r->city),
                    static::show('founded_display', 'سنة التأسيس', fn ($r) => $r->founded_year),
                    static::show('staff_display', 'عدد الموظفين', fn ($r) => $R::STAFF_SIZES[$r->staff_size] ?? null),
                ]),

            Section::make('ممثل الجمعية')
                ->columns(2)
                ->schema([
                    static::show('contact_name_display', 'الاسم', fn ($r) => $r->contact_name.' — '.$r->contact_role),
                    static::show('contact_phone_display', 'الجوال', fn ($r) => new HtmlString('<a dir="ltr" class="text-primary-600 underline" href="https://wa.me/966'.e(substr((string) $r->contact_phone, 1)).'" target="_blank">'.e($r->contact_phone).'</a>')),
                    static::show('contact_email_display', 'البريد', fn ($r) => new HtmlString('<a dir="ltr" class="text-primary-600 underline" href="mailto:'.e($r->contact_email).'">'.e($r->contact_email).'</a>')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $R = ExperienceHouseRequest::class;

        return static::applyEditOnlyTable($table)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference_no')
                    ->label('رقم الطلب')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('organization_name')
                    ->label('الجمعية')
                    ->searchable()
                    ->description(fn (ExperienceHouseRequest $r) => $r->region.' — '.$r->city)
                    ->limit(40),
                TextColumn::make('departments')
                    ->label('الإدارات')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $R::DEPARTMENTS[$state] ?? $state)
                    ->separator(','),
                TextColumn::make('priority')
                    ->label('الأولوية')
                    ->badge()
                    ->color(fn ($state) => $state === 'urgent' ? 'danger' : 'gray')
                    ->formatStateUsing(fn ($state) => $R::PRIORITIES[$state] ?? $state),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn ($state) => static::statusColor($state))
                    ->formatStateUsing(fn ($state) => $R::STATUSES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->label('ممثل الجمعية')
                    ->description(fn (ExperienceHouseRequest $r) => $r->contact_phone)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الرفع')
                    ->date('Y/m/d')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(ExperienceHouseRequest::STATUSES),
                SelectFilter::make('priority')
                    ->label('الأولوية')
                    ->options(ExperienceHouseRequest::PRIORITIES),
                SelectFilter::make('region')
                    ->label('المنطقة')
                    ->options(array_combine(ExperienceHouseRequest::REGIONS, ExperienceHouseRequest::REGIONS)),
                Filter::make('department')
                    ->label('الإدارة')
                    ->schema([
                        Select::make('value')->label('الإدارة')->options(ExperienceHouseRequest::DEPARTMENTS),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        if (filled($data['value'] ?? null)) {
                            $query->whereJsonContains('departments', $data['value']);
                        }
                    }),
            ])
            ->actions([
                EditAction::make()->label('فتح')->color('gray'),
            ])
            ->headerActions([
                Action::make('copyPublicLink')
                    ->label('رابط النموذج للجمعيات')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->url(route('public.experience-houses.create'))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExperienceHouseRequests::route('/'),
            'edit' => Pages\EditExperienceHouseRequest::route('/{record}/edit'),
        ];
    }
}

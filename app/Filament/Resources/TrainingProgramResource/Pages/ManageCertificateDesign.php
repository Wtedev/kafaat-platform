<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Filament\Resources\TrainingProgramResource;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Policies\CertificateTemplatePolicy;
use App\Services\Certificates\CertificateDesignService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

class ManageCertificateDesign extends Page
{
    use InteractsWithRecord;
    use WithFileUploads;

    protected static string $resource = TrainingProgramResource::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'تعيين تصميم الشهادة';

    protected static ?string $breadcrumb = 'تصميم الشهادة';

    protected string $view = 'filament.resources.training-program-resource.pages.manage-certificate-design';

    public UploadedFile|string|null $backgroundUpload = null;

    public UploadedFile|string|null $elementImage = null;

    /** @var list<string> */
    public array $approvalErrors = [];

    public bool $showRegeneratePrompt = false;

    public int $olderCertificateCount = 0;

    public ?string $previewUrl = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::canAccess(['record' => $this->getRecord()]), 403);
        app(CertificateDesignService::class)->ensureDraft($this->owner());
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        $record = $parameters['record'] ?? null;

        $expected = static::getResource()::getModel();

        if (! $user instanceof User || ! $record instanceof $expected) {
            return false;
        }

        return app(CertificateTemplatePolicy::class)->manageForOwner($user, $record);
    }

    public function updatedBackgroundUpload(): void
    {
        $this->authorizeManage();
        if (! $this->backgroundUpload instanceof UploadedFile) {
            return;
        }

        $template = app(CertificateDesignService::class)->storeBackground($this->template(), $this->backgroundUpload);
        $this->backgroundUpload = null;
        $this->approvalErrors = [];
        $this->dispatch(
            'certificate-background-updated',
            url: app(CertificateDesignService::class)->backgroundUrl($template),
            pageWidthMm: (float) $template->page_width_mm,
            pageHeightMm: (float) $template->page_height_mm,
        );
        Notification::make()->title('رُفعت خلفية الشهادة')->success()->send();
    }

    /**
     * @return array{path: string, url: string}
     */
    public function storeElementImage(): array
    {
        $this->authorizeManage();
        $this->validate([
            'elementImage' => ['required', 'file', 'max:10240'],
        ], [
            'elementImage.required' => 'اختر صورة.',
            'elementImage.max' => 'الحد الأقصى للصورة 10 ميغابايت.',
        ]);

        if (! $this->elementImage instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'elementImage' => 'اختر صورة.',
            ]);
        }

        $stored = app(CertificateDesignService::class)->storeElementImage($this->template(), $this->elementImage);
        $this->elementImage = null;

        return $stored;
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @param  array<string, mixed>  $eligibility
     */
    public function saveDraft(array $elements, array $eligibility): void
    {
        $this->authorizeManage();
        app(CertificateDesignService::class)->saveDraft($this->template(), $elements, $eligibility);
        $this->approvalErrors = [];
        $this->showRegeneratePrompt = false;
        Notification::make()->title('حُفظت المسودة')->success()->send();
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     * @param  array<string, mixed>  $eligibility
     * @return array{status: string, prompt?: bool}
     */
    public function approve(array $elements, array $eligibility): array
    {
        $this->authorizeManage();
        $service = app(CertificateDesignService::class);
        $template = $this->template();
        $normalized = $service->normalizeElements($template, $elements);
        $service->normalizeEligibility($eligibility);
        $gaps = $service->approvalGaps($template, $normalized);

        if ($gaps !== []) {
            $service->saveDraft($template, $normalized, $eligibility);
            $this->approvalErrors = $gaps;
            $this->showRegeneratePrompt = false;

            return ['status' => 'draft'];
        }

        $older = $service->olderCertificateCount($template, ((int) $template->version) + 1);
        if ($older > 0) {
            Cache::put($this->pendingApprovalKey(), [
                'elements' => $normalized,
                'eligibility' => $eligibility,
            ], now()->addMinutes(30));
            $this->olderCertificateCount = $older;
            $this->showRegeneratePrompt = true;
            $this->approvalErrors = [];

            return ['status' => 'prompt', 'prompt' => true];
        }

        $service->approve($template, $normalized, $eligibility, false);
        $this->approvalErrors = [];
        $this->showRegeneratePrompt = false;
        Notification::make()->title('اعتُمد التصميم')->success()->send();

        return ['status' => 'ready'];
    }

    public function confirmRegeneration(bool $regenerate): void
    {
        $this->authorizeManage();
        $pending = Cache::pull($this->pendingApprovalKey());
        if (! is_array($pending)) {
            $this->showRegeneratePrompt = false;
            Notification::make()->title('انتهت مهلة التأكيد، أعد الاعتماد.')->warning()->send();

            return;
        }

        app(CertificateDesignService::class)->approve(
            $this->template(),
            $pending['elements'],
            $pending['eligibility'],
            $regenerate,
        );
        $this->showRegeneratePrompt = false;
        $this->approvalErrors = [];
        $this->dispatch('certificate-design-approved', statusLabel: 'جاهز');
        Notification::make()
            ->title($regenerate
                ? 'اعتُمد التصميم وأُرسلت إعادة توليد الشهادات السابقة.'
                : 'اعتُمد التصميم دون إعادة توليد الشهادات السابقة.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    public function copyFromProgram(int $sourceId): array
    {
        $this->authorizeManage();
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $ownerClass = $this->owner()::class;
        $sourceOwner = $ownerClass::query()->with('certificateTemplate')->find($sourceId);
        $source = $sourceOwner?->certificateTemplate;
        if (! $sourceOwner instanceof Model || ! $source instanceof CertificateTemplate) {
            throw ValidationException::withMessages([
                'copySourceId' => 'اختر نشاطاً له قالب شهادة.',
            ]);
        }

        abort_unless(
            app(CertificateTemplatePolicy::class)->manageForOwner($user, $sourceOwner),
            403,
        );

        $template = app(CertificateDesignService::class)->copyFrom($this->template(), $source);
        $this->approvalErrors = [];
        Notification::make()->title('نُسخ التصميم دون شروط الأحقية')->success()->send();

        return app(CertificateDesignService::class)->designerPayload($template, $this->owner(), $user);
    }

    /**
     * @param  list<array<string, mixed>>  $elements
     */
    public function preview(array $elements, ?int $registrationId = null): void
    {
        $this->authorizeManage();
        $service = app(CertificateDesignService::class);
        $values = $service->fieldValues($this->owner(), $registrationId);
        $this->previewUrl = $service->rememberPreview($this->template(), $elements, $values);
        $this->dispatch('certificate-preview-ready', url: $this->previewUrl);
    }

    /**
     * @return array<string, string>
     */
    public function fieldValues(?int $registrationId = null): array
    {
        $this->authorizeManage();

        return app(CertificateDesignService::class)->fieldValues($this->owner(), $registrationId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return [
            'designer' => app(CertificateDesignService::class)->designerPayload(
                $this->template(),
                $this->owner(),
                $user,
            ),
        ];
    }

    private function owner(): Model
    {
        $record = $this->getRecord();
        $expected = static::getResource()::getModel();
        abort_unless($record instanceof $expected, 404);

        return $record;
    }

    private function template(): CertificateTemplate
    {
        $template = $this->owner()->certificateTemplate()->first();
        abort_unless($template instanceof CertificateTemplate, 404);

        return $template;
    }

    private function authorizeManage(): void
    {
        abort_unless(static::canAccess(['record' => $this->owner()]), 403);
    }

    private function pendingApprovalKey(): string
    {
        return 'certificate-approve:'.auth()->id().':'.$this->template()->id;
    }
}

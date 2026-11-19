<?php

namespace App\Services\StaffUi;

use App\Enums\OpportunityStatus;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\Media\PublicMediaLifecycleService;
use App\Support\RichContentSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Volunteer opportunities in the staff UI. An opportunity is an announcement:
 * title, description, dates, capacity, and a link to its page on the national
 * volunteering platform. Registration, hours and certificates all happen there.
 */
class StaffVolunteerOpportunities
{
    public const VIEWS = ['all', 'published', 'draft', 'archived'];

    public const PER_PAGE = 20;

    public const IMAGE_DIRECTORY = 'volunteer-opportunities/images';

    public function __construct(
        private readonly PublicMediaLifecycleService $media,
    ) {}

    public function paginate(User $viewer, string $view, string $search, int $page): LengthAwarePaginator
    {
        $query = $this->visible($viewer);

        if ($view !== 'all') {
            $query->where('status', $view);
        }

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where('title', 'like', $like);
        }

        return $query
            ->orderByRaw("case when status = 'published' then 0 when status = 'draft' then 1 else 2 end")
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page))
            ->withQueryString();
    }

    /**
     * @return array<string, int>
     */
    public function viewCounts(User $viewer): array
    {
        $counts = $this->visible($viewer)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total);

        return [
            'all' => (int) $counts->sum(),
            'published' => (int) ($counts[OpportunityStatus::Published->value] ?? 0),
            'draft' => (int) ($counts[OpportunityStatus::Draft->value] ?? 0),
            'archived' => (int) ($counts[OpportunityStatus::Archived->value] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $actor, array $input, ?UploadedFile $image, bool $publish): VolunteerOpportunity
    {
        $data = $this->validated($input, $image);

        $opportunity = new VolunteerOpportunity($data);
        $opportunity->status = $publish ? OpportunityStatus::Published : OpportunityStatus::Draft;
        $opportunity->published_at = $publish ? now() : null;
        $opportunity->created_by = $actor->id;
        $opportunity->updated_by = $actor->id;

        $stored = $this->storeImage($image);
        if ($stored !== null) {
            $opportunity->image = $stored;
        }

        try {
            $opportunity->save();
        } catch (\Throwable $e) {
            $this->media->discardFailedUpload($stored);
            throw $e;
        }

        return $opportunity;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $actor, VolunteerOpportunity $opportunity, array $input, ?UploadedFile $image, bool $removeImage): void
    {
        $data = $this->validated($input, $image);

        if ($this->isUntouchedRichDescription($opportunity, $data['description'] ?? null)) {
            unset($data['description']);
        }

        $previousImage = $opportunity->image;
        $opportunity->fill($data);
        $opportunity->updated_by = $actor->id;

        $stored = $this->storeImage($image);
        if ($stored !== null) {
            $opportunity->image = $stored;
        } elseif ($removeImage) {
            $opportunity->image = null;
        }

        try {
            $opportunity->save();
        } catch (\Throwable $e) {
            $this->media->discardFailedUpload($stored);
            throw $e;
        }

        $this->media->deleteOwnedIfReplaced($previousImage, $opportunity->image);
    }

    /**
     * publish | unpublish | archive | restore. Returns the status message.
     */
    public function changeStatus(User $actor, VolunteerOpportunity $opportunity, string $action): string
    {
        [$status, $message] = match ($action) {
            'publish' => [OpportunityStatus::Published, 'نُشرت الفرصة، وأصبحت ظاهرة في صفحة الفرص التطوعية.'],
            'unpublish' => [OpportunityStatus::Draft, 'أُعيدت الفرصة إلى المسودات، ولم تعد ظاهرة في الموقع.'],
            'archive' => [OpportunityStatus::Archived, 'نُقلت الفرصة إلى الأرشيف، ولم تعد ظاهرة في الموقع.'],
            'restore' => [OpportunityStatus::Draft, 'استُعيدت الفرصة من الأرشيف كمسودة.'],
        };

        $opportunity->status = $status;
        if ($status === OpportunityStatus::Published) {
            $opportunity->published_at = now();
        }
        $opportunity->updated_by = $actor->id;
        $opportunity->save();

        return $message;
    }

    /**
     * Allowed status actions for this user on this opportunity.
     *
     * @return list<string>
     */
    public function availableActions(User $actor, VolunteerOpportunity $opportunity): array
    {
        $canPublish = $actor->can('publish', $opportunity);
        $canArchive = $actor->can('archive', $opportunity);

        return array_values(array_filter([
            $opportunity->status === OpportunityStatus::Draft && $canPublish ? 'publish' : null,
            $opportunity->status === OpportunityStatus::Published && $canPublish ? 'unpublish' : null,
            $opportunity->status !== OpportunityStatus::Archived && $canArchive ? 'archive' : null,
            $opportunity->status === OpportunityStatus::Archived && $canArchive ? 'restore' : null,
        ]));
    }

    public function visible(User $viewer): Builder
    {
        return VolunteerOpportunity::query()->forFilamentAssignmentAccess($viewer);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validated(array $input, ?UploadedFile $image): array
    {
        $data = Validator::make(
            [...$input, 'image' => $image],
            [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:20000'],
                'external_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
                'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
                'hours_expected' => ['nullable', 'numeric', 'min:0', 'max:9999'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', Rule::when(filled($input['start_date'] ?? null), ['after_or_equal:start_date'])],
                'notify_on_publish' => ['nullable', 'boolean'],
                'image' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000'],
            ],
            [
                'title.required' => 'يُرجى إدخال عنوان الفرصة.',
                'external_url.url' => 'يُرجى إدخال رابط صحيح يبدأ بـ https://',
                'capacity.integer' => 'السعة يجب أن تكون رقماً صحيحاً.',
                'capacity.min' => 'السعة يجب أن تكون 1 على الأقل.',
                'hours_expected.numeric' => 'الساعات المتوقعة يجب أن تكون رقماً.',
                'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون في يوم البداية أو بعده.',
                'image.image' => 'يجب أن يكون الملف صورة (JPEG أو PNG أو WebP).',
                'image.mimes' => 'الصيغ المسموحة: JPEG و PNG و WebP.',
                'image.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
                'image.dimensions' => 'أبعاد الصورة كبيرة جداً. الحد الأقصى 4000×4000 بكسل.',
            ],
            [
                'title' => 'العنوان',
                'description' => 'الوصف',
                'external_url' => 'رابط الفرصة',
                'capacity' => 'السعة',
                'hours_expected' => 'الساعات المتوقعة',
                'start_date' => 'تاريخ البداية',
                'end_date' => 'تاريخ النهاية',
            ],
        )->validate();

        unset($data['image']);

        $data['title'] = trim((string) $data['title']);
        $data['description'] = RichContentSupport::normalizeForStorage($data['description'] ?? null);
        $data['external_url'] = filled($data['external_url'] ?? null) ? trim((string) $data['external_url']) : null;
        $data['notify_on_publish'] = (bool) ($data['notify_on_publish'] ?? false);
        foreach (['capacity', 'hours_expected', 'start_date', 'end_date'] as $key) {
            $data[$key] = filled($data[$key] ?? null) ? $data[$key] : null;
        }

        return $data;
    }

    private function storeImage(?UploadedFile $image): ?string
    {
        return $image instanceof UploadedFile ? $this->media->storeUpload($image, self::IMAGE_DIRECTORY) : null;
    }

    /**
     * The textarea shows formatted descriptions as text; saving that text unchanged keeps the original formatting.
     */
    private function isUntouchedRichDescription(VolunteerOpportunity $opportunity, ?string $submitted): bool
    {
        $current = $opportunity->getOriginal('description');
        if (blank($current) || ! RichContentSupport::isRichContent($current)) {
            return false;
        }

        $normalize = static fn (?string $text): string => trim(preg_replace('/\s+/u', ' ', (string) $text) ?? '');

        return $normalize($submitted) === $normalize(RichContentSupport::toEditableText($current));
    }
}

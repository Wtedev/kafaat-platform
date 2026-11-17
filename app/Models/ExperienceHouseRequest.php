<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * طلب دعم من جمعية مستفيدة ضمن مشروع بيوت الخبرة.
 */
class ExperienceHouseRequest extends Model
{
    public const DEPARTMENTS = [
        'strategic_planning' => 'التخطيط الاستراتيجي',
        'governance' => 'الحوكمة والامتثال',
        'finance' => 'الإدارة المالية',
        'hr' => 'الموارد البشرية',
        'fundraising' => 'تنمية الموارد المالية',
        'programs' => 'إدارة البرامج والمشاريع',
        'media' => 'الإعلام والعلاقات العامة',
        'volunteering' => 'إدارة التطوع',
        'digital' => 'التحول الرقمي والتقنية',
        'impact' => 'قياس الأثر والجودة',
        'other' => 'أخرى',
    ];

    public const SUPPORT_TYPES = [
        'consultation' => 'استشارة متخصصة',
        'workshop' => 'ورشة عمل / تدريب',
        'intro_meeting' => 'لقاء تعريفي',
        'document_review' => 'مراجعة وثائق ونماذج',
        'field_visit' => 'زيارة ميدانية',
        'mentoring' => 'مرافقة واحتضان',
    ];

    public const REGIONS = [
        'القصيم', 'الرياض', 'مكة المكرمة', 'المدينة المنورة', 'المنطقة الشرقية',
        'عسير', 'تبوك', 'حائل', 'الحدود الشمالية', 'جازان', 'نجران', 'الباحة', 'الجوف',
    ];

    public const STAFF_SIZES = [
        '1-5' => 'من 1 إلى 5',
        '6-15' => 'من 6 إلى 15',
        '16-50' => 'من 16 إلى 50',
        '50+' => 'أكثر من 50',
    ];

    public const PRIORITIES = [
        'urgent' => 'عاجل',
        'month' => 'خلال شهر',
        'quarter' => 'خلال ثلاثة أشهر',
    ];

    public const DELIVERY_MODES = [
        'onsite' => 'حضوري',
        'remote' => 'عن بُعد',
        'hybrid' => 'مدمج',
    ];

    public const STATUSES = [
        'new' => 'جديد',
        'in_review' => 'قيد الدراسة',
        'scheduled' => 'تمت الجدولة',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'rejected' => 'معتذر عنه',
    ];

    protected $fillable = [
        'organization_name',
        'license_number',
        'region',
        'city',
        'founded_year',
        'staff_size',
        'contact_name',
        'contact_role',
        'contact_phone',
        'contact_email',
        'departments',
        'other_department',
        'support_types',
        'challenge',
        'expected_outcome',
        'priority',
        'delivery_mode',
        'attachment_path',
        'attachment_name',
        'status',
        'staff_notes',
        'handled_by_id',
        'status_changed_at',
        'submitted_ip',
    ];

    protected function casts(): array
    {
        return [
            'departments' => 'array',
            'support_types' => 'array',
            'founded_year' => 'integer',
            'status_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $request): void {
            if ($request->reference_no === null) {
                $request->reference_no = sprintf('BK-%s-%04d', $request->created_at?->format('Y') ?? now()->format('Y'), $request->id);
                $request->saveQuietly();
            }
        });
    }

    /** مراحل سير الطلب بالترتيب (الاعتذار خارجها). */
    public const FLOW = ['new', 'in_review', 'scheduled', 'in_progress', 'completed'];

    public const DEPARTMENT_ICONS = [
        'strategic_planning' => 'target',
        'governance' => 'shield',
        'finance' => 'wallet',
        'hr' => 'users',
        'fundraising' => 'coins',
        'programs' => 'clipboard',
        'media' => 'megaphone',
        'volunteering' => 'heart',
        'digital' => 'monitor',
        'impact' => 'chart',
        'other' => 'dots',
    ];

    public const STATUS_ICONS = [
        'new' => 'inbox',
        'in_review' => 'search',
        'scheduled' => 'calendar',
        'in_progress' => 'play',
        'completed' => 'check',
        'rejected' => 'x',
    ];

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(ExperienceHouseManager::class, 'handled_by_id');
    }

    public function nextStatus(): ?string
    {
        $i = array_search($this->status, self::FLOW, true);

        return $i === false ? null : (self::FLOW[$i + 1] ?? null);
    }

    public function whatsappUrl(): string
    {
        return 'https://wa.me/966'.substr((string) $this->contact_phone, 1);
    }

    /**
     * @param  array<int, string>|null  $keys
     * @param  array<string, string>  $map
     * @return array<int, string>
     */
    public static function labelsFor(?array $keys, array $map): array
    {
        return array_values(array_map(fn ($k) => $map[$k] ?? (string) $k, $keys ?? []));
    }
}

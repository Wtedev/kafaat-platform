<?php

namespace App\Models;

use App\Casts\CertificateElementsCast;
use App\Casts\EligibilityRulesCast;
use App\Enums\CertificateTemplateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CertificateTemplate extends Model
{
    use LogsActivity;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'background_path',
        'background_disk',
        'page_width_mm',
        'page_height_mm',
        'elements',
        'eligibility',
        'auto_issue',
        'status',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'background_disk' => 'public',
        'page_width_mm' => 297,
        'page_height_mm' => 210,
        'auto_issue' => false,
        'status' => 'draft',
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'page_width_mm' => 'decimal:2',
            'page_height_mm' => 'decimal:2',
            'elements' => CertificateElementsCast::class,
            'eligibility' => EligibilityRulesCast::class,
            'auto_issue' => 'boolean',
            'status' => CertificateTemplateStatus::class,
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            if (! Auth::check()) {
                return;
            }

            $userId = Auth::id();
            if ($template->created_by === null) {
                $template->created_by = $userId;
            }
            $template->updated_by = $userId;
        });

        static::updating(function (self $template): void {
            if (Auth::check()) {
                $template->updated_by = Auth::id();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('certificate_template')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => 'أُنشئ قالب الشهادة',
            'updated' => 'عُدّل قالب الشهادة',
            'deleted' => 'حُذف قالب الشهادة',
            default => $eventName,
        };
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

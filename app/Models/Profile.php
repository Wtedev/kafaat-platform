<?php

namespace App\Models;

use App\Enums\MembershipType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'membership_type' => 'beneficiary',
    ];

    protected $fillable = [
        'user_id',
        'membership_type',
        'gender',
        'birth_date',
        'city',
        'bio',
        'avatar',
        'iconic_skill',
        'competency_levels',
        'cv_sections',
        'cv_path',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'competency_levels' => 'array',
            'cv_sections' => 'array',
            'membership_type' => MembershipType::class,
        ];
    }

    public function cvSection(string $key): ?string
    {
        $raw = $this->cv_sections ?? [];
        if (! is_array($raw)) {
            return null;
        }
        $v = $raw[$key] ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public function cvLinksList(): array
    {
        $raw = $this->cv_sections['links'] ?? [];
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = isset($row['url']) ? trim((string) $row['url']) : '';
            if ($url === '') {
                continue;
            }
            $label = isset($row['label']) ? trim((string) $row['label']) : '';
            $out[] = [
                'label' => $label !== '' ? $label : $url,
                'url' => $url,
            ];
        }

        return $out;
    }

    public function avatarUrl(): ?string
    {
        if (! filled($this->avatar)) {
            return null;
        }

        return asset('storage/'.$this->avatar);
    }

    public static function initialsFromName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($fullName, 0, min(2, mb_strlen($fullName))));
    }

    /**
     * Competency cards for portal (only keys with non-empty values).
     *
     * @return list<array{key: string, title: string, level: string}>
     */
    public function presentCompetencyCards(): array
    {
        $raw = $this->competency_levels;
        if (! is_array($raw)) {
            return [];
        }

        $definitions = [
            'english' => 'مستوى اللغة الإنجليزية',
            'office' => 'مستوى برامج الأوفيس',
            'courses' => 'مستوى الدورات',
            'continuous_learning' => 'التعلم المستمر',
        ];

        $out = [];
        foreach ($definitions as $key => $title) {
            $value = $raw[$key] ?? null;
            if (filled($value)) {
                $out[] = [
                    'key' => $key,
                    'title' => $title,
                    'level' => (string) $value,
                ];
            }
        }

        return $out;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

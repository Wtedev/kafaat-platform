<?php

namespace App\Http\Controllers\Portal\Concerns;

use App\Services\Portal\CvFormOptions;
use Illuminate\Http\Request;

trait NormalizesPortalCvInput
{
    private static function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $t = trim($value);

        return $t === '' ? null : $t;
    }

    /**
     * @param  array<string, mixed>  $cv
     */
    private static function cvSectionsHasContent(array $cv): bool
    {
        $links = $cv['links'] ?? [];
        if (is_array($links) && count($links) > 0) {
            return true;
        }

        foreach (['skills', 'languages', 'office_tools', 'education', 'experience', 'external_courses'] as $listKey) {
            $v = $cv[$listKey] ?? null;
            if (is_array($v) && count($v) > 0) {
                return true;
            }
        }

        foreach (['education_legacy', 'languages_legacy', 'skills_legacy', 'experience_legacy', 'external_training_legacy'] as $legacy) {
            $s = $cv[$legacy] ?? null;
            if (is_string($s) && trim($s) !== '') {
                return true;
            }
        }

        foreach (['education', 'languages', 'skills', 'experience', 'external_training'] as $oldText) {
            $s = $cv[$oldText] ?? null;
            if (is_string($s) && trim($s) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{label: string, url: string, type: ?string}>
     */
    private static function normalizeLinkItemsFromRequest(Request $request): array
    {
        $out = [];
        foreach ($request->input('link_items', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = isset($row['label']) ? trim((string) $row['label']) : '';
            $url = isset($row['url']) ? trim((string) $row['url']) : '';
            if ($url === '') {
                continue;
            }
            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                $url = 'https://'.$url;
            }
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $type = isset($row['type']) ? trim((string) $row['type']) : '';
            if ($type !== '' && ! in_array($type, CvFormOptions::LINK_TYPES, true)) {
                $type = 'Other';
            }
            $out[] = [
                'label' => $label !== '' ? $label : (parse_url($url, PHP_URL_HOST) ?: $url),
                'url' => $url,
                'type' => $type !== '' ? $type : null,
            ];
            if (count($out) >= 15) {
                break;
            }
        }

        return $out;
    }
}

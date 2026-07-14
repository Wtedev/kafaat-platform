<?php

namespace App\Support;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Str;
use JsonException;

final class RichContentSupport
{
    public static function isTipTapJson(mixed $value): bool
    {
        if (is_array($value)) {
            return ($value['type'] ?? null) === 'doc';
        }

        $value = trim((string) $value);

        if ($value === '' || ! str_starts_with($value, '{')) {
            return false;
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return is_array($decoded) && ($decoded['type'] ?? null) === 'doc';
    }

    public static function looksLikeHtml(string $value): bool
    {
        return $value !== '' && (bool) preg_match('/<[a-z][\s\S]*>/i', $value);
    }

    public static function isRichContent(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (self::isTipTapJson($value)) {
            return true;
        }

        return self::looksLikeHtml(trim((string) $value));
    }

    /**
     * Render stored rich content (TipTap JSON, HTML, or plain text) as sanitized HTML.
     */
    public static function toDisplayHtml(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (self::isTipTapJson($value)) {
            return self::renderer($value)->toHtml();
        }

        if (self::looksLikeHtml($value)) {
            return clean($value);
        }

        return nl2br(e($value));
    }

    /**
     * Plain-text extraction for previews, exports, and search snippets.
     */
    public static function toPlainText(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (self::isTipTapJson($value)) {
            return self::renderer($value)->toText();
        }

        if (self::looksLikeHtml($value)) {
            $plain = strip_tags($value);
            $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $normalized = preg_replace('/\s+/u', ' ', $plain);

            return trim(is_string($normalized) ? $normalized : $plain);
        }

        return $value;
    }

    /**
     * Plain-text preview for cards, listings, and admin snippets.
     */
    public static function excerpt(?string $value, int $limit = 140): string
    {
        $plain = self::toPlainText($value);

        if ($plain === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', $plain);

        return Str::limit(trim(is_string($normalized) ? $normalized : $plain), $limit);
    }

    /**
     * @param  string|array<string, mixed>  $content
     */
    private static function renderer(string|array $content): RichContentRenderer
    {
        return RichContentRenderer::make($content)
            ->textColors(NewsFormSupport::richEditorTextColors());
    }
}

<?php

namespace Tests\Unit\Support;

use App\Support\RichContentSupport;
use App\Support\TrainingProgramExtrasSupport;
use Tests\TestCase;

class RichContentSupportTest extends TestCase
{
    public function test_detects_tiptap_json_document(): void
    {
        $json = '{"type":"doc","content":[{"type":"paragraph","attrs":{"textAlign":"start"},"content":[{"type":"text","text":"\u062a\u062c\u0631\u0628\u0629","marks":[{"type":"bold"}]}]}]}';

        $this->assertTrue(RichContentSupport::isTipTapJson($json));
        $this->assertTrue(RichContentSupport::isRichContent($json));
    }

    public function test_renders_tiptap_json_as_bold_html(): void
    {
        $json = '{"type":"doc","content":[{"type":"paragraph","attrs":{"textAlign":"start"},"content":[{"type":"text","text":"\u062a\u062c\u0631\u0628\u0629","marks":[{"type":"bold"}]}]}]}';

        $html = RichContentSupport::toDisplayHtml($json);

        $this->assertStringContainsString('<strong>', $html);
        $this->assertStringContainsString('تجربة', $html);
        $this->assertStringNotContainsString('"type":"doc"', $html);
    }

    public function test_extracts_plain_text_from_tiptap_json(): void
    {
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"\u062a\u062c\u0631\u0628\u0629","marks":[{"type":"bold"}]}]}]}';

        $this->assertSame('تجربة', RichContentSupport::toPlainText($json));
    }

    public function test_excerpt_strips_tiptap_json_and_truncates(): void
    {
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"\u0646\u0628\u0630\u0629 \u0639\u0646 \u0628\u0631\u0646\u0627\u0645\u062c \u0642\u0627\u062f\u0629 \u0627\u0644\u062a\u0637\u0648\u0639 \u0644\u062a\u0646\u0645\u064a\u0629 \u0627\u0644\u0645\u0647\u0627\u0631\u0627\u062a \u0627\u0644\u0642\u064a\u0627\u062f\u064a\u0629 \u0648\u0627\u0644\u062a\u0637\u0648\u0639\u064a\u0629 \u0641\u064a \u0627\u0644\u0645\u062c\u062a\u0645\u0639 \u0627\u0644\u0645\u062d\u0644\u064a."}]}]}';

        $excerpt = RichContentSupport::excerpt($json, 40);

        $this->assertStringContainsString('نبذة عن برنامج', $excerpt);
        $this->assertStringNotContainsString('"type":"doc"', $excerpt);
        $this->assertLessThanOrEqual(43, mb_strlen($excerpt));
    }

    public function test_normalize_for_storage_encodes_tiptap_array(): void
    {
        $document = [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'تجربة',
                ]],
            ]],
        ];

        $stored = RichContentSupport::normalizeForStorage($document);

        $this->assertIsString($stored);
        $this->assertTrue(RichContentSupport::isTipTapJson($stored));
    }

    public function test_format_public_description_renders_tiptap_json(): void
    {
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"\u062a\u062c\u0631\u0628\u0629","marks":[{"type":"bold"}]}]}]}';

        $text = TrainingProgramExtrasSupport::formatPublicDescription($json, false, null);

        $this->assertStringContainsString('<strong>', $text);
        $this->assertStringContainsString('تجربة', $text);
        $this->assertStringNotContainsString('"type":"doc"', $text);
    }
}

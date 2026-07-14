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

    public function test_format_public_description_renders_tiptap_json(): void
    {
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"\u062a\u062c\u0631\u0628\u0629","marks":[{"type":"bold"}]}]}]}';

        $text = TrainingProgramExtrasSupport::formatPublicDescription($json, false, null);

        $this->assertStringContainsString('<strong>', $text);
        $this->assertStringContainsString('تجربة', $text);
        $this->assertStringNotContainsString('"type":"doc"', $text);
    }
}

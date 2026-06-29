<?php

namespace BootDesk\ChatSDK\Core\Tests;

use BootDesk\ChatSDK\Core\Cards\Card;
use BootDesk\ChatSDK\Core\Markdown\BaseFormatConverter;
use BootDesk\ChatSDK\Core\PostableMessage;
use League\CommonMark\Node\Block\Document;
use PHPUnit\Framework\TestCase;

class TestFormatConverter extends BaseFormatConverter
{
    public function toAst(string $platformText): Document
    {
        return $this->parseMarkdown($platformText);
    }

    public function fromAst(Document $ast): string
    {
        return $this->renderMarkdown($ast);
    }
}

class MarkdownTest extends TestCase
{
    private TestFormatConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new TestFormatConverter;
    }

    public function test_render_postable_card_returns_fallback_text(): void
    {
        $card = Card::make()->header('Test Card');
        $message = PostableMessage::card($card);

        $result = $this->converter->renderPostable($message);
        $this->assertStringContainsString('Test Card', $result);
    }

    public function test_render_postable_text(): void
    {
        $message = PostableMessage::text('Hello **world**');
        $result = $this->converter->renderPostable($message);
        $this->assertSame('Hello **world**', $result);
    }

    public function test_to_ast_and_from_ast(): void
    {
        $ast = $this->converter->toAst('Hello **world**');
        $this->assertInstanceOf(Document::class, $ast);

        $markdown = $this->converter->fromAst($ast);
        $this->assertStringContainsString('**world**', $markdown);
    }

    public function test_extract_plain_text(): void
    {
        $text = $this->converter->extractPlainText('Hello **world**');
        $this->assertSame('Hello world', $text);
    }

    public function test_gfm_strikethrough(): void
    {
        $markdown = $this->converter->fromMarkdown('~~strikethrough~~');
        $this->assertStringContainsString('~~strikethrough~~', $markdown);
    }

    public function test_gfm_table(): void
    {
        $markdown = "| A | B |\n|---|---|\n| 1 | 2 |";
        $ast = $this->converter->toAst($markdown);
        $result = $this->converter->fromAst($ast);
        $this->assertStringContainsString('A', $result);
        $this->assertStringContainsString('B', $result);
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('2', $result);
    }

    public function test_gfm_task_list(): void
    {
        $markdown = "- [x] done\n- [ ] todo";
        $ast = $this->converter->toAst($markdown);
        $result = $this->converter->fromAst($ast);
        $this->assertStringContainsString('[x]', $result);
        $this->assertStringContainsString('[ ]', $result);
    }

    public function test_render_as_gfm_from_string(): void
    {
        $result = $this->converter->renderAsGFM('Hello **world**');

        $this->assertStringContainsString('**world**', $result);
    }

    public function test_render_as_gfm_from_ast(): void
    {
        $ast = $this->converter->toAst('Hello **world**');
        $result = $this->converter->renderAsGFM($ast);

        $this->assertStringContainsString('**world**', $result);
    }

    public function test_render_as_gfm_bold(): void
    {
        $result = $this->converter->renderAsGFM('**bold**');

        $this->assertSame('**bold**', $result);
    }

    public function test_render_as_gfm_italic(): void
    {
        $result = $this->converter->renderAsGFM('*italic*');

        $this->assertSame('*italic*', $result);
    }

    public function test_render_as_gfm_strikethrough(): void
    {
        $result = $this->converter->renderAsGFM('~~strike~~');

        $this->assertSame('~~strike~~', $result);
    }

    public function test_render_as_gfm_link(): void
    {
        $result = $this->converter->renderAsGFM('[text](https://example.com)');

        $this->assertStringContainsString('[text](https://example.com)', $result);
    }

    public function test_render_as_gfm_table(): void
    {
        $markdown = "| A | B |\n|---|---|\n| 1 | 2 |";
        $result = $this->converter->renderAsGFM($markdown);

        $this->assertStringContainsString('| A | B |', $result);
        $this->assertStringContainsString('| 1 | 2 |', $result);
    }

    public function test_render_as_gfm_task_list(): void
    {
        $markdown = "- [x] done\n- [ ] todo";
        $result = $this->converter->renderAsGFM($markdown);

        $this->assertStringContainsString('[x]', $result);
        $this->assertStringContainsString('[ ]', $result);
    }

    public function test_render_as_gfm_code_block(): void
    {
        $markdown = "```php\necho 'hi';\n```";
        $result = $this->converter->renderAsGFM($markdown);

        $this->assertStringContainsString('```php', $result);
        $this->assertStringContainsString("echo 'hi'", $result);
    }

    public function test_render_as_gfm_heading(): void
    {
        $result = $this->converter->renderAsGFM('# Heading');

        $this->assertStringContainsString('# Heading', $result);
    }

    public function test_render_as_gfm_unordered_list(): void
    {
        $result = $this->converter->renderAsGFM("- item1\n- item2");

        $this->assertStringContainsString('- item1', $result);
        $this->assertStringContainsString('- item2', $result);
    }

    public function test_render_as_gfm_ordered_list(): void
    {
        $result = $this->converter->renderAsGFM("1. one\n2. two");

        $this->assertStringContainsString('1. one', $result);
        $this->assertStringContainsString('2. two', $result);
    }
}

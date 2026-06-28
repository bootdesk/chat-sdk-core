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
}

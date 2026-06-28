<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Markdown;

use BootDesk\ChatSDK\Core\Contracts\FormatConverter;
use BootDesk\ChatSDK\Core\Markdown\Renderer\BlockQuoteRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\CodeRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\EmphasisRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\FencedCodeRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\HeadingRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\ImageRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\IndentedCodeRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\LinkRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\ListBlockRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\ListItemRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\MarkdownRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\NewlineRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\ParagraphRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\StrikethroughRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\StrongRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\TableRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\TaskListItemMarkerRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\TextRenderer;
use BootDesk\ChatSDK\Core\Markdown\Renderer\ThematicBreakRenderer;
use BootDesk\ChatSDK\Core\PostableMessage;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Extension\TaskList\TaskListItemMarker;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\NodeRendererInterface;

abstract class BaseFormatConverter implements FormatConverter
{
    private Environment $environment;

    private MarkdownParser $parser;

    private MarkdownRenderer $renderer;

    public function __construct()
    {
        $this->environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $this->environment->addExtension(new CommonMarkCoreExtension);
        $this->environment->addExtension(new StrikethroughExtension);
        $this->environment->addExtension(new TableExtension);
        $this->environment->addExtension(new TaskListExtension);
        $this->registerRenderers();
        $this->parser = new MarkdownParser($this->environment);
        $this->renderer = new MarkdownRenderer($this->environment);
    }

    abstract public function toAst(string $platformText): Document;

    abstract public function fromAst(Document $ast): string;

    public function fromMarkdown(string $markdown): string
    {
        $ast = $this->parseMarkdown($markdown);

        return $this->fromAst($ast);
    }

    public function extractPlainText(string $platformText): string
    {
        $ast = $this->toAst($platformText);

        return $this->astToPlainText($ast);
    }

    public function renderPostable(PostableMessage $message): string
    {
        if ($message->isCard()) {
            return $message->content->getFallbackText();
        }

        return (string) $message->content;
    }

    protected function parseMarkdown(string $markdown): Document
    {
        return $this->parser->parse($markdown);
    }

    protected function renderMarkdown(Document $ast): string
    {
        return trim($this->renderer->renderDocument($ast)->getContent());
    }

    protected function registerRenderers(): void
    {
        $this->addRenderer(Text::class, new TextRenderer);
        $this->addRenderer(Strong::class, new StrongRenderer);
        $this->addRenderer(Emphasis::class, new EmphasisRenderer);
        $this->addRenderer(Strikethrough::class, new StrikethroughRenderer);
        $this->addRenderer(Heading::class, new HeadingRenderer);
        $this->addRenderer(Link::class, new LinkRenderer);
        $this->addRenderer(Image::class, new ImageRenderer);
        $this->addRenderer(Code::class, new CodeRenderer);
        $this->addRenderer(FencedCode::class, new FencedCodeRenderer);
        $this->addRenderer(IndentedCode::class, new IndentedCodeRenderer);
        $this->addRenderer(ListBlock::class, new ListBlockRenderer);
        $this->addRenderer(ListItem::class, new ListItemRenderer);
        $this->addRenderer(Paragraph::class, new ParagraphRenderer);
        $this->addRenderer(BlockQuote::class, new BlockQuoteRenderer);
        $this->addRenderer(ThematicBreak::class, new ThematicBreakRenderer);
        $this->addRenderer(Newline::class, new NewlineRenderer);
        $this->addRenderer(Table::class, new TableRenderer);
        $this->addRenderer(TableCell::class, new ParagraphRenderer);
        $this->addRenderer(TableRow::class, new ParagraphRenderer);
        $this->addRenderer(TableSection::class, new ParagraphRenderer);
        $this->addRenderer(TaskListItemMarker::class, new TaskListItemMarkerRenderer);
    }

    protected function addRenderer(string $nodeClass, NodeRendererInterface $renderer, int $priority = 0): void
    {
        $this->environment->addRenderer($nodeClass, $renderer, $priority);
    }

    private function astToPlainText(Document $ast): string
    {
        $walker = $ast->walker();
        $text = '';

        while ($event = $walker->next()) {
            $node = $event->getNode();
            if ($event->isEntering() && method_exists($node, 'getLiteral')) {
                $text .= $node->getLiteral();
            }
        }

        return trim($text);
    }
}

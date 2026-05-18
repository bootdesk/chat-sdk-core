<?php

namespace BootDesk\ChatSDK\Core\Markdown;

use BootDesk\ChatSDK\Core\Contracts\FormatConverter;
use BootDesk\ChatSDK\Core\PostableMessage;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;

abstract class BaseFormatConverter implements FormatConverter
{
    private Environment $environment;

    private MarkdownParser $parser;

    private HtmlRenderer $renderer;

    public function __construct()
    {
        $this->environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $this->environment->addExtension(new CommonMarkCoreExtension);
        $this->parser = new MarkdownParser($this->environment);
        $this->renderer = new HtmlRenderer($this->environment);
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
        return $this->renderer->renderDocument($ast)->getContent();
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

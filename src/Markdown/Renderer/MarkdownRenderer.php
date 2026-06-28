<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Markdown\Renderer;

use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Node;
use League\CommonMark\Output\RenderedContent;
use League\CommonMark\Output\RenderedContentInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\DocumentRendererInterface;
use League\CommonMark\Renderer\NoMatchingRendererException;

final class MarkdownRenderer implements ChildNodeRendererInterface, DocumentRendererInterface
{
    private EnvironmentInterface $environment;

    public function __construct(EnvironmentInterface $environment)
    {
        $this->environment = $environment;
    }

    public function renderDocument(Document $document): RenderedContentInterface
    {
        return new RenderedContent($document, $this->renderNode($document));
    }

    public function renderNodes(iterable $nodes): string
    {
        $output = '';
        $isFirst = true;

        foreach ($nodes as $node) {
            if (! $isFirst && $node instanceof AbstractBlock) {
                $output .= "\n\n";
            }

            $output .= $this->renderNode($node);
            $isFirst = false;
        }

        return $output;
    }

    public function renderNode(Node $node): string
    {
        $renderers = $this->environment->getRenderersForClass(\get_class($node));

        foreach ($renderers as $renderer) {
            $result = $renderer->render($node, $this);
            if ($result !== null) {
                return (string) $result;
            }
        }

        throw new NoMatchingRendererException('Unable to find corresponding renderer for node type '.\get_class($node));
    }

    public function getBlockSeparator(): string
    {
        return "\n\n";
    }

    public function getInnerSeparator(): string
    {
        return '';
    }
}

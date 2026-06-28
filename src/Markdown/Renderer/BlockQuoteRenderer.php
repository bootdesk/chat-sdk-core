<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Markdown\Renderer;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class BlockQuoteRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        if (! $node instanceof BlockQuote) {
            return null;
        }

        $content = $childRenderer->renderNodes($node->children());
        $lines = explode("\n", trim($content));

        return '> '.implode("\n> ", $lines);
    }
}

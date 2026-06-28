<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Markdown\Renderer;

use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class TextRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        if (! $node instanceof Text) {
            return null;
        }

        return $node->getLiteral();
    }
}

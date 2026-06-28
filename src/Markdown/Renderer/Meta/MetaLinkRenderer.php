<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Markdown\Renderer\Meta;

use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class MetaLinkRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        if (! $node instanceof Link) {
            return null;
        }

        return $childRenderer->renderNodes($node->children());
    }
}

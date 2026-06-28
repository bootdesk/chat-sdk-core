<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Markdown\Renderer\Meta;

use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class MetaStrongRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        if (! $node instanceof Strong) {
            return null;
        }

        return '*'.$childRenderer->renderNodes($node->children()).'*';
    }
}

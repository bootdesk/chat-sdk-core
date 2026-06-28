<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Markdown\Renderer;

use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class HeadingRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        if (! $node instanceof Heading) {
            return null;
        }

        $level = $node->getLevel();
        $prefix = str_repeat('#', $level).' ';
        $content = $childRenderer->renderNodes($node->children());

        return $prefix.$content;
    }
}

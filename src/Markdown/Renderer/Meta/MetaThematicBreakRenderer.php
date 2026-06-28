<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Markdown\Renderer\Meta;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class MetaThematicBreakRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        return null;
    }
}

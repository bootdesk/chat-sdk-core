<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Markdown\Renderer;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class FencedCodeRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?string
    {
        if (! $node instanceof FencedCode) {
            return null;
        }

        $info = $node->getInfo() ?? '';
        $code = rtrim($node->getLiteral(), "\n");

        return "```{$info}\n{$code}\n```";
    }
}

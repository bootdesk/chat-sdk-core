<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface HandlesReactions
{
    /**
     * @return array{emoji: string, rawEmoji: string, added: bool, threadId: string, messageId: string, userId: string, raw: mixed}|null
     */
    public function parseReaction(ServerRequestInterface $request): ?array;
}

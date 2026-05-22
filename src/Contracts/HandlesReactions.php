<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Author;
use Psr\Http\Message\ServerRequestInterface;

interface HandlesReactions
{
    /**
     * @return array{author?: Author, emoji: string, rawEmoji: string, added: bool, threadId: string, messageId: string, userId: string, raw: mixed, originId: ?string}|null
     */
    public function parseReaction(ServerRequestInterface $request): ?array;
}

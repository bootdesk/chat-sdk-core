<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Author;
use Psr\Http\Message\ServerRequestInterface;

interface HandlesSlashCommands
{
    /**
     * @return array{author?: Author, command: string, text: string, userId: string, isBot: bool, isMe: bool, channelId: string, triggerId: ?string, raw: mixed}|null
     */
    public function parseSlashCommand(ServerRequestInterface $request): ?array;
}

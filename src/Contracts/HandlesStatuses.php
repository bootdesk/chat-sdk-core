<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface HandlesStatuses
{
    /**
     * @return array{type: 'delivered'|'read'|'failed', messageIds: string[], threadId: string, userId: string, raw: mixed, timestamp: ?int}|null
     */
    public function parseStatus(ServerRequestInterface $request): ?array;
}

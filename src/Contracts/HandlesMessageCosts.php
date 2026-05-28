<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use Money\Money;
use Psr\Http\Message\ServerRequestInterface;

interface HandlesMessageCosts
{
    /**
     * @return array{messageIds: string[], threadId: string, userId: string, price: ?Money, raw: mixed, originId: ?string}|null
     */
    public function parseMessageCost(ServerRequestInterface $request): ?array;
}

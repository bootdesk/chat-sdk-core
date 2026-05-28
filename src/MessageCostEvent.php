<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use Money\Money;

class MessageCostEvent
{
    /** @param string[] $messageIds */
    public function __construct(
        public readonly array $messageIds,
        public readonly string $threadId,
        public readonly string $userId,
        public readonly ?Money $price = null,
        public readonly mixed $raw = null,
        public readonly ?string $originId = null,
    ) {}
}

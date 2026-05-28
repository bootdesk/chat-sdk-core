<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use Money\Money;

class SentMessage
{
    /** @var SentMessage[] */
    public readonly array $additionalMessages;

    /**
     * @param  SentMessage[]  $additionalMessages
     */
    public function __construct(
        public readonly string $id,
        public readonly string $threadId,
        public readonly ?string $timestamp = null,
        array $additionalMessages = [],
        public readonly mixed $raw = null,
        public readonly ?Money $price = null,
    ) {
        $this->additionalMessages = $additionalMessages;
    }
}

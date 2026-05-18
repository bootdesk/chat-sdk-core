<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class FetchResult
{
    /**
     * @param  Message[]  $messages
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?string $nextCursor = null,
    ) {}
}

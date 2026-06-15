<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class UnsupportedOperationEvent
{
    /**
     * @param  mixed  $payload  Raw decoded webhook body (array from json_decode, or raw string)
     */
    public function __construct(
        public readonly string $adapterName,
        public readonly mixed $payload,
        public readonly ?string $threadId = '',
    ) {}
}

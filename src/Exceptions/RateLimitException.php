<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Exceptions;

class RateLimitException extends AdapterException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

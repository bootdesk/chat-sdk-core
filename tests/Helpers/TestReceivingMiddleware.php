<?php

namespace BootDesk\ChatSDK\Core\Tests\Helpers;

use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\ReceivingMiddleware;
use BootDesk\ChatSDK\Core\Message;

class TestReceivingMiddleware implements ReceivingMiddleware
{
    public function __construct(
        private readonly bool $stop = false,
    ) {}

    public function handle(Message $message, Adapter $adapter, callable $next): ?Message
    {
        return $this->stop ? null : $next($message);
    }
}

<?php

namespace BootDesk\ChatSDK\Core\Tests\Helpers;

use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\SendingMiddleware;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\SentMessage;

class TestSendingMiddleware implements SendingMiddleware
{
    public bool $called = false;

    public function handle(string $threadId, PostableMessage $message, Adapter $adapter, string $operation, callable $next): ?SentMessage
    {
        $this->called = true;

        return $next($threadId, $message, $adapter, $operation);
    }
}

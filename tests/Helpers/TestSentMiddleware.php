<?php

namespace BootDesk\ChatSDK\Core\Tests\Helpers;

use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\SentMiddleware;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\SentMessage;

class TestSentMiddleware implements SentMiddleware
{
    public bool $called = false;

    public ?SentMessage $lastResult = null;

    public ?PostableMessage $lastMessage = null;

    public ?string $lastOperation = null;

    public function handle(string $threadId, PostableMessage $message, SentMessage $result, Adapter $adapter, string $operation, callable $next): SentMessage
    {
        $this->called = true;
        $this->lastMessage = $message;
        $this->lastResult = $result;
        $this->lastOperation = $operation;

        return $next($threadId, $message, $result, $adapter, $operation);
    }
}

<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Middleware\ForwardDirection;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\SentMessage;

interface SentMiddleware extends ForwardDirection
{
    /**
     * @param  callable(string, PostableMessage, SentMessage, Adapter, string): SentMessage  $next
     */
    public function handle(string $threadId, PostableMessage $message, SentMessage $result, Adapter $adapter, string $operation, callable $next): SentMessage;
}

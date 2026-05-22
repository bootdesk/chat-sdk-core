<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Middleware\ForwardDirection;
use BootDesk\ChatSDK\Core\PostableMessage;

interface SendingMiddleware extends ForwardDirection
{
    /**
     * @param  callable(string, PostableMessage, Adapter, string): ?PostableMessage  $next
     */
    public function handle(string $threadId, PostableMessage $message, Adapter $adapter, string $operation, callable $next): ?PostableMessage;
}

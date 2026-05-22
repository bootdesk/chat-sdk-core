<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\Middleware\ForwardDirection;

interface ReceivingMiddleware extends ForwardDirection
{
    /**
     * @param  callable(Message, Adapter): ?Message  $next
     */
    public function handle(Message $message, Adapter $adapter, callable $next): ?Message;
}

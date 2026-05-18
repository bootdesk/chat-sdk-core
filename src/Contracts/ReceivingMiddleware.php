<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Message;

interface ReceivingMiddleware
{
    public function handle(Message $message, Adapter $adapter, callable $next): ?Message;
}

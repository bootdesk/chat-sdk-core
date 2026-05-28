<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\MessageContext;
use BootDesk\ChatSDK\Core\Middleware\ForwardDirection;

interface HeardMiddleware extends ForwardDirection
{
    public function handle(MessageContext $context, string $pattern, Adapter $adapter, callable $next): ?MessageContext;
}

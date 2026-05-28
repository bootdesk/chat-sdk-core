<?php

namespace BootDesk\ChatSDK\Core\Tests\Helpers;

use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\HeardMiddleware;
use BootDesk\ChatSDK\Core\MessageContext;

class TestHeardMiddleware implements HeardMiddleware
{
    public ?string $lastPattern = null;

    public function __construct(
        private readonly bool $stop = false,
    ) {}

    public function handle(MessageContext $context, string $pattern, Adapter $adapter, callable $next): ?MessageContext
    {
        $this->lastPattern = $pattern;

        return $next($this->stop ? null : $context);
    }
}

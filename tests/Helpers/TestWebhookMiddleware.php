<?php

namespace BootDesk\ChatSDK\Core\Tests\Helpers;

use BootDesk\ChatSDK\Core\Contracts\WebhookMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TestWebhookMiddleware implements WebhookMiddleware
{
    public bool $called = false;

    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $this->called = true;

        return $next($request);
    }
}

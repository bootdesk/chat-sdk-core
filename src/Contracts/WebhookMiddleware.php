<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Middleware\OnionDirection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface WebhookMiddleware extends OnionDirection
{
    /**
     * @param  callable(ServerRequestInterface): ResponseInterface  $next
     */
    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface;
}

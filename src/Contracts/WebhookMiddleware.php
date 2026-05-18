<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface WebhookMiddleware
{
    public function handle(ServerRequestInterface $request, callable $next): ResponseInterface;
}
